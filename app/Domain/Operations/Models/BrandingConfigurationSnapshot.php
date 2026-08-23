<?php

namespace App\Domain\Operations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'previous_values', 'new_values'])]
final class BrandingConfigurationSnapshot extends Model
{
    protected function casts(): array
    {
        return ['previous_values' => 'array', 'new_values' => 'array'];
    }
}
