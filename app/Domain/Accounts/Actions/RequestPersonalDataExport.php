<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\PersonalDataExport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestPersonalDataExport
{
    public function handle(User $user): PersonalDataExport
    {
        return DB::transaction(function () use ($user): PersonalDataExport {
            $existing = PersonalDataExport::query()
                ->where('user_id', $user->id)
                ->where(function ($query): void {
                    $query->whereIn('status', ['requested', 'processing'])
                        ->orWhere(fn ($query) => $query->where('status', 'ready')->where('expires_at', '>', now()));
                })
                ->lockForUpdate()
                ->first();

            return $existing ?? PersonalDataExport::query()->create([
                'user_id' => $user->id,
                'status' => 'requested',
                'requested_at' => now(),
            ]);
        });
    }
}
