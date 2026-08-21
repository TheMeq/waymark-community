<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Notifications\AdministratorAccessChanged;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class PromoteToAdministrator
{
    public function __construct(private RecordAccountAdministrationAudit $audit) {}

    public function handle(User $actor, User $account): User
    {
        if ($actor->is($account)) {
            throw ValidationException::withMessages([
                'account' => 'You cannot promote your own account.',
            ]);
        }

        Gate::forUser($actor)->authorize('promoteAdministrator', $account);

        [$account, $recipients] = DB::transaction(function () use ($actor, $account): array {
            $account = User::query()->lockForUpdate()->findOrFail($account->getKey());
            Gate::forUser($actor)->authorize('promoteAdministrator', $account);

            if (! $account->isActive() || ! $account->hasVerifiedEmail() || $account->role === AccountRole::Administrator) {
                throw ValidationException::withMessages([
                    'account' => 'Choose an active, verified account that is not already an administrator.',
                ]);
            }

            $recipients = $this->existingAdministrators();
            $account->forceFill(['role' => AccountRole::Administrator])->save();
            $this->audit->handle($actor, $account, 'administrator_promoted');

            return [$account->fresh(), $recipients];
        });

        foreach ($recipients as $recipient) {
            $recipient->notify(new AdministratorAccessChanged($account, 'promoted'));
        }

        return $account;
    }

    /** @return array<int, User> */
    private function existingAdministrators(): array
    {
        return User::query()
            ->where('account_status', 'active')
            ->where('role', AccountRole::Administrator->value)
            ->get()
            ->all();
    }
}
