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

final class ProcessPersonalDataExports
{
    private const PROCESSING_TIMEOUT_MINUTES = 15;

    public function handle(int $limit = 25): int
    {
        $this->expireDownloads();
        $this->recoverStaleClaims();
        $processed = 0;

        PersonalDataExport::query()
            ->where(function ($query): void {
                $query->where('status', 'requested')
                    ->orWhere(fn ($query) => $query->where('status', 'failed')->where('attempts', '<', 3));
            })
            ->orderBy('id')
            ->limit($limit)
            ->each(function (PersonalDataExport $export) use (&$processed): void {
                $this->process($export->id);
                $processed++;
            });

        return $processed;
    }

    private function process(int $exportId): void
    {
        $path = null;

        try {
            [$export, $revokedPath] = $this->claim($exportId);
            $this->deleteSafeFile($revokedPath);

            if (! $export instanceof PersonalDataExport) {
                return;
            }

            $user = User::query()->with(['communicationPreferences', 'favourites.event'])->findOrFail($export->user_id);
            if (! $user->isActive()) {
                $this->revokeIfProcessing($export->id);

                return;
            }

            $path = 'account-exports/'.$user->id.'/'.Str::uuid().'.json';
            Storage::disk('local')->put($path, json_encode($this->payload($user), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            if (! $this->finalise($export->id, $path, Str::random(64))) {
                $this->deleteSafeFile($path);
            }
        } catch (Throwable) {
            $this->deleteSafeFile($path);
            $this->failOrRevoke($exportId);
        }
    }

    /** @return array{0: ?PersonalDataExport, 1: ?string} */
    private function claim(int $exportId): array
    {
        return DB::transaction(function () use ($exportId): array {
            $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($exportId);
            $user = User::query()->lockForUpdate()->findOrFail($export->user_id);

            if (! in_array($export->status, ['requested', 'failed'], true) || $export->attempts >= 3) {
                return [null, null];
            }

            if (! $user->isActive()) {
                return [null, $this->revoke($export)];
            }

            $export->update([
                'status' => 'processing',
                'attempts' => $export->attempts + 1,
                'processing_started_at' => now(),
                'failure_reason' => null,
                'failed_at' => null,
            ]);

            return [$export->fresh(), null];
        });
    }

    private function finalise(int $exportId, string $path, string $token): bool
    {
        return DB::transaction(function () use ($exportId, $path, $token): bool {
            $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($exportId);
            $user = User::query()->lockForUpdate()->findOrFail($export->user_id);

            if ($export->status !== 'processing' || ! $user->isActive()) {
                if (! $user->isActive() && $export->status === 'processing') {
                    $this->revoke($export);
                }

                return false;
            }

            $export->forceFill([
                'status' => 'ready',
                'storage_path' => $path,
                'download_token' => $token,
                'download_token_hash' => Hash::make($token),
                'ready_at' => now(),
                'expires_at' => now()->addDays(7),
                'processing_started_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
            ])->save();

            return true;
        });
    }

    private function failOrRevoke(int $exportId): void
    {
        try {
            $path = DB::transaction(function () use ($exportId): ?string {
                $export = PersonalDataExport::query()->lockForUpdate()->find($exportId);
                if (! $export instanceof PersonalDataExport || $export->status !== 'processing') {
                    return null;
                }
                $user = User::query()->lockForUpdate()->findOrFail($export->user_id);

                if (! $user->isActive()) {
                    return $this->revoke($export);
                }

                $export->update([
                    'status' => 'failed', 'failed_at' => now(), 'processing_started_at' => null,
                    'failure_reason' => 'Export generation failed.',
                ]);

                return null;
            });
            $this->deleteSafeFile($path);
        } catch (Throwable) {
            // A later cron run can recover a still-processing claim.
        }
    }

    private function revokeIfProcessing(int $exportId): void
    {
        $path = DB::transaction(function () use ($exportId): ?string {
            $export = PersonalDataExport::query()->lockForUpdate()->find($exportId);

            return $export instanceof PersonalDataExport && $export->status === 'processing'
                ? $this->revoke($export)
                : null;
        });
        $this->deleteSafeFile($path);
    }

    private function recoverStaleClaims(): void
    {
        PersonalDataExport::query()
            ->where('status', 'processing')
            ->where('processing_started_at', '<=', now()->subMinutes(self::PROCESSING_TIMEOUT_MINUTES))
            ->orderBy('id')
            ->each(function (PersonalDataExport $candidate): void {
                try {
                    DB::transaction(function () use ($candidate): void {
                        $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($candidate->id);
                        if ($export->status !== 'processing' || $export->processing_started_at?->gt(now()->subMinutes(self::PROCESSING_TIMEOUT_MINUTES))) {
                            return;
                        }
                        $export->update([
                            'status' => 'failed', 'failed_at' => now(), 'processing_started_at' => null,
                            'failure_reason' => 'Export processing timed out.',
                        ]);
                    });
                } catch (Throwable) {
                    // One corrupt record must not prevent later records being recovered.
                }
            });
    }

    private function expireDownloads(): void
    {
        PersonalDataExport::query()->where('status', 'ready')->where('expires_at', '<=', now())->orderBy('id')->each(function (PersonalDataExport $candidate): void {
            try {
                $path = DB::transaction(function () use ($candidate): ?string {
                    $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($candidate->id);
                    if ($export->status !== 'ready' || ! $export->isExpired()) {
                        return null;
                    }
                    $path = $export->hasSafeStoragePath() ? $export->storage_path : null;
                    $export->update([
                        'status' => 'expired', 'storage_path' => null,
                        'download_token' => null, 'download_token_hash' => null,
                    ]);

                    return $path;
                });
                $this->deleteSafeFile($path);
            } catch (Throwable) {
                // One malformed or unavailable record must not block expiry for other records.
            }
        });
    }

    private function revoke(PersonalDataExport $export): ?string
    {
        $path = $export->hasSafeStoragePath() ? $export->storage_path : null;
        $export->update([
            'status' => 'revoked', 'storage_path' => null,
            'download_token' => null, 'download_token_hash' => null,
            'processing_started_at' => null, 'expires_at' => null,
        ]);

        return $path;
    }

    private function deleteSafeFile(?string $path): void
    {
        if (! is_string($path) || ! preg_match('#\Aaccount-exports/\d+/[a-f0-9-]{36}\.json\z#', $path)) {
            return;
        }

        try {
            Storage::disk('local')->delete($path);
        } catch (Throwable) {
            // Revocation has already committed; a later retention run can retry cleanup.
        }
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
