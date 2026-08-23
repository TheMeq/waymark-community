<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\PublicRedirect;
use App\Filament\Resources\PublicRedirectResource\Pages\CreatePublicRedirect;
use App\Filament\Resources\PublicRedirectResource\Pages\EditPublicRedirect;
use App\Filament\Resources\PublicRedirectResource\Pages\ListPublicRedirects;
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

final class PublicRedirectResource extends Resource
{
    protected static ?string $model = PublicRedirect::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Redirects';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('source_path')->required()->maxLength(1024)->placeholder('/old-page'),
            TextInput::make('target_url')->required()->maxLength(2048)->placeholder('/new-page'),
            Select::make('status_code')->options([301 => 'Permanent (301)', 302 => 'Temporary (302)'])->required()->default(301),
            Toggle::make('enabled')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('source_path')->searchable(), TextColumn::make('target_url')->searchable(),
            TextColumn::make('status_code')->label('Status'), IconColumn::make('enabled')->boolean(), IconColumn::make('automatic')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListPublicRedirects::route('/'), 'create' => CreatePublicRedirect::route('/create'), 'edit' => EditPublicRedirect::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }
}
