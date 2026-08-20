<?php

namespace App\Domain\Walks\Data;

use App\Domain\Events\Models\Event;
use App\Domain\Walks\Enums\DuplicateWalkCopyGroup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class DuplicateWalkOptions
{
    /** @param array<int, string> $copyGroups */
    private function __construct(
        public string $title,
        public string $slug,
        public string $startsAt,
        public ?string $endsAt,
        public array $copyGroups,
    ) {}

    /** @param array<string, mixed> $attributes */
    public static function from(array $attributes): self
    {
        $validated = Validator::make(self::normaliseEmptyStrings($attributes), [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(Event::class, 'slug')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'copy_groups' => ['nullable', 'array'],
            'copy_groups.*' => ['string', 'distinct', Rule::in(DuplicateWalkCopyGroup::values())],
        ])->validate();

        return new self(
            title: $validated['title'],
            slug: $validated['slug'],
            startsAt: $validated['starts_at'],
            endsAt: $validated['ends_at'] ?? null,
            copyGroups: $validated['copy_groups'] ?? [],
        );
    }

    public function copies(DuplicateWalkCopyGroup $group): bool
    {
        return in_array($group->value, $this->copyGroups, true);
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private static function normaliseEmptyStrings(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (is_string($value)) {
                $attributes[$key] = ($value = trim($value)) === '' ? null : $value;
            }
        }

        return $attributes;
    }
}
