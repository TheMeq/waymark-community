<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Actions\UpdateProfilePhoto;
use App\Domain\Accounts\Data\ProfilePhotoInput;
use App\Domain\Accounts\Models\ProfilePhoto;
use App\Http\Requests\UpdateProfilePhotoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class AccountProfilePhotoController
{
    public function update(UpdateProfilePhotoRequest $request, UpdateProfilePhoto $update): RedirectResponse
    {
        $update->handle($request->user(), ProfilePhotoInput::from($request->validated()));

        return to_route('account.profile.edit')->with('status', 'Your profile photo has been saved.');
    }

    public function image(ProfilePhoto $photo, string $variant): Response
    {
        $path = $photo->variantPath($variant);
        abort_if($path === null || ! $photo->user?->isActive(), 404);

        return response(Storage::disk($photo->storage_disk)->get($path), 200, [
            'Content-Type' => $photo->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
