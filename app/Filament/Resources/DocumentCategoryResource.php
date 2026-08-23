<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\DocumentCategory;
use App\Filament\Resources\DocumentCategoryResource\Pages\CreateDocumentCategory;
use App\Filament\Resources\DocumentCategoryResource\Pages\EditDocumentCategory;
use App\Filament\Resources\DocumentCategoryResource\Pages\ListDocumentCategories;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class DocumentCategoryResource extends Resource
{
    protected static ?string $model = DocumentCategory::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Group';

    protected static ?string $navigationLabel = 'Document categories';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required(), TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true), TextInput::make('sort_order')->numeric()->required(), Toggle::make('review_reminders_enabled')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('sort_order')->sortable(), IconColumn::make('review_reminders_enabled')->boolean()])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListDocumentCategories::route('/'), 'create' => CreateDocumentCategory::route('/create'), 'edit' => EditDocumentCategory::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageGovernance);
    }
}
