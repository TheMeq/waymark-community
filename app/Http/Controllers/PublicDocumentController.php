<?php

namespace App\Http\Controllers;

use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentVersion;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicDocumentController
{
    public function index(): View
    {
        return view('documents.index', [...$this->site(), 'documents' => Document::query()->with('category')->where('visibility', 'public')->whereNotNull('current_version_id')->orderBy('title')->get()]);
    }

    public function show(string $slug): View
    {
        $document = Document::query()->with(['category', 'currentVersion', 'versions'])->where('visibility', 'public')->where('slug', $slug)->whereNotNull('current_version_id')->firstOrFail();

        return view('documents.show', [...$this->site(), 'document' => $document]);
    }

    public function download(string $slug, DocumentVersion $version): StreamedResponse
    {
        $document = Document::query()->where('visibility', 'public')->where('slug', $slug)->firstOrFail();
        abort_unless($version->document_id === $document->id && ($version->id === $document->current_version_id || ($document->public_version_history && $version->published_at !== null)), 404);
        abort_unless(Storage::disk($version->storage_disk)->exists($version->storage_path), 404);
        $document->increment('download_count');

        return Storage::disk($version->storage_disk)->download($version->storage_path, $version->original_filename, ['Content-Type' => $version->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    private function site(): array
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return ['site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'], 'theme' => BrandTheme::fromSiteProfile($profile)];
    }
}
