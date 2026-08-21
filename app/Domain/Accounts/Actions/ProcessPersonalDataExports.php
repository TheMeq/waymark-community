<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\PersonalDataExport;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class ProcessPersonalDataExports
{
    private const PROCESSING_TIMEOUT_MINUTES = 15;

    public function __construct(private CleanUpPersonalDataExports $cleanup) {}

    public function handle(int $limit = 25): int
    {
        $this->cleanup->handle($limit);
        $expired = $this->expireDownloads($limit);
        $remaining = max(0, $limit - $expired);
        $recovered = $this->recoverStaleClaims($remaining);
        $remaining -= $recovered;
        $processed = 0;

        if ($remaining > 0) {
            PersonalDataExport::query()
                ->where(function ($query): void {
                    $query->where('status', 'requested')
                        ->orWhere(fn ($query) => $query->where('status', 'failed')->whereNull('storage_path')->where('attempts', '<', 3));
                })
                ->orderBy('id')
                ->limit($remaining)
                ->pluck('id')
                ->each(function (int $exportId) use (&$processed): void {
                    $this->process($exportId);
                    $processed++;
                });
        }

        $this->cleanup->handle($limit);

        return $expired + $recovered + $processed;
    }

    private function process(int $exportId): void
    {
        try {
            $export = $this->claim($exportId);
            if (! $export instanceof PersonalDataExport) {
                return;
            }

            $user = User::query()->with(['communicationPreferences', 'favourites.event'])->findOrFail($export->user_id);
            if (! $user->isActive()) {
                $this->revokeIfProcessing($export->id);

                return;
            }

            $path = $export->storage_path;
            if (! is_string($path) || ! $export->hasSafeStoragePath()) {
                return;
            }
            Storage::disk('local')->put($path, json_encode($this->payload($user), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            if (! $this->finalise($export->id, $path, Str::random(64))) {
                $this->cleanup->handleExport($export->id);
            }
        } catch (Throwable) {
            $this->failOrRevoke($exportId);
        }
    }

    private function claim(int $exportId): ?PersonalDataExport
    {
        $ownerId = $this->ownerId($exportId);

        return $ownerId === null ? null : DB::transaction(function () use ($exportId, $ownerId): ?PersonalDataExport {
            $user = User::query()->lockForUpdate()->findOrFail($ownerId);
            $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($exportId);

            if (! in_array($export->status, ['requested', 'failed'], true)
                || $export->attempts >= 3
                || ($export->status === 'failed' && $export->storage_path !== null)) {
                return null;
            }
            if (! $user->isActive()) {
                $this->revoke($export);

                return null;
            }

            $export->update([
                'status' => 'processing', 'attempts' => $export->attempts + 1,
                'storage_path' => 'account-exports/'.$user->id.'/'.Str::uuid().'.json',
                'processing_started_at' => now(), 'failure_reason' => null, 'failed_at' => null,
            ]);

            return $export->fresh();
        });
    }

    private function finalise(int $exportId, string $path, string $token): bool
    {
        $ownerId = $this->ownerId($exportId);
        if ($ownerId === null) {
            return false;
        }

        return DB::transaction(function () use ($exportId, $ownerId, $path, $token): bool {
            $user = User::query()->lockForUpdate()->findOrFail($ownerId);
            $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($exportId);

            if ($export->status !== 'processing' || ! $user->isActive()) {
                if (! $user->isActive() && $export->status === 'processing') {
                    $this->revoke($export);
                }

                return false;
            }

            $export->forceFill([
                'status' => 'ready', 'storage_path' => $path,
                'download_token' => $token, 'download_token_hash' => Hash::make($token),
                'ready_at' => now(), 'expires_at' => now()->addDays(7),
                'processing_started_at' => null, 'failed_at' => null, 'failure_reason' => null,
            ])->save();

            return true;
        });
    }

    private function failOrRevoke(int $exportId): void
    {
        $ownerId = $this->ownerId($exportId);
        if ($ownerId === null) {
            return;
        }

        try {
            DB::transaction(function () use ($exportId, $ownerId): void {
                $user = User::query()->lockForUpdate()->findOrFail($ownerId);
                $export = PersonalDataExport::query()->lockForUpdate()->find($exportId);
                if (! $export instanceof PersonalDataExport || $export->status !== 'processing') {
                    return;
                }
                if (! $user->isActive()) {
                    $this->revoke($export);

                    return;
                }
                $export->update([
                    'status' => 'failed', 'failed_at' => now(), 'processing_started_at' => null,
                    'failure_reason' => 'Export generation failed.',
                ]);
            });
        } catch (Throwable) {
            // A stale claim will be recovered by a later bounded run.
        }
    }

    private function revokeIfProcessing(int $exportId): void
    {
        $ownerId = $this->ownerId($exportId);
        if ($ownerId === null) {
            return;
        }

        DB::transaction(function () use ($exportId, $ownerId): void {
            User::query()->lockForUpdate()->findOrFail($ownerId);
            $export = PersonalDataExport::query()->lockForUpdate()->find($exportId);
            if ($export instanceof PersonalDataExport && $export->status === 'processing') {
                $this->revoke($export);
            }
        });
    }

    private function recoverStaleClaims(int $limit): int
    {
        if ($limit === 0) {
            return 0;
        }

        $recovered = 0;
        PersonalDataExport::query()
            ->where('status', 'processing')
            ->where('processing_started_at', '<=', now()->subMinutes(self::PROCESSING_TIMEOUT_MINUTES))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $exportId) use (&$recovered): void {
                $ownerId = $this->ownerId($exportId);
                if ($ownerId === null) {
                    return;
                }
                try {
                    $changed = DB::transaction(function () use ($exportId, $ownerId): bool {
                        User::query()->lockForUpdate()->findOrFail($ownerId);
                        $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($exportId);
                        if ($export->status !== 'processing' || $export->processing_started_at?->gt(now()->subMinutes(self::PROCESSING_TIMEOUT_MINUTES))) {
                            return false;
                        }
                        $export->update([
                            'status' => 'failed', 'failed_at' => now(), 'processing_started_at' => null,
                            'failure_reason' => 'Export processing timed out.',
                        ]);

                        return true;
                    });
                    $recovered += $changed ? 1 : 0;
                } catch (Throwable) {
                    // A malformed record does not stop later records in the bounded batch.
                }
            });

        return $recovered;
    }

    private function expireDownloads(int $limit): int
    {
        $expired = 0;
        PersonalDataExport::query()->where('status', 'ready')->where('expires_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id')
            ->each(function (int $exportId) use (&$expired): void {
                $ownerId = $this->ownerId($exportId);
                if ($ownerId === null) {
                    return;
                }
                try {
                    $changed = DB::transaction(function () use ($exportId, $ownerId): bool {
                        User::query()->lockForUpdate()->findOrFail($ownerId);
                        $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($exportId);
                        if ($export->status !== 'ready' || ! $export->isExpired()) {
                            return false;
                        }
                        $export->update([
                            'status' => 'expired',
                            'storage_path' => $export->hasSafeStoragePath() ? $export->storage_path : null,
                            'download_token' => null, 'download_token_hash' => null,
                        ]);

                        return true;
                    });
                    $expired += $changed ? 1 : 0;
                } catch (Throwable) {
                    // One unavailable record does not stop expiry for later records.
                }
            });

        return $expired;
    }

    private function revoke(PersonalDataExport $export): void
    {
        $export->update([
            'status' => 'revoked',
            'storage_path' => $export->hasSafeStoragePath() ? $export->storage_path : null,
            'download_token' => null, 'download_token_hash' => null,
            'processing_started_at' => null, 'expires_at' => null,
        ]);
    }

    private function ownerId(int $exportId): ?int
    {
        $ownerId = PersonalDataExport::query()->whereKey($exportId)->value('user_id');

        return is_int($ownerId) ? $ownerId : null;
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        return [
            'account' => [
                'name' => $user->name, 'email' => $user->email, 'display_name' => $user->display_name, 'phone' => $user->phone,
                'created_at' => $user->created_at?->toAtomString(), 'membership_status' => $user->membership_status?->value,
                'membership_verified_at' => $user->membership_verified_at?->toAtomString(), 'membership_verification_source' => $user->membership_verification_source,
            ],
            'profile' => [
                'public_profile_enabled' => $user->public_profile_enabled,
                'public_profile_slug' => $user->public_profile_slug,
                'public_profile_introduction' => $user->public_profile_introduction,
            ],
            'preferences' => $user->communicationPreferences->map(fn ($preference) => [
                'category' => $preference->category, 'is_subscribed' => $preference->is_subscribed, 'consented_at' => $preference->consented_at?->toAtomString(),
            ])->values()->all(),
            'favourites' => $user->favourites->map(fn ($favourite) => [
                'event_id' => $favourite->event_id, 'title' => $favourite->event?->title, 'saved_at' => $favourite->created_at?->toAtomString(),
            ])->values()->all(),
            'owned_events' => Event::query()->where('organiser_id', $user->id)->get(['id', 'type', 'title', 'starts_at', 'status'])->map(fn ($event) => $event->toArray())->all(),
        ];
    }
}
