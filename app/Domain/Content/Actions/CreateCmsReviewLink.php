<?php

namespace App\Domain\Content\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Data\CreatedCmsReviewLink;
use App\Domain\Content\Models\CmsPage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateCmsReviewLink
{
    public function handle(User $actor, CmsPage $page, Carbon $expiresAt): CreatedCmsReviewLink
    {
        if (! $actor->hasCapability(ModuleCapability::ManageContent)) {
            throw ValidationException::withMessages(['review_link' => 'You are not allowed to create content review links.']);
        }

        $token = Str::random(64);
        $link = $page->reviewLinks()->create(['created_by_user_id' => $actor->id, 'token_hash' => Hash::make($token), 'expires_at' => $expiresAt]);

        return new CreatedCmsReviewLink($link, $token);
    }
}
