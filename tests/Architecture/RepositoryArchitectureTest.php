<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RepositoryArchitectureTest extends TestCase
{
    #[DataProvider('bannedRootProvider')]
    public function test_generic_dumping_ground_roots_are_absent(string $directory): void
    {
        $this->assertDirectoryDoesNotExist(app_path($directory));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bannedRootProvider(): iterable
    {
        yield 'Helpers' => ['Helpers'];
        yield 'Utils' => ['Utils'];
        yield 'Misc' => ['Misc'];
        yield 'Common' => ['Common'];
    }
}
