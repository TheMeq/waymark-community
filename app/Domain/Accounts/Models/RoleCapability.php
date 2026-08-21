<?php

namespace App\Domain\Accounts\Models;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['role', 'capability'])]
final class RoleCapability extends Model
{
    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'role' => AccountRole::class,
            'capability' => ModuleCapability::class,
        ];
    }
}
