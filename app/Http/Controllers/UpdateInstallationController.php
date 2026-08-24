<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Updates\Actions\ApplyUpdate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

final readonly class UpdateInstallationController
{
    public function __construct(private ApplyUpdate $updates) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts), 403);
        $validator = Validator::make($request->all(), [
            'confirmation' => ['required', Rule::in([ApplyUpdate::CONFIRMATION])],
        ]);
        if ($validator->fails()) {
            return redirect('/admin/update-centre')->withErrors($validator);
        }
        $validated = $validator->validated();

        try {
            $result = $this->updates->handle($validated['confirmation']);

            return redirect('/admin/update-centre')->with('status', 'Waymark Community '.$result->version.' was installed successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/admin/update-centre')->withErrors([
                'install' => 'The update was not applied. Review the compatibility, maintenance and recovery status before trying again.',
            ]);
        }
    }
}
