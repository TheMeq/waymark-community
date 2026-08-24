#!/usr/bin/env php
<?php

declare(strict_types=1);

use Waymark\Release\ReleaseApplicationExtractor;

require_once __DIR__.'/release/ReleaseArchiveVerifier.php';
require_once __DIR__.'/release/ReleaseApplicationExtractor.php';

$archive = $argv[1] ?? null;
$destination = $argv[2] ?? null;
if (! is_string($archive) || $archive === '' || ! is_string($destination) || $destination === '') {
    fwrite(STDERR, "Usage: php scripts/extract-release.php <shared-hosting.zip> <empty-destination>\n");
    exit(1);
}

try {
    $result = (new ReleaseApplicationExtractor)->extract($archive, $destination);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
