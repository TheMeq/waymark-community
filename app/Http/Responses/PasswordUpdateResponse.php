<?php

namespace App\Http\Responses;

use App\Domain\Accounts\Security\SensitiveActionAssurance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\PasswordUpdateResponse as PasswordUpdateResponseContract;
use Laravel\Fortify\Fortify;

final readonly class PasswordUpdateResponse implements PasswordUpdateResponseContract
{
    public function __construct(private SensitiveActionAssurance $assurance) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->hasSession()) {
            $this->assurance->forgetAll($request->session());
        }

        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', Fortify::PASSWORD_UPDATED);
    }
}
