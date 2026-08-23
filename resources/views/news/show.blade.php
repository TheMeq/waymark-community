@extends('layouts.public')
@section('title', $article->share_title ?: $article->title)
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('content')
<article class="wm-container py-12 sm:py-16"><div class="mx-auto max-w-4xl"><p class="text-xs font-semibold uppercase tracking-wider text-brand">{{ $article->primary_category }}</p><h1 class="wm-heading-xl mt-3">{{ $article->title }}</h1><p class="mt-4 text-ink-muted">By {{ $article->author->publicDisplayName() }} · {{ optional($article->publish_at)->format('j F Y') }}</p>@if($featuredImage)<img class="mt-8 aspect-[16/9] w-full rounded-[var(--wm-radius-card)] object-cover" src="{{ $featuredImage->url }}" alt="{{ $featuredImage->alt }}" width="{{ $featuredImage->width }}" height="{{ $featuredImage->height }}">@endif<div class="mt-10 space-y-8">@foreach($article->blocks as $block)@if($block['type']==='rich_text')<div class="wm-prose">{!! app(\App\Domain\Content\Support\CmsBlockRenderer::class)->richText($block['content'] ?? '') !!}</div>@elseif(filled($block['heading'] ?? null))<section><h2 class="wm-heading-md">{{ $block['heading'] }}</h2><p class="mt-3">{{ $block['body'] ?? '' }}</p></section>@endif @endforeach</div></div></article>
@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection
