@props(['photo'])

<figure {{ $attributes->class('group overflow-hidden rounded-[var(--wm-radius-md)] bg-surface-raised') }}>
    <img
        class="aspect-[4/3] w-full object-cover transition-transform duration-300 group-hover:scale-[1.025]"
        src="{{ $photo['image_url'] }}"
        alt="{{ $photo['image_alt'] }}"
        loading="lazy"
    >
    @if (! empty($photo['caption']))
        <figcaption class="px-4 py-3 text-sm text-ink-muted">{{ $photo['caption'] }}</figcaption>
    @endif
</figure>
