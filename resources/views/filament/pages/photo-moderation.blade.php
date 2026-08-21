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
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                No photos are waiting for moderation.
            </p>
        @endforelse
    </div>
</x-filament-panels::page>
