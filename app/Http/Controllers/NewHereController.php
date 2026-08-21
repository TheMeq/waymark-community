<?php

namespace App\Http\Controllers;

use App\ViewModels\PublicAccountPageViewModel;
use Illuminate\Contracts\View\View;

final class NewHereController
{
    public function __invoke(): View
    {
        $page = PublicAccountPageViewModel::current();

        return view('account.new-here', [
            'site' => $page->site,
            'theme' => $page->theme,
        ]);
    }
}
