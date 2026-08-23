<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Models\ContactDepartment;
use App\Filament\Resources\ContactDepartmentResource\Pages\CreateContactDepartment;
use App\Filament\Resources\ContactDepartmentResource\Pages\EditContactDepartment;
use App\Filament\Resources\ContactDepartmentResource\Pages\ListContactDepartments;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ContactDepartmentResource extends Resource
{
    protected static ?string $model = ContactDepartment::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Group';

    protected static ?string $navigationLabel = 'Contact departments';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('public_label')->required(), TextInput::make('destination_email')->email()->required(), Textarea::make('description'), KeyValue::make('routing_rules'), Toggle::make('show_address_publicly'), Toggle::make('active')->default(true), TextInput::make('sort_order')->numeric()->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('public_label')->searchable(), TextColumn::make('destination_email'), TextColumn::make('sort_order')->sortable(), IconColumn::make('active')->boolean()])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListContactDepartments::route('/'), 'create' => CreateContactDepartment::route('/create'), 'edit' => EditContactDepartment::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageCommunications);
    }
}
