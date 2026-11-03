<?php

namespace App\Domain\Accounts\Queries;

use App\Domain\Accounts\Models\ProfilePhoto;
use App\Domain\Content\Presentation\PublicImageReference;
use App\Models\User;

final class ProfilePhotoPresentation
{
    public function for(User $member, string $variant = 'thumbnail'): array
    {
        $photo = ProfilePhoto::query()->where('user_id', $member->id)->first();
        $url = $photo?->variantPath($variant) === null ? null : route('profile-photos.image', [$photo, $variant]);

        return [
            'source' => $photo !== null ? 'managed' : (PublicImageReference::resolve($member->profile_photo_reference) === null ? 'none' : 'external'),
            'url' => $url ?? ($photo === null ? PublicImageReference::resolve($member->profile_photo_reference) : null),
            'external_url' => $member->profile_photo_reference,
        ];
    }
}
