<?php

namespace App\Domain\Operations\Updates;

final class UpdateCompatibilityChecker
{
    public function check(ReleaseMetadata $metadata, UpdateEnvironment $environment): UpdateCompatibilityReport
    {
        $requirements = $metadata->requirements;
        $missingExtensions = array_values(array_diff($requirements['extensions'], array_map('strtolower', $environment->extensions)));
        $databaseMinimum = $requirements['database'][$environment->databaseFamily] ?? null;
        $databaseLabel = $environment->databaseFamily === 'mariadb' ? 'MariaDB' : ($environment->databaseFamily === 'mysql' ? 'MySQL' : ucfirst($environment->databaseFamily));

        return new UpdateCompatibilityReport([
            [
                'key' => 'php', 'label' => 'PHP',
                'status' => version_compare($environment->phpVersion, $requirements['php'], '>=') ? 'pass' : 'blocker',
                'message' => version_compare($environment->phpVersion, $requirements['php'], '>=')
                    ? 'PHP '.$environment->phpVersion.' meets the release requirement.'
                    : 'PHP '.$requirements['php'].' or newer is required; this host runs '.$environment->phpVersion.'.',
            ],
            [
                'key' => 'extensions', 'label' => 'PHP extensions',
                'status' => $missingExtensions === [] ? 'pass' : 'blocker',
                'message' => $missingExtensions === [] ? 'All release PHP extensions are available.' : 'Ask the host to enable: '.implode(', ', $missingExtensions).'.',
            ],
            [
                'key' => 'database', 'label' => 'Database',
                'status' => is_string($databaseMinimum) && version_compare($environment->databaseVersion, $databaseMinimum, '>=') ? 'pass' : 'blocker',
                'message' => is_string($databaseMinimum) && version_compare($environment->databaseVersion, $databaseMinimum, '>=')
                    ? $databaseLabel.' '.$environment->databaseVersion.' meets the release requirement.'
                    : (is_string($databaseMinimum)
                        ? $databaseLabel.' '.$databaseMinimum.' or newer is required; this host runs '.$environment->databaseVersion.'.'
                        : $databaseLabel.' is not supported by this release.'),
            ],
            [
                'key' => 'disk', 'label' => 'Disk space',
                'status' => $environment->diskFreeBytes >= $requirements['disk_free_bytes'] ? 'pass' : 'blocker',
                'message' => $environment->diskFreeBytes >= $requirements['disk_free_bytes']
                    ? 'Enough disk space is available for staging and rollback.'
                    : 'More free disk space is required before this release can be staged safely.',
            ],
        ]);
    }
}
