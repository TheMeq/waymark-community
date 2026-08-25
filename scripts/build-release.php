#!/usr/bin/env php
<?php

declare(strict_types=1);

use Waymark\Release\GitCommitResolver;
use Waymark\Release\ReleaseArchiveBuilder;
use Waymark\Release\ReleaseArchiveVerifier;
use Waymark\Release\ReleaseSourcePreparer;
use Waymark\Release\RuntimePathFilter;
use Waymark\Release\VerificationEnvironment;

require_once __DIR__.'/release/GitCommitResolver.php';
require_once __DIR__.'/release/ReleaseArchiveBuilder.php';
require_once __DIR__.'/release/ReleaseArchiveVerifier.php';
require_once __DIR__.'/release/ReleaseSourcePreparer.php';
require_once __DIR__.'/release/RuntimePathFilter.php';
require_once __DIR__.'/release/VerificationEnvironment.php';

$options = getopt('', ['version:', 'commit::', 'output::', 'formats::', 'plan']);
$version = is_string($options['version'] ?? null) ? trim($options['version']) : '';
$requestedCommit = is_string($options['commit'] ?? null) ? trim($options['commit']) : 'HEAD';
$requestedFormats = is_string($options['formats'] ?? null) ? trim($options['formats']) : 'all';
$formats = $requestedFormats === 'all' ? ['standard', 'public-html'] : array_values(array_filter(array_map('trim', explode(',', $requestedFormats))));
if (preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/', $version) !== 1) {
    fwrite(STDERR, "Usage: php scripts/build-release.php --version=<semver> [--commit=<40-char-sha>] [--output=<directory>] [--formats=all|standard|public-html] [--plan]\n");
    exit(1);
}
if ($requestedCommit !== 'HEAD' && preg_match('/\A[a-f0-9]{40}\z/', $requestedCommit) !== 1) {
    fwrite(STDERR, "Release source commit must be HEAD or an exact 40-character SHA.\n");
    exit(1);
}
if ($formats === [] || array_diff($formats, ['standard', 'public-html']) !== []) {
    fwrite(STDERR, "Release formats must be all, standard, public-html, or a comma-separated selection.\n");
    exit(1);
}

$artifacts = [
    'standard' => "waymark-community-{$version}-shared-hosting.zip",
    'public-html' => "waymark-community-{$version}-public-html.zip",
];

$plan = [
    'version' => $version,
    'archive' => $artifacts['standard'],
    'formats' => $formats,
    'artifacts' => array_intersect_key($artifacts, array_flip($formats)),
    'source' => 'git clone + detached checkout '.$requestedCommit,
    'build' => [
        'composer install --no-interaction --prefer-dist',
        'npm ci',
        'npm run build',
    ],
    'verification_environment' => [
        'copy .env.example to an untracked disposable .env',
        'generate an ephemeral APP_KEY without inheriting developer configuration',
        'exclude .env from the release package',
    ],
    'verification' => [
        'composer validate --strict',
        'composer prohibits php 8.3',
        'composer verify:repository',
        'composer audit:v1',
        'php -d memory_limit=512M vendor/bin/phpunit',
        'npm run test:pwa',
        'npm run test:tooling',
    ],
    'package' => [
        'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader',
        'generate production-safe .env.example and operator README',
        'protect the internal application beneath the public web root',
        'write release-manifest.json',
        'write SHA-256 checksum',
        'verify the completed shared-hosting ZIP',
    ],
    'excludes' => ['.git', '.env', 'node_modules', 'tests', 'runtime storage', 'backups', 'logs', 'caches', 'reports', 'developer reference assets', 'Waymark developer and contributor files'],
];

if (array_key_exists('plan', $options)) {
    fwrite(STDOUT, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    exit(0);
}

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fail('The repository root could not be resolved.');
}

$status = capture('git status --porcelain', $root);
if (trim($status) !== '') {
    fail('Release builds require a clean Git worktree.');
}
$commit = (new GitCommitResolver)->resolve($root, $requestedCommit);

$temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'waymark-release-'.bin2hex(random_bytes(8));
$source = $temporaryRoot.DIRECTORY_SEPARATOR.'source';
$application = $temporaryRoot.DIRECTORY_SEPARATOR.'application';
$publicHtmlApplication = $temporaryRoot.DIRECTORY_SEPARATOR.'public-html-application';

