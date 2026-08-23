<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Data\CreatedBrandingPreview;
use App\Domain\Operations\Support\BrandingConfigurationValidator;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateBrandingPreview
{
    public function __construct(private BrandingConfigurationValidator $validator) {}

    /** @param array<string, mixed> $values */
    public function handle(User $actor, array $values): CreatedBrandingPreview
    {
        if (! $actor->hasCapability(ModuleCapability::ManageContent)) {
            throw ValidationException::withMessages(['branding' => 'You are not allowed to preview branding.']);
        }

        $values = $this->validator->validate($values);
        $token = Str::random(48);
        Cache::store('file')->put('branding-preview:'.$token, ['user_id' => $actor->id, 'values' => $values], now()->addMinutes(30));

        return new CreatedBrandingPreview($token);
    }
}
