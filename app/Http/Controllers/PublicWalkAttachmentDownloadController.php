<?php

namespace App\Http\Controllers;

use App\Domain\Walks\Actions\DownloadWalkAttachment;
use App\Domain\Walks\Queries\PublicWalksQuery;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicWalkAttachmentDownloadController
{
    public function __invoke(string $slug, int $attachment, PublicWalksQuery $walks, DownloadWalkAttachment $download): StreamedResponse
    {
        $event = $walks->published()->where('slug', $slug)->firstOrFail();

        try {
            return $download->handle($event->walk, $attachment);
        } catch (LogicException) {
            abort(404);
        }
    }
}
