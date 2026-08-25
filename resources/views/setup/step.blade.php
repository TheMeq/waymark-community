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
            ul { display: grid; gap: .75rem; padding: 0; list-style: none; }
            li { padding: 1rem; border: 1px solid #d9ddd4; border-radius: .875rem; }
            li[data-status="blocker"] { border-color: #a63f36; background: #fff6f4; }
            li[data-status="warning"] { border-color: #b28a36; background: #fffaf0; }
            li strong { display: block; }
            li p { margin: .25rem 0 0; }
            .error { color: #8e3028; font-weight: 700; }
            button { min-height: 44px; margin-top: 1rem; padding: .75rem 1.25rem; border: 0; border-radius: 999px; color: #fff; background: #526b3f; font: inherit; font-weight: 700; cursor: pointer; }
            label { display: grid; gap: .35rem; margin-top: 1rem; font-weight: 700; }
            input, select { min-height: 44px; padding: .65rem .75rem; border: 1px solid #9da59a; border-radius: .65rem; background: #fff; color: inherit; font: inherit; }
            textarea { width: 100%; min-height: 18rem; padding: .75rem; border: 1px solid #9da59a; border-radius: .65rem; font-family: ui-monospace, monospace; }
            .brand-preview { margin-top: 1.5rem; padding: 1.5rem; border-radius: 1rem; color: #fff; background: var(--preview-primary); }
            .brand-preview span { display: inline-block; margin-top: .75rem; padding: .5rem .8rem; border-radius: 999px; color: #252a24; background: var(--preview-accent); font-weight: 700; }
        </style>
    </head>
    <body>
        <main>
            <p class="product">Waymark Community</p>
            <p class="progress">Step {{ $step->number() }} of 11</p>
            <h1>{{ $step->heading() }}</h1>

            @if ($step === App\Domain\Operations\Installation\SetupStep::Welcome)
                <p>Installation has not started. This guide will check the server and collect the details needed for this walking group.</p>
                <form method="post" action="{{ route('setup.start') }}">
                    @csrf
                    <button type="submit">Begin setup</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::ServerChecks)
                <p>Waymark checked the PHP and hosting features it needs.</p>
                @error('server')<p class="error">{{ $message }}</p>@enderror
                <ul>
                    @foreach ($preflight->checks as $check)
                        <li data-status="{{ $check->status }}">
                            <strong>{{ $check->label }} — {{ ucfirst($check->status) }}</strong>
                            <p>{{ $check->message }}</p>
                            @if ($check->remediation)<p>{{ $check->remediation }}</p>@endif
                        </li>
                    @endforeach
                </ul>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">
                    @csrf
                    <button type="submit">Continue</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Database)
                <p>Connect Waymark to the MySQL or MariaDB database supplied by your host.</p>
                @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">
                    @csrf
                    <label>Database type
                        <select name="driver">
                            <option value="mysql" @selected(old('driver', $data['driver'] ?? 'mysql') === 'mysql')>MySQL</option>
                            <option value="mariadb" @selected(old('driver', $data['driver'] ?? '') === 'mariadb')>MariaDB</option>
                        </select>
                    </label>
                    <label>Host <input name="host" value="{{ old('host', $data['host'] ?? '') }}" autocomplete="off" required></label>
                    <label>Port <input name="port" type="number" value="{{ old('port', $data['port'] ?? 3306) }}" min="1" max="65535" required></label>
                    <label>Database name <input name="database" value="{{ old('database', $data['database'] ?? '') }}" autocomplete="off" required></label>
                    <label>Username <input name="username" value="{{ old('username', $data['username'] ?? '') }}" autocomplete="username" required></label>
                    <label>Password <input name="password" type="password" value="" autocomplete="new-password"></label>
                    <button type="submit">Test connection and continue</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::GroupDetails)
                <p>Use the walking group's own identity. These details can be refined later.</p>
                @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label>Group name <input name="group_name" value="{{ old('group_name', $data['group_name'] ?? '') }}" required></label>
                    <label>Short name <input name="short_name" value="{{ old('short_name', $data['short_name'] ?? '') }}"></label>
                    <label>Contact email <input name="contact_email" type="email" value="{{ old('contact_email', $data['contact_email'] ?? '') }}" required></label>
                    <label>Timezone <input name="timezone" value="{{ old('timezone', $data['timezone'] ?? 'Europe/London') }}" required></label>
                    <label>Distance <select name="distance_unit"><option value="miles">Miles</option><option value="kilometres">Kilometres</option></select></label>
                    <label>Ascent <select name="ascent_unit"><option value="feet">Feet</option><option value="metres">Metres</option></select></label>
                    <button type="submit">Continue</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Branding)
                <p>Choose a restrained starting palette. The full branding editor remains available after setup.</p>
                <div class="brand-preview" style="--preview-primary: {{ $data['primary_colour'] ?? '#526B3F' }}; --preview-accent: {{ $data['accent_colour'] ?? '#D97845' }}">
                    <strong>{{ $groupDetails['group_name'] ?? 'Your walking group' }}</strong><br>
                    <span>View upcoming walks</span>
                </div>
                @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label>Primary colour <input name="primary_colour" type="color" value="{{ old('primary_colour', $data['primary_colour'] ?? '#526B3F') }}"></label>
                    <label>Accent colour <input name="accent_colour" type="color" value="{{ old('accent_colour', $data['accent_colour'] ?? '#D97845') }}"></label>
                    <label>Typography <select name="typography_option"><option value="instrument">Waymark type</option><option value="system">System type</option></select></label>
                    <button type="submit">Continue</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::FirstAdministrator)
                <p>This verified account becomes the installation owner.</p>
                @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label>Name <input name="name" value="{{ old('name', $data['name'] ?? '') }}" autocomplete="name" required></label>
                    <label>Email <input name="email" type="email" value="{{ old('email', $data['email'] ?? '') }}" autocomplete="email" required></label>
                    <label>Password <input name="password" type="password" value="" autocomplete="new-password" required></label>
                    <label>Confirm password <input name="password_confirmation" type="password" value="" autocomplete="new-password" required></label>
                    <button type="submit">Continue</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Mail)
                <p>Waymark will send a test message before saving these SMTP settings.</p>
                @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label>SMTP host <input name="host" value="{{ old('host', $data['host'] ?? '') }}" required></label>
                    <label>Port <input name="port" type="number" value="{{ old('port', $data['port'] ?? 587) }}" required></label>
                    <label>Encryption <select name="encryption"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="">None</option></select></label>
                    <label>Username <input name="username" value="{{ old('username', $data['username'] ?? '') }}" autocomplete="username"></label>
                    <label>Password <input name="password" type="password" value="" autocomplete="new-password"></label>
                    <label>From address <input name="from_address" type="email" value="{{ old('from_address', $data['from_address'] ?? '') }}" required></label>
                    <label>Send test to <input name="test_address" type="email" value="{{ old('test_address', $data['test_address'] ?? '') }}" required></label>
                    <button type="submit">Send test and continue</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Modules)
                <p>Walks are always available. Choose the other public areas this group needs now.</p>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    @foreach (['walks' => 'Walks', 'socials' => 'Socials', 'holidays' => 'Weekends away', 'gallery' => 'Gallery', 'news' => 'News', 'documents' => 'Documents'] as $value => $label)
                        <label><input name="modules[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, old('modules', $data['enabled'] ?? ['walks', 'socials', 'holidays', 'gallery', 'news', 'documents']), true))> {{ $label }}</label>
                    @endforeach
                    <button type="submit">Continue</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Advanced)
                <p>Local backups are the safe default. External storage and release metadata can be added now or later.</p>
                @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <label>Backup destination <select name="backup_disk"><option value="local">Local private storage</option><option value="s3">S3-compatible storage</option></select></label>
                    <label>Release metadata URL <input name="release_metadata_url" type="url" value="{{ old('release_metadata_url', $data['release_metadata_url'] ?? '') }}"></label>
                    <label>Release verification public key <textarea name="release_public_key_base64" rows="3">{{ old('release_public_key_base64', $data['release_public_key_base64'] ?? '') }}</textarea></label>
                    <label>S3-compatible endpoint <input name="s3_endpoint" type="url" value="{{ old('s3_endpoint', $data['s3_endpoint'] ?? '') }}"></label>
                    <label>S3 bucket <input name="s3_bucket" value="{{ old('s3_bucket', $data['s3_bucket'] ?? '') }}"></label>
                    <label>S3 access key <input name="s3_access_key" value="{{ old('s3_access_key', $data['s3_access_key'] ?? '') }}" autocomplete="off"></label>
                    <label>S3 secret key <input name="s3_secret_key" type="password" value="" autocomplete="new-password"></label>
                    <label>Recovery token <input name="recovery_token" type="password" value="" autocomplete="new-password" minlength="24" required></label>
                    <label>Confirm recovery token <input name="recovery_token_confirmation" type="password" value="" autocomplete="new-password" minlength="24" required></label>
                    <p>Store this token in your group's password manager. It is required for standalone disaster recovery and cannot be shown again.</p>
                    <button type="submit">Continue</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::Install)
                <p>Waymark will save configuration, create the database tables, and establish the first verified administrator.</p>
                @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
                @if ($environmentFile)
                    <p>{{ $environmentInstructions }}</p>
                    <label>Environment file content
                        <textarea readonly>{{ $environmentFile }}</textarea>
                    </label>
                @endif
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <button type="submit">{{ $environmentFile ? 'Re-check and install' : 'Install Waymark Community' }}</button>
                </form>
            @elseif ($step === App\Domain\Operations\Installation\SetupStep::HealthCheck)
                <p>These final checks must pass before the installer is permanently locked.</p>
                @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
                <ul>
                    @foreach ($healthChecks as $check)
                        <li data-status="{{ $check['passed'] ? 'pass' : 'blocker' }}">
                            <strong>{{ $check['label'] }}</strong>
                            <p>{{ $check['message'] }}</p>
                        </li>
                    @endforeach
                </ul>
                <form method="post" action="{{ route('setup.step.store', $step->value) }}">@csrf
                    <button type="submit">Finish setup</button>
                </form>
            @else
                <p>This setup step is ready for its checks and configuration.</p>
            @endif
        </main>
    </body>
</html>
