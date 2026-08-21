@props(['favourite'])

@if ($favourite['is_visible'])
    <div class="wm-print-hidden mt-5">
        @if ($favourite['is_guest'])
            <a class="text-sm font-semibold text-brand underline decoration-brand/30 underline-offset-4" href="{{ route('login') }}">Sign in to save</a>
        @elseif ($favourite['is_saved'])
            <form method="POST" action="{{ $favourite['remove_url'] }}">
                @csrf
                @method('DELETE')
                <x-public.button type="submit" variant="secondary">Remove from favourites</x-public.button>
            </form>
        @else
            <form method="POST" action="{{ $favourite['save_url'] }}">
                @csrf
                <x-public.button type="submit" variant="secondary">Save to favourites</x-public.button>
            </form>
        @endif
    </div>
@endif
