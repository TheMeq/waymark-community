<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Updates\Actions\FinalizeUpdate;
use App\Domain\Operations\Updates\PendingUpdateRollback;
use App\Domain\Operations\Updates\UpdateStateStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class UpdateActivationController
{
    public function __construct(
        private UpdateStateStore $state,
        private FinalizeUpdate $updates,
    ) {}

    public function show(Request $request): View|Response
    {
        $token = (string) $request->session()->get('waymark.update_activation_token', '');
        $pending = $this->state->authorisedPending($token);
        abort_unless(is_array($pending) && is_string($pending['pending_version'] ?? null), 404);

        return response()->view('updates.activate', ['token' => $token, 'version' => $pending['pending_version']])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $token = (string) $request->input('token');
        $sessionToken = (string) $request->session()->get('waymark.update_activation_token', '');
        abort_unless($sessionToken !== '' && hash_equals($sessionToken, $token) && $this->state->authorisedPending($token) !== null, 404);

        try {
            $result = $this->updates->handle($token);
            $request->session()->forget('waymark.update_activation_token');

            return to_route('filament.admin.pages.update-centre')->with('status', 'Waymark Community '.$result->version.' was installed successfully.');
        } catch (PendingUpdateRollback $exception) {
            $request->session()->forget('waymark.update_activation_token');

            return response()->view('updates.rollback', ['token' => $exception->rollbackToken])
                ->header('Referrer-Policy', 'no-referrer')
                ->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            report($exception);

            return to_route('recovery.show')->withErrors([
                'recovery' => 'Fresh-runtime update activation failed. Maintenance remains active; review recovery before retrying.',
            ]);
        }
    }
}
