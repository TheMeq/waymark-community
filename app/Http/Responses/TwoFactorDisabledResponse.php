<?php

namespace App\Http\Responses;

use App\Domain\Accounts\Security\SensitiveActionAssurance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\TwoFactorDisabledResponse as TwoFactorDisabledResponseContract;
use Laravel\Fortify\Fortify;

final readonly class TwoFactorDisabledResponse implements TwoFactorDisabledResponseContract
{
    public function __construct(private SensitiveActionAssurance $assurance) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->hasSession()) {
            $this->assurance->forgetSecondFactorConfirmation($request->session());
        }

        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('status', Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED);
    }
}
