@extends('layouts.public')

@section('title', 'Report a photo')

@section('content')
<section class="bg-surface py-12 sm:py-16">
    <div class="wm-container max-w-2xl">
        <h1>Report a photo</h1>
        <p class="mt-3 text-ink-muted">Tell the moderators what needs attention.</p>
        @if (session('status'))<p class="mt-5 rounded-[var(--wm-radius-sm)] border border-brand/30 bg-surface-soft p-4" role="status">{{ session('status') }}</p>@endif
        <form method="post" action="{{ route('community-photos.reports.store', $photo) }}" class="mt-8 grid gap-5 rounded-[var(--wm-radius-md)] border border-border bg-surface-raised p-5 shadow-[var(--wm-shadow-card)]">
            @csrf
            <div class="hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <div><label class="font-semibold" for="reason">Reason</label><select class="wm-form-control mt-2 w-full" id="reason" name="reason" required><option value="">Choose a reason</option><option value="in_photo">I am in this photo</option><option value="privacy">Privacy</option><option value="copyright">Copyright</option><option value="inappropriate">Inappropriate content</option><option value="other">Other</option></select></div>
            <div><label class="font-semibold" for="detail">Details</label><textarea class="wm-form-control mt-2 w-full" id="detail" name="detail" maxlength="1000"></textarea></div>
            <div><label class="font-semibold" for="contact">Contact email (optional)</label><input class="wm-form-control mt-2 w-full" id="contact" type="email" name="contact" maxlength="255"></div>
            <x-public.turnstile />
            <button class="wm-button wm-button-primary justify-self-start" type="submit">Send report</button>
        </form>
    </div>
</section>
@endsection
