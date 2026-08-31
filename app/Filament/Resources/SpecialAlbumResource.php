<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Actions\DeleteSpecialAlbum;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Filament\Resources\SpecialAlbumResource\Pages\CreateSpecialAlbum;
use App\Filament\Resources\SpecialAlbumResource\Pages\EditSpecialAlbum;
use App\Filament\Resources\SpecialAlbumResource\Pages\ListSpecialAlbums;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class SpecialAlbumResource extends Resource
{
    protected static ?string $model = SpecialAlbum::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Special albums';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
            Textarea::make('description')->rows(4)->maxLength(5000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->sortable(),
                TextColumn::make('description')->limit(80),
                TextColumn::make('photos_count')->counts('photos')->label('Photos'),
            ])
            ->defaultSort('title')
            ->recordActions([
                EditAction::make(),
                self::deleteAction(),
            ]);
    }

    public static function canViewAny(): bool
    {
        return self::actorCanManage();
    }

    public static function canCreate(): bool
    {
        return self::actorCanManage();
    }

    public static function canEdit($record): bool
    {
        return self::actorCanManage();
    }

    public static function canDelete($record): bool
    {
        return self::actorCanManage();
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->disabled(fn (SpecialAlbum $record): bool => $record->photos()->exists())
            ->tooltip(fn (SpecialAlbum $record): ?string => $record->photos()->exists() ? 'Remove referenced photos before deleting this album.' : null)
            ->using(function (SpecialAlbum $record): bool {
                /** @var User $actor */
                $actor = auth()->user();

                return app(DeleteSpecialAlbum::class)->handle($actor, $record);
            });
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListSpecialAlbums::route('/'),
            'create' => CreateSpecialAlbum::route('/create'),
            'edit' => EditSpecialAlbum::route('/{record}/edit'),
        ];
    }

    private static function actorCanManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageSpecialAlbums);
    }
}
