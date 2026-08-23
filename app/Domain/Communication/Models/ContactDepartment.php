<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['public_label', 'destination_email', 'description', 'routing_rules', 'show_address_publicly', 'active', 'sort_order'])]
final class ContactDepartment extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $department): void {
            $rules = (array) $department->routing_rules;
            if (filter_var($department->destination_email, FILTER_VALIDATE_EMAIL) === false || count($rules) > 10) {
                throw ValidationException::withMessages(['routing_rules' => 'Use a valid destination and no more than ten routing rules.']);
            }
            foreach ($rules as $keyword => $destination) {
                if (! is_string($keyword) || trim($keyword) === '' || mb_strlen($keyword) > 50 || ! is_string($destination) || filter_var($destination, FILTER_VALIDATE_EMAIL) === false) {
                    throw ValidationException::withMessages(['routing_rules' => 'Each routing rule needs a short keyword and valid private email address.']);
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['routing_rules' => 'array', 'show_address_publicly' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ContactSubmission::class);
    }
}
