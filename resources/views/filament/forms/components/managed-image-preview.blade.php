@php
    $savedPreview = is_array($preview ?? null) ? $preview : null;
@endphp

<div class="space-y-3">
    @if ($savedPreview && filled($savedPreview['url'] ?? null))
        <figure data-testid="managed-image-preview" class="max-w-xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
            <img
                src="{{ $savedPreview['url'] }}"
                alt="{{ $savedPreview['alt'] ?? '' }}"
                class="h-auto max-h-80 w-full object-cover"
                @if (filled($savedPreview['object_position'] ?? null)) style="object-position: {{ $savedPreview['object_position'] }}" @endif
            >
        </figure>
    @endif

    <div data-testid="managed-image-status" role="status" aria-live="polite" aria-atomic="true" class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
        @if ($pendingUpload ?? false)
            <p>Temporary preview selected. Your image will be saved when you continue from Step 4.</p>
        @elseif (($savedPreview['source'] ?? null) === 'managed')
            <p>Saved local image.</p>
        @elseif (($savedPreview['source'] ?? null) === 'external')
            <p>Saved external image.</p>
        @else
            <p>No saved featured image.</p>
        @endif

        @if (($savedPreview['source'] ?? null) === 'managed' && filled($savedPreview['fallback_url'] ?? null))
            <p>A saved external fallback is retained and can be used if you remove the local image.</p>
        @endif
    </div>
</div>
