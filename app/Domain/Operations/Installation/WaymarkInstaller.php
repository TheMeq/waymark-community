<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Accounts\Actions\EstablishInitialInstallationOwner;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class WaymarkInstaller
{
    public function __construct(
        private EnvironmentWriter $environmentWriter,
        private UpdateSiteProfile $updateSiteProfile,
        private EstablishInitialInstallationOwner $establishOwner,
    ) {}

    /** @param array<string, array<string, mixed>> $data */
    public function install(array $data, string $applicationUrl): InstallationAttempt
    {
        $environment = $this->environmentWriter->write($this->environmentValues($data, $applicationUrl));

        if (! $environment->written) {
            return new InstallationAttempt(false, $environment, 'The environment file must be in place before installation can continue.');
        }

        try {
            $this->configureDatabase($data['database']);
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);

            DB::transaction(function () use ($data): void {
                if (SiteProfile::query()->exists() || User::query()->exists()) {
                    throw new \RuntimeException('Existing application records prevent a fresh installation.');
                }

                $enabledModules = $data['modules']['enabled'];
                $moduleConfiguration = [];
                foreach (['walks', 'socials', 'holidays', 'gallery', 'news', 'documents'] as $module) {
                    $moduleConfiguration[$module] = in_array($module, $enabledModules, true);
                }

                $this->updateSiteProfile->handle([
                    ...$data['group-details'],
                    ...$data['branding'],
                    'module_configuration' => $moduleConfiguration,
                ]);

                $administrator = new User;
                $administrator->forceFill([
                    'name' => $data['first-administrator']['name'],
                    'display_name' => $data['first-administrator']['name'],
                    'email' => $data['first-administrator']['email'],
                    'password' => $data['first-administrator']['password'],
                    'email_verified_at' => now(),
                    'role' => AccountRole::Administrator,
                    'is_admin' => true,
                ])->save();
                $this->establishOwner->handle($administrator);
            });

            return new InstallationAttempt(true, $environment, 'Waymark Community was installed successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return new InstallationAttempt(false, $environment, 'Waymark could not complete the database installation. No framework error details were exposed.');
        }
    }

    /** @param array<string, array<string, mixed>> $data
     * @return array<string, string>
     */
    private function environmentValues(array $data, string $applicationUrl): array
    {
        $database = $data['database'];
        $mail = $data['mail'];
        $advanced = $data['advanced'];
        $configuredKey = trim((string) config('app.key'));

        return [
            'APP_NAME' => (string) $data['group-details']['group_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $configuredKey !== '' ? $configuredKey : 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($applicationUrl, '/'),
            'WAYMARK_INSTALLED' => 'false',
            'DB_CONNECTION' => $database['driver'] === 'mariadb' ? 'mysql' : (string) $database['driver'],
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) ($database['port'] ?? ''),
            'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => (string) $mail['host'],
            'MAIL_PORT' => (string) $mail['port'],
            'MAIL_USERNAME' => (string) ($mail['username'] ?? ''),
            'MAIL_PASSWORD' => (string) ($mail['password'] ?? ''),
            'MAIL_ENCRYPTION' => (string) ($mail['encryption'] ?? ''),
            'MAIL_FROM_ADDRESS' => (string) $mail['from_address'],
            'WAYMARK_BACKUP_DISK' => (string) $advanced['backup_disk'],
            'WAYMARK_RELEASE_METADATA_URL' => (string) ($advanced['release_metadata_url'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $database */
    private function configureDatabase(array $database): void
    {
        $connection = $database['driver'] === 'mariadb' ? 'mysql' : (string) $database['driver'];

        if ($connection === 'sqlite') {
            config()->set('database.connections.sqlite.database', $database['database']);
        } else {
            config()->set('database.connections.mysql.host', $database['host']);
            config()->set('database.connections.mysql.port', $database['port']);
            config()->set('database.connections.mysql.database', $database['database']);
            config()->set('database.connections.mysql.username', $database['username']);
            config()->set('database.connections.mysql.password', $database['password']);
        }

        if (config('database.default') !== $connection) {
            config()->set('database.default', $connection);
            DB::purge($connection);
        }
    }
}
