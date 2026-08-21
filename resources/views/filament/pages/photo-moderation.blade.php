<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <x-filament::button size="sm" wire:click="bulkApprove">Approve selected</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="bulkReject">Reject selected</x-filament::button>
        </div>
        @forelse ($this->pendingPhotos() as $photo)
            <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex gap-3">
                        <input wire:model.live="selectedPhotoIds" value="{{ $photo->id }}" type="checkbox" aria-label="Select {{ $photo->caption ?: 'photo' }} for bulk moderation" />
                        <div>
                        @if ($photo->processed_variants['master'] ?? false)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($photo->storage_disk)->url($photo->processed_variants['master']) }}" alt="" class="mb-3 h-28 w-40 rounded-lg object-cover" style="{{ $photo->presentationRotationStyle() }}" />
                        @endif
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $photo->caption ?: 'Untitled photo' }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $photo->event?->title ?? $photo->specialAlbum?->title }}
                            <span aria-hidden="true">·</span>
                            {{ $photo->uploader?->publicDisplayName() }}
                        </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <x-filament::button size="sm" wire:click="approve({{ $photo->id }})">Approve</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="reject({{ $photo->id }})">Reject</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="rotate({{ $photo->id }}, 90)">Rotate</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="beginEditing({{ $photo->id }})">Edit</x-filament::button>
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                No photos are waiting for moderation.
            </p>
        @endforelse
        @if ($editingPhotoId)
            <form wire:submit="saveEditing" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <label class="block text-sm font-medium" for="moderation-caption">Caption</label>
                <input id="moderation-caption" wire:model="caption" type="text" class="mt-1 w-full rounded-lg border-gray-300" />
                <label class="mt-3 block text-sm font-medium" for="moderation-photographer">Photographer attribution</label>
                <input id="moderation-photographer" wire:model="photographerName" type="text" class="mt-1 w-full rounded-lg border-gray-300" />
                <label class="mt-3 block text-sm font-medium" for="moderation-context">Context</label>
                <select id="moderation-context" wire:model="targetContext" class="mt-1 w-full rounded-lg border-gray-300">
                    @foreach ($this->contextOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <x-filament::button type="submit" class="mt-4">Save details</x-filament::button>
            </form>
        @endif
    </div>
</x-filament-panels::page>
