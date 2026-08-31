<x-filament-widgets::widget>
    @if ($visible)
        <x-filament::section>
            <x-slot name="heading">Getting started</x-slot>
            <x-slot name="afterHeader">
                <button type="button" wire:click="dismiss" class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-white/10">Dismiss</button>
            </x-slot>

            @if (! $emailConfigured)
                <div class="mb-4 rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
                    <p>Email delivery is not configured. Password resets, verification messages and notifications that rely on email will remain unavailable until it is set up.</p>
                    @if ($emailUrl)<a href="{{ $emailUrl }}" class="mt-2 inline-block font-semibold underline">Configure email</a>@endif
                </div>
            @endif

            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($tasks as $task)
                    <li class="flex min-h-14 items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-white/10">
                        <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full {{ $task['complete'] ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300' }}">{{ $task['complete'] ? '✓' : '○' }}</span>
                        @if ($task['url'])<a class="font-medium text-gray-950 underline-offset-4 hover:underline dark:text-white" href="{{ $task['url'] }}">{{ $task['label'] }}</a>@else<span class="font-medium">{{ $task['label'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
