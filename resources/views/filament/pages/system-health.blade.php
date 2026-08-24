<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600">Plain-language operational checks only. Technical logs and stack traces are not shown here.</p>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($this->healthChecks() as $check)
                <section class="rounded-xl border p-4" aria-labelledby="health-{{ $check->key }}">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="health-{{ $check->key }}" class="font-semibold">{{ $check->label }}</h2>
                        <span>{{ ucfirst($check->status) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">{{ $check->message }}</p>
                </section>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-3">
            <x-filament::button wire:click="scanMedia">Scan for missing media</x-filament::button>
            <x-filament::button color="gray" wire:click="runFallback">Run bounded fallback work</x-filament::button>
        </div>
        <section class="rounded-xl border p-4" aria-labelledby="backup-controls">
            <h2 id="backup-controls" class="text-lg font-semibold">Backups</h2>
            <p class="mt-1 text-sm text-gray-600">Leave the passphrase blank for a private unencrypted backup, or supply one for portable encrypted recovery.</p>
            <x-filament::input.wrapper class="mt-3"><x-filament::input wire:model="backupPassphrase" type="password" autocomplete="new-password" aria-label="Optional backup encryption passphrase" /></x-filament::input.wrapper>
            <x-filament::button class="mt-3" wire:click="createBackup" wire:loading.attr="disabled">Create backup</x-filament::button>
            <div class="mt-4 space-y-2">
                @forelse ($this->backups() as $backup)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3">
                        <span>{{ ucfirst($backup->trigger) }} — {{ ucfirst($backup->status) }} @if($backup->encrypted)(encrypted)@endif</span>
                        @if($backup->status === 'completed')<a class="underline" href="{{ route('admin.backups.download', $backup) }}">Download</a>@endif
                    </div>
                @empty
                    <p class="text-sm text-gray-600">No backup runs have been recorded.</p>
                @endforelse
            </div>
        </section>
        <section aria-labelledby="repair-queue">
            <h2 id="repair-queue" class="text-lg font-semibold">Missing-media repair queue</h2>
            @forelse ($this->repairs() as $repair)
                <article class="mt-3 rounded-xl border p-4">
                    <p class="font-semibold">{{ class_basename($repair->media_type) }} #{{ $repair->record_id }}</p>
                    <p class="text-sm text-gray-600">{{ $repair->path }}</p>
                    <x-filament::button class="mt-3" color="gray" wire:click="recheck({{ $repair->id }})">Re-check file</x-filament::button>
                </article>
            @empty
                <p class="mt-2 text-sm text-gray-600">No missing files are waiting for repair.</p>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
