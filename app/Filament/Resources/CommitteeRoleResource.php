<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\CommitteeRole;
use App\Filament\Resources\CommitteeRoleResource\Pages\CreateCommitteeRole;
use App\Filament\Resources\CommitteeRoleResource\Pages\EditCommitteeRole;
use App\Filament\Resources\CommitteeRoleResource\Pages\ListCommitteeRoles;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CommitteeRoleResource extends Resource
{
    protected static ?string $model = CommitteeRole::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Group';

    protected static ?string $navigationLabel = 'Committee';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('title')->required(), Select::make('person_id')->relationship('person', 'name')->searchable()->preload(), TextInput::make('public_name'), TextInput::make('sort_order')->numeric()->required(), DatePicker::make('start_date'), DatePicker::make('end_date'), Toggle::make('active')->default(true), Toggle::make('publicly_visible')->default(true), Select::make('public_photo_media_id')->relationship('publicPhotoMedia', 'alt_text')->searchable()->preload(), Textarea::make('public_details'), TextInput::make('private_email')->email(), TextInput::make('private_phone'), Textarea::make('private_notes')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable(), TextColumn::make('public_name'), TextColumn::make('sort_order')->sortable(), IconColumn::make('active')->boolean(), IconColumn::make('publicly_visible')->boolean()])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return self::canManage();
    }

    public static function canCreate(): bool
    {
        return self::canManage();
    }

    public static function canEdit($record): bool
    {
        return self::canManage();
    }

    public static function canDelete($record): bool
    {
        return self::canManage();
    }

    public static function getPages(): array
    {
        return ['index' => ListCommitteeRoles::route('/'), 'create' => CreateCommitteeRole::route('/create'), 'edit' => EditCommitteeRole::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageGovernance);
    }
}
