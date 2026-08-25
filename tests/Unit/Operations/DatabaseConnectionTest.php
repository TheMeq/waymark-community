<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Installation\DatabaseConfiguration;
use App\Domain\Operations\Installation\PdoDatabaseConnectionTester;
use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    public function test_a_real_pdo_connection_is_verified_with_a_query(): void
    {
        $result = (new PdoDatabaseConnectionTester)->test(new DatabaseConfiguration(
            driver: 'sqlite',
            host: '',
            port: null,
            database: ':memory:',
            username: '',
            password: '',
        ));

        $this->assertTrue($result->successful);
        $this->assertSame('Database connection and schema permissions verified.', $result->message);
    }

    public function test_connection_failures_are_sanitised_for_non_developers(): void
    {
        $result = (new PdoDatabaseConnectionTester)->test(new DatabaseConfiguration(
            driver: 'unsupported',
            host: 'db.internal',
            port: 3306,
            database: 'waymark',
            username: 'owner',
            password: 'top-secret',
        ));

        $this->assertFalse($result->successful);
        $this->assertSame('Waymark could not connect using those database details.', $result->message);
        $this->assertStringNotContainsString('top-secret', $result->message);
        $this->assertStringNotContainsString('PDO', $result->message);
    }
}
