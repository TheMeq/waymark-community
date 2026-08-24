<x-filament-panels::page>
    <section class="max-w-3xl rounded-xl border p-5" aria-labelledby="restore-heading">
        <h2 id="restore-heading" class="text-lg font-semibold">Restore a backup</h2>
        <p class="mt-2 text-sm text-gray-600">This destructive operation replaces the database, private media, documents and restoration configuration. Only backups that pass every manifest and checksum check are used.</p>

        <label class="mt-5 block font-medium" for="backup-id">Completed backup</label>
        <select id="backup-id" wire:model="backupId" class="mt-2 block min-h-11 w-full rounded-lg border px-3">
            <option value="">Choose a backup</option>
            @foreach ($this->backups() as $backup)
                <option value="{{ $backup->id }}">#{{ $backup->id }} — {{ $backup->completed_at?->format('j M Y H:i') }} @if($backup->encrypted)(encrypted)@endif</option>
            @endforeach
        </select>

        <label class="mt-5 block font-medium" for="backup-passphrase">Encryption passphrase, if used</label>
        <x-filament::input.wrapper class="mt-2"><x-filament::input id="backup-passphrase" wire:model="backupPassphrase" type="password" autocomplete="new-password" /></x-filament::input.wrapper>

        <label class="mt-5 block font-medium" for="restore-confirmation">Type <strong>RESTORE WAYMARK</strong></label>
        <x-filament::input.wrapper class="mt-2"><x-filament::input id="restore-confirmation" wire:model="restoreConfirmation" autocomplete="off" /></x-filament::input.wrapper>

        @if (($this->restoreState()['status'] ?? null) === 'waiting_for_safety_backup')
            <p class="mt-5 rounded-lg border p-3">The target is verified. Its bounded safety backup must complete before destructive restore begins.</p>
            <x-filament::button class="mt-3" wire:click="continueRestore" wire:loading.attr="disabled">Continue safety backup / restore</x-filament::button>
        @else
            <x-filament::button class="mt-5" color="danger" wire:click="restore" wire:loading.attr="disabled">Restore selected backup</x-filament::button>
        @endif
    </section>
</x-filament-panels::page>
