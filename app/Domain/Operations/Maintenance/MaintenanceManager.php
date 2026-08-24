<?php

namespace App\Domain\Operations\Maintenance;

use App\Domain\Operations\Models\SiteProfile;
use DateTimeInterface;
use RuntimeException;
use Throwable;

final class MaintenanceManager
{
    public const string BYPASS_COOKIE = 'waymark_maintenance_bypass';

    public function active(): bool
    {
        return $this->state() !== null;
    }

    /** @return array<string, mixed>|null */
    public function state(): ?array
    {
        $contents = @file_get_contents($this->path());
        $state = is_string($contents) ? json_decode($contents, true) : null;

        return is_array($state) && ($state['format'] ?? null) === 1 ? $state : null;
    }

    public function enable(
        string $message,
        ?DateTimeInterface $expectedReturnAt = null,
        ?string $contactUrl = null,
    ): string {
        $profile = null;
        try {
            $profile = SiteProfile::query()->first();
        } catch (Throwable) {
            // Maintenance state must remain writable when the database is unavailable.
        }

        $token = random_bytes(32);
        $state = [
            'format' => 1,
            'enabled_at' => now('UTC')->toIso8601String(),
            'message' => trim($message) !== '' ? trim($message) : 'We are carrying out essential maintenance. Please check back shortly.',
            'expected_return_at' => $expectedReturnAt?->format(DATE_ATOM),
            'contact_url' => $contactUrl,
            'group_name' => $profile?->group_name ?? 'Waymark Community',
            'primary_colour' => $profile?->primary_colour ?? '#526B3F',
            'accent_colour' => $profile?->accent_colour ?? '#D97845',
            'bypass_token_hash' => hash('sha256', $token),
        ];
        $this->write($state);

        return $this->signedToken($token);
    }

    public function disable(): void
    {
        @unlink($this->path());
    }

    public function validBypass(?string $cookie): bool
    {
        $state = $this->state();
        if ($state === null || ! is_string($cookie) || ! str_contains($cookie, '.')) {
            return false;
        }
        [$encoded, $signature] = explode('.', $cookie, 2);
        $token = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (! is_string($token)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $encoded, $this->signingKey()), $signature)
            && hash_equals((string) $state['bypass_token_hash'], hash('sha256', $token));
    }

    public function run(callable $operation, string $message): mixed
    {
        $alreadyActive = $this->active();
        if (! $alreadyActive) {
            $this->enable($message);
        }

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            throw $exception;
        }

        if (! $alreadyActive) {
            $this->disable();
        }

        return $result;
    }

    /** @param array<string, mixed> $state */
    private function write(array $state): void
    {
        $path = $this->path();
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Maintenance mode could not create its private state directory.');
        }
        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $temporary = $path.'.tmp';
        if (! is_string($encoded) || file_put_contents($temporary, $encoded."\n", LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Maintenance mode could not be enabled.');
        }
        @chmod($path, 0600);
    }

    private function signedToken(string $token): string
    {
        $encoded = rtrim(strtr(base64_encode($token), '+/', '-_'), '=');

        return $encoded.'.'.hash_hmac('sha256', $encoded, $this->signingKey());
    }

    private function signingKey(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (is_string($decoded)) {
                return $decoded;
            }
        }

        return $key;
    }

    private function path(): string
    {
        return (string) config('waymark.maintenance.state_path', storage_path('framework/waymark-maintenance.json'));
    }
}
