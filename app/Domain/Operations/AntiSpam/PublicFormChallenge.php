<?php

namespace App\Domain\Operations\AntiSpam;

use Illuminate\Http\Request;

interface PublicFormChallenge
{
    public function verify(Request $request): void;
}
