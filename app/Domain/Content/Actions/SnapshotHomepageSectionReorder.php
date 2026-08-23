<?php

namespace App\Domain\Content\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\HomepageConfigurationSnapshot;
use App\Domain\Content\Models\HomepageSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SnapshotHomepageSectionReorder
{
    /** @param array<int, array<string, mixed>> $previousValuesBySection */
    public function handle(User $actor, array $previousValuesBySection): void
    {
        if (! $actor->hasCapability(ModuleCapability::ManageContent)) {
            throw ValidationException::withMessages(['homepage' => 'You are not allowed to reorder the homepage.']);
        }

        DB::transaction(function () use ($actor, $previousValuesBySection): void {
            $sections = HomepageSection::query()
                ->whereKey(array_keys($previousValuesBySection))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (HomepageSection $section): int => $section->getKey());

            foreach ($previousValuesBySection as $sectionId => $previousValues) {
                $section = $sections->get($sectionId);
                if ($section === null || (int) $previousValues['sort_order'] === $section->sort_order) {
                    continue;
                }

                HomepageConfigurationSnapshot::query()->create([
                    'homepage_section_id' => $section->id,
                    'actor_id' => $actor->id,
                    'previous_values' => $previousValues,
                    'new_values' => $section->getAttributes(),
                ]);
            }
        });
    }
}
