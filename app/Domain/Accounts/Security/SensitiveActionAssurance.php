<?php

namespace App\Domain\Accounts\Security;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Date;

final class SensitiveActionAssurance
{
    public const PASSWORD_CONFIRMED_USER_ID = 'sensitive.password_confirmed_user_id';

    public const TWO_FACTOR_CONFIRMED_AT = 'sensitive.two_factor_confirmed_at';

    public const TWO_FACTOR_CONFIRMED_USER_ID = 'sensitive.two_factor_confirmed_user_id';

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
}
