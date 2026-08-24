<?php

namespace App\Domain\Operations\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class PortableDatabaseExporter
{
    public function export(string $path, int $chunkSize = 100): void
    {
        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException('The database export file could not be created.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if ($driver === 'mysql') {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA read_uncommitted = 0');
        }
        $connection->beginTransaction();

        try {
            foreach ($this->tables() as $table) {
                $columns = $this->writableColumns($table);
                $this->line($stream, ['type' => 'table', 'name' => $table, 'columns' => $columns]);
                $query = DB::table($table)->select($columns);
                if ($columns !== []) {
                    $query->orderBy($columns[0]);
                }
                $query->chunk(max(10, min($chunkSize, 500)), function ($rows) use ($stream, $table): void {
                    foreach ($rows as $row) {
                        $this->line($stream, ['type' => 'row', 'table' => $table, 'values' => (array) $row]);
                    }
                });
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            throw $exception;
        } finally {
            fclose($stream);
        }
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

    /** @return list<string> */
    private function tables(): array
    {
        if (DB::getDriverName() === 'sqlite') {
            return array_values(array_map(
                fn (object $row): string => (string) $row->name,
                DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
            ));
        }

        $tables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        return array_values(array_map(fn (object $row): string => (string) array_values((array) $row)[0], $tables));
    }

    /** @param resource $stream
     * @param  array<string, mixed>  $value
     */
    private function line($stream, array $value): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_string($encoded) || fwrite($stream, $encoded."\n") === false) {
            throw new RuntimeException('The database export could not be written.');
        }
    }
}
