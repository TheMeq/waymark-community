<?php

namespace App\Domain\Content\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\PublicRedirect;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class SavePublicRedirect
{
    public function handle(User $actor, ?PublicRedirect $redirect, string $sourcePath, string $targetUrl, int $statusCode, bool $enabled): PublicRedirect
    {
        if (! $actor->hasCapability(ModuleCapability::ManageContent)) {
            throw ValidationException::withMessages(['redirect' => 'You are not allowed to manage redirects.']);
        }

        $redirect ??= new PublicRedirect;
        $redirect->fill([
            'source_path' => $sourcePath,
            'target_url' => $targetUrl,
            'status_code' => $statusCode,
            'enabled' => $enabled,
            'automatic' => false,
            'created_by_user_id' => $redirect->created_by_user_id ?? $actor->id,
        ])->save();

        return $redirect;
    }
}
