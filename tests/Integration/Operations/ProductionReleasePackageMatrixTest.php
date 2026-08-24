<?php

namespace Tests\Integration\Operations;

use App\Domain\Operations\Updates\ApplicationFileTransaction;
use App\Domain\Operations\Updates\PreparedApplicationUpdate;
use App\Domain\Operations\Updates\ReleaseMetadata;
use App\Domain\Operations\Updates\ReleasePackageVerifier;
use App\Domain\Operations\Updates\VerifiedReleasePackage;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Waymark\Release\ReleaseApplicationExtractor;
use Waymark\Release\ReleaseArchiveVerifier;

require_once dirname(__DIR__, 3).'/scripts/release/ReleaseArchiveVerifier.php';
require_once dirname(__DIR__, 3).'/scripts/release/ReleaseApplicationExtractor.php';

final class ProductionReleasePackageMatrixTest extends TestCase
{
    private string $root;

    private string $previousArchive;

    private string $currentArchive;

    protected function setUp(): void
    {
        parent::setUp();
        $previous = getenv('WAYMARK_PREVIOUS_RELEASE_ARCHIVE');
        $current = getenv('WAYMARK_CURRENT_RELEASE_ARCHIVE');
        if (! is_string($previous) || ! is_file($previous) || ! is_string($current) || ! is_file($current)) {
            $this->markTestSkipped('Set WAYMARK_PREVIOUS_RELEASE_ARCHIVE and WAYMARK_CURRENT_RELEASE_ARCHIVE to run the production package matrix.');
        }
        $this->previousArchive = realpath($previous);
        $this->currentArchive = realpath($current);
        $this->root = sys_get_temp_dir().'/waymark-production-matrix-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            $this->clean($this->root);
        }
        parent::tearDown();
    }

    public function test_real_prior_package_upgrades_with_migrations_data_and_media_retained(): void
    {
        $database = $this->preparePriorInstallation();
        [$transaction, $release] = $this->applyCurrentPackage();

        $this->runArtisan('migrate', '--force');

        $pdo = new PDO('sqlite:'.$database);
        $this->assertSame('Release matrix group', $pdo->query('SELECT group_name FROM site_profiles')->fetchColumn());
        $this->assertSame('portability_export_runs', $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='portability_export_runs'")->fetchColumn());
        $this->assertSame('retained media', file_get_contents($this->root.'/storage/app/private/site-media/release-matrix.txt'));
        $this->assertSame('1.0.0', trim((string) file_get_contents($this->root.'/VERSION')));

        $transaction->cleanup();
        $release->cleanup();
    }

    public function test_controlled_activation_failure_rolls_real_package_files_and_database_back_to_prior_release(): void
    {
        $database = $this->preparePriorInstallation();
        $databaseBackup = $database.'.pre-update';
        copy($database, $databaseBackup);
        [$transaction, $release] = $this->applyCurrentPackage();
        $this->runArtisan('migrate', '--force');

        $transaction->rollback();
        copy($databaseBackup, $database);

        $pdo = new PDO('sqlite:'.$database);
        $this->assertSame('Release matrix group', $pdo->query('SELECT group_name FROM site_profiles')->fetchColumn());
        $this->assertFalse($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='portability_export_runs'")->fetchColumn());
        $this->assertSame('retained media', file_get_contents($this->root.'/storage/app/private/site-media/release-matrix.txt'));
        $this->assertSame('0.9.0', trim((string) file_get_contents($this->root.'/VERSION')));
        $this->runArtisan('about', '--only=environment');

        $transaction->cleanup();
        $release->cleanup();
    }

    private function preparePriorInstallation(): string
    {
        (new ReleaseApplicationExtractor)->extract($this->previousArchive, $this->root);
        $database = $this->root.'/database/database.sqlite';
        touch($database);
        $key = 'base64:'.base64_encode(random_bytes(32));
        file_put_contents($this->root.'/.env', implode("\n", [
            'APP_NAME="Waymark Community"',
            'APP_ENV=production',
            'APP_KEY='.$key,
            'APP_DEBUG=false',
            'APP_URL=http://localhost',
            'DB_CONNECTION=sqlite',
            'DB_DATABASE='.str_replace('\\', '/', $database),
            'CACHE_STORE=array',
            'SESSION_DRIVER=file',
            'QUEUE_CONNECTION=sync',
            'MAIL_MAILER=array',
            'WAYMARK_INSTALLED=true',
        ])."\n");
        $this->runArtisan('migrate', '--force');

        $pdo = new PDO('sqlite:'.$database);
        $statement = $pdo->prepare('INSERT INTO site_profiles (group_name, created_at, updated_at) VALUES (?, ?, ?)');
        $timestamp = gmdate('Y-m-d H:i:s');
        $statement->execute(['Release matrix group', $timestamp, $timestamp]);
        mkdir($this->root.'/storage/app/private/site-media', 0700, true);
        file_put_contents($this->root.'/storage/app/private/site-media/release-matrix.txt', 'retained media');

        return $database;
    }

    /** @return array{PreparedApplicationUpdate, VerifiedReleasePackage} */
    private function applyCurrentPackage(): array
    {
        $evidence = (new ReleaseArchiveVerifier)->verify($this->currentArchive);
        $metadata = new ReleaseMetadata(
            $evidence['version'],
            '2026-08-24T12:00:00Z',
            false,
            'Production package matrix',
            [],
            'https://updates.example.test/waymark.zip',
            $evidence['sha256'],
            $evidence['size_bytes'],
            ['php' => $evidence['minimum_php'], 'extensions' => [], 'database' => ['mysql' => '8.4', 'mariadb' => '11.4'], 'disk_free_bytes' => 1],
        );
        $release = (new ReleasePackageVerifier)->stage($this->currentArchive, $metadata, $this->root.'/.update-staging');
        $transaction = (new ApplicationFileTransaction)->prepare($release, $this->root, $this->root.'/.file-rollback');
        $transaction->apply();

        return [$transaction, $release];
    }

    private function runArtisan(string ...$arguments): void
    {
        $process = new Process([PHP_BINARY, 'artisan', ...$arguments], $this->root, $this->phpEnvironment());
        $process->setTimeout(120);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    /** @return array<string, string> */
    private function phpEnvironment(): array
    {
        $environment = [];
        $scan = getenv('PHP_INI_SCAN_DIR');
        if (is_string($scan)) {
            $environment['PHP_INI_SCAN_DIR'] = $scan;
        }

        return $environment;
    }

    private function clean(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
