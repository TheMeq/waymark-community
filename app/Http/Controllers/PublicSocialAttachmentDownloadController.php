<?php

namespace App\Http\Controllers;

use App\Domain\Socials\Actions\DownloadSocialAttachment;
use App\Domain\Socials\Queries\PublicSocialsQuery;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicSocialAttachmentDownloadController
{
    public function __invoke(string $slug, int $attachment, PublicSocialsQuery $socials, DownloadSocialAttachment $download): StreamedResponse
    {
        $event = $socials->published()->where('slug', $slug)->firstOrFail();

        try {
            return $download->handle($event->social, $attachment);
        } catch (LogicException) {
            abort(404);
        }
    }
}
