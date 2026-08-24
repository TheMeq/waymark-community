<?php

declare(strict_types=1);

final class WaymarkEmergencyUpdateRollback
{
    public static function restore(string $statePath, string $token): void
    {
        $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : null;
        $status = is_array($state) ? ($state['status'] ?? null) : null;
        if (! is_array($state) || ($state['format'] ?? null) !== 1 || ! in_array($status, ['pending_activation', 'pending_rollback'], true)
            || ! is_string($state['activation_token_hash'] ?? null)
            || ! hash_equals($state['activation_token_hash'], hash('sha256', $token))) {
            throw new RuntimeException('Emergency update rollback could not be authorised.');
        }

        $applicationRoot = realpath((string) ($state['application_root'] ?? ''));
        $rollbackDirectory = realpath((string) ($state['rollback_directory'] ?? ''));
        $records = $state['rollback_records'] ?? null;
        if (! is_string($applicationRoot) || ! is_string($rollbackDirectory) || ! is_array($records)) {
            throw new RuntimeException('Emergency update rollback state is invalid.');
        }

        foreach (array_reverse($records) as $record) {
            $path = is_array($record) ? ($record['path'] ?? null) : null;
            if (! is_string($path) || $path === '' || str_contains($path, '\\') || str_starts_with($path, '/')
                || array_intersect(explode('/', $path), ['', '.', '..']) !== [] || ! is_bool($record['existed'] ?? null)) {
                throw new RuntimeException('Emergency update rollback state is invalid.');
            }
            $target = $applicationRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
            if ($record['existed']) {
                $snapshot = $rollbackDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
                if (! is_file($snapshot) || (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target)))) {
                    throw new RuntimeException('Emergency update rollback could not restore an application file.');
                }
                $temporary = $target.'.waymark-emergency-'.bin2hex(random_bytes(4));
                if (! copy($snapshot, $temporary) || ! rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('Emergency update rollback could not restore an application file.');
                }
                if (is_int($record['permissions'] ?? null)) {
                    @chmod($target, $record['permissions']);
                }
            } elseif (is_file($target) && ! unlink($target)) {
                throw new RuntimeException('Emergency update rollback could not remove an added application file.');
            }
        }

        $state['status'] = $status === 'pending_activation' ? 'boot_rollback_completed' : 'pending_rollback';
        $state['boot_rollback_completed_at'] = gmdate('c');
        $state['emergency_application_files_restored'] = true;
        $state['message'] = 'The previous application files were restored. Maintenance remains active; use Waymark recovery to verify the installation.';
        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $temporaryState = $statePath.'.tmp';
        if (! is_string($encoded) || file_put_contents($temporaryState, $encoded."\n", LOCK_EX) === false || ! rename($temporaryState, $statePath)) {
            @unlink($temporaryState);
            throw new RuntimeException('Emergency update rollback state could not be recorded.');
        }
        @chmod($statePath, 0600);
    }
}
