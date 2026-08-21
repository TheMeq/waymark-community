<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\ViewModels\AccountSecurityPageViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AccountSecurityController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account.security.show', AccountSecurityPageViewModel::for($user)->toArray());
    }
}
