<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Common tasks</x-slot>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Common admin tasks">
            @foreach ($actions as $action)
                <a href="{{ $action['url'] }}" style="display: flex; min-height: 48px; align-items: center; padding: 12px 16px" class="rounded-xl border border-gray-200 bg-white font-semibold text-gray-950 shadow-sm transition hover:border-primary-400 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-primary-950">
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
