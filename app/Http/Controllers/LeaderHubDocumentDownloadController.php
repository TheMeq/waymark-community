<?php

namespace App\Http\Controllers;

use App\Domain\Governance\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LeaderHubDocumentDownloadController
{
    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        /** @var User $leader */
        $leader = $request->user();
        Gate::authorize('manageLeaderHub', $leader);

        $document->loadMissing('currentVersion');
        abort_unless($document->visibility === 'leader' && $document->approval_status === 'approved' && $document->currentVersion !== null, 404);
        abort_unless(Storage::disk($document->currentVersion->storage_disk)->exists($document->currentVersion->storage_path), 404);
        $document->increment('download_count');

        return Storage::disk($document->currentVersion->storage_disk)->download(
            $document->currentVersion->storage_path,
            $document->currentVersion->original_filename,
            ['Content-Type' => $document->currentVersion->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
