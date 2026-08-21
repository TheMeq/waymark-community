@extends('layouts.public')

@section('title', 'Share photos')

@section('site-header')<x-public.site-header :site="$site" />@endsection

@section('content')
    <section class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-brand">Community photos</p>
            <h1 class="mt-2 text-4xl text-ink sm:text-5xl">Share photos</h1>
            @if (session('status'))<p class="mt-6 rounded-[var(--wm-radius-md)] bg-surface-soft p-4 text-ink" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<div class="mt-6 rounded-[var(--wm-radius-md)] border border-red-700 bg-surface p-4 text-ink" role="alert" tabindex="-1" autofocus><p class="font-semibold">Please review the photo upload.</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @if (session('upload_results'))<section class="mt-6" aria-labelledby="upload-results"><h2 id="upload-results" class="text-xl text-ink">Upload results</h2><ul class="mt-3 grid gap-2">@foreach (session('upload_results') as $result)<li class="rounded-[var(--wm-radius-md)] bg-surface-soft p-3"><strong>{{ $result['name'] }}</strong>: {{ $result['status'] === 'uploaded' ? 'Submitted' : 'Not uploaded' }}@foreach ($result['errors'] ?? [] as $error)<p>{{ $error }}</p>@endforeach</li>@endforeach</ul></section>@endif
            <form x-data="photoUpload" x-on:submit.prevent="uploadAll" class="mt-8 grid gap-6 rounded-[var(--wm-radius-lg)] border border-border bg-surface p-5 shadow-[var(--wm-shadow-card)] sm:p-7" action="{{ route('community-photos.upload.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div>
                    <label class="text-sm font-semibold text-ink" for="photo-context">Add to</label>
                    <select class="mt-2 block w-full rounded-[var(--wm-radius-sm)] border-border bg-surface px-3 py-2 text-ink" id="photo-context" name="context" required>
                        <option value="">Choose an event or album</option>
                        @foreach ($events as $event)
                            <option value="event:{{ $event->id }}" @selected(old('context', $selectedContext) === 'event:'.$event->id)>{{ $event->title }}</option>
                        @endforeach
                        @foreach ($specialAlbums as $album)
                            <option value="album:{{ $album->id }}" @selected(old('context', $selectedContext) === 'album:'.$album->id)>{{ $album->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-ink" for="photos">Photos</label>
                    <input x-on:change="queueFiles" class="mt-2 block w-full text-ink" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp,image/avif" multiple required>
                    <p class="mt-2 text-sm text-ink-muted">Choose one or more photos. Each photo is submitted separately.</p>
                </div>
                @if ($policyDecision !== \App\Domain\Gallery\PhotoUploadPolicyDecision::UploadAllowedWithReminder)
                    <label class="flex gap-3 rounded-[var(--wm-radius-md)] bg-surface-soft p-4 text-sm text-ink" for="accept-photo-policy">
                        <input id="accept-photo-policy" name="accept_photo_policy" type="checkbox" value="1" required>
                        <span>I understand that approved photos may be public, under the current photo policy.</span>
                    </label>
                @else
                    <p class="rounded-[var(--wm-radius-md)] bg-surface-soft p-4 text-sm text-ink-muted">Approved photos may be public. You can review the photo policy at any time.</p>
                @endif
                <div>
                    <label class="text-sm font-semibold text-ink" for="photographer-name">Photographer credit <span class="font-normal text-ink-muted">(optional)</span></label>
                    <input class="mt-2 block w-full rounded-[var(--wm-radius-sm)] border-border bg-surface px-3 py-2 text-ink" id="photographer-name" name="photographer_name" type="text" maxlength="255">
                </div>
                <div>
                    <label class="text-sm font-semibold text-ink" for="photo-caption">Caption <span class="font-normal text-ink-muted">(optional)</span></label>
                    <textarea class="mt-2 block w-full rounded-[var(--wm-radius-sm)] border-border bg-surface px-3 py-2 text-ink" id="photo-caption" name="caption" rows="3"></textarea>
                </div>
                <ul x-cloak x-show="files.length" class="grid gap-3" aria-live="polite">
                    <template x-for="(item, index) in files" :key="`${item.file.name}-${index}`">
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--wm-radius-md)] bg-surface-soft p-4">
                            <span class="font-semibold text-ink" x-text="item.file.name"></span>
                            <span class="text-sm text-ink-muted" x-show="item.status === 'ready'">Ready</span>
                            <span class="text-sm text-ink-muted" x-show="item.status === 'uploading'">Uploading</span>
                            <span class="text-sm text-ink-muted" x-show="item.status === 'uploaded'">Submitted</span>
                            <template x-if="item.status === 'failed'"><div class="flex items-center gap-3"><span class="text-sm text-red-700" x-text="item.error"></span><x-public.button type="button" variant="secondary" x-on:click="upload(item)">Retry</x-public.button></div></template>
                        </li>
                    </template>
                </ul>
                <div><x-public.button type="submit" x-on:click.prevent="uploadAll">Upload photos</x-public.button></div>
            </form>
        </div>
    </section>
@endsection

@section('site-footer')<x-public.site-footer :site="$site" />@endsection
