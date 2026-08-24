<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Updates\Actions\ApplyUpdate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final readonly class UpdateContinuationController
{
    public function __construct(private ApplyUpdate $updates) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts), 403);

        try {
            $result = $this->updates->continue();
            $request->session()->put('waymark.update_activation_token', $result->activationToken);

            return redirect('/updates/activate')->withHeaders(['Referrer-Policy' => 'no-referrer']);
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/admin/update-centre')->withErrors([
                'install' => 'The update is still waiting for a completed verified safety backup, or continuation failed safely.',
            ]);
        }
    }
}
