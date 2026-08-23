<?php

namespace App\Http\Controllers;

use App\Domain\Governance\Models\DocumentVersion;
use App\Domain\Governance\Queries\AvailableDocuments;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicDocumentController
{
    public function index(Request $request, AvailableDocuments $available): View
    {
        return view('documents.index', [...$this->site(), 'documents' => $available->query($request->user())->with('category')->orderBy('title')->get()]);
    }

    public function show(Request $request, AvailableDocuments $available, string $slug): View
    {
        $document = $available->query($request->user())->with(['category', 'currentVersion', 'versions' => fn ($query) => $query->whereNotNull('published_at')->where('published_at', '<=', now())])->where('slug', $slug)->firstOrFail();

        return view('documents.show', [...$this->site(), 'document' => $document]);
    }

    public function download(Request $request, AvailableDocuments $available, string $slug, DocumentVersion $version): StreamedResponse
    {
        $document = $available->documentForVersion($slug, $version, $request->user());
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
