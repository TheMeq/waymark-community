@extends('layouts.public')

@section('title', ($photo->caption ?? 'Community photo').' | '.$site['name'])
@section('site-header') @include('components.public.site-header', ['site' => $site]) @endsection
@section('site-footer') @include('components.public.site-footer', ['site' => $site]) @endsection

@section('content')
    <article class="wm-container py-[var(--wm-space-7)] max-w-5xl">
        <img src="{{ $photo->imageUrl }}" alt="{{ $photo->caption ?? 'Community photo' }}" width="{{ $photo->width }}" height="{{ $photo->height }}" style="{{ $photo->rotationStyle }}" class="w-full rounded-[var(--wm-radius-lg)] bg-surface-raised object-contain shadow-[var(--wm-shadow-card)]">
        <div class="mt-5 flex flex-wrap items-start justify-between gap-4"><div>@if($photo->caption)<h1 class="text-3xl">{{ $photo->caption }}</h1>@endif @if($photo->photographerName)<p class="mt-2 text-ink-muted">Photo: {{ $photo->photographerName }}</p>@endif @if($photo->contextUrl)<a class="mt-2 inline-block underline" href="{{ $photo->contextUrl }}">{{ $photo->contextLabel }}</a>@endif</div><div class="flex gap-3"><a class="wm-button-secondary" href="{{ $photo->reportUrl }}">Report photo</a>@if($downloadsEnabled)<a class="wm-button-secondary" href="{{ route('gallery.photos.download', $photo->id) }}">Download</a>@endif</div></div>
    </article>
@endsection
