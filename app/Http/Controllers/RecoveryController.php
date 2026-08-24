<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Backups\Actions\RestoreBackup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

final readonly class RecoveryController
{
    public function __construct(private RestoreBackup $restore) {}

    public function show(): View
    {
        return view('recovery.show', ['completed' => false]);
    }

    public function restore(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'recovery_token' => ['required', 'string', 'max:1024'],
            'confirmation' => ['required', 'string', 'max:100'],
            'backup' => ['required', 'file', 'max:1048576'],
            'passphrase' => ['nullable', 'string', 'max:1024'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors(['recovery' => 'Recovery could not be authorised or the backup was invalid.']);
        }

        $tokenHash = config('waymark.recovery.token_hash');
        $suppliedHash = hash('sha256', (string) $request->input('recovery_token'));
        if (! is_string($tokenHash) || strlen($tokenHash) !== 64 || ! hash_equals($tokenHash, $suppliedHash)) {
            return back()->withErrors(['recovery' => 'Recovery could not be authorised or the backup was invalid.']);
        }

        try {
            $upload = $request->file('backup');
            $this->restore->restoreFile(
                (string) $upload?->getRealPath(),
                (string) $request->input('confirmation'),
                $request->filled('passphrase') ? (string) $request->input('passphrase') : null,
            );

            return view('recovery.show', ['completed' => true]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['recovery' => 'Recovery could not be authorised or the backup was invalid.']);
        }
    }
}
