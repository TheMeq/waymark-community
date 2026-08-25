<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <title>{{ $step->heading() }} — Waymark Community</title>
        <style>
            :root { color-scheme: light; font-family: system-ui, sans-serif; color: #252a24; background: #f5f4ef; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem; }
            main { min-width: 0; width: min(100%, 48rem); padding: clamp(2rem, 6vw, 4rem); overflow-wrap: anywhere; border: 1px solid #d9ddd4; border-radius: 1.5rem; background: #fff; box-shadow: 0 1rem 3rem rgb(37 42 36 / 8%); }
            .product { margin: 0; color: #526b3f; font-weight: 750; }
            .progress { margin: 2rem 0 .5rem; color: #677064; font-size: .875rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
            h1 { margin: 0; font-size: clamp(2rem, 6vw, 3.5rem); line-height: 1; letter-spacing: -.04em; }
            p { line-height: 1.6; }
            ul.checks { display: grid; gap: .75rem; padding: 0; list-style: none; }
            ul.checks > li { padding: 1rem; border: 1px solid #d9ddd4; border-radius: .875rem; }
            li[data-status="blocker"] { border-color: #a63f36; background: #fff6f4; }
            li[data-status="warning"] { border-color: #b28a36; background: #fffaf0; }
            li strong { display: block; }
            li p { margin: .25rem 0 0; }
            .error, .field-error { color: #8e3028; font-weight: 700; }
            .field-error { font-size: .875rem; }
            .error-summary { margin-top: 1rem; padding: 1rem; border: 2px solid #a63f36; border-radius: .875rem; background: #fff6f4; }
            .error-summary ul { margin-bottom: 0; }
            .required-note, .hint { color: #5f685c; font-size: .9rem; }
            .required { color: #8e3028; }
            .wm-field { display: grid; gap: .35rem; margin-top: 1rem; font-weight: 700; }
            input:not([type="checkbox"]):not([type="radio"]), select, textarea { width: 100%; min-height: 44px; padding: .65rem .75rem; border: 1px solid #7f897c; border-radius: .65rem; background: #fff; color: inherit; font: inherit; }
            textarea { min-height: 8rem; font-family: ui-monospace, monospace; }
            input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid #d97845; outline-offset: 3px; }
            button { min-height: 44px; padding: .75rem 1.25rem; border: 0; border-radius: 999px; color: #fff; background: #526b3f; font: inherit; font-weight: 700; cursor: pointer; }
            button.secondary { border: 1px solid #526b3f; color: #526b3f; background: #fff; }
            .actions { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-top: 1.5rem; }
            .actions a { display: inline-flex; min-height: 44px; align-items: center; padding: .65rem .25rem; color: #405631; font-weight: 700; }
            fieldset { min-width: 0; margin: 1rem 0 0; padding: 0; border: 0; }
            legend { font-weight: 750; }
            .wm-choice { display: flex; min-height: 44px; align-items: flex-start; gap: .75rem; margin-top: .35rem; padding: .55rem .35rem; border-radius: .65rem; font-weight: 650; cursor: pointer; }
            .wm-choice input[type="checkbox"], .wm-choice input[type="radio"] { flex: 0 0 auto; width: 1.125rem; height: 1.125rem; min-height: 0; margin: .15rem 0 0; padding: 0; accent-color: #526b3f; }
            .wm-choice:has(input:disabled) { color: #677064; cursor: not-allowed; }
            .brand-preview { margin-top: 1.5rem; padding: 1.5rem; border-radius: 1rem; color: #fff; background: var(--preview-primary); }
            .brand-preview span { display: inline-block; margin-top: .75rem; padding: .5rem .8rem; border-radius: 999px; color: #252a24; background: var(--preview-accent); font-weight: 700; }
            [hidden] { display: none !important; }
            @media (max-width: 36rem) { body { align-items: start; padding: .75rem; } main { padding: 1.5rem; border-radius: 1rem; } .actions { align-items: stretch; } .actions button, .actions a { justify-content: center; width: 100%; } }
        </style>
    </head>
    <body>
        @php
            $formSteps = [
                App\Domain\Operations\Installation\SetupStep::Database,
                App\Domain\Operations\Installation\SetupStep::GroupDetails,
                App\Domain\Operations\Installation\SetupStep::Branding,
                App\Domain\Operations\Installation\SetupStep::FirstAdministrator,
                App\Domain\Operations\Installation\SetupStep::Mail,
                App\Domain\Operations\Installation\SetupStep::Modules,
                App\Domain\Operations\Installation\SetupStep::Advanced,
            ];
            $previous = $step->number() > 1 ? App\Domain\Operations\Installation\SetupStep::cases()[$step->number() - 2] : null;
        @endphp
        <main>
            <p class="product">Waymark Community</p>
            <p class="progress" aria-label="Setup progress">Step {{ $step->number() }} of 11</p>
            <h1>{{ $step->heading() }}</h1>

            @if (in_array($step, $formSteps, true))
                <p class="required-note"><span class="required" aria-hidden="true">*</span> Fields marked * are required.</p>
            @endif

            @if ($errors->any())
                <div class="error-summary" id="error-summary" role="alert" tabindex="-1">
                    <strong>Check the highlighted fields.</strong>
                    <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif
            @if (session('status'))<p role="status">{{ session('status') }}</p>@endif

            @if ($step === App\Domain\Operations\Installation\SetupStep::Welcome)
                <p>Installation has not started. This guide will check the server and collect the details needed for this walking group.</p>
                <form method="post" action="{{ route('setup.start') }}">
                    @csrf
                    <div class="actions"><button type="submit">Begin setup</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::ServerChecks)
                <p>Waymark checked the PHP and hosting features it needs.</p>
                <ul class="checks">
                    @foreach ($preflight->checks as $check)
                        <li data-status="{{ $check->status }}">
                            <strong>{{ $check->label }} — {{ ucfirst($check->status) }}</strong>
                            <p>{{ $check->message }}</p>
                            @if ($check->remediation)<p>{{ $check->remediation }}</p>@endif
                        </li>
                    @endforeach
                </ul>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <div class="actions"><a href="{{ route('setup') }}">Back</a><button type="submit">Continue</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Database)
                <p>Connect Waymark to the MySQL or MariaDB database supplied by your host. The database account must be able to create and change its schema.</p>
                @if ($databaseRecovery)
                    <section class="error-summary" aria-labelledby="database-recovery-heading">
                        <h2 id="database-recovery-heading">Incomplete Waymark installation detected</h2>
                        <p>Waymark found a known partial fresh-install schema. You can remove it only after the installer verifies that no unrelated data would be affected.</p>
                        @error('reset')<p class="field-error">{{ $message }}</p>@enderror
                        <form method="post" action="{{ route('setup.install.reset') }}">
                            @csrf
                            <label class="wm-field" for="reset-confirmation">Type <strong>{{ App\Domain\Operations\Installation\ResetIncompleteInstallation::CONFIRMATION }}</strong> to confirm <span class="required" aria-hidden="true">*</span>
                                <input id="reset-confirmation" name="confirmation" required autocomplete="off">
                            </label>
                            <div class="actions"><button type="submit">Reset incomplete installation and retry</button></div>
                        </form>
                    </section>
                @endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label class="wm-field" for="driver">Database type <span class="required" aria-hidden="true">*</span>
                        <select id="driver" name="driver" required aria-describedby="driver-hint"><option value="mysql" @selected(old('driver', $data['driver'] ?? 'mysql') === 'mysql')>MySQL</option><option value="mariadb" @selected(old('driver', $data['driver'] ?? '') === 'mariadb')>MariaDB</option></select>
                        <span class="hint" id="driver-hint">Choose the engine used by the database you created in the hosting control panel.</span>
                        @error('driver')<span class="field-error" data-field-error="driver">{{ $message }}</span>@enderror
                    </label>
                    <label class="wm-field" for="host">Database host <span class="required" aria-hidden="true">*</span>
                        <input id="host" name="host" value="{{ old('host', $data['host'] ?? '') }}" autocomplete="off" required aria-describedby="host-hint">
                        <span class="hint" id="host-hint">The database server name supplied by your host, often localhost.</span>
                        @error('host')<span class="field-error" data-field-error="host">{{ $message }}</span>@enderror
                    </label>
                    <label class="wm-field" for="port">Port <span class="required" aria-hidden="true">*</span>
                        <input id="port" name="port" type="number" value="{{ old('port', $data['port'] ?? 3306) }}" min="1" max="65535" required aria-describedby="port-hint">
                        <span class="hint" id="port-hint">Use the host's database port; MySQL and MariaDB commonly use 3306.</span>
                        @error('port')<span class="field-error" data-field-error="port">{{ $message }}</span>@enderror
                    </label>
                    <label class="wm-field" for="database">Database name <span class="required" aria-hidden="true">*</span>
                        <input id="database" name="database" value="{{ old('database', $data['database'] ?? '') }}" autocomplete="off" required aria-describedby="database-hint">
                        <span class="hint" id="database-hint">The empty database created for this Waymark installation.</span>
                        @error('database')<span class="field-error" data-field-error="database">{{ $message }}</span>@enderror
                    </label>
                    <label class="wm-field" for="username">Username <span class="required" aria-hidden="true">*</span>
                        <input id="username" name="username" value="{{ old('username', $data['username'] ?? '') }}" autocomplete="username" required aria-describedby="username-hint">
                        <span class="hint" id="username-hint">The database account assigned to this database with schema permissions.</span>
                        @error('username')<span class="field-error" data-field-error="username">{{ $message }}</span>@enderror
                    </label>
                    <label class="wm-field" for="database-password">Password
                        <input id="database-password" name="password" type="password" value="" autocomplete="new-password" aria-describedby="database-password-hint">
                        <span class="hint" id="database-password-hint">The password for the database account. Leave blank only if your host explicitly requires no password.</span>
                        @error('password')<span class="field-error" data-field-error="password">{{ $message }}</span>@enderror
                    </label>
                    <div class="actions"><a href="{{ route('setup.step', 'server-checks') }}">Back</a><button type="submit">Verify database and continue</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::GroupDetails)
                <p>Use the walking group's own identity. These details can be refined later.</p>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label class="wm-field" for="group-name">Group name <span class="required" aria-hidden="true">*</span><input id="group-name" name="group_name" value="{{ old('group_name', $data['group_name'] ?? '') }}" required>@error('group_name')<span class="field-error" data-field-error="group_name">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="short-name">Short name <input id="short-name" name="short_name" value="{{ old('short_name', $data['short_name'] ?? '') }}"></label>
                    <label class="wm-field" for="contact-email">Contact email <span class="required" aria-hidden="true">*</span><input id="contact-email" name="contact_email" type="email" value="{{ old('contact_email', $data['contact_email'] ?? '') }}" required>@error('contact_email')<span class="field-error" data-field-error="contact_email">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="timezone">Timezone <span class="required" aria-hidden="true">*</span><input id="timezone" name="timezone" value="{{ old('timezone', $data['timezone'] ?? 'Europe/London') }}" required>@error('timezone')<span class="field-error" data-field-error="timezone">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="distance-unit">Distance <span class="required" aria-hidden="true">*</span><select id="distance-unit" name="distance_unit" required><option value="miles">Miles</option><option value="kilometres">Kilometres</option></select>@error('distance_unit')<span class="field-error" data-field-error="distance_unit">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="ascent-unit">Ascent <span class="required" aria-hidden="true">*</span><select id="ascent-unit" name="ascent_unit" required><option value="feet">Feet</option><option value="metres">Metres</option></select>@error('ascent_unit')<span class="field-error" data-field-error="ascent_unit">{{ $message }}</span>@enderror</label>
                    <div class="actions"><a href="{{ route('setup.step', 'database') }}">Back</a><button type="submit">Continue</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Branding)
                <p>Choose a restrained starting palette. The full branding editor remains available after setup.</p>
                <div class="brand-preview" style="--preview-primary: {{ $data['primary_colour'] ?? '#526B3F' }}; --preview-accent: {{ $data['accent_colour'] ?? '#D97845' }}"><strong>{{ $groupDetails['group_name'] ?? 'Your walking group' }}</strong><br><span>View upcoming walks</span></div>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label class="wm-field" for="primary-colour">Primary colour <span class="required" aria-hidden="true">*</span><input id="primary-colour" name="primary_colour" type="color" value="{{ old('primary_colour', $data['primary_colour'] ?? '#526B3F') }}" required>@error('primary_colour')<span class="field-error" data-field-error="primary_colour">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="accent-colour">Accent colour <span class="required" aria-hidden="true">*</span><input id="accent-colour" name="accent_colour" type="color" value="{{ old('accent_colour', $data['accent_colour'] ?? '#D97845') }}" required>@error('accent_colour')<span class="field-error" data-field-error="accent_colour">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="typography">Typography <span class="required" aria-hidden="true">*</span><select id="typography" name="typography_option" required><option value="instrument">Waymark type</option><option value="system">System type</option></select>@error('typography_option')<span class="field-error" data-field-error="typography_option">{{ $message }}</span>@enderror</label>
                    <div class="actions"><a href="{{ route('setup.step', 'group-details') }}">Back</a><button type="submit">Continue</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::FirstAdministrator)
                <p>This verified account becomes the installation owner.</p>
                <p class="hint">The password must use at least 12 characters and include letters and numbers.</p>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label class="wm-field" for="admin-name">Name <span class="required" aria-hidden="true">*</span><input id="admin-name" name="name" value="{{ old('name', $data['name'] ?? '') }}" autocomplete="name" required>@error('name')<span class="field-error" data-field-error="name">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="admin-email">Email <span class="required" aria-hidden="true">*</span><input id="admin-email" name="email" type="email" value="{{ old('email', $data['email'] ?? '') }}" autocomplete="email" required>@error('email')<span class="field-error" data-field-error="email">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="admin-password">Password <span class="required" aria-hidden="true">*</span><input id="admin-password" name="password" type="password" value="" autocomplete="new-password" minlength="12" required>@error('password')<span class="field-error" data-field-error="password">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="admin-password-confirmation">Confirm password <span class="required" aria-hidden="true">*</span><input id="admin-password-confirmation" name="password_confirmation" type="password" value="" autocomplete="new-password" minlength="12" required>@error('password_confirmation')<span class="field-error" data-field-error="password_confirmation">{{ $message }}</span>@enderror</label>
                    <div class="actions"><a href="{{ route('setup.step', 'branding') }}">Back</a><button type="submit">Continue</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Mail)
                @php $emailChoice = old('email_setup', ($data['configured'] ?? true) ? 'configure' : 'later'); @endphp
                <p>Waymark can be installed without email, but email verification, password resets, notifications and other email-dependent features will not work until email delivery is configured.</p>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}" id="mail-form">@csrf
                    <fieldset>
                        <legend>Email setup <span class="required" aria-hidden="true">*</span></legend>
                        <label class="wm-choice"><input name="email_setup" type="radio" value="configure" @checked($emailChoice === 'configure') required> <span>Configure email now</span></label>
                        <label class="wm-choice"><input name="email_setup" type="radio" value="later" @checked($emailChoice === 'later') required> <span>Set up email later</span></label>
                        @error('email_setup')<span class="field-error" data-field-error="email_setup">{{ $message }}</span>@enderror
                    </fieldset>
                    <div id="smtp-fields" @if($emailChoice === 'later') hidden @endif>
                        <p class="hint">Waymark sends a test message before saving these settings.</p>
                        <label class="wm-field" for="smtp-host">SMTP host <span class="required" aria-hidden="true">*</span><input id="smtp-host" name="host" value="{{ old('host', $data['host'] ?? '') }}" data-mail-required @if($emailChoice === 'configure') required @endif>@error('host')<span class="field-error" data-field-error="host">{{ $message }}</span>@enderror</label>
                        <label class="wm-field" for="smtp-port">Port <span class="required" aria-hidden="true">*</span><input id="smtp-port" name="port" type="number" min="1" max="65535" value="{{ old('port', $data['port'] ?? 587) }}" data-mail-required @if($emailChoice === 'configure') required @endif>@error('port')<span class="field-error" data-field-error="port">{{ $message }}</span>@enderror</label>
                        <label class="wm-field" for="smtp-encryption">Encryption <select id="smtp-encryption" name="encryption"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="">None</option></select></label>
                        <label class="wm-field" for="smtp-username">Username <input id="smtp-username" name="username" value="{{ old('username', $data['username'] ?? '') }}" autocomplete="username"></label>
                        <label class="wm-field" for="smtp-password">Password <input id="smtp-password" name="password" type="password" value="" autocomplete="new-password"></label>
                        <label class="wm-field" for="from-address">From address <span class="required" aria-hidden="true">*</span><input id="from-address" name="from_address" type="email" value="{{ old('from_address', $data['from_address'] ?? '') }}" data-mail-required @if($emailChoice === 'configure') required @endif>@error('from_address')<span class="field-error" data-field-error="from_address">{{ $message }}</span>@enderror</label>
                        <label class="wm-field" for="test-address">Send test to <span class="required" aria-hidden="true">*</span><input id="test-address" name="test_address" type="email" value="{{ old('test_address', $data['test_address'] ?? '') }}" data-mail-required @if($emailChoice === 'configure') required @endif>@error('test_address')<span class="field-error" data-field-error="test_address">{{ $message }}</span>@enderror</label>
                        @error('mail')<p class="field-error" data-field-error="mail">{{ $message }}</p>@enderror
                    </div>
                    <div class="actions"><a href="{{ route('setup.step', 'first-administrator') }}">Back</a><button type="submit">Continue</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Modules)
                <p>Walks are always available. Choose the other public areas this group needs now.</p>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <fieldset>
                        <legend>Modules <span class="required" aria-hidden="true">*</span></legend>
                        <input type="hidden" name="modules[]" value="walks">
                        @foreach (['walks' => 'Walks', 'socials' => 'Socials', 'holidays' => 'Weekends away', 'gallery' => 'Gallery', 'news' => 'News', 'documents' => 'Documents'] as $value => $label)
                            <label class="wm-choice"><input @if($value !== 'walks') name="modules[]" @endif type="checkbox" value="{{ $value }}" @checked($value === 'walks' || in_array($value, old('modules', $data['enabled'] ?? ['walks', 'socials', 'holidays', 'gallery', 'news', 'documents']), true)) @disabled($value === 'walks')> <span>{{ $label }} @if($value === 'walks')<span class="hint">(always available)</span>@endif</span></label>
                        @endforeach
                        @error('modules')<span class="field-error" data-field-error="modules">{{ $message }}</span>@enderror
                    </fieldset>
                    <div class="actions"><a href="{{ route('setup.step', 'mail') }}">Back</a><button type="submit">Continue</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Advanced)
                <p>Local backups are the safe default. External storage and release metadata can be added now or later.</p>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label class="wm-field" for="backup-disk">Backup destination <span class="required" aria-hidden="true">*</span><select id="backup-disk" name="backup_disk" required><option value="local" @selected(old('backup_disk', $data['backup_disk'] ?? 'local') === 'local')>Local private storage</option><option value="s3" @selected(old('backup_disk', $data['backup_disk'] ?? '') === 's3')>S3-compatible storage</option></select>@error('backup_disk')<span class="field-error" data-field-error="backup_disk">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="release-url">Release metadata URL <input id="release-url" name="release_metadata_url" type="url" value="{{ old('release_metadata_url', $data['release_metadata_url'] ?? '') }}"></label>
                    <label class="wm-field" for="release-key">Release verification public key <span class="required" data-release-indicator aria-hidden="true" hidden>*</span><span class="hint">Required when a release metadata URL is supplied.</span><textarea id="release-key" name="release_public_key_base64" rows="3">{{ old('release_public_key_base64', $data['release_public_key_base64'] ?? '') }}</textarea>@error('release_public_key_base64')<span class="field-error" data-field-error="release_public_key_base64">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="s3-endpoint">S3-compatible endpoint <span class="required" data-s3-indicator aria-hidden="true" hidden>*</span><span class="hint">Required only for S3-compatible storage.</span><input id="s3-endpoint" data-s3-required name="s3_endpoint" type="url" value="{{ old('s3_endpoint', $data['s3_endpoint'] ?? '') }}">@error('s3_endpoint')<span class="field-error" data-field-error="s3_endpoint">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="s3-bucket">S3 bucket <span class="required" data-s3-indicator aria-hidden="true" hidden>*</span><span class="hint">Required only for S3-compatible storage.</span><input id="s3-bucket" data-s3-required name="s3_bucket" value="{{ old('s3_bucket', $data['s3_bucket'] ?? '') }}">@error('s3_bucket')<span class="field-error" data-field-error="s3_bucket">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="s3-access-key">S3 access key <span class="required" data-s3-indicator aria-hidden="true" hidden>*</span><span class="hint">Required only for S3-compatible storage.</span><input id="s3-access-key" data-s3-required name="s3_access_key" value="{{ old('s3_access_key', $data['s3_access_key'] ?? '') }}" autocomplete="off">@error('s3_access_key')<span class="field-error" data-field-error="s3_access_key">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="s3-secret-key">S3 secret key <span class="required" data-s3-indicator aria-hidden="true" hidden>*</span><span class="hint">Required only for S3-compatible storage.</span><input id="s3-secret-key" data-s3-required name="s3_secret_key" type="password" value="" autocomplete="new-password">@error('s3_secret_key')<span class="field-error" data-field-error="s3_secret_key">{{ $message }}</span>@enderror</label>
                    <p>The recovery key protects standalone disaster recovery. Store it in a password manager; Waymark stores only a one-way hash and cannot display the plaintext key again later.</p>
                    <p class="hint" id="recovery-rules">Use at least 24 characters with uppercase and lowercase, at least one number, and at least one symbol.</p>
                    <label class="wm-field" for="recovery-key">Recovery key <span class="required" aria-hidden="true">*</span><input id="recovery-key" name="recovery_token" type="password" value="" autocomplete="new-password" minlength="24" required aria-describedby="recovery-rules">@error('recovery_token')<span class="field-error" data-field-error="recovery_token">{{ $message }}</span>@enderror</label>
                    <label class="wm-field" for="recovery-key-confirmation">Confirm recovery key <span class="required" aria-hidden="true">*</span><input id="recovery-key-confirmation" name="recovery_token_confirmation" type="password" value="" autocomplete="new-password" minlength="24" required>@error('recovery_token_confirmation')<span class="field-error" data-field-error="recovery_token_confirmation">{{ $message }}</span>@enderror</label>
                    <div class="actions"><button class="secondary" id="generate-recovery-key" type="button" data-url="{{ route('setup.recovery-key') }}">Generate secure recovery key</button><button class="secondary" id="copy-recovery-key" type="button">Copy recovery key</button></div>
                    <label class="wm-choice"><input name="recovery_key_saved" type="checkbox" value="1" required> <span>I have saved the recovery key in a password manager. <span class="required" aria-hidden="true">*</span></span></label>
                    @error('recovery_key_saved')<span class="field-error" data-field-error="recovery_key_saved">{{ $message }}</span>@enderror
                    <div class="actions"><a href="{{ route('setup.step', 'modules') }}">Back</a><button type="submit">Continue</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Install)
                <p>Waymark will save configuration and begin a resumable installation. The final page shows each verified stage as it completes.</p>
                @if ($environmentFile)<p>{{ $environmentInstructions }}</p><label class="wm-field">Environment file content<textarea readonly>{{ $environmentFile }}</textarea></label>@endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <div class="actions"><a href="{{ route('setup.step', 'advanced') }}">Back</a><button type="submit">Install Waymark Community</button></div>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::HealthCheck)
                <p>These final checks must pass before the installer is permanently locked.</p>
                <ul class="checks">@foreach ($healthChecks as $check)<li data-status="{{ $check['passed'] ? 'pass' : 'blocker' }}"><strong>{{ $check['label'] }}</strong><p>{{ $check['message'] }}</p></li>@endforeach</ul>
            @endif
        </main>
        <script>
            document.getElementById('error-summary')?.focus();

            const mailChoices = document.querySelectorAll('input[name="email_setup"]');
            const smtpFields = document.getElementById('smtp-fields');
            const updateMailFields = () => {
                if (!smtpFields) return;
                const configure = document.querySelector('input[name="email_setup"]:checked')?.value === 'configure';
                smtpFields.hidden = !configure;
                smtpFields.querySelectorAll('[data-mail-required]').forEach((field) => field.required = configure);
            };
            mailChoices.forEach((choice) => choice.addEventListener('change', updateMailFields));
            updateMailFields();

            const backupDisk = document.getElementById('backup-disk');
            const updateS3Fields = () => {
                const required = backupDisk?.value === 's3';
                document.querySelectorAll('[data-s3-required]').forEach((field) => field.required = required);
                document.querySelectorAll('[data-s3-indicator]').forEach((indicator) => indicator.hidden = !required);
            };
            backupDisk?.addEventListener('change', updateS3Fields);
            updateS3Fields();

            const releaseUrl = document.getElementById('release-url');
            const releaseKey = document.getElementById('release-key');
            const updateReleaseKey = () => {
                const required = Boolean(releaseUrl?.value.trim());
                if (releaseKey) releaseKey.required = required;
                document.querySelectorAll('[data-release-indicator]').forEach((indicator) => indicator.hidden = !required);
            };
            releaseUrl?.addEventListener('input', updateReleaseKey);
            updateReleaseKey();

            const generate = document.getElementById('generate-recovery-key');
            const copy = document.getElementById('copy-recovery-key');
            const recoveryKey = document.getElementById('recovery-key');
            const recoveryConfirmation = document.getElementById('recovery-key-confirmation');
            generate?.addEventListener('click', async () => {
                const response = await fetch(generate.dataset.url, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                });
                if (!response.ok) return;
                const result = await response.json();
                recoveryKey.value = result.recovery_key;
                recoveryConfirmation.value = result.recovery_key;
                recoveryKey.type = 'text';
                recoveryConfirmation.type = 'text';
                recoveryKey.focus();
            });
            copy?.addEventListener('click', async () => {
                if (!recoveryKey?.value) return;
                await navigator.clipboard.writeText(recoveryKey.value);
                copy.textContent = 'Recovery key copied';
            });
        </script>
    </body>
</html>
