<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['homepage_section_id', 'actor_id', 'previous_values', 'new_values'])]
final class HomepageConfigurationSnapshot extends Model
{
    protected function casts(): array
    {
        return ['previous_values' => 'array', 'new_values' => 'array'];
    }
}
