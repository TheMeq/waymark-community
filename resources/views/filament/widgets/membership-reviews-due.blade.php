<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Membership reviews due ({{ $accounts->count() }})
        </x-slot>

        @if ($accounts->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">No membership reviews are due.</p>
        @else
            <ul class="space-y-2 text-sm">
                @foreach ($accounts as $account)
                    <li class="flex items-center justify-between gap-4">
                        <span>{{ $account->name }}</span>
                        <span class="text-gray-600 dark:text-gray-400">Review due {{ $account->membership_review_due_at->toFormattedDateString() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
