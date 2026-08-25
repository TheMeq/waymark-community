<?php

namespace App\Domain\Operations\Installation;

use InvalidArgumentException;

final readonly class InstallationAttemptRecord
{
    public function __construct(
        public string $id,
        public string $ownershipToken,
        public string $connectionFingerprint,
        public string $migrationSetHash,
        public InstallationAttemptStatus $status,
        public InstallationStage $stage,
        public int $migrationIndex,
        public int $totalMigrations,
        public ?string $diagnosticId,
        public ?string $failureCategory,
        public string $message,
        public bool $changed,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function start(
        string $id,
        string $ownershipToken,
        string $connectionFingerprint,
        string $migrationSetHash,
        int $totalMigrations,
        string $now,
    ): self {
        return new self(
            $id,
            $ownershipToken,
            $connectionFingerprint,
            $migrationSetHash,
            InstallationAttemptStatus::Running,
            InstallationStage::PreparingConfiguration,
            0,
            $totalMigrations,
            null,
            null,
            'Installation is ready to begin.',
            false,
            $now,
            $now,
        );
    }

    public function advance(InstallationStage $stage, int $migrationIndex, string $now, string $message = ''): self
    {
        return new self(
            $this->id,
            $this->ownershipToken,
            $this->connectionFingerprint,
            $this->migrationSetHash,
            InstallationAttemptStatus::Running,
            $stage,
            max(0, min($this->totalMigrations, $migrationIndex)),
            $this->totalMigrations,
            null,
            null,
            $message !== '' ? $message : $stage->label().'.',
            $this->changed,
            $this->createdAt,
            $now,
        );
    }

    public function withChangedState(string $now): self
    {
        return new self(
            $this->id,
            $this->ownershipToken,
            $this->connectionFingerprint,
            $this->migrationSetHash,
            $this->status,
            $this->stage,
            $this->migrationIndex,
            $this->totalMigrations,
            $this->diagnosticId,
            $this->failureCategory,
            $this->message,
            true,
            $this->createdAt,
            $now,
        );
    }

    public function fail(string $category, string $diagnosticId, string $message, bool $changed, string $now): self
    {
        return new self(
            $this->id,
            $this->ownershipToken,
            $this->connectionFingerprint,
            $this->migrationSetHash,
            InstallationAttemptStatus::Failed,
            $this->stage,
            $this->migrationIndex,
            $this->totalMigrations,
            $diagnosticId,
            $category,
            $message,
            $changed,
            $this->createdAt,
            $now,
        );
    }

    public function complete(string $now): self
    {
        return new self(
            $this->id,
            $this->ownershipToken,
            $this->connectionFingerprint,
            $this->migrationSetHash,
            InstallationAttemptStatus::Completed,
            InstallationStage::CompletingInstallation,
            $this->totalMigrations,
            $this->totalMigrations,
            null,
            null,
            'Waymark Community was installed successfully.',
            true,
            $this->createdAt,
            $now,
        );
    }

    /** @return array<string, int|string|bool|null> */
    public function toArray(): array
    {
        return [
            'format' => 1,
            'id' => $this->id,
            'ownership_token' => $this->ownershipToken,
            'connection_fingerprint' => $this->connectionFingerprint,
            'migration_set_hash' => $this->migrationSetHash,
            'status' => $this->status->value,
            'stage' => $this->stage->value,
            'migration_index' => $this->migrationIndex,
            'total_migrations' => $this->totalMigrations,
            'diagnostic_id' => $this->diagnosticId,
            'failure_category' => $this->failureCategory,
            'message' => $this->message,
            'changed' => $this->changed,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        if (($values['format'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported installation-attempt format.');
        }

        return new self(
            (string) $values['id'],
            (string) $values['ownership_token'],
            (string) $values['connection_fingerprint'],
            (string) $values['migration_set_hash'],
            InstallationAttemptStatus::from((string) $values['status']),
            InstallationStage::from((string) $values['stage']),
            (int) $values['migration_index'],
            (int) $values['total_migrations'],
            isset($values['diagnostic_id']) ? (string) $values['diagnostic_id'] : null,
            isset($values['failure_category']) ? (string) $values['failure_category'] : null,
            (string) $values['message'],
            (bool) $values['changed'],
            (string) $values['created_at'],
            (string) $values['updated_at'],
        );
    }
}
