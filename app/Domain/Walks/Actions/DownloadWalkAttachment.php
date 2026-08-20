<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Data\WalkAttachment;
use App\Domain\Walks\Models\Walk;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadWalkAttachment
{
    public function handle(Walk $walk, int $index): StreamedResponse
    {
        $attachment = WalkAttachment::at($walk, $index);

        if ($attachment === null) {
            throw new LogicException('This walk attachment is not available.');
        }

        return Storage::disk((string) config('walks.attachments.disk', 'local'))->download($attachment->path, $attachment->name);
    }
}
