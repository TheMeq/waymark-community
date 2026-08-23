<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Models\Newsletter;
use App\Domain\Membership\Enums\AccountStatus;
use App\Filament\Resources\NewsletterResource\Pages\CreateNewsletter;
use App\Filament\Resources\NewsletterResource\Pages\EditNewsletter;
use App\Filament\Resources\NewsletterResource\Pages\ListNewsletters;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class NewsletterResource extends Resource
{
    protected static ?string $model = Newsletter::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('subject')->required()->maxLength(255),
            Textarea::make('body')->required()->rows(12),
            Select::make('status')->options(['draft' => 'Draft', 'scheduled' => 'Scheduled'])->required(),
            DateTimePicker::make('scheduled_for'),
            Select::make('audience_roles')->multiple()->options(collect(AccountRole::cases())->mapWithKeys(fn (AccountRole $role): array => [$role->value => ucfirst(str_replace('_', ' ', $role->value))])->all())->required(),
            Select::make('audience_account_statuses')->multiple()->options(collect(AccountStatus::cases())->mapWithKeys(fn (AccountStatus $status): array => [$status->value => ucfirst(str_replace('_', ' ', $status->value))])->all())->required(),
            Toggle::make('consent_required')->default(true),
            Select::make('consent_policy_version_id')->relationship('consentPolicyVersion', 'version_number')->required()->searchable()->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('scheduled_for')->dateTime(),
            TextColumn::make('sent_at')->dateTime(),
        ])->defaultSort('created_at', 'desc')->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function canViewAny(): bool { return self::canManage(); }
    public static function canCreate(): bool { return self::canManage(); }
    public static function canEdit($record): bool { return self::canManage(); }
    public static function canDelete($record): bool { return self::canManage(); }

    public static function getPages(): array
    {
        return ['index' => ListNewsletters::route('/'), 'create' => CreateNewsletter::route('/create'), 'edit' => EditNewsletter::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageCommunications);
    }
}
