<?php

namespace App\Http\Responses;

use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse as PasswordConfirmedResponseContract;

final readonly class PasswordConfirmedResponse implements PasswordConfirmedResponseContract
{
    public function __construct(private SensitiveActionAssurance $assurance) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->assurance->recordPasswordConfirmation($user, $request->session());
        }

        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->to($this->assurance->consumeIntendedDestination(
                $request,
                route('new-here'),
            ));
    }
}
