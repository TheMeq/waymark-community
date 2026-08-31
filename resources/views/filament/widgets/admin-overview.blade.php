<x-filament-widgets::widget>
    <div class="grid gap-6 xl:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Upcoming events</x-slot>
            @if ($upcoming->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-400">No upcoming events yet.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($upcoming as $event)
                        <li class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $event->title }}</span>
                            <time class="shrink-0 text-sm text-gray-600 dark:text-gray-400" datetime="{{ $event->starts_at->toAtomString() }}">{{ $event->starts_at->format('j M, H:i') }}</time>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Things to review</x-slot>
            <dl class="grid gap-3 sm:grid-cols-2">
                @if ($pendingPhotos !== null)<div><dt class="text-sm text-gray-600 dark:text-gray-400">Pending photo moderation</dt><dd class="text-2xl font-semibold">{{ $pendingPhotos }}</dd></div>@endif
                @if ($documentsDue !== null)<div><dt class="text-sm text-gray-600 dark:text-gray-400">Documents due for review</dt><dd class="text-2xl font-semibold">{{ $documentsDue }}</dd></div>@endif
                @if ($membershipReviews !== null)<div><dt class="text-sm text-gray-600 dark:text-gray-400">Membership reviews due</dt><dd class="text-2xl font-semibold">{{ $membershipReviews }}</dd></div>@endif
            </dl>
            @if (! $emailConfigured)
                <p class="mt-4 rounded-lg bg-warning-50 p-3 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-200">Email delivery is not configured.@if ($emailUrl) <a class="font-semibold underline" href="{{ $emailUrl }}">Configure email</a>@endif</p>
            @endif
            @if ($healthNeedsAttention && $healthUrl)
                <p class="mt-3 rounded-lg bg-danger-50 p-3 text-sm text-danger-800 dark:bg-danger-950 dark:text-danger-200"><a class="font-semibold underline" href="{{ $healthUrl }}">System Health needs attention</a></p>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
