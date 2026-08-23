@if(config('waymark.anti_spam.turnstile.enabled') && filled(config('waymark.anti_spam.turnstile.site_key')))
    <div class="cf-turnstile" data-sitekey="{{ config('waymark.anti_spam.turnstile.site_key') }}"></div>
    @error('turnstile')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
    @once
        @push('scripts')<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endpush
    @endonce
@endif
