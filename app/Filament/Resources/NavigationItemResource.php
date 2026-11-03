<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\NavigationItem;
use App\Filament\Resources\NavigationItemResource\Pages\CreateNavigationItem;
use App\Filament\Resources\NavigationItemResource\Pages\EditNavigationItem;
use App\Filament\Resources\NavigationItemResource\Pages\ListNavigationItems;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Navigation';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->required()->maxLength(100), TextInput::make('url')->required()->maxLength(2048),
            TextInput::make('sort_order')->numeric()->minValue(0)->required(), Toggle::make('enabled')->default(true),
            Select::make('module_key')->options(['walks' => 'Walks', 'socials' => 'Socials', 'holidays' => 'Holidays', 'gallery' => 'Gallery', 'news' => 'News'])->nullable(),
            Toggle::make('open_in_new_tab'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('label')->searchable(), TextColumn::make('url'), TextColumn::make('sort_order')->sortable(), IconColumn::make('enabled')->boolean()])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListNavigationItems::route('/'), 'create' => CreateNavigationItem::route('/create'), 'edit' => EditNavigationItem::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }
}
