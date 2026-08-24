<?php

declare(strict_types=1);

namespace Waymark\Release;

use RuntimeException;

final class VerificationEnvironment
{
    public function prepare(string $repository): void
    {
        $examplePath = rtrim($repository, '\\/').DIRECTORY_SEPARATOR.'.env.example';
        $environmentPath = rtrim($repository, '\\/').DIRECTORY_SEPARATOR.'.env';
        $contents = file_get_contents($examplePath);

        if (! is_string($contents)) {
            throw new RuntimeException('The verification environment example could not be read.');
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        $prepared = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $contents, 1, $replacements);

        if (! is_string($prepared) || $replacements !== 1) {
            throw new RuntimeException('The verification environment does not contain one APP_KEY setting.');
        }

        if (file_put_contents($environmentPath, $prepared, LOCK_EX) === false) {
            throw new RuntimeException('The disposable verification environment could not be written.');
        }
    }
}
