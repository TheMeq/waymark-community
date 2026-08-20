<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\ViewModels\HomepageViewModel;
use Illuminate\Contracts\View\View;

final class HomeController
{
    public function __invoke(): View
    {
        return view('home', [
            'homepage' => HomepageViewModel::demo(),
            'theme' => BrandTheme::fromSiteProfile(new SiteProfile),
        ]);
    }
}
