<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Updates\Actions\FinalizeUpdate;
use App\Domain\Operations\Updates\UpdateStateStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final readonly class UpdateActivationController
{
    public function __construct(
        private UpdateStateStore $state,
        private FinalizeUpdate $updates,
    ) {}

    public function show(Request $request): View
    {
        $token = (string) $request->query('token');
        $pending = $this->state->authorisedPending($token);
        abort_unless(is_array($pending) && is_string($pending['pending_version'] ?? null), 404);

        return view('updates.activate', ['token' => $token, 'version' => $pending['pending_version']]);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = (string) $request->input('token');
        abort_unless($this->state->authorisedPending($token) !== null, 404);

        try {
            $result = $this->updates->handle($token);

            return redirect('/admin/update-centre')->with('status', 'Waymark Community '.$result->version.' was installed successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/recovery')->withErrors([
                'recovery' => 'Fresh-runtime update activation failed. Maintenance remains active; review recovery before retrying.',
            ]);
        }
    }
}
