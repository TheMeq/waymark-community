@extends('layouts.public')

@section('title', $social['title'])
@section('meta_description', $social['summary'])

@section('site-header')<x-public.site-header :site="$site" />@endsection

@section('content')
    <article class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <a class="text-sm font-semibold text-brand underline" href="{{ route('socials.index') }}">&larr; All socials</a>
            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.15em] text-brand">Social</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">{{ $social['title'] }}</h1>
            <p class="mt-4 text-ink-muted"><time datetime="{{ $event->starts_at->toAtomString() }}">{{ $social['starts_at'] }}</time>@if ($social['ends_at']) – {{ $social['ends_at'] }}@endif</p>
            @if ($social['summary'])<p class="mt-6 text-lg text-ink-muted">{{ $social['summary'] }}</p>@endif
            @if ($social['description'])<div class="prose mt-6 max-w-none text-ink">{!! nl2br(e($social['description'])) !!}</div>@endif

            <dl class="mt-8 grid gap-4 border-y border-border py-5 sm:grid-cols-2">
                @if ($social['organiser'])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Organiser</dt><dd class="mt-1 font-semibold text-ink">{{ $social['organiser'] }}</dd></div>@endif
                @if ($social['cost'])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Cost</dt><dd class="mt-1 font-semibold text-ink">{{ $social['cost'] }}</dd></div>@endif
                @if ($social['capacity'] !== null)<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Capacity</dt><dd class="mt-1 font-semibold text-ink">{{ $social['capacity'] }}</dd></div>@endif
                @if ($social['availability'])<div><dt class="text-xs font-semibold uppercase tracking-[0.1em] text-ink-muted">Availability</dt><dd class="mt-1 font-semibold text-ink">{{ $social['availability'] }}</dd></div>@endif
            </dl>

            @if ($social['venue'] !== [])<section class="mt-8"><h2 class="text-2xl text-ink">Venue</h2>@foreach ($social['venue'] as $line)<p class="mt-2 text-ink-muted">{{ $line }}</p>@endforeach</section>@endif
            @if ($social['booking'] !== [])<section class="mt-8"><h2 class="text-2xl text-ink">Booking details</h2>@foreach (['status', 'instructions', 'contact_name', 'contact_details'] as $field)@if (filled($social['booking'][$field] ?? null))<p class="mt-2 text-ink-muted">{{ $social['booking'][$field] }}</p>@endif @endforeach @if (filled($social['booking']['url'] ?? null))<a class="mt-3 inline-block font-semibold text-brand underline" href="{{ $social['booking']['url'] }}">External booking information</a>@endif</section>@endif
            @if ($social['accessibility'])<section class="mt-8"><h2 class="text-2xl text-ink">Accessibility</h2><p class="mt-3 text-ink-muted">{{ $social['accessibility'] }}</p></section>@endif
            @if ($social['transport'])<section class="mt-8"><h2 class="text-2xl text-ink">Getting there</h2><p class="mt-3 text-ink-muted">{{ $social['transport'] }}</p></section>@endif
            @if ($social['attachments'] !== [])<section class="mt-8"><h2 class="text-2xl text-ink">Downloads</h2><ul class="mt-3 grid gap-2">@foreach ($social['attachments'] as $attachment)<li><a class="font-semibold text-brand underline" href="{{ route('socials.attachment', [$event->slug, $attachment['index']]) }}">{{ $attachment['name'] }}</a></li>@endforeach</ul></section>@endif
        </div>
    </article>
@endsection

@section('site-footer')<x-public.site-footer :site="$site" />@endsection
