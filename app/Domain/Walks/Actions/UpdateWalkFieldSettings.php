<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Models\WalkFieldSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateWalkFieldSettings
{
    /** @param array<string, bool> $fieldConfiguration */
    public function handle(array $fieldConfiguration): WalkFieldSettings
    {
        return DB::transaction(function () use ($fieldConfiguration): WalkFieldSettings {
            $settings = WalkFieldSettings::query()
                ->lockForUpdate()
                ->find(WalkFieldSettings::SINGLETON_ID) ?? new WalkFieldSettings;

            $settings->field_configuration = array_replace(
                WalkFieldSettings::defaultFieldConfiguration(),
                $settings->field_configuration ?? [],
                Arr::only($fieldConfiguration, array_keys(WalkFieldSettings::OPTIONAL_FIELDS)),
            );
            $settings->save();

            return $settings->refresh();
        });
    }
}
