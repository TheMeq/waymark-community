<?php

namespace App\Http\Controllers;

use App\Domain\Gallery\CommunityPhotoModerationPreviewResolver;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class CommunityPhotoModerationPreviewController
{
    public function __invoke(CommunityPhoto $photo): Response
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $actor->hasVerifiedEmail() && $actor->canAccessPanel(Filament::getPanel('admin')), 403);
        $resolver = app(CommunityPhotoModerationPreviewResolver::class);
        abort_unless($resolver->canAccess($actor, $photo), 403);
        $preview = $resolver->resolve($actor, $photo);
        abort_unless($preview !== null, 404);

        return Storage::disk($preview->disk)->response($preview->path, null, ['Cache-Control' => 'private, no-store']);
    }
}
