<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="submit">Save branding</x-filament::button>
            <x-filament::button type="button" color="gray" outlined wire:click="preview">Preview branding</x-filament::button>
        </div>
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
