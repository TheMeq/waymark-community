<?php

namespace App\Domain\Holidays\Actions;

use App\Domain\Holidays\Data\HolidayAttachment;
use App\Domain\Holidays\Models\Holiday;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadHolidayAttachment
{
    public function handle(Holiday $holiday, int $index): StreamedResponse
    {
        $attachment = HolidayAttachment::at($holiday, $index);
        if ($attachment === null) {
            throw new LogicException('Holiday attachment is unavailable.');
        }

        return Storage::disk((string) config('holidays.attachments.disk', 'local'))->download($attachment->path, $attachment->name);
    }
}
