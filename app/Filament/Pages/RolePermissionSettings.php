<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Actions\ConfigureRoleCapabilityMatrix;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class RolePermissionSettings extends Page
{
    protected static ?string $title = 'Role permissions';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Role permissions';

    protected static string|array $routeMiddleware = ['sensitive.confirmed'];

    protected string $view = 'filament.pages.role-permission-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'role-permissions';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManagePermissions);
    }

    public function mount(): void
    {
        $this->form->fill([
            'roles' => collect(AccountRole::cases())
                ->mapWithKeys(fn (AccountRole $role): array => [
                    $role->value => RoleCapability::query()
                        ->where('role', $role->value)
                        ->orderBy('capability')
                        ->get()
                        ->map(static fn (RoleCapability $assignment): string => $assignment->capability->value)
                        ->all(),
                ])
                ->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                fn (AccountRole $role): Section => Section::make($this->roleLabel($role))
                    ->schema([
                        CheckboxList::make('roles.'.$role->value)
                            ->label('Enabled capabilities')
                            ->options($this->capabilityOptions($role))
                            ->columns(2),
                    ]),
                AccountRole::cases(),
            ))
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->ensureSensitiveAssurance($user);
        $roles = $this->form->getState()['roles'] ?? [];

        app(ConfigureRoleCapabilityMatrix::class)->handle($user, $roles);

        Notification::make()
            ->success()
            ->title('Role permissions saved')
            ->send();
    }

    private function ensureSensitiveAssurance(User $user): void
    {
        $assurance = app(SensitiveActionAssurance::class);
        $session = app('session.store');

        abort_unless($assurance->hasRecentPasswordConfirmation($user, $session), 403);
        abort_unless(
            ! $assurance->requiresSecondFactor($user)
                || $assurance->hasRecentSecondFactorConfirmation($user, $session),
            403,
        );
    }

    /** @return array<string, string> */
    private function capabilityOptions(AccountRole $role): array
    {
        return collect(ModuleCapability::cases())
            ->filter(static fn (ModuleCapability $capability): bool => $role === AccountRole::Administrator || $capability !== ModuleCapability::ManagePermissions)
            ->mapWithKeys(fn (ModuleCapability $capability): array => [
                $capability->value => str($capability->value)->after('.')->replace('_', ' ')->title()->toString(),
            ])
            ->all();
    }

    private function roleLabel(AccountRole $role): string
    {
        return str($role->value)->replace('_', ' ')->title()->toString();
    }
}
