<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\Document;
use App\Filament\Resources\DocumentResource\Pages\CreateDocument;
use App\Filament\Resources\DocumentResource\Pages\EditDocument;
use App\Filament\Resources\DocumentResource\Pages\ListDocuments;
use App\Filament\Resources\DocumentResource\RelationManagers\VersionsRelationManager;
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

final class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Group';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('document_category_id')->relationship('category', 'name')->required()->preload(), TextInput::make('title')->required(), TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true), Textarea::make('description'), Select::make('visibility')->options(['public' => 'Public', 'registered' => 'Registered users', 'leader' => 'Walk leaders', 'committee' => 'Committee'])->required(), DatePicker::make('publication_date'), Toggle::make('public_version_history'), Toggle::make('controlled'), Select::make('approval_status')->options(['draft' => 'Draft', 'approved' => 'Approved'])->required(), DatePicker::make('review_date'), Toggle::make('review_email_reminder')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable(), TextColumn::make('category.name'), TextColumn::make('visibility')->badge(), TextColumn::make('approval_status')->badge(), IconColumn::make('controlled')->boolean(), TextColumn::make('review_date')->date()])->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListDocuments::route('/'), 'create' => CreateDocument::route('/create'), 'edit' => EditDocument::route('/{record}/edit')];
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageGovernance);
    }
}
