@props(['items'])

<dl {{ $attributes->class('relative flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-muted') }}>
    @foreach ($items as $item)
        <div class="inline-flex items-center gap-1.5">
            <dt class="sr-only">{{ $item['label'] }}</dt>
            @if (isset($item['symbol']))
                <span class="text-brand" aria-hidden="true">{{ $item['symbol'] }}</span>
            @endif
            <dd>{{ $item['value'] }}</dd>
        </div>
    @endforeach
</dl>
