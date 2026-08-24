<?php

declare(strict_types=1);

namespace Waymark\Release;

use RuntimeException;

final class GitCommitResolver
{
    public function resolve(string $repository, string $revision): string
    {
        $pipes = [];
        $process = proc_open(
            ['git', 'rev-parse', $revision.'^{commit}'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $repository,
            null,
            ['bypass_shell' => true],
        );
        if (! is_resource($process)) {
            throw new RuntimeException('The exact source commit could not be resolved.');
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $commit = trim(is_string($output) ? $output : '');

        if ($exitCode !== 0 || preg_match('/\A[a-f0-9]{40}\z/', $commit) !== 1) {
            throw new RuntimeException('The exact source commit could not be resolved.'.($error === '' ? '' : ' Git rejected the revision.'));
        }

        return $commit;
    }
}
