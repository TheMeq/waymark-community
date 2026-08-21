<?php

namespace App\Domain\Accounts\Security;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

final class SensitiveActionAssurance
{
    public const PASSWORD_CONFIRMED_USER_ID = 'sensitive.password_confirmed_user_id';

    public const TWO_FACTOR_CONFIRMED_AT = 'sensitive.two_factor_confirmed_at';

    public const TWO_FACTOR_CONFIRMED_USER_ID = 'sensitive.two_factor_confirmed_user_id';

    public const INTENDED_DESTINATION = 'sensitive.intended_destination';

    public function recordPasswordConfirmation(User $user, Session $session): void
    {
        $session->put(self::PASSWORD_CONFIRMED_USER_ID, $user->getKey());
        $this->forgetSecondFactorConfirmation($session);
    }

    public function recordSecondFactorConfirmation(User $user, Session $session): void
    {
        $session->put([
            self::TWO_FACTOR_CONFIRMED_AT => Date::now()->unix(),
            self::TWO_FACTOR_CONFIRMED_USER_ID => $user->getKey(),
        ]);
    }

    public function hasRecentPasswordConfirmation(User $user, Session $session): bool
    {
        return $this->belongsTo($user, $session->get(self::PASSWORD_CONFIRMED_USER_ID))
            && $this->isRecent($session->get('auth.password_confirmed_at'));
    }

    public function hasRecentSecondFactorConfirmation(User $user, Session $session): bool
    {
        return $this->belongsTo($user, $session->get(self::TWO_FACTOR_CONFIRMED_USER_ID))
            && $this->isRecent($session->get(self::TWO_FACTOR_CONFIRMED_AT));
    }

    public function requiresSecondFactor(User $user): bool
    {
        return $user->hasEnabledTwoFactorAuthentication();
    }

    public function captureIntendedDestination(Request $request): void
    {
        $request->session()->forget([self::INTENDED_DESTINATION, 'url.intended']);

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return;
        }

        $destination = $request->fullUrl();

        if ($this->isSafeDestination($destination, $request)) {
            $request->session()->put(self::INTENDED_DESTINATION, $destination);
        }
    }

    public function consumeIntendedDestination(Request $request, string $fallback): string
    {
        $destination = $request->session()->pull(self::INTENDED_DESTINATION);
        $request->session()->forget('url.intended');

        return is_string($destination) && $this->isSafeDestination($destination, $request)
            ? $destination
            : $fallback;
    }

    public function forgetSecondFactorConfirmation(Session $session): void
    {
        $session->forget([
            self::TWO_FACTOR_CONFIRMED_AT,
            self::TWO_FACTOR_CONFIRMED_USER_ID,
        ]);
    }

    public function forgetAll(Session $session): void
    {
        $session->forget([
            'auth.password_confirmed_at',
            self::PASSWORD_CONFIRMED_USER_ID,
            self::TWO_FACTOR_CONFIRMED_AT,
            self::TWO_FACTOR_CONFIRMED_USER_ID,
            self::INTENDED_DESTINATION,
            'url.intended',
        ]);
    }

    private function belongsTo(User $user, mixed $confirmedUserId): bool
    {
        return is_scalar($confirmedUserId)
            && hash_equals((string) $user->getKey(), (string) $confirmedUserId);
    }

    private function isRecent(mixed $timestamp): bool
    {
        if (! is_numeric($timestamp)) {
            return false;
        }

        $age = Date::now()->unix() - (int) $timestamp;

        return $age >= 0 && $age <= (int) config('security.sensitive_action_timeout');
    }

    private function isSafeDestination(string $destination, Request $request): bool
    {
        $destinationParts = parse_url($destination);
        $requestParts = parse_url($request->root());

        if (! is_array($destinationParts) || ! is_array($requestParts)
            || ! isset($destinationParts['scheme'], $destinationParts['host'], $requestParts['scheme'], $requestParts['host'])) {
            return false;
        }

        $sameOrigin = strcasecmp($destinationParts['scheme'], $requestParts['scheme']) === 0
            && strcasecmp($destinationParts['host'], $requestParts['host']) === 0
            && $this->portFor($destinationParts) === $this->portFor($requestParts);

        if (! $sameOrigin
            || array_key_exists('user', $destinationParts)
            || array_key_exists('pass', $destinationParts)) {
            return false;
        }

        $path = $this->canonicalPath($destinationParts['path'] ?? '/');
        $passwordConfirmationPath = $this->canonicalPath((string) parse_url(route('password.confirm'), PHP_URL_PATH));
        $sensitiveConfirmationPath = $this->canonicalPath((string) parse_url(route('account.sensitive-confirmation.create'), PHP_URL_PATH));

        return $path !== null
            && $path !== $passwordConfirmationPath
            && $path !== $sensitiveConfirmationPath;
    }

    /** @param array<string, int|string> $parts */
    private function portFor(array $parts): int
    {
        if (isset($parts['port'])) {
            return (int) $parts['port'];
        }

        return strtolower((string) $parts['scheme']) === 'https' ? 443 : 80;
    }

    private function canonicalPath(string $path): ?string
    {
        $decodedPath = $path;

        for ($decodingPass = 0; $decodingPass < 2; $decodingPass++) {
            $nextPath = rawurldecode($decodedPath);

            if ($nextPath === $decodedPath) {
                break;
            }

            $decodedPath = $nextPath;
        }

        if (str_contains($decodedPath, '\\') || str_starts_with($decodedPath, '//')) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $decodedPath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }
}
