<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>Installation progress — Waymark Community</title>
        <style>
            :root { color-scheme: light; font-family: system-ui, sans-serif; color: #252a24; background: #f5f4ef; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
            main { min-width: 0; width: min(100%, 48rem); padding: clamp(2rem, 6vw, 4rem); overflow-wrap: anywhere; border: 1px solid #d9ddd4; border-radius: 1.5rem; background: #fff; box-shadow: 0 1rem 3rem rgb(37 42 36 / 8%); }
            .product { margin: 0; color: #526b3f; font-weight: 750; }
            h1 { margin: 1rem 0 0; font-size: clamp(2rem, 6vw, 3.5rem); line-height: 1; letter-spacing: -.04em; }
            p { line-height: 1.6; }
            progress { width: 100%; min-height: 1rem; accent-color: #526b3f; }
            .status { margin-top: 1.5rem; padding: 1rem; border: 1px solid #d9ddd4; border-radius: .875rem; background: #f8f8f4; }
            .status[data-failed="true"] { border-color: #a63f36; background: #fff6f4; }
            button { min-height: 44px; margin-top: 1rem; padding: .75rem 1.25rem; border: 0; border-radius: 999px; color: #fff; background: #526b3f; font: inherit; font-weight: 700; cursor: pointer; }
            button:focus-visible { outline: 3px solid #d97845; outline-offset: 3px; }
        </style>
    </head>
    <body>
        <main>
            <p class="product">Waymark Community</p>
            <h1>Installation progress</h1>
            <p>Keep this page open while Waymark works through the installation. Progress is saved, so refreshing or reconnecting resumes this attempt.</p>

            <progress id="installation-progress" max="{{ max(1, $status['migration']['total'] + 7) }}" value="{{ $status['migration']['current'] }}">{{ $status['migration']['current'] }}</progress>
            <section class="status" id="installation-status" data-stage="{{ $status['stage'] }}" data-failed="{{ $status['failed'] ? 'true' : 'false' }}" role="status" aria-live="polite" aria-atomic="true">
                <strong id="installation-stage">{{ $status['stage_label'] }}</strong>
                <p id="installation-message">{{ $status['message'] }}</p>
                @if ($status['diagnostic_id'])
                    <p>Reference: <strong>{{ $status['diagnostic_id'] }}</strong></p>
                    <p>Detailed diagnostics are recorded server-side in <code>storage/logs/laravel.log</code>.</p>
                @endif
            </section>

            @if (! $status['completed'] && ! $status['failed'])
                <form id="installation-advance" method="post" action="{{ route('setup.install.advance') }}">
                    @csrf
                    <button type="submit">Continue installation</button>
                </form>
            @elseif ($status['completed'])
                <p><a href="{{ route('filament.admin.pages.dashboard') }}">Continue to administration</a></p>
            @elseif ($status['changed'])
                @if ($errors->any())<p>{{ $errors->first() }}</p>@endif
                <form method="post" action="{{ route('setup.install.reset') }}">
                    @csrf
                    <p><strong>Reset incomplete installation and retry</strong></p>
                    <p>This permanently removes only the fresh Waymark schema that this installer can prove belongs to the incomplete attempt. It will stop without changing the database if that proof fails.</p>
                    <label for="reset-confirmation">Type <strong>{{ App\Domain\Operations\Installation\ResetIncompleteInstallation::CONFIRMATION }}</strong> to confirm
                        <input id="reset-confirmation" name="confirmation" required autocomplete="off">
                    </label>
                    <button type="submit">Reset incomplete installation and retry</button>
                </form>
            @endif
        </main>

        @if (! $status['completed'] && ! $status['failed'])
            <script>
                (() => {
                    const form = document.getElementById('installation-advance');
                    const status = document.getElementById('installation-status');
                    const stage = document.getElementById('installation-stage');
                    const message = document.getElementById('installation-message');
                    const progress = document.getElementById('installation-progress');
                    let advancing = false;

                    const advance = async () => {
                        if (advancing) return;
                        advancing = true;

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                headers: { Accept: 'application/json' },
                                body: new FormData(form),
                            });
                            const result = await response.json();
                            stage.textContent = result.stage_label ?? 'Installation paused';
                            message.textContent = result.message ?? 'Review the installation status before continuing.';
                            status.dataset.stage = result.stage ?? '';
                            status.dataset.failed = result.failed ? 'true' : 'false';
                            progress.value = result.migration?.current ?? progress.value;

                            if (result.completed) {
                                window.location.assign(@json(route('filament.admin.pages.dashboard')));
                                return;
                            }

                            if (!result.failed && response.ok) window.setTimeout(advance, 250);
                        } catch (error) {
                            message.textContent = 'The connection was interrupted. Refresh this page to resume the saved installation attempt.';
                        } finally {
                            advancing = false;
                        }
                    };

                    form.addEventListener('submit', (event) => {
                        event.preventDefault();
                        advance();
                    });
                    window.setTimeout(advance, 100);
                })();
            </script>
        @endif
    </body>
</html>
