<?php

namespace App\Domain\Operations\Imports\Contracts;

interface ImportDefinition
{
    public function key(): string;

    public function label(): string;

    /** @return array<string, array{label: string, required: bool}> */
    public function fields(): array;

    /**
     * @param  array<string, string|null>  $values
     * @return array{messages: list<string>, normalized: array<string, mixed>, identity: string|null}
     */
    public function assess(array $values): array;

    /** @param array<string, mixed> $normalized */
    public function duplicateExists(array $normalized): bool;

    /** @param array<string, mixed> $normalized */
    public function import(array $normalized): void;
}
