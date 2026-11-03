<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Notifications\AdministratorAccessChanged;
use App\Domain\Communication\Support\OutboundEmailStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class CreateAdministrator
{
    public function __construct(
        private RecordAccountAdministrationAudit $audit,
        private OutboundEmailStatus $emailStatus,
    ) {}

    public function handle(User $actor, string $name, string $email): User
    {
        Gate::forUser($actor)->authorize('createAdministrator', User::class);

        if (! $this->emailStatus->configured()) {
            throw ValidationException::withMessages([
                'email' => $this->emailStatus->unavailableMessage(),
            ]);
        }

        $data = validator([
            'name' => $name,
            'email' => $email,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
        ])->validate();

        [$created, $recipients] = DB::transaction(function () use ($actor, $data): array {
            Gate::forUser($actor)->authorize('createAdministrator', User::class);
            $recipients = $this->existingAdministrators();
            $created = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
                'email_verified_at' => null,
            ]);
            $created->forceFill(['role' => AccountRole::Administrator])->save();
            $this->audit->handle($actor, $created, 'administrator_created');

            return [$created, $recipients];
        });

        Password::sendResetLink(['email' => $created->email]);
        $created->sendEmailVerificationNotification();

        foreach ($recipients as $recipient) {
            $recipient->notify(new AdministratorAccessChanged($created, 'created'));
        }

        return $created;
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
