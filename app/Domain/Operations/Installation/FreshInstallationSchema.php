<?php

namespace App\Domain\Operations\Installation;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;

final readonly class FreshInstallationSchema
{
    /** @param list<string> $migrations
     * @param  array<string, list<string>>  $tables
     */
    private function __construct(
        private array $migrations,
        private array $tables,
    ) {}

    public static function load(string $path): self
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($decoded) || ($decoded['format'] ?? null) !== 1 || ! is_array($decoded['migrations'] ?? null) || ! is_array($decoded['tables'] ?? null)) {
            throw new InvalidArgumentException('The fresh-install schema manifest is missing or invalid.');
        }

        $migrations = array_values(array_map('strval', $decoded['migrations']));
        $tables = [];

        foreach ($decoded['tables'] as $table => $columns) {
            if (! is_string($table) || ! is_array($columns)) {
                throw new InvalidArgumentException('The fresh-install schema manifest contains an invalid table definition.');
            }

            $tables[$table] = array_values(array_map('strval', $columns));
            sort($tables[$table]);
        }

        sort($migrations);
        ksort($tables);

        return new self($migrations, $tables);
    }

    public static function capture(ConnectionInterface $connection): self
    {
        $migrations = array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            glob(database_path('migrations/*.php')) ?: [],
        );
        sort($migrations);

        $tables = [];
        $schema = $connection->getSchemaBuilder();

        foreach ($schema->getTables() as $table) {
            $name = (string) ($table['name'] ?? $table['table'] ?? '');

            if ($name === '' || str_starts_with($name, 'sqlite_')) {
                continue;
            }

            $columns = array_map(
                static fn (array $column): string => (string) $column['name'],
                $schema->getColumns($name),
            );
            sort($columns);
            $tables[$name] = array_values($columns);
        }

        ksort($tables);

        return new self(array_values($migrations), $tables);
    }

    /** @return list<string> */
    public function migrationNames(): array
    {
        return $this->migrations;
    }

    /** @return array<string, list<string>> */
    public function tables(): array
    {
        return $this->tables;
    }

    public function migrationSetHash(): string
    {
        return hash('sha256', implode("\n", $this->migrations));
    }

    public function matches(self $other): bool
    {
        return $this->migrations === $other->migrations && $this->tables === $other->tables;
    }

    public function write(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the fresh-install schema manifest directory.');
        }

        $contents = json_encode([
            'format' => 1,
            'migration_set_hash' => $this->migrationSetHash(),
            'migrations' => $this->migrations,
            'tables' => $this->tables,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($contents) || file_put_contents($path, $contents."\n", LOCK_EX) === false) {
            throw new RuntimeException('Could not write the fresh-install schema manifest.');
        }
    }
}