try {
    mkdir($temporaryRoot, 0700, true);
    runCommand('git clone --quiet --no-hardlinks --no-checkout '.escapeshellarg($root).' '.escapeshellarg($source), $root);
    runCommand('git checkout --quiet --detach '.$commit, $source);
    (new VerificationEnvironment)->prepare($source);

    foreach ($plan['build'] as $command) {
        runCommand($command, $source);
    }
    foreach ($plan['verification'] as $command) {
        if ($command === 'composer audit:v1' && ! is_file($source.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'audit-v1-scope.php')) {
            continue;
        }
        runCommand($command, $source);
    }

    (new ReleaseSourcePreparer)->prepare(
        $source,
        $application,
        $version,
        template('.env.production.example'),
        template('shared-hosting-README.md'),
    );
    copyGeneratedDirectory($source, $application, 'public/build');
    file_put_contents($application.DIRECTORY_SEPARATOR.'VERSION', $version."\n", LOCK_EX);
    file_put_contents($application.DIRECTORY_SEPARATOR.'DEPLOYMENT-LAYOUT', "standard\n", LOCK_EX);
    runCommand($plan['package'][0], $application);
    RuntimePathFilter::purge($application);

    $schema = latestMigration($application);
    $outputDirectory = is_string($options['output'] ?? null) && trim($options['output']) !== ''
        ? absolutePath($root, trim($options['output']))
        : $root.DIRECTORY_SEPARATOR.'dist';
    $metadata = [
        'version' => $version,
        'commit' => $commit,
        'built_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'minimum_php' => '8.3.0',
        'schema' => $schema,
    ];
    $results = [];
    if (in_array('standard', $formats, true)) {
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.$artifacts['standard'];
        $results['standard'] = (new ReleaseArchiveBuilder)->build($application, $outputPath, [...$metadata, 'layout' => 'standard']);
        $results['standard']['verification'] = (new ReleaseArchiveVerifier)->verify($outputPath);
    }
    if (in_array('public-html', $formats, true)) {
        copyPreparedApplication($application, $publicHtmlApplication);
        file_put_contents($publicHtmlApplication.DIRECTORY_SEPARATOR.'DEPLOYMENT-LAYOUT', "public-html\n", LOCK_EX);
        file_put_contents($publicHtmlApplication.DIRECTORY_SEPARATOR.'.htaccess', template('public-html-application.htaccess'), LOCK_EX);
        file_put_contents($publicHtmlApplication.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php', template('public-html-index.php'), LOCK_EX);
        file_put_contents($publicHtmlApplication.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'README.md', str_replace('{{VERSION}}', $version, template('public-html-README.md')), LOCK_EX);
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.$artifacts['public-html'];
        $results['public-html'] = (new ReleaseArchiveBuilder)->build($publicHtmlApplication, $outputPath, [...$metadata, 'layout' => 'public-html']);
        $results['public-html']['verification'] = (new ReleaseArchiveVerifier)->verify($outputPath);
    }

    fwrite(STDOUT, json_encode(['version' => $version, 'commit' => $commit, 'artifacts' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fail($exception->getMessage());
} finally {
    removeDirectory($temporaryRoot);
}

function fail(string $message): never
{
    fwrite(STDERR, "Release build failed: {$message}\n");
    exit(1);
}

function runCommand(string $command, string $directory): void
{
    fwrite(STDOUT, "[release] {$command}\n");
    $previous = getcwd();
    if ($previous === false || ! chdir($directory)) {
        throw new RuntimeException('A release build workspace could not be entered.');
    }
    try {
        passthru($command, $exitCode);
    } finally {
        chdir($previous);
    }
    if ($exitCode !== 0) {
        throw new RuntimeException("Command failed ({$exitCode}): {$command}");
    }
}

function capture(string $command, string $directory): string
{
    $previous = getcwd();
    if ($previous === false || ! chdir($directory)) {
        throw new RuntimeException('The repository could not be entered.');
    }
    try {
        $lines = [];
        exec($command, $lines, $exitCode);
    } finally {
        chdir($previous);
    }
    if ($exitCode !== 0) {
        throw new RuntimeException("Command failed ({$exitCode}): {$command}");
    }

    return implode("\n", $lines);
}

function copyGeneratedDirectory(string $source, string $destination, string $relative): void
{
    $from = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (! is_dir($from)) {
        throw new RuntimeException("Required generated directory is missing: {$relative}");
    }
    $to = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $suffix = substr($item->getPathname(), strlen($from) + 1);
        $target = $to.DIRECTORY_SEPARATOR.$suffix;
        if ($item->isDir()) {
            if (! is_dir($target)) {
                mkdir($target, 0700, true);
            }
        } else {
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}

function copyPreparedApplication(string $source, string $destination): void
{
    if (! mkdir($destination, 0700, true) && ! is_dir($destination)) {
        throw new RuntimeException('The public-html application workspace could not be created.');
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $suffix = substr($item->getPathname(), strlen($source) + 1);
        $target = $destination.DIRECTORY_SEPARATOR.$suffix;
        if ($item->isDir()) {
            if (! is_dir($target) && ! mkdir($target, 0700, true) && ! is_dir($target)) {
                throw new RuntimeException('The public-html application directory could not be copied.');
            }
        } elseif (! copy($item->getPathname(), $target)) {
            throw new RuntimeException('The public-html application file could not be copied.');
        }
    }
}

function template(string $name): string
{
    $contents = file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'release'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.$name);
    if (! is_string($contents)) {
        throw new RuntimeException("Release template is missing: {$name}");
    }

    return $contents;
}

function latestMigration(string $application): string
{
    $files = glob($application.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'*.php');
    if (! is_array($files) || $files === []) {
        throw new RuntimeException('Release schema metadata could not be resolved.');
    }
    sort($files, SORT_STRING);
    $name = pathinfo(end($files), PATHINFO_FILENAME);
    if (preg_match('/\A(\d{4}_\d{2}_\d{2}_\d{6})_/', $name, $matches) !== 1) {
        throw new RuntimeException('Release schema metadata is invalid.');
    }

    return $matches[1];
}

function absolutePath(string $root, string $path): string
{
    if (preg_match('#\A(?:[A-Za-z]:[\\/]|/)#', $path) === 1) {
        return rtrim($path, '\\/');
    }

    return $root.DIRECTORY_SEPARATOR.rtrim($path, '\\/');
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}
