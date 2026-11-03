<section class="mt-8 rounded-[var(--wm-radius-md)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" aria-labelledby="profile-photo-heading">
    <h2 id="profile-photo-heading" class="text-2xl text-ink">Profile photo</h2>
    @if ($account['can_upload_photo'])
        <p class="mt-3 text-sm text-ink-muted">Your photo is public once saved and is used on your walk leader profile if you have one.</p>
        <form class="mt-5 grid gap-5" method="POST" enctype="multipart/form-data" action="{{ route('account.profile.photo.update') }}"
            x-data="{ source: @js(old('profile_photo_source', $account['photo']['source'] === 'none' ? 'managed' : $account['photo']['source'])), preview: null }">
            @csrf
            @if ($account['photo']['url'])
                <figure>
                    <img class="h-28 w-auto max-w-full rounded-[var(--wm-radius-sm)] object-contain" src="{{ $account['photo']['url'] }}" alt="Your saved profile photo">
                    <figcaption class="mt-2 text-sm text-ink-muted">Saved photo</figcaption>
                </figure>
            @endif
            <fieldset class="grid gap-3">
                <legend class="mb-3 text-sm font-semibold">Photo source</legend>
                @foreach (['managed' => 'Upload a local image (recommended)', 'external' => 'Use an external image instead', 'none' => 'No image'] as $value => $label)
                    <label class="flex items-center gap-3 text-sm" for="profile-photo-source-{{ $value }}">
                        <input class="size-4 accent-brand" id="profile-photo-source-{{ $value }}" name="profile_photo_source" type="radio" value="{{ $value }}" x-model="source" @checked(old('profile_photo_source', $account['photo']['source'] === 'none' ? 'managed' : $account['photo']['source']) === $value)>
                        {{ $label }}
                    </label>
                @endforeach
                @error('profile_photo_source') <p class="text-sm text-[var(--wm-critical)]" role="alert">{{ $message }}</p> @enderror
            </fieldset>
            <div x-show="source === 'managed'" class="grid gap-2">
                <label class="text-sm font-semibold" for="profile-photo-upload">Upload photo</label>
                <input class="wm-form-control" id="profile-photo-upload" name="profile_photo_upload" type="file" accept="image/jpeg,image/png,image/webp,image/avif" aria-describedby="profile-photo-upload-help @error('profile_photo_upload') profile-photo-upload-error @enderror"
                    x-on:change="if (preview) URL.revokeObjectURL(preview); preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                <p id="profile-photo-upload-help" class="text-sm text-ink-muted">JPEG, PNG, WebP or AVIF. Maximum {{ (int) ceil(config('gallery.processing.max_upload_bytes', 10485760) / 1048576) }} MB.</p>
                @error('profile_photo_upload') <p id="profile-photo-upload-error" class="text-sm text-[var(--wm-critical)]" role="alert">{{ $message }}</p> @enderror
                <template x-if="preview">
                    <figure><img class="h-28 w-auto max-w-full rounded-[var(--wm-radius-sm)] object-contain" x-bind:src="preview" alt="Your selected profile photo"><figcaption class="mt-2 text-sm text-ink-muted">Selected photo — save to publish</figcaption></figure>
                </template>
            </div>
            <div x-show="source === 'external'" class="grid gap-2">
                <label class="text-sm font-semibold" for="profile-photo-url">External image URL or site path</label>
                <input class="wm-form-control" id="profile-photo-url" name="profile_photo_url" type="text" maxlength="255" value="{{ old('profile_photo_url', $account['photo']['external_url']) }}" aria-describedby="profile-photo-url-help @error('profile_photo_url') profile-photo-url-error @enderror">
                <p id="profile-photo-url-help" class="text-sm text-ink-muted">Use a secure image URL or a public site image path.</p>
                @error('profile_photo_url') <p id="profile-photo-url-error" class="text-sm text-[var(--wm-critical)]" role="alert">{{ $message }}</p> @enderror
            </div>
            <div><x-public.button type="submit">Save photo</x-public.button></div>
        </form>
    @else
        <p class="mt-3 text-sm text-ink-muted"><a class="font-semibold text-brand" href="{{ route('verification.notice') }}">Verify your email</a> to add a profile photo.</p>
    @endif
</section>
