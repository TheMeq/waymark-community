<?php

namespace App\Http\Controllers;

use App\Domain\Holidays\Actions\DownloadHolidayAttachment;
use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicHolidayAttachmentDownloadController
{
    public function __invoke(string $slug, int $attachment, PublicHolidaysQuery $holidays, DownloadHolidayAttachment $download): StreamedResponse
    {
        $event = $holidays->published()->where('slug', $slug)->firstOrFail();
        try {
            return $download->handle($event->holiday, $attachment);
        } catch (LogicException) {
            abort(404);
        }
    }
}
