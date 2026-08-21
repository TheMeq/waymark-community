<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <x-filament::button size="md" style="min-width: 24px; min-height: 24px;" wire:click="bulkApprove">Approve selected</x-filament::button>
            <x-filament::button size="md" style="min-width: 24px; min-height: 24px;" color="gray" wire:click="bulkReject">Reject selected</x-filament::button>
        </div>
        @php($pendingPhotos = $this->pendingPhotos())
        @forelse ($pendingPhotos as $photo)
            @php($preview = $this->previewFor($photo))
            <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex gap-3">
                        <input wire:model.live="selectedPhotoIds" value="{{ $photo->id }}" type="checkbox" style="width: 24px; height: 24px;" aria-label="Select {{ $photo->caption ?: 'photo' }} for bulk moderation" />
                        <div>
                        @if ($preview)<img src="{{ $preview->url }}" alt="{{ $preview->alt }}" class="mb-3 h-28 w-40 rounded-lg object-cover" style="{{ $photo->presentationRotationStyle() }}" />@endif
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $photo->caption ?: 'Untitled photo' }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $photo->event?->title ?? $photo->specialAlbum?->title }}
                            <span aria-hidden="true">·</span>
                            {{ $photo->uploader?->publicDisplayName() }}
                        </p>
                        @if ($state = $this->processingState($photo))<p class="mt-1 text-sm font-semibold">{{ $state }}</p>@elseif (! $preview)<p class="mt-1 text-sm font-semibold">Preview unavailable</p>@endif
                        </div>
                    </div>
                    @if ($preview)<div class="flex gap-2">
                        <x-filament::button size="md" style="min-width: 24px; min-height: 24px;" wire:click="approve({{ $photo->id }})">Approve</x-filament::button>
                        <x-filament::button size="md" style="min-width: 24px; min-height: 24px;" color="gray" wire:click="reject({{ $photo->id }})">Reject</x-filament::button>
                        <x-filament::button size="md" style="min-width: 24px; min-height: 24px;" color="gray" wire:click="rotate({{ $photo->id }}, 90)">Rotate</x-filament::button>
                        <x-filament::button size="md" style="min-width: 24px; min-height: 24px;" color="gray" wire:click="beginEditing({{ $photo->id }})">Edit</x-filament::button>
                    </div>@endif
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                No photos are waiting for moderation.
            </p>
        @endforelse
        @if ($pendingPhotos->hasPages())
            <nav class="flex items-center justify-between gap-3" aria-label="Pending photo pagination">
                @if ($pendingPhotos->onFirstPage())
                    <span aria-disabled="true">Previous</span>
                @else
                    <a class="underline" href="{{ request()->fullUrlWithQuery(['page' => $pendingPhotos->currentPage() - 1]) }}">Previous</a>
                @endif
                <span>Page {{ $pendingPhotos->currentPage() }} of {{ $pendingPhotos->lastPage() }}</span>
                @if ($pendingPhotos->hasMorePages())
                    <a class="underline" href="{{ request()->fullUrlWithQuery(['page' => $pendingPhotos->currentPage() + 1]) }}">Next</a>
                @else
                    <span aria-disabled="true">Next</span>
                @endif
            </nav>
        @endif
        <section class="space-y-3" aria-labelledby="published-photos">
            <h2 id="published-photos" class="text-lg font-semibold">Published photos</h2>
            @php($approvedPhotos = $this->approvedPhotos())
            @foreach ($approvedPhotos as $photo)
                @php($preview = $this->previewFor($photo))
                <article class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        @if ($preview)<img src="{{ $preview->url }}" alt="{{ $preview->alt }}" class="h-24 w-32 rounded-lg object-cover" style="{{ $photo->presentationRotationStyle() }}" />@endif
                        <div><p class="font-semibold">{{ $photo->caption ?: 'Untitled photo' }}</p>
                        <p class="text-sm text-gray-600">{{ $photo->event?->title ?? $photo->specialAlbum?->title }} · {{ $photo->photographer_name ?: $photo->uploader?->publicDisplayName() }}</p>
                        @if ($photo->is_featured)<p class="text-sm font-semibold">Featured</p>@endif</div>
                    </div>
                    @if ($preview)<span class="flex gap-2">
                        <x-filament::button size="md" color="gray" wire:click="beginEditing({{ $photo->id }})">Edit</x-filament::button>
                        <x-filament::button size="md" color="gray" wire:click="rotate({{ $photo->id }}, 90)">Rotate</x-filament::button>
                        <x-filament::button size="md" style="min-width: 24px; min-height: 24px;" color="gray" wire:click="feature({{ $photo->id }})">Feature</x-filament::button>
                        <x-filament::button size="md" style="min-width: 24px; min-height: 24px;" color="danger" wire:click="remove({{ $photo->id }})">Remove</x-filament::button>
                    </span>@else<span class="text-sm font-semibold">Preview unavailable</span>@endif
                </article>
            @endforeach
            @if ($approvedPhotos->hasPages())
                <nav class="flex items-center justify-between gap-3" aria-label="Published photo pagination">
                    @if ($approvedPhotos->onFirstPage())
                        <span aria-disabled="true">Previous</span>
                    @else
                        <a class="underline" href="{{ request()->fullUrlWithQuery(['publishedPage' => $approvedPhotos->currentPage() - 1]) }}">Previous</a>
                    @endif
                    <span>Page {{ $approvedPhotos->currentPage() }} of {{ $approvedPhotos->lastPage() }}</span>
                    @if ($approvedPhotos->hasMorePages())
                        <a class="underline" href="{{ request()->fullUrlWithQuery(['publishedPage' => $approvedPhotos->currentPage() + 1]) }}">Next</a>
                    @else
                        <span aria-disabled="true">Next</span>
                    @endif
                </nav>
            @endif
        </section>
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
