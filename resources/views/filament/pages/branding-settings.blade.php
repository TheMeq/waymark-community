<x-filament-panels::page>
    <p class="max-w-3xl text-sm text-gray-600 dark:text-gray-300">
        Upload local branding images where possible so they remain available with your Waymark installation.
    </p>

    <form wire:submit="save">
        {{ $this->form }}
        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="submit" class="min-h-11">Save branding</x-filament::button>
            <x-filament::button type="button" class="min-h-11" color="gray" outlined wire:click="preview">Preview branding</x-filament::button>
        </div>
        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ $saveStatus }}</p>
    </form>

    @if ($previewLinks !== [])
        <section class="mt-6 rounded-xl border p-4" aria-labelledby="branding-preview-links">
            <h2 id="branding-preview-links" class="text-sm font-semibold">Unsaved preview</h2>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach ($previewLinks as $viewport => $url)
                    <x-filament::button tag="a" :href="$url" target="_blank" color="gray" outlined>{{ ucfirst($viewport) }}</x-filament::button>
                @endforeach
            </div>
        </section>
    @endif
</x-filament-panels::page>
