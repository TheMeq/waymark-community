<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Actions\RequestAccountDeletion;
use App\Domain\Accounts\Actions\RequestPersonalDataExport;
use App\Domain\Accounts\Models\PersonalDataExport;
use App\Models\User;
use App\ViewModels\PublicAccountPageViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class AccountPrivacyController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $page = PublicAccountPageViewModel::current();

        return view('account.privacy.show', [
            'site' => $page->site,
            'theme' => $page->theme,
            'exports' => $user->personalDataExports()->latest('id')->limit(5)->get(),
            'deletionRequest' => $user->deletionRequests()->latest('id')->first(),
        ]);
    }

    public function requestExport(Request $request, RequestPersonalDataExport $exports): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $exports->handle($user);

        return to_route('account.privacy.show')->with('status', 'Your personal data export has been requested.');
    }

    public function requestDeletion(Request $request, RequestAccountDeletion $deletions): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $deletions->handle($user);

        return to_route('account.privacy.show')->with('status', 'Your deletion request has been sent for administrator review.');
    }

    public function download(Request $request, PersonalDataExport $export)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($export->user_id === $user->id && $export->status === 'ready' && ! $export->isExpired() && $export->acceptsDownloadToken($request->string('token')->toString()), 403);
        abort_unless($export->hasSafeStoragePath() && Storage::disk('local')->exists($export->storage_path), 403);

        return Storage::disk('local')->download($export->storage_path, 'waymark-personal-data-export.json', ['Content-Type' => 'application/json']);
    }
}
