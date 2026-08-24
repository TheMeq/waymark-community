<x-filament-panels::page>
    @php($state = $this->state())
    <div class="max-w-4xl space-y-5">
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

        @if (($state['status'] ?? null) === 'failed')
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
            </section>
            <section class="rounded-xl border p-5">
                <h2 class="text-lg font-semibold">Host compatibility</h2>
                <div class="mt-3 space-y-3">
                    @foreach ($state['compatibility']['checks'] as $check)
                        <div class="rounded-lg border p-3"><strong>{{ $check['label'] }} — {{ ucfirst($check['status']) }}</strong><p class="text-sm text-gray-600">{{ $check['message'] }}</p></div>
                    @endforeach
                </div>
            </section>
        @else
            <section class="rounded-xl border p-5"><p>No verified stable release check has completed yet.</p></section>
        @endif
    </div>
</x-filament-panels::page>
