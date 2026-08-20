@extends('layouts.public')

@section('title', 'Walk grading guide')
@section('meta_description', 'Practical guidance for choosing a walk.')

@section('site-header')
    <x-public.site-header :site="$site" />
@endsection

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Plan with confidence</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Walk grading guide</h1>
            <p class="mt-5 text-ink-muted">Grades describe the expected pace and terrain. Read the full walk details before deciding whether it is right for you.</p>

            @if ($grades->isNotEmpty())
                <dl class="mt-8 divide-y divide-border rounded-[var(--wm-radius-md)] border border-border bg-surface">
                    @foreach ($grades as $grade)
                        <div style="{!! $grade['accent_style'] !!}" class="border-l-4 border-[var(--wm-grade-accent,var(--wm-border))] p-5 pl-4">
                            <dt class="text-xl font-semibold text-ink">{{ $grade['name'] }}</dt>
                            <dd class="mt-2 text-ink-muted">{{ $grade['description'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="mt-8 text-ink-muted">Walk grades will be published here soon.</p>
            @endif
        </div>
    </section>
@endsection

@section('site-footer')
    <x-public.site-footer :site="$site" />
@endsection
