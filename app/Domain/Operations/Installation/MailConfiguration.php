<?php

namespace App\Domain\Operations\Installation;

final readonly class MailConfiguration
{
    public function __construct(
        public string $host,
        public int $port,
        public ?string $encryption,
        public ?string $username,
        public ?string $password,
        public string $fromAddress,
        public string $testAddress,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(
            host: (string) $values['host'],
            port: (int) $values['port'],
            encryption: isset($values['encryption']) && $values['encryption'] !== '' ? (string) $values['encryption'] : null,
            username: isset($values['username']) && $values['username'] !== '' ? (string) $values['username'] : null,
            password: isset($values['password']) && $values['password'] !== '' ? (string) $values['password'] : null,
            fromAddress: (string) $values['from_address'],
            testAddress: (string) $values['test_address'],
        );
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'password' => $this->password,
            'from_address' => $this->fromAddress,
            'test_address' => $this->testAddress,
        ];
    }
}
