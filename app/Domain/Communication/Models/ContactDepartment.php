<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['public_label', 'destination_email', 'description', 'routing_rules', 'show_address_publicly', 'active', 'sort_order'])]
final class ContactDepartment extends Model
{
    protected function casts(): array
    {
        return ['routing_rules' => 'array', 'show_address_publicly' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ContactSubmission::class);
    }
}
