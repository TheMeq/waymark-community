<?php

namespace App\Domain\Operations\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class PortableDatabaseImporter
{
    public function restore(string $path, int $chunkSize = 100): void
    {
        $tables = $this->validate($path);

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($path, $tables, $chunkSize): void {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }

                $stream = fopen($path, 'rb');
                if ($stream === false) {
                    throw new RuntimeException('The verified database component could not be read.');
                }
                try {
                    $currentTable = null;
                    $batch = [];
                    while (($line = fgets($stream)) !== false) {
                        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                        if ($record['type'] === 'table') {
                            $this->insertBatch($currentTable, $batch);
                            $currentTable = $record['name'];
                            $batch = [];
                        } else {
                            if ($record['table'] !== $currentTable) {
                                throw new RuntimeException('The verified database component has invalid ordering.');
                            }
                            $batch[] = $record['values'];
                            if (count($batch) >= max(10, min($chunkSize, 500))) {
                                $this->insertBatch($currentTable, $batch);
                                $batch = [];
                            }
                        }
                    }
                    $this->insertBatch($currentTable, $batch);
                } finally {
                    fclose($stream);
                }
            }, 1);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function assertCompatible(string $path): void
    {
        $this->validate($path);
    }

    public function assertStructurallyValid(string $path): void
    {
        $this->validate($path, false);
    }

    /** @return list<string> */
    private function validate(string $path, bool $checkSchema = true): array
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The verified database component could not be read.');
        }
        $tables = [];
        $knownColumns = [];
        try {
            while (($line = fgets($stream)) !== false) {
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($record) || ! in_array($record['type'] ?? null, ['table', 'row'], true)) {
                    throw new RuntimeException('The verified database component is invalid.');
                }
                if ($record['type'] === 'table') {
                    $table = $record['name'] ?? null;
                    $declaredColumns = $record['columns'] ?? null;
                    if (! is_string($table) || $table === '' || isset($knownColumns[$table])
                        || ! is_array($declaredColumns) || $declaredColumns === []
                        || array_filter($declaredColumns, fn (mixed $column): bool => ! is_string($column) || $column === '') !== []
                        || count(array_unique($declaredColumns)) !== count($declaredColumns)) {
                        throw new RuntimeException('The verified database component does not match this installation.');
                    }
                    if ($checkSchema && (! Schema::hasTable($table) || $declaredColumns !== $this->writableColumns($table))) {
                        throw new RuntimeException('The verified database component does not match this installation.');
                    }
                    $tables[] = $table;
                    $knownColumns[$table] = array_fill_keys($declaredColumns, true);

                    continue;
                }
                $table = $record['table'] ?? null;
                $values = $record['values'] ?? null;
                if (! is_string($table) || ! isset($knownColumns[$table]) || ! is_array($values)
                    || array_diff_key($values, $knownColumns[$table]) !== []
                    || array_diff_key($knownColumns[$table], $values) !== []) {
                    throw new RuntimeException('The verified database component is invalid.');
                }
            }
        } catch (\JsonException $exception) {
            throw new RuntimeException('The verified database component is invalid.', previous: $exception);
        } finally {
            fclose($stream);
        }

        if ($tables === []) {
            throw new RuntimeException('The verified database component is empty.');
        }

        return $tables;
    }

    /** @return list<string> */
    private function writableColumns(string $table): array
    {
        return array_values(array_map(
            fn (array $column): string => (string) $column['name'],
            array_filter(Schema::getColumns($table), fn (array $column): bool => ($column['generation'] ?? null) === null
                && trim((string) ($column['expression'] ?? '')) === ''
                && ! str_contains(strtolower((string) ($column['extra'] ?? '')), 'generated')),
        ));
    }

    /** @param list<array<string, mixed>> $batch */
    private function insertBatch(?string $table, array $batch): void
    {
        if (is_string($table) && $batch !== []) {
            DB::table($table)->insert($batch);
        }
    }
}
