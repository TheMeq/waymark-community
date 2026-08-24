<?php

namespace App\Domain\Operations\Installation;

final readonly class DatabaseConfiguration
{
    public function __construct(
        public string $driver,
        public string $host,
        public ?int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(
            driver: (string) $values['driver'],
            host: (string) $values['host'],
            port: isset($values['port']) ? (int) $values['port'] : null,
            database: (string) $values['database'],
            username: (string) $values['username'],
            password: (string) ($values['password'] ?? ''),
        );
    }

    /** @return array{driver: string, host: string, port: int|null, database: string, username: string, password: string} */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
