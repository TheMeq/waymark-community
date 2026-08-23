<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contact_department_id', 'name', 'email', 'message', 'source_ip', 'retention_expires_at'])]
final class ContactSubmission extends Model
{
    protected function casts(): array
    {
        return ['retention_expires_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(ContactDepartment::class, 'contact_department_id');
    }
}
