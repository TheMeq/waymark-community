<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use Waymark\Release\GitCommitResolver;

require_once dirname(__DIR__, 3).'/scripts/release/GitCommitResolver.php';

final class GitCommitResolverTest extends TestCase
{
    public function test_exact_commit_is_resolved_without_shell_interpreting_revision_syntax(): void
    {
        $root = dirname(__DIR__, 3);
        $expected = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD'));

        $this->assertMatchesRegularExpression('/\A[a-f0-9]{40}\z/', $expected);
        $this->assertSame($expected, (new GitCommitResolver)->resolve($root, $expected));
    }
}
