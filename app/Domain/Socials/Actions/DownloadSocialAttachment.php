<?php

namespace App\Domain\Socials\Actions;

use App\Domain\Socials\Data\SocialAttachment;
use App\Domain\Socials\Models\Social;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadSocialAttachment
{
    public function handle(Social $social, int $index): StreamedResponse
    {
        $attachment = SocialAttachment::at($social, $index);

        if ($attachment === null) {
            throw new LogicException('This Social attachment is not available.');
        }

        return Storage::disk((string) config('socials.attachments.disk', 'local'))->download($attachment->path, $attachment->name);
    }
}
