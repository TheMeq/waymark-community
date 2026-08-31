<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\NewsArticle;
use App\Filament\Resources\NewsArticleResource\Pages\CreateNewsArticle;
use App\Filament\Resources\NewsArticleResource\Pages\EditNewsArticle;
use App\Filament\Resources\NewsArticleResource\Pages\ListNewsArticles;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'News';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255), TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true), Textarea::make('summary')->maxLength(1000),
            Builder::make('blocks')->required()->blocks([Block::make('rich_text')->schema([RichEditor::make('content')->required()]), Block::make('callout')->schema([TextInput::make('heading'), Textarea::make('body')])]),
            Select::make('featured_media_id')->relationship('featuredMedia', 'alt_text')->searchable()->preload(), Select::make('author_id')->relationship('author', 'name')->required()->searchable()->preload(),
            TextInput::make('primary_category')->required()->maxLength(100), TagsInput::make('tags'), Select::make('publication_state')->options(['draft' => 'Draft', 'published' => 'Published'])->required(),
            DateTimePicker::make('publish_at'), DateTimePicker::make('expires_at'), Toggle::make('featured_on_homepage'), TextInput::make('share_title'), Textarea::make('share_description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable(), TextColumn::make('primary_category'), TextColumn::make('publication_state')->badge(), TextColumn::make('publish_at')->dateTime(), IconColumn::make('featured_on_homepage')->boolean()])->defaultSort('publish_at', 'desc')->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListNewsArticles::route('/'), 'create' => CreateNewsArticle::route('/create'), 'edit' => EditNewsArticle::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }
}
