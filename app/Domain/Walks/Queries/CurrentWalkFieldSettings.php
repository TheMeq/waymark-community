<?php

namespace App\Domain\Walks\Queries;

use App\Domain\Walks\Models\WalkFieldSettings;

final class CurrentWalkFieldSettings
{
    private ?WalkFieldSettings $settings = null;

    public function get(): WalkFieldSettings
    {
        return $this->settings ??= WalkFieldSettings::current();
    }
}
