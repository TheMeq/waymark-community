<?php

namespace App\Domain\Content\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\HomepageConfigurationSnapshot;
use App\Domain\Content\Models\HomepageSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveHomepageSection
{
    /** @param array<string, mixed> $values */
    public function handle(User $actor, HomepageSection $section, array $values): HomepageSection
    {
        if (! $actor->hasCapability(ModuleCapability::ManageContent)) {
            throw ValidationException::withMessages(['homepage' => 'You are not allowed to manage the homepage.']);
        }

        return DB::transaction(function () use ($actor, $section, $values): HomepageSection {
            $previous = $section->getAttributes();
            $section->fill($values)->save();
            HomepageConfigurationSnapshot::query()->create([
                'homepage_section_id' => $section->id,
                'actor_id' => $actor->id,
                'previous_values' => $previous,
                'new_values' => $section->getAttributes(),
            ]);

            return $section;
        });
    }
}
