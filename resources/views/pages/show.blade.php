@extends('layouts.public')

@section('title', $page->seo_title ?: $page->title)

@section('content')
    <main id="main-content" class="wm-container py-12 sm:py-16">
        @if ($notice)
            <p class="mb-6 inline-flex rounded-full border border-[var(--wm-color-border)] bg-[var(--wm-color-surface-muted)] px-4 py-2 text-sm font-semibold">{{ $notice }}</p>
        @endif
        <article class="mx-auto max-w-4xl">
            <h1 class="wm-heading-xl">{{ $page->title }}</h1>
            <div class="mt-10 space-y-8">
                @foreach ($page->blocks as $block)
                    @switch($block['type'])
                        @case('rich_text')
                            <div class="wm-prose">{!! app(\App\Domain\Content\Support\CmsBlockRenderer::class)->richText($block['content'] ?? '') !!}</div>
                            @break
                        @case('callout')
                            <aside class="rounded-[var(--wm-radius-card)] border border-[var(--wm-color-border)] bg-[var(--wm-color-surface-muted)] p-6">
                                @if (filled($block['heading'] ?? null))<h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@endif
                                @if (filled($block['body'] ?? null))<p class="mt-2 text-[var(--wm-color-text-muted)]">{{ $block['body'] }}</p>@endif
                            </aside>
                            @break
                        @case('faq')
                            <div class="space-y-3">
                                @foreach ($block['items'] ?? [] as $item)
                                    <details class="rounded-[var(--wm-radius-card)] border border-[var(--wm-color-border)] p-5">
                                        <summary class="cursor-pointer font-semibold">{{ $item['question'] ?? '' }}</summary>
                                        <p class="mt-3 text-[var(--wm-color-text-muted)]">{{ $item['answer'] ?? '' }}</p>
                                    </details>
                                @endforeach
                            </div>
                            @break
                        @default
                            @if (filled($block['heading'] ?? null))<section><h2 class="wm-heading-md">{{ $block['heading'] }}</h2>@if (filled($block['body'] ?? null))<p class="mt-3">{{ $block['body'] }}</p>@endif</section>@endif
                    @endswitch
                @endforeach
            </div>
        </article>
    </main>
@endsection
