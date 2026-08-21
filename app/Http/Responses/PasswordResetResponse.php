<?php

namespace App\Http\Responses;

use App\Domain\Accounts\Security\SensitiveActionAssurance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Fortify;

final readonly class PasswordResetResponse implements PasswordResetResponseContract
{
    public function __construct(
        private string $status,
        private SensitiveActionAssurance $assurance,
    ) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->hasSession()) {
            $this->assurance->forgetAll($request->session());
        }

        return $request->wantsJson()
            ? new JsonResponse(['message' => trans($this->status)], 200)
            : redirect(Fortify::redirects('password-reset', config('fortify.views', true) ? route('login') : null))
                ->with('status', trans($this->status));
    }
}
