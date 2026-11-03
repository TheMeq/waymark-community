<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <p class="text-sm text-gray-600">Configure and test outbound email. Email verification, password resets, notifications and other email-dependent features become available after these settings are saved.</p>
        {{ $this->form }}
        <x-filament::button type="submit">Test and save email delivery</x-filament::button>
    </form>
</x-filament-panels::page>
