<x-filament-panels::page>
    <section class="max-w-3xl rounded-xl border p-5" aria-labelledby="maintenance-heading">
        <div class="flex items-center justify-between gap-4">
            <h2 id="maintenance-heading" class="text-lg font-semibold">Maintenance mode</h2>
            <span>{{ $this->active() ? 'Active' : 'Inactive' }}</span>
        </div>
        <p class="mt-2 text-sm text-gray-600">Public visitors receive a group-branded maintenance page. The browser that enables it receives a private signed bypass for checking the site.</p>

        @if (session('status'))<p class="mt-3 text-sm">{{ session('status') }}</p>@endif
        <form method="post" action="{{ route('admin.maintenance.enable') }}">
            @csrf
            <label class="mt-5 block font-medium" for="maintenance-message">Message</label>
            <textarea id="maintenance-message" name="message" class="mt-2 block min-h-28 w-full rounded-lg border p-3">{{ old('message', 'We are carrying out essential maintenance. Please check back shortly.') }}</textarea>
            @error('message')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror

            <label class="mt-5 block font-medium" for="expected-return">Expected return, optional</label>
            <input id="expected-return" name="expected_return_at" value="{{ old('expected_return_at') }}" type="datetime-local" class="mt-2 block min-h-11 w-full rounded-lg border px-3">
            @error('expected_return_at')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror

            <label class="mt-5 block font-medium" for="maintenance-contact">Contact or status URL, optional</label>
            <input id="maintenance-contact" name="contact_url" value="{{ old('contact_url') }}" type="url" class="mt-2 block min-h-11 w-full rounded-lg border px-3">
            @error('contact_url')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror

            <x-filament::button class="mt-5" type="submit">Enable maintenance mode</x-filament::button>
        </form>
        <form method="post" action="{{ route('admin.maintenance.disable') }}" class="mt-3">
            @csrf
            <x-filament::button color="gray" type="submit">Disable maintenance mode</x-filament::button>
        </form>
    </section>
</x-filament-panels::page>
