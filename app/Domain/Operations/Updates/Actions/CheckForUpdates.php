<?php

namespace App\Domain\Operations\Updates\Actions;

use App\Domain\Operations\Updates\Contracts\UpdateEnvironmentProbe;
use App\Domain\Operations\Updates\ReleaseMetadataClient;
use App\Domain\Operations\Updates\UpdateCheckResult;
use App\Domain\Operations\Updates\UpdateCompatibilityChecker;
use App\Domain\Operations\Updates\UpdateStateStore;
use RuntimeException;
use Throwable;

final readonly class CheckForUpdates
{
    public function __construct(
        private ReleaseMetadataClient $client,
        private UpdateEnvironmentProbe $environment,
        private UpdateCompatibilityChecker $compatibility,
        private UpdateStateStore $state,
    ) {}

    public function handle(string $trigger): UpdateCheckResult
    {
        try {
            $metadata = $this->client->fetch();
            $compatibility = $this->compatibility->check($metadata, $this->environment->capture());
            $currentVersion = (string) config('waymark.version', 'development');
            $updateAvailable = preg_match('/^\d+\.\d+\.\d+$/', $currentVersion) === 1
                && version_compare($metadata->version, $currentVersion, '>');
            $this->state->write([
                'status' => 'checked',
                'checked_at' => now('UTC')->toIso8601String(),
                'trigger' => $trigger,
                'current_version' => $currentVersion,
                'update_available' => $updateAvailable,
                'metadata' => $metadata->toArray(),
                'compatibility' => [
                    'compatible' => $compatibility->compatible(),
                    'checks' => $compatibility->checks,
                ],
            ]);

            return new UpdateCheckResult($metadata, $compatibility, $updateAvailable);
        } catch (Throwable $exception) {
            try {
                $this->state->write([
                    'status' => 'failed',
                    'checked_at' => now('UTC')->toIso8601String(),
                    'trigger' => $trigger,
                    'message' => 'The stable release check failed. No update information was accepted.',
                ]);
            } catch (Throwable) {
                // Preserve the original verification/retrieval failure.
            }

            throw new RuntimeException('The stable release check failed. No update information was accepted.', previous: $exception);
        }
    }
}
