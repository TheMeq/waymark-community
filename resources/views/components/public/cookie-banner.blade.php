@if(! $cookiePreferences['decided'])
<aside class="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-4xl rounded-[var(--wm-radius-card)] border border-border bg-surface-raised p-4 shadow-[var(--wm-shadow-float)] sm:p-5" aria-labelledby="cookie-banner-heading">
    <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
        <div>
            <h2 id="cookie-banner-heading" class="text-lg">Cookie choices</h2>
            <p class="mt-1 text-sm text-ink-muted">Essential cookies keep Waymark working. Optional analytics is off unless you allow it.</p>
            <a class="mt-2 inline-block text-sm font-semibold text-brand" href="{{ route('cookie-settings.edit') }}">Review cookie settings</a>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="post" action="{{ route('cookie-settings.update') }}">@csrf<input type="hidden" name="analytics" value="0"><x-public.button type="submit" variant="secondary">Use essential only</x-public.button></form>
            <form method="post" action="{{ route('cookie-settings.update') }}">@csrf<input type="hidden" name="analytics" value="1"><x-public.button type="submit">Allow analytics</x-public.button></form>
        </div>
    </div>
</aside>
@endif
