<?php

namespace App\Http\Controllers;

use App\Domain\Walks\Actions\DownloadWalkGpx;
use App\Domain\Walks\Models\WalkFieldSettings;
use App\Domain\Walks\Queries\PublicWalksQuery;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicWalkGpxDownloadController
{
    public function __invoke(string $slug, PublicWalksQuery $walks, DownloadWalkGpx $download): StreamedResponse
    {
        abort_unless(WalkFieldSettings::current()->isEnabled('gpx'), 404);
        $event = $walks->published()->where('slug', $slug)->firstOrFail();

        try {
            return $download->handle($event->walk);
        } catch (LogicException) {
            abort(404);
        }
    }
}
