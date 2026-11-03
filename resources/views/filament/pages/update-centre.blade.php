<x-filament-panels::page>
    @php($state = $this->state())
    <div class="max-w-4xl space-y-5">
        @if (session('status'))<p class="rounded-xl border p-4">{{ session('status') }}</p>@endif
        @error('install')<p class="rounded-xl border border-danger-300 p-4 text-danger-700">{{ $message }}</p>@enderror
        <section class="rounded-xl border p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Stable channel</h2>
                    <p class="text-sm text-gray-600">Installed: {{ config('waymark.version', 'development') }}</p>
                </div>
                <x-filament::button wire:click="checkNow" wire:loading.attr="disabled">Check now</x-filament::button>
            </div>
            <p class="mt-3 text-sm text-gray-600">Waymark checks and reports verified releases. It never installs an update automatically.</p>
        </section>

        @if (($state['status'] ?? null) === 'waiting_for_safety_backup')
            <section class="rounded-xl border p-5">
                <h2 class="font-semibold">Safety backup in progress</h2>
                <p class="mt-2">The verified release remains staged and no application files have been activated. Advance the safety backup in System health, then continue.</p>
                <div class="mt-4 flex gap-3">
                    <a class="underline" href="{{ route('filament.admin.pages.system-health') }}">System health</a>
                    <form method="post" action="{{ route('admin.updates.continue') }}">@csrf<x-filament::button type="submit">Continue update</x-filament::button></form>
                </div>
            </section>
        @elseif (($state['status'] ?? null) === 'failed')
            <section class="rounded-xl border p-5"><h2 class="font-semibold">Check failed</h2><p class="mt-2">{{ $state['message'] }}</p></section>
        @elseif (($state['status'] ?? null) === 'checked')
            <section class="rounded-xl border p-5">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-semibold">Waymark Community {{ $state['metadata']['version'] }}</h2>
                    @if ($state['metadata']['security_release'])<span class="rounded-full bg-danger-100 px-3 py-1 text-sm font-semibold text-danger-800">Security release</span>@endif
                </div>
                <p class="mt-3">{{ $state['metadata']['summary'] }}</p>
                <ul class="mt-3 list-disc space-y-1 pl-6">@foreach ($state['metadata']['release_notes'] as $note)<li>{{ $note }}</li>@endforeach</ul>
                <p class="mt-3 font-medium">{{ $state['update_available'] ? 'A newer verified release is available.' : 'No newer stable release is available.' }}</p>
                @if ($state['update_available'] && $state['compatibility']['compatible'])
                    <form method="post" action="{{ route('admin.updates.install') }}" class="mt-5 rounded-lg border border-danger-300 p-4">
                        @csrf
                        <p>This always creates a fresh backup, stages the verified package, enters maintenance mode, applies migrations and health checks, and rolls back on failure.</p>
                        <label class="mt-3 block font-medium" for="update-confirmation">Type UPDATE WAYMARK</label>
                        <input id="update-confirmation" name="confirmation" autocomplete="off" class="mt-2 block min-h-11 w-full rounded-lg border px-3" required>
                        @error('confirmation')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                        <x-filament::button class="mt-3" color="danger" type="submit">Install verified update</x-filament::button>
                    </form>
                @endif
            </section>
            <section class="rounded-xl border p-5">
                <h2 class="text-lg font-semibold">Host compatibility</h2>
                <div class="mt-3 space-y-3">
                    @foreach ($state['compatibility']['checks'] as $check)
                        <div class="rounded-lg border p-3"><strong>{{ $check['label'] }} — {{ ucfirst($check['status']) }}</strong><p class="text-sm text-gray-600">{{ $check['message'] }}</p></div>
                    @endforeach
                </div>
            </section>
        @elseif (($state['status'] ?? null) === 'installed')
            <section class="rounded-xl border p-5"><h2 class="font-semibold">Update installed</h2><p class="mt-2">{{ $state['message'] }}</p></section>
        @elseif (($state['status'] ?? null) === 'update_failed')
            <section class="rounded-xl border border-danger-300 p-5"><h2 class="font-semibold">Update failed</h2><p class="mt-2">{{ $state['message'] }}</p></section>
        @else
            <section class="rounded-xl border p-5"><p>No verified stable release check has completed yet.</p></section>
        @endif
    </div>
</x-filament-panels::page>
