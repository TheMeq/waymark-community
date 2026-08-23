<?php

namespace App\Domain\Content\Data;

use Carbon\CarbonImmutable;

final readonly class PublicSearchFilters
{
    public function __construct(
        public ?string $term = null,
        public ?string $type = null,
        public ?CarbonImmutable $dateFrom = null,
        public ?CarbonImmutable $dateTo = null,
        public ?string $difficulty = null,
        public ?string $location = null,
        public ?string $documentCategory = null,
    ) {}

    /** @param array<string, mixed> $values */
    public static function from(array $values): self
    {
        return new self(
            term: self::text($values['q'] ?? null),
            type: self::text($values['type'] ?? null),
            dateFrom: isset($values['date_from']) ? CarbonImmutable::parse((string) $values['date_from'])->startOfDay() : null,
            dateTo: isset($values['date_to']) ? CarbonImmutable::parse((string) $values['date_to'])->endOfDay() : null,
            difficulty: self::text($values['difficulty'] ?? null),
            location: self::text($values['location'] ?? null),
            documentCategory: self::text($values['document_category'] ?? null),
        );
    }

    public function hasCriteria(): bool
    {
        return $this->term !== null || $this->type !== null || $this->dateFrom !== null || $this->dateTo !== null
            || $this->difficulty !== null || $this->location !== null || $this->documentCategory !== null;
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 200);
    }
}
