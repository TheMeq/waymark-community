<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class SetupController
{
    public function __invoke(): View
    {
        return view('setup.welcome');
    }
}
