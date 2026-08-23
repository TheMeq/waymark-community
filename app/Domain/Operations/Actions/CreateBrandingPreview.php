<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Data\CreatedBrandingPreview;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateBrandingPreview
{
    /** @param array<string, mixed> $values */
    public function handle(User $actor, array $values): CreatedBrandingPreview
    {
        if (! $actor->hasCapability(ModuleCapability::ManageContent)) {
            throw ValidationException::withMessages(['branding' => 'You are not allowed to preview branding.']);
        }

        $values = validator($values, ['primary_colour' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'accent_colour' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'typography_option' => ['nullable', 'in:instrument,system']])->validate();
        $token = Str::random(48);
        Cache::put('branding-preview:'.$token, ['user_id' => $actor->id, 'values' => $values], now()->addMinutes(30));

        return new CreatedBrandingPreview($token);
    }
}
