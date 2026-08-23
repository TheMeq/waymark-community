@props(['blocks'])

<div {{ $attributes->class(['space-y-8']) }}>
    @foreach ($blocks as $block)
        @switch($block['type'])
            @case('rich_text')
                <div class="wm-prose">{!! $block['html'] !!}</div>
                @break
            @case('image_text')
                <section class="grid overflow-hidden rounded-[var(--wm-radius-card)] border border-border bg-surface md:grid-cols-2">
                    @if ($block['media'])
                        <img class="h-full min-h-64 w-full object-cover" src="{{ $block['media']->url }}" alt="{{ $block['media']->alt }}" width="{{ $block['media']->width }}" height="{{ $block['media']->height }}" loading="lazy">
                    @endif
                    <div class="self-center p-6 sm:p-8">
                        @if (filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif
                        @if (filled($block['body'] ?? null))<p class="mt-3 text-ink-muted">{{ $block['body'] }}</p>@endif
                    </div>
                </section>
                @break
            @case('callout')
                <aside class="rounded-[var(--wm-radius-card)] border border-border bg-surface-soft p-6">
                    @if (filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif
                    @if (filled($block['body'] ?? null))<p class="mt-2 text-ink-muted">{{ $block['body'] }}</p>@endif
                </aside>
                @break
            @case('faq')
                <section class="space-y-3" aria-label="Frequently asked questions">
                    @foreach ($block['items'] as $item)<details class="rounded-[var(--wm-radius-card)] border border-border p-5"><summary class="min-h-11 cursor-pointer font-semibold">{{ $item['question'] }}</summary><p class="mt-3 text-ink-muted">{{ $item['answer'] }}</p></details>@endforeach
                </section>
                @break
            @case('quote')
                <figure class="rounded-[var(--wm-radius-card)] bg-brand px-6 py-8 text-on-brand sm:px-10"><blockquote class="text-2xl leading-relaxed">“{{ $block['quote'] }}”</blockquote>@if(filled($block['attribution'] ?? null))<figcaption class="mt-4 text-sm font-semibold">{{ $block['attribution'] }}</figcaption>@endif</figure>
                @break
            @case('cta')
                <section class="rounded-[var(--wm-radius-card)] border border-border bg-surface-soft p-6 sm:flex sm:items-center sm:justify-between sm:gap-6"><div>@if(filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif @if(filled($block['body'] ?? null))<p class="mt-2 text-ink-muted">{{ $block['body'] }}</p>@endif</div><x-public.button class="mt-5 shrink-0 sm:mt-0" :href="$block['url']">{{ $block['label'] }}</x-public.button></section>
                @break
            @case('button_group')
                <section>@if(filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif<div class="mt-4 flex flex-wrap gap-3">@foreach($block['items'] as $item)<x-public.button :href="$item['url']" :variant="$loop->first ? 'primary' : 'secondary'">{{ $item['label'] }}</x-public.button>@endforeach</div></section>
                @break
            @case('document_list')
                <section>@if(filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif<ul class="mt-4 divide-y divide-border rounded-[var(--wm-radius-card)] border border-border">@foreach($block['items'] as $item)<li><a class="flex min-h-12 items-center justify-between gap-4 px-5 py-3 font-semibold hover:text-brand" href="{{ $item['url'] }}"><span>{{ $item['label'] }}</span><span aria-hidden="true">↓</span></a></li>@endforeach</ul></section>
                @break
            @case('gallery')
                @if($block['media'] !== [])<section>@if(filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif<div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($block['media'] as $media)<img class="aspect-[4/3] w-full rounded-[var(--wm-radius-md)] object-cover" src="{{ $media->url }}" alt="{{ $media->alt }}" width="{{ $media->width }}" height="{{ $media->height }}" loading="lazy">@endforeach</div></section>@endif
                @break
            @case('statistics')
                <section>@if(filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif<dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach($block['items'] as $item)<div class="rounded-[var(--wm-radius-md)] border border-border bg-surface p-5"><dt class="text-sm text-ink-muted">{{ $item['label'] }}</dt><dd class="mt-1 text-3xl font-semibold text-brand">{{ $item['value'] }}</dd></div>@endforeach</dl></section>
                @break
            @case('timeline')
                <section>@if(filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif<ol class="mt-5 space-y-5 border-l-2 border-brand/25 pl-6">@foreach($block['items'] as $item)<li><h3 class="font-semibold text-brand">{{ $item['label'] }}</h3><p class="mt-1 text-ink-muted">{{ $item['body'] }}</p></li>@endforeach</ol></section>
                @break
            @case('columns')
                <section>@if(filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif<div class="mt-4 grid gap-5 {{ count($block['items']) === 3 ? 'lg:grid-cols-3' : 'md:grid-cols-2' }}">@foreach($block['items'] as $item)<div class="rounded-[var(--wm-radius-md)] bg-surface-soft p-5">@if(filled($item['label'] ?? null))<h3 class="font-semibold">{{ $item['label'] }}</h3>@endif<p class="mt-2 text-ink-muted">{{ $item['body'] }}</p></div>@endforeach</div></section>
                @break
        @endswitch
    @endforeach
</div>
