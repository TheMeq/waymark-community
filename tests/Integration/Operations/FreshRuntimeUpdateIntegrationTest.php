<?php

namespace Tests\Integration\Operations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class FreshRuntimeUpdateIntegrationTest extends TestCase
{
    private string $root;

    private ?Process $server = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-fresh-runtime-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->server?->stop(1);
        $this->clean($this->root);
        parent::tearDown();
    }

    public function test_success_uses_new_class_map_and_migrates_only_in_a_fresh_request(): void
    {
        $baseUrl = $this->startFixture();

        $begin = $this->request($baseUrl.'/begin?mode=success');
        $this->assertSame(200, $begin['status']);
        $pending = json_decode($begin['body'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($pending['old_request_has_new_class']);
        $this->assertFalse($pending['old_request_after_swap_has_new_class']);
        $this->assertFileExists($this->root.'/storage/update/file-rollback/vendor/composer/autoload_classmap.php');
        $this->assertFileExists($this->root.'/storage/framework/maintenance.json');

        $this->assertSame(403, $this->request($baseUrl.'/activate?token='.urlencode($pending['token']))['status']);
        $activation = $this->request($baseUrl.'/activate', 'POST', http_build_query(['token' => $pending['token']]));
        $this->assertSame(200, $activation['status']);
        $activated = json_decode($activation['body'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($activated['new_class_loaded']);
        $this->assertNotSame($pending['initiating_request_id'], $activated['activation_request_id']);
        $this->assertSame($activated['activation_request_id'], $activated['migration_request_id']);
        $this->assertSame('new-schema', file_get_contents($this->root.'/storage/database.txt'));
        $this->assertSame("2.0.0\n", file_get_contents($this->root.'/VERSION'));
        $this->assertFileDoesNotExist($this->root.'/storage/framework/maintenance.json');
        $this->assertDirectoryDoesNotExist($this->root.'/storage/update');
        $this->assertSame('installed', $this->state()['status']);
    }

    public function test_migration_failure_rolls_back_and_retains_recovery_artifacts(): void
    {
        $baseUrl = $this->startFixture();
        $pending = json_decode($this->request($baseUrl.'/begin?mode=migration_failure')['body'], true, flags: JSON_THROW_ON_ERROR);

        $activation = $this->request($baseUrl.'/activate', 'POST', http_build_query(['token' => $pending['token']]));

        $this->assertSame(409, $activation['status']);
        $rollback = json_decode($activation['body'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame("1.0.0\n", file_get_contents($this->root.'/VERSION'));
        $this->assertSame('v2-incompatible-schema', file_get_contents($this->root.'/storage/database.txt'));
        $this->assertFileExists($this->root.'/storage/framework/maintenance.json');
        $this->assertDirectoryExists($this->root.'/storage/update/file-rollback');
        $this->assertSame('pending_rollback', $this->state()['status']);
        $this->assertFalse($this->state()['rollback_complete']);

        $completed = $this->request($baseUrl.'/rollback', 'POST', http_build_query(['token' => $rollback['rollback_token']]));

        $this->assertSame(200, $completed['status']);
        $result = json_decode($completed['body'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($result['old_class_loaded']);
        $this->assertFalse($result['new_class_loaded']);
        $this->assertSame('old-schema', file_get_contents($this->root.'/storage/database.txt'));
        $this->assertSame('update_failed', $this->state()['status']);
        $this->assertTrue($this->state()['rollback_complete']);
        $this->assertSame('1.0.0', $this->state()['health_runtime_version']);
        $this->assertFileExists($this->root.'/storage/framework/maintenance.json');
        $this->assertDirectoryExists($this->root.'/storage/update/file-rollback');
    }

    public function test_boot_failure_leaves_pending_state_for_framework_independent_recovery(): void
    {
        $baseUrl = $this->startFixture();
        $pending = json_decode($this->request($baseUrl.'/begin?mode=boot_failure')['body'], true, flags: JSON_THROW_ON_ERROR);

        $activation = $this->request($baseUrl.'/activate', 'POST', http_build_query(['token' => $pending['token']]));
        $this->assertSame(500, $activation['status']);
        $this->assertSame('pending_activation', $this->state()['status']);
        $this->assertDirectoryExists($this->root.'/storage/update/file-rollback');
        $this->assertFileExists($this->root.'/storage/framework/maintenance.json');

        $recovery = $this->request(
            $baseUrl.'/waymark-update-recovery.php',
            'POST',
            http_build_query(['activation_token' => $pending['token']]),
        );

        $this->assertSame(200, $recovery['status']);
        $this->assertStringContainsString('Previous application files restored', $recovery['body']);
        $this->assertSame('boot_rollback_completed', $this->state()['status']);
        $this->assertSame("1.0.0\n", file_get_contents($this->root.'/VERSION'));
        $this->assertFileExists($this->root.'/storage/framework/maintenance.json');
        $this->assertDirectoryExists($this->root.'/storage/update/file-rollback');
        $this->assertSame(200, $this->request($baseUrl.'/status')['status'], 'The restored old runtime must boot again.');
    }

    private function startFixture(): string
    {
        $this->writeFixture();
        $portSocket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($portSocket, $errorMessage);
        $address = stream_socket_get_name($portSocket, false);
        fclose($portSocket);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);

        $this->server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', 'public', 'router.php'], $this->root);
        $this->server->start();
        $baseUrl = 'http://127.0.0.1:'.$port;
        $deadline = microtime(true) + 10;
        do {
            if (! $this->server->isRunning()) {
                self::fail('The real update fixture server stopped: '.$this->server->getErrorOutput());
            }
            $response = $this->request($baseUrl.'/status');
            if ($response['status'] === 200) {
                return $baseUrl;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        self::fail('The real update fixture server did not become ready.');
    }

    /** @return array{status: int, body: string} */
    private function request(string $url, string $method = 'GET', string $content = ''): array
    {
        $headers = $method === 'POST' ? "Content-Type: application/x-www-form-urlencoded\r\n" : '';
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $content,
            'ignore_errors' => true,
            'timeout' => 2,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches) === 1
            ? (int) $matches[1]
            : 0;

        return ['status' => $status, 'body' => is_string($body) ? $body : ''];
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        return json_decode(
            (string) file_get_contents($this->root.'/storage/app/private/update-state.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private function writeFixture(): void
    {
        foreach (['public', 'vendor/composer', 'vendor/fixture', 'storage/app/private', 'storage/framework'] as $directory) {
            mkdir($this->root.DIRECTORY_SEPARATOR.$directory, 0700, true);
        }
        file_put_contents($this->root.'/VERSION', "1.0.0\n");
        file_put_contents($this->root.'/storage/database.txt', 'old-schema');
        file_put_contents($this->root.'/vendor/fixture/OldPackage.php', "<?php namespace Fixture; final class OldPackage {}\n");
        file_put_contents($this->root.'/vendor/autoload.php', <<<'PHP'
<?php
$map = require __DIR__.'/composer/autoload_classmap.php';
spl_autoload_register(static function (string $class) use ($map): void {
    if (isset($map[$class])) {
        require $map[$class];
    }
});
PHP);
        file_put_contents($this->root.'/vendor/composer/autoload_classmap.php', <<<'PHP'
<?php
return ['Fixture\\OldPackage' => __DIR__.'/../fixture/OldPackage.php'];
PHP);
        copy(dirname(__DIR__, 3).'/public/waymark-update-recovery.php', $this->root.'/public/waymark-update-recovery.php');
        copy(dirname(__DIR__, 3).'/public/waymark-update-recovery-runtime.php', $this->root.'/public/waymark-update-recovery-runtime.php');
        file_put_contents($this->root.'/router.php', $this->routerSource());
    }

    private function routerSource(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

$root = __DIR__;
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (PHP_SAPI === 'cli-server' && is_string($path) && is_file($root.'/public'.$path)) {
    return false;
}

require $root.'/vendor/autoload.php';
$requestId = bin2hex(random_bytes(16));
$statePath = $root.'/storage/app/private/update-state.json';
$maintenancePath = $root.'/storage/framework/maintenance.json';

$writeState = static function (array $state) use ($statePath): void {
    file_put_contents($statePath, json_encode(['format' => 1, ...$state], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
};
$restoreFiles = static function (array $state) use ($root): void {
    foreach (array_reverse($state['rollback_records']) as $record) {
        $target = $root.'/'.$record['path'];
        if ($record['existed']) {
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }
            copy($state['rollback_directory'].'/'.$record['path'], $target);
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
};

header('Content-Type: application/json');
header('Referrer-Policy: no-referrer');
if ($path === '/status') {
    echo json_encode(['version' => trim((string) file_get_contents($root.'/VERSION'))]);
    return;
}

if ($path === '/begin') {
    $mode = is_string($_GET['mode'] ?? null) ? $_GET['mode'] : 'success';
    $oldHasNew = class_exists('Fixture\\NewPackage');
    $rollback = $root.'/storage/update/file-rollback';
    foreach (['vendor/autoload.php', 'vendor/composer/autoload_classmap.php', 'VERSION'] as $file) {
        if (! is_dir($rollback.'/'.dirname($file))) {
            mkdir($rollback.'/'.dirname($file), 0700, true);
        }
        copy($root.'/'.$file, $rollback.'/'.$file);
    }
    copy($root.'/storage/database.txt', $root.'/storage/update/database.txt');
    $records = [
        ['path' => 'vendor/autoload.php', 'existed' => true, 'permissions' => null],
        ['path' => 'vendor/composer/autoload_classmap.php', 'existed' => true, 'permissions' => null],
        ['path' => 'vendor/fixture/NewPackage.php', 'existed' => false, 'permissions' => null],
        ['path' => 'VERSION', 'existed' => true, 'permissions' => null],
    ];
    file_put_contents($root.'/vendor/fixture/NewPackage.php', "<?php namespace Fixture; final class NewPackage { public const RELEASE = 'new'; }\n");
    file_put_contents($root.'/vendor/composer/autoload_classmap.php', "<?php\nreturn ['Fixture\\\\OldPackage' => __DIR__.'/../fixture/OldPackage.php', 'Fixture\\\\NewPackage' => __DIR__.'/../fixture/NewPackage.php'];\n");
    if ($mode === 'boot_failure') {
        file_put_contents($root.'/vendor/autoload.php', "<?php this is deliberately invalid PHP !!!\n");
    }
    file_put_contents($root.'/VERSION', "2.0.0\n");
    file_put_contents($maintenancePath, "maintenance\n");
    $token = bin2hex(random_bytes(32));
    $writeState([
        'status' => 'pending_activation',
        'activation_token_hash' => hash('sha256', $token),
        'initiating_runtime_id' => $requestId,
        'application_root' => $root,
        'rollback_directory' => $rollback,
        'rollback_records' => $records,
        'mode' => $mode,
    ]);
    echo json_encode([
        'token' => $token,
        'initiating_request_id' => $requestId,
        'old_request_has_new_class' => $oldHasNew,
        'old_request_after_swap_has_new_class' => class_exists('Fixture\\NewPackage'),
    ]);
    return;
}

if ($path === '/activate') {
    $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : null;
    $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || ! is_array($state) || $state['status'] !== 'pending_activation'
        || ! hash_equals($state['activation_token_hash'], hash('sha256', $token))
        || hash_equals($state['initiating_runtime_id'], $requestId)) {
        http_response_code(403);
        echo json_encode(['error' => 'activation rejected']);
        return;
    }
    try {
        if (! class_exists('Fixture\\NewPackage')) {
            throw new RuntimeException('new class map unavailable');
        }
        file_put_contents($root.'/storage/database.txt', $state['mode'] === 'migration_failure' ? 'v2-incompatible-schema' : 'new-schema');
        if ($state['mode'] === 'migration_failure') {
            throw new RuntimeException('controlled migration failure');
        }
        $writeState(['status' => 'installed', 'installed_version' => '2.0.0']);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/storage/update', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root.'/storage/update');
        unlink($maintenancePath);
        echo json_encode([
            'new_class_loaded' => true,
            'activation_request_id' => $requestId,
            'migration_request_id' => $requestId,
        ]);
    } catch (Throwable $failure) {
        $restoreFiles($state);
        $rollbackToken = bin2hex(random_bytes(32));
        $writeState([
            ...$state,
            'status' => 'pending_rollback',
            'failed_runtime_id' => $requestId,
            'rollback_token_hash' => hash('sha256', $rollbackToken),
            'rollback_complete' => false,
            'failure' => $failure->getMessage(),
        ]);
        http_response_code(409);
        echo json_encode(['error' => 'old runtime rollback required', 'rollback_token' => $rollbackToken]);
    }
    return;
}

if ($path === '/rollback') {
    $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : null;
    $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || ! is_array($state) || $state['status'] !== 'pending_rollback'
        || ! hash_equals($state['rollback_token_hash'], hash('sha256', $token))
        || hash_equals($state['failed_runtime_id'], $requestId)) {
        http_response_code(403);
        echo json_encode(['error' => 'rollback rejected']);
        return;
    }
    copy($root.'/storage/update/database.txt', $root.'/storage/database.txt');
    $writeState([
        ...$state,
        'status' => 'update_failed',
        'rollback_complete' => true,
        'rollback_runtime_id' => $requestId,
        'health_runtime_version' => trim((string) file_get_contents($root.'/VERSION')),
    ]);
    echo json_encode([
        'old_class_loaded' => class_exists('Fixture\\OldPackage'),
        'new_class_loaded' => class_exists('Fixture\\NewPackage'),
        'rollback_request_id' => $requestId,
    ]);
    return;
}

http_response_code(404);
echo json_encode(['error' => 'not found']);
PHP;
    }

    private function clean(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
