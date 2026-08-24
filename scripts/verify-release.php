#!/usr/bin/env php
<?php

declare(strict_types=1);

use Waymark\Release\ReleaseArchiveVerifier;

require_once __DIR__.'/release/ReleaseArchiveVerifier.php';

$archive = $argv[1] ?? null;
if (! is_string($archive) || $archive === '') {
    fwrite(STDERR, "Usage: php scripts/verify-release.php <shared-hosting.zip>\n");
    exit(1);
}

try {
    $result = (new ReleaseArchiveVerifier)->verify($archive);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
