<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\CmsReviewLink;
use App\Domain\Content\Queries\PublicCmsPages;
use App\Domain\Content\Support\CmsBlockPresenter;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class CmsPageController extends Controller
{
    public function show(string $slug, PublicCmsPages $pages): View
    {
        return $this->render($pages->query()->where('slug', $slug)->firstOrFail());
    }

    public function preview(Request $request, CmsPage $page): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent), 403);

        return $this->render($page, 'Draft preview');
    }

    public function review(string $token): View
    {
        $link = CmsReviewLink::query()->with('page')->whereNull('revoked_at')->where('expires_at', '>', now())->get()
            ->first(fn (CmsReviewLink $candidate): bool => Hash::check($token, $candidate->token_hash));
        abort_unless($link instanceof CmsReviewLink, 404);

        return $this->render($link->page, 'Review copy');
    }

    private function render(CmsPage $page, ?string $notice = null): View
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('pages.show', [
            'page' => $page,
            'blocks' => app(CmsBlockPresenter::class)->present($page->blocks),
            'notice' => $notice,
            'site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($profile),
        ]);
    }
}
