<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\PersonalDataExport;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ProcessPersonalDataExports
{
    public function handle(int $limit = 25): int
    {
        $this->expireDownloads();
        $processed = 0;

        PersonalDataExport::query()
            ->where(function ($query): void {
                $query->where('status', 'requested')
                    ->orWhere(fn ($query) => $query->where('status', 'failed')->where('attempts', '<', 3));
            })
            ->orderBy('id')
            ->limit($limit)
            ->each(function (PersonalDataExport $export) use (&$processed): void {
                $this->process($export);
                $processed++;
            });

        return $processed;
    }

    private function process(PersonalDataExport $export): void
    {
        try {
            $export = DB::transaction(function () use ($export): PersonalDataExport {
                $locked = PersonalDataExport::query()->lockForUpdate()->findOrFail($export->id);
                if (! in_array($locked->status, ['requested', 'failed'], true) || $locked->attempts >= 3) {
                    return $locked;
                }
                $locked->update(['status' => 'processing', 'attempts' => $locked->attempts + 1, 'processing_started_at' => now(), 'failure_reason' => null]);

                return $locked->fresh();
            });

            if ($export->status !== 'processing') {
                return;
            }

            $user = User::query()->with(['communicationPreferences', 'favourites.event'])->findOrFail($export->user_id);
            $path = 'account-exports/'.$user->id.'/'.Str::uuid().'.json';
            Storage::disk('local')->put($path, json_encode($this->payload($user), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $token = Str::random(64);
            $export->setDownloadToken($token);
            $export->update([
                'status' => 'ready', 'storage_path' => $path, 'ready_at' => now(), 'expires_at' => now()->addDays(7), 'failed_at' => null,
            ]);
        } catch (Throwable $exception) {
            $export->forceFill(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'Export generation failed.'])->save();
        }
    }

    private function expireDownloads(): void
    {
        PersonalDataExport::query()->where('status', 'ready')->where('expires_at', '<=', now())->each(function (PersonalDataExport $export): void {
            if ($export->storage_path !== null) {
                Storage::disk('local')->delete($export->storage_path);
            }
            $export->update(['status' => 'expired', 'storage_path' => null, 'download_token' => null, 'download_token_hash' => null]);
        });
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
