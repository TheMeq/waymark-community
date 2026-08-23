<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\FooterSection;
use App\Filament\Resources\FooterSectionResource\Pages\CreateFooterSection;
use App\Filament\Resources\FooterSectionResource\Pages\EditFooterSection;
use App\Filament\Resources\FooterSectionResource\Pages\ListFooterSections;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class FooterSectionResource extends Resource
{
    protected static ?string $model = FooterSection::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Footer';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('section_key')->options(['explore' => 'Explore', 'resources' => 'Resources', 'legal' => 'Legal', 'contact' => 'Contact', 'social' => 'Social'])->required()->unique(ignoreRecord: true),
            TextInput::make('heading')->maxLength(100), TextInput::make('sort_order')->numeric()->required(), Toggle::make('enabled')->default(true),
            Repeater::make('links')->maxItems(8)->schema([TextInput::make('label')->required(), TextInput::make('url')->required()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('section_key'), TextColumn::make('heading'), TextColumn::make('sort_order')->sortable(), IconColumn::make('enabled')->boolean()])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListFooterSections::route('/'), 'create' => CreateFooterSection::route('/create'), 'edit' => EditFooterSection::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }
}
