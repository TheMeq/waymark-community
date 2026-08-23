<?php

namespace App\Http\Controllers;

use App\Domain\Gallery\Actions\SubmitCommunityPhotoReport;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Operations\AntiSpam\PublicFormChallenge;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CommunityPhotoReportController
{
    public function create(int $photo, SubmitCommunityPhotoReport $reports): View
    {
        $photo = CommunityPhoto::query()->findOrFail($photo);
        abort_unless($reports->isReportable($photo), 404);
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('gallery.report', ['photo' => $photo, 'theme' => BrandTheme::fromSiteProfile($siteProfile), 'site' => ['name' => $siteProfile->group_name ?? 'Waymark Community']]);
    }

    public function __invoke(Request $request, ?int $photo, SubmitCommunityPhotoReport $reports, PublicFormChallenge $challenge): RedirectResponse
    {
        $challenge->verify($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'in:in_photo,privacy,copyright,inappropriate,other'], 'detail' => ['nullable', 'string', 'max:1000', 'required_if:reason,other'], 'contact' => ['nullable', 'email:rfc,dns', 'max:255'], 'website' => ['nullable', 'max:0']]);
        try {
            $photo = is_int($photo) ? CommunityPhoto::query()->find($photo) : null;
            if (! $photo instanceof CommunityPhoto) {
                throw ValidationException::withMessages(['photo' => 'This photo is not available for reporting.']);
            }
            $user = $request->user();
            $reports->handle($photo, $validated['reason'], $validated['detail'] ?? null, $validated['contact'] ?? null, $user instanceof User ? $user : null);
        } catch (ValidationException) {
            // A public response must not reveal whether a submitted ID is private, removed, or absent.
        }

        return back()->with('status', 'Thank you. Your report has been received.');
    }
}
