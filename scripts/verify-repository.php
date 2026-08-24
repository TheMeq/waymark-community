#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));

if ($root === false) {
    fwrite(STDERR, "Unable to resolve the repository root.\n");
    exit(1);
}

chdir($root);

$required = [
    '.env.example',
    '.gitignore',
    'README.md',
    'artisan',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'phpunit.xml',
    'playwright.config.ts',
    'vite.config.js',
    'app/Domain/Events/.gitkeep',
    'app/Domain/Gallery/.gitkeep',
    'app/Domain/Membership/.gitkeep',
    'app/Domain/Content/.gitkeep',
    'app/Domain/Governance/.gitkeep',
    'app/Domain/Operations/.gitkeep',
    'tests/Feature/Foundation/ApplicationBootTest.php',
];

$errors = [];

$composer = json_decode((string) file_get_contents($root.DIRECTORY_SEPARATOR.'composer.json'), true);
$lock = json_decode((string) file_get_contents($root.DIRECTORY_SEPARATOR.'composer.lock'), true);
$minimumPhp = '8.3.0';

if (($composer['require']['php'] ?? null) !== '^8.3') {
    $errors[] = 'composer.json must retain PHP ^8.3 as the supported runtime contract.';
}

if (($composer['config']['platform']['php'] ?? null) !== $minimumPhp) {
    $errors[] = "Composer dependency resolution must target PHP {$minimumPhp}.";
}

if (($lock['platform-overrides']['php'] ?? null) !== $minimumPhp) {
    $errors[] = "composer.lock was not resolved for PHP {$minimumPhp}.";
}

foreach ($required as $path) {
    if (! file_exists($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path))) {
        $errors[] = "Required baseline file is missing: {$path}";
    }
}

if (is_file($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'robots.txt')) {
    $errors[] = 'public/robots.txt must remain application-routed so staging indexing safeguards cannot be bypassed.';
}

$tracked = [];
exec('git ls-files', $tracked, $gitExitCode);

if ($gitExitCode !== 0) {
    $errors[] = 'Unable to inspect tracked files with git ls-files.';
}

$portableSourceExtensions = [
    'css',
    'env',
    'ini',
    'js',
    'json',
    'mjs',
    'php',
    'ps1',
    'scss',
    'sh',
    'toml',
    'ts',
    'xml',
    'yaml',
    'yml',
];
$developerProfilePatterns = [
    '#(?<![A-Za-z0-9_.-])[A-Za-z]:[\\\\/]+Users[\\\\/]+[A-Za-z0-9][A-Za-z0-9._-]*[\\\\/]+#i',
    '#(?<![A-Za-z0-9_.-])/Users/[A-Za-z0-9][A-Za-z0-9._-]*/#',
    '#(?<![A-Za-z0-9_.-])/home/[A-Za-z0-9][A-Za-z0-9._-]*/#',
];

foreach ($tracked as $path) {
    $normalized = str_replace('\\', '/', trim($path));
    $isPlaceholder = str_ends_with($normalized, '/.gitignore');

    if ($normalized === '.env.example') {
        continue;
    }

    if (preg_match('#^\.env(?:\.|$)#', $normalized)
        || preg_match('#(^|/)(vendor|node_modules)(/|$)#', $normalized)
        || preg_match('#^public/build/#', $normalized)
        || preg_match('#^public/(css|fonts|js)/filament/#', $normalized)
        || (! $isPlaceholder && preg_match('#^storage/(app/(backups|uploads)|framework/(cache|sessions|views)|logs)/#', $normalized))
        || preg_match('#^(coverage|playwright-report|test-results|dist|releases)/#', $normalized)
        || preg_match('#\.(zip|sha256)$#', $normalized)
    ) {
        $errors[] = "Forbidden runtime, generated, or release path is tracked: {$normalized}";
    }

    $extension = strtolower((string) pathinfo($normalized, PATHINFO_EXTENSION));

    if (! in_array($extension, $portableSourceExtensions, true)) {
        continue;
    }

    $contents = file_get_contents($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized));

    if ($contents === false || str_contains($contents, "\0")) {
        continue;
    }

    foreach ($developerProfilePatterns as $pattern) {
        if (preg_match($pattern, $contents) === 1) {
            $errors[] = "Developer-local absolute path is tracked: {$normalized}";
            break;
        }
    }
}

$ignoredSentinels = [
    '.env',
    'vendor/.waymark-check',
    'node_modules/.waymark-check',
    'public/build/.waymark-check',
    'public/js/filament/.waymark-check',
    'storage/logs/.waymark-check',
    'test-results/.waymark-check',
    'dist/waymark-community-test.zip',
];

foreach ($ignoredSentinels as $path) {
    $command = 'git check-ignore --quiet --no-index '.escapeshellarg($path);
    exec($command, $output, $ignoreExitCode);

    if ($ignoreExitCode !== 0) {
        $errors[] = "Expected path is not ignored: {$path}";
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, "Repository verification passed.\n");
