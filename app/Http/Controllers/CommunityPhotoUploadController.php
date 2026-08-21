<?php

namespace App\Http\Controllers;

use App\Domain\Gallery\Actions\UploadCommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\PhotoUploadPolicyGate;
use App\Domain\Gallery\Queries\UploadablePublicEvents;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CommunityPhotoUploadController
{
    public function create(Request $request, PhotoUploadPolicyGate $policyGate, UploadablePublicEvents $events): View
    {
        $account = $request->user();

        abort_unless($account?->isActive(), 403);

        $selectedEvent = $events->find($request->integer('event'));
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('gallery.upload', [
            'site' => [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'events' => $events->query()
                ->orderByRaw('case when starts_at <= ? then 0 else 1 end', [now()])
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get(),
            'specialAlbums' => SpecialAlbum::query()->orderBy('title')->orderBy('id')->get(),
            'selectedContext' => $selectedEvent === null ? null : 'event:'.$selectedEvent->id,
            'policyDecision' => $policyGate->for($account),
        ]);
    }

    public function store(Request $request, UploadCommunityPhoto $upload): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'context' => ['required', 'string', 'max:32'],
            'event_id' => ['prohibited'],
            'special_album_id' => ['prohibited'],
            'photos' => ['required', 'array', 'max:'.(int) config('gallery.upload.max_files', 10)],
            'photos.*' => ['required', 'file'],
            'accept_photo_policy' => ['nullable', 'boolean'],
            'photographer_name' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:65535'],
        ]);

        $results = [];
        $failed = false;

        foreach ($validated['photos'] as $index => $photo) {
            try {
                $communityPhoto = $upload->handle(
                    $request->user(),
                    $photo,
                    $validated['context'],
                    $validated['photographer_name'] ?? null,
                    $validated['caption'] ?? null,
                    (bool) ($validated['accept_photo_policy'] ?? false),
                );
                $results[] = ['index' => $index, 'name' => $photo->getClientOriginalName(), 'status' => 'uploaded', 'photo_id' => $communityPhoto->id];
            } catch (ValidationException|\RuntimeException $exception) {
                if ($exception instanceof \RuntimeException) {
                    report($exception);
                }

                $failed = true;
                $results[] = [
                    'index' => $index,
                    'name' => $photo->getClientOriginalName(),
                    'status' => 'failed',
                    'errors' => $exception instanceof ValidationException ? collect($exception->errors())->flatten()->values()->all() : ['This photo could not be uploaded.'],
                ];
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['photos' => $results], $failed ? 422 : 201);
        }

        if ($failed) {
            return back()->withInput()->with('upload_results', $results)
                ->withErrors(['photos' => 'One or more photos could not be uploaded.']);
        }

        return redirect()->route('community-photos.upload.create')
            ->with('status', 'Your photos have been submitted for moderation.');
    }
}
