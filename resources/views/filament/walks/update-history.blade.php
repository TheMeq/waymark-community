<div class="space-y-4">
    @forelse ($updates as $update)
        <article class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <p class="text-sm text-gray-500">{{ $update->created_at->format('j F Y, H:i') }} by {{ $update->author->name }}</p>
            <p class="mt-2 whitespace-pre-line">{{ $update->message }}</p>
        </article>
    @empty
        <p class="text-sm text-gray-500">No organiser updates have been recorded.</p>
    @endforelse
</div>
