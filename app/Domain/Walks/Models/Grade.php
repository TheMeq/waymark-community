<?php

namespace App\Domain\Walks\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'display_order',
    'name',
    'description',
    'colour',
])]
final class Grade extends Model
{
    /** @var array<string, string> */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }
}
