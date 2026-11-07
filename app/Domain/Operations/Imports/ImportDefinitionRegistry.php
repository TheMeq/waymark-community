<?php

namespace App\Domain\Operations\Imports;

use App\Domain\Operations\Imports\Contracts\ImportDefinition;
use App\Domain\Operations\Imports\Definitions\EventImportDefinition;
use App\Domain\Operations\Imports\Definitions\HolidayImportDefinition;
use App\Domain\Operations\Imports\Definitions\WalkImportDefinition;

final class ImportDefinitionRegistry
{
    /** @return list<ImportDefinition> */
    public function all(): array
    {
        return [app(WalkImportDefinition::class), app(HolidayImportDefinition::class), app(EventImportDefinition::class)];
    }

    public function find(string $key): ImportDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->key() === $key) {
                return $definition;
            }
        }

        throw new \InvalidArgumentException('The selected import type is not available.');
    }
}
