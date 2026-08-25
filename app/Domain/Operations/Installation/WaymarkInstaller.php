<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Accounts\Actions\EstablishInitialInstallationOwner;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Installation\Contracts\DatabaseConnectionTester;
use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class WaymarkInstaller
{
    private ?EnvironmentWriteResult $environmentResult = null;

    public function __construct(
        private readonly EnvironmentWriter $environmentWriter,
        private readonly DatabaseConnectionTester $databaseTester,
        private readonly UpdateSiteProfile $updateSiteProfile,
        private readonly EstablishInitialInstallationOwner $establishOwner,
        private readonly InstallationAttemptStore $attemptStore,
        private readonly InstallationDatabaseOwnership $databaseOwnership,
        private readonly SetupHealth $setupHealth,
        private readonly InstallationState $installationState,
    ) {}

    /** @param array<string, array<string, mixed>> $data */
    public function start(array $data): InstallationAttemptRecord
    {
        $existing = $this->attemptStore->load();

        if ($existing !== null) {
            return $existing;
        }

        $database = DatabaseConfiguration::fromArray($data['database']);
        $manifest = FreshInstallationSchema::load(resource_path('installation/fresh-schema.json'));
        $now = now()->toIso8601String();
        $record = InstallationAttemptRecord::start(
            id: (string) Str::ulid(),
            ownershipToken: bin2hex(random_bytes(32)),
            connectionFingerprint: $this->fingerprint($database),
            migrationSetHash: $manifest->migrationSetHash(),
            totalMigrations: count($manifest->migrationNames()),
            now: $now,
        );
        $this->attemptStore->save($record);

        return $record;
    }

    /** @param array<string, array<string, mixed>> $data */
    public function advance(array $data, string $applicationUrl): InstallationAttemptRecord
    {
        $record = $this->attemptStore->load();

        if ($record === null) {
            throw new RuntimeException('No installation attempt is ready to continue.');
        }

        if ($record->status !== InstallationAttemptStatus::Running) {
            return $record;
        }

        try {
            return match ($record->stage) {
                InstallationStage::PreparingConfiguration => $this->prepareConfiguration($record, $data, $applicationUrl),
                InstallationStage::CheckingDatabase => $this->checkDatabase($record, $data),
                InstallationStage::PreparingDatabaseSchema => $this->prepareDatabaseSchema($record, $data),
                InstallationStage::CreatingGroupSettings => $this->createGroupSettings($record, $data),
                InstallationStage::CreatingAdministrator => $this->createAdministrator($record, $data),
                InstallationStage::ApplyingOptionalConfiguration => $this->applyOptionalConfiguration($record),
                InstallationStage::RunningHealthChecks => $this->runHealthChecks($record, $data, $applicationUrl),
                InstallationStage::CompletingInstallation => $this->completeInstallation($record, $data),
            };
        } catch (Throwable $exception) {
            return $this->fail($record, $exception);
        }
    }

    public function environmentWriteResult(): ?EnvironmentWriteResult
    {
        return $this->environmentResult;
    }

    /** @param array<string, array<string, mixed>> $data */
    private function prepareConfiguration(InstallationAttemptRecord $record, array $data, string $applicationUrl): InstallationAttemptRecord
    {
        $environment = $this->environmentWriter->write($this->environmentValues($data, $applicationUrl));
        $this->environmentResult = $environment;

        if (! $environment->written) {
            throw new InstallationStageException(
                'configuration_file_could_not_be_written',
                'Waymark could not write the configuration file. Follow the displayed file instructions, then retry this step.',
                false,
            );
        }

        config()->set('cache.default', 'array');

        return $this->save($record->advance(InstallationStage::CheckingDatabase, 0, now()->toIso8601String()));
    }

    /** @param array<string, array<string, mixed>> $data */
    private function checkDatabase(InstallationAttemptRecord $record, array $data): InstallationAttemptRecord
    {
        $database = DatabaseConfiguration::fromArray($data['database']);
        $inspection = $this->databaseTester->test($database);

        if (! $inspection->successful || $inspection->state !== DatabaseInstallationState::Empty) {
            $category = $inspection->state === DatabaseInstallationState::Incomplete
                ? 'incomplete_waymark_installation_detected'
                : ($inspection->state === DatabaseInstallationState::Ambiguous ? 'database_not_empty_or_ambiguous' : 'database_connection_or_permissions_failed');

            throw new InstallationStageException($category, $inspection->message, false);
        }

        if (! hash_equals($record->connectionFingerprint, $this->fingerprint($database))) {
            throw new InstallationStageException('database_configuration_changed', 'The database settings changed after this installation attempt started. Return to database settings and re-check them.', false);
        }

        $this->databaseOwnership->claim($database, $record);
        $changed = $record->withChangedState(now()->toIso8601String());

        return $this->save($changed->advance(InstallationStage::PreparingDatabaseSchema, 0, now()->toIso8601String()));
    }

    /** @param array<string, array<string, mixed>> $data */
    private function prepareDatabaseSchema(InstallationAttemptRecord $record, array $data): InstallationAttemptRecord
    {
        $database = DatabaseConfiguration::fromArray($data['database']);
        $this->configureDatabase($database);
        $migrations = FreshInstallationSchema::load(resource_path('installation/fresh-schema.json'))->migrationNames();

        if ($record->migrationIndex >= count($migrations)) {
            return $this->save($record->advance(InstallationStage::CreatingGroupSettings, count($migrations), now()->toIso8601String()));
        }

        if ($record->migrationIndex === 0) {
            Artisan::call('migrate:install', ['--database' => config('database.default')]);
        }

        $migration = $migrations[$record->migrationIndex];
        Artisan::call('migrate', [
            '--database' => config('database.default'),
            '--path' => database_path('migrations/'.$migration.'.php'),
            '--realpath' => true,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $nextIndex = $record->migrationIndex + 1;
        $nextStage = $nextIndex >= count($migrations)
            ? InstallationStage::CreatingGroupSettings
            : InstallationStage::PreparingDatabaseSchema;

        return $this->save($record
            ->withChangedState(now()->toIso8601String())
            ->advance($nextStage, $nextIndex, now()->toIso8601String()));
    }

    /** @param array<string, array<string, mixed>> $data */
    private function createGroupSettings(InstallationAttemptRecord $record, array $data): InstallationAttemptRecord
    {
        $this->configureDatabase(DatabaseConfiguration::fromArray($data['database']));
        DB::transaction(function () use ($data): void {
            $existing = SiteProfile::query()->first();

            if ($existing !== null) {
                if (SiteProfile::query()->count() !== 1 || $existing->group_name !== $data['group-details']['group_name']) {
                    throw new RuntimeException('Existing group settings do not match this installation attempt.');
                }

                return;
            }

            $moduleConfiguration = [];
            foreach (['walks', 'socials', 'holidays', 'gallery', 'news', 'documents'] as $module) {
                $moduleConfiguration[$module] = in_array($module, $data['modules']['enabled'], true);
            }

            $this->updateSiteProfile->handle([
                ...$data['group-details'],
                ...$data['branding'],
                'module_configuration' => $moduleConfiguration,
            ]);
        });

        return $this->save($record->withChangedState(now()->toIso8601String())
            ->advance(InstallationStage::CreatingAdministrator, $record->migrationIndex, now()->toIso8601String()));
    }

    /** @param array<string, array<string, mixed>> $data */
    private function createAdministrator(InstallationAttemptRecord $record, array $data): InstallationAttemptRecord
    {
        $this->configureDatabase(DatabaseConfiguration::fromArray($data['database']));
        DB::transaction(function () use ($data): void {
            $existing = User::query()->first();

            if ($existing !== null) {
                if (User::query()->count() !== 1 || $existing->email !== $data['first-administrator']['email']) {
                    throw new RuntimeException('Existing administrator data does not match this installation attempt.');
                }

                return;
            }

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

        return $this->save($record->withChangedState(now()->toIso8601String())
            ->advance(InstallationStage::ApplyingOptionalConfiguration, $record->migrationIndex, now()->toIso8601String()));
    }

    private function applyOptionalConfiguration(InstallationAttemptRecord $record): InstallationAttemptRecord
    {
        return $this->save($record->advance(InstallationStage::RunningHealthChecks, $record->migrationIndex, now()->toIso8601String()));
    }

    /** @param array<string, array<string, mixed>> $data */
    private function runHealthChecks(InstallationAttemptRecord $record, array $data, string $applicationUrl): InstallationAttemptRecord
    {
        $this->configureDatabase(DatabaseConfiguration::fromArray($data['database']));

        if (! $this->setupHealth->ready($applicationUrl)) {
            throw new InstallationStageException('health_verification_failed', 'Final health verification failed. Review the failed checks before retrying.', true);
        }

        return $this->save($record->advance(InstallationStage::CompletingInstallation, $record->migrationIndex, now()->toIso8601String()));
    }

    /** @param array<string, array<string, mixed>> $data */
    private function completeInstallation(InstallationAttemptRecord $record, array $data): InstallationAttemptRecord
    {
        $database = DatabaseConfiguration::fromArray($data['database']);
        $this->databaseOwnership->release($database, $record);
        $this->installationState->complete();

        return $this->save($record->complete(now()->toIso8601String()));
    }

    private function fail(InstallationAttemptRecord $record, Throwable $exception): InstallationAttemptRecord
    {
        $diagnosticId = 'WM-'.strtoupper(bin2hex(random_bytes(4)));
        $category = $exception instanceof InstallationStageException ? $exception->category : $this->categoryFor($record->stage);
        $message = $exception instanceof InstallationStageException ? $exception->safeMessage : $this->messageFor($category);
        $changed = $exception instanceof InstallationStageException ? $exception->changed : $record->changed;

        Log::error('Waymark installation stage failed.', [
            'diagnostic_id' => $diagnosticId,
            'attempt_id' => $record->id,
            'stage' => $record->stage->value,
            'category' => $category,
            'exception' => $exception,
        ]);

        return $this->save($record->fail($category, $diagnosticId, $message, $changed, now()->toIso8601String()));
    }

    private function categoryFor(InstallationStage $stage): string
    {
        return match ($stage) {
            InstallationStage::PreparingConfiguration => 'configuration_file_could_not_be_written',
            InstallationStage::CheckingDatabase => 'database_connection_or_permissions_failed',
            InstallationStage::PreparingDatabaseSchema => 'database_schema_installation_failed',
            InstallationStage::CreatingGroupSettings => 'group_creation_failed',
            InstallationStage::CreatingAdministrator => 'administrator_creation_failed',
            InstallationStage::ApplyingOptionalConfiguration => 'optional_configuration_failed',
            InstallationStage::RunningHealthChecks => 'health_verification_failed',
            InstallationStage::CompletingInstallation => 'filesystem_or_completion_failure',
        };
    }

    private function messageFor(string $category): string
    {
        return match ($category) {
            'database_schema_installation_failed' => 'Database schema installation failed.',
            'group_creation_failed' => 'Creating the group settings failed.',
            'administrator_creation_failed' => 'Creating the first administrator failed.',
            'health_verification_failed' => 'Final health verification failed.',
            'configuration_file_could_not_be_written' => 'Waymark could not write the configuration file.',
            default => 'Waymark encountered an unexpected installation error.',
        };
    }

    private function save(InstallationAttemptRecord $record): InstallationAttemptRecord
    {
        $this->attemptStore->save($record);

        return $record;
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
            'SESSION_COOKIE' => 'waymark-community-session',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => (string) $mail['host'],
            'MAIL_PORT' => (string) $mail['port'],
            'MAIL_USERNAME' => (string) ($mail['username'] ?? ''),
            'MAIL_PASSWORD' => (string) ($mail['password'] ?? ''),
            'MAIL_ENCRYPTION' => (string) ($mail['encryption'] ?? ''),
            'MAIL_FROM_ADDRESS' => (string) $mail['from_address'],
            'WAYMARK_BACKUP_DISK' => $advanced['backup_disk'] === 's3' ? 's3' : 'backups',
            'AWS_ENDPOINT' => (string) ($advanced['s3_endpoint'] ?? ''),
            'AWS_BUCKET' => (string) ($advanced['s3_bucket'] ?? ''),
            'AWS_ACCESS_KEY_ID' => (string) ($advanced['s3_access_key'] ?? ''),
            'AWS_SECRET_ACCESS_KEY' => (string) ($advanced['s3_secret_key'] ?? ''),
            'AWS_USE_PATH_STYLE_ENDPOINT' => $advanced['backup_disk'] === 's3' ? 'true' : 'false',
            'WAYMARK_RECOVERY_TOKEN_HASH' => hash('sha256', (string) $advanced['recovery_token']),
            'WAYMARK_RELEASE_METADATA_URL' => (string) ($advanced['release_metadata_url'] ?? ''),
            'WAYMARK_RELEASE_PUBLIC_KEY_BASE64' => (string) ($advanced['release_public_key_base64'] ?? ''),
        ];
    }

    private function configureDatabase(DatabaseConfiguration $database): void
    {
        $connection = $database->driver === 'mariadb' ? 'mysql' : $database->driver;

        if ($connection === 'sqlite') {
            config()->set('database.connections.sqlite.database', $database->database);
        } else {
            config()->set('database.connections.mysql.host', $database->host);
            config()->set('database.connections.mysql.port', $database->port);
            config()->set('database.connections.mysql.database', $database->database);
            config()->set('database.connections.mysql.username', $database->username);
            config()->set('database.connections.mysql.password', $database->password);
        }

        config()->set('database.default', $connection);
        DB::purge($connection);
    }

    private function fingerprint(DatabaseConfiguration $database): string
    {
        return hash('sha256', implode('|', [
            $database->driver,
            strtolower($database->host),
            (string) ($database->port ?? ''),
            $database->database,
            $database->username,
        ]));
    }
}
