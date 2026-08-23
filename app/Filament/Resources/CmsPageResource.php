<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\CmsPage;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Filament\Resources\CmsPageResource\Pages\CreateCmsPage;
use App\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use App\Filament\Resources\CmsPageResource\Pages\ListCmsPages;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Pages';

    public static function form(Schema $schema): Schema
    {
        $mediaOptions = fn (): array => SiteMedia::query()->where('processing_status', 'complete')->orderBy('alt_text')->pluck('alt_text', 'id')->all();

        return $schema->components([
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('slug')->required()->alphaDash()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('publication_state')->options(['draft' => 'Draft', 'published' => 'Published'])->required(),
            DateTimePicker::make('publish_at')->label('Publish from'),
            Select::make('hero_media_id')->relationship('heroMedia', 'alt_text')->searchable()->preload(),
            Builder::make('blocks')->required()->blocks([
                Block::make('rich_text')->schema([RichEditor::make('content')->required()]),
                Block::make('image_text')->label('Image and text')->schema([Select::make('media_id')->options($mediaOptions)->required()->searchable(), TextInput::make('heading'), Textarea::make('body')->required()]),
                Block::make('callout')->schema([TextInput::make('heading'), Textarea::make('body')->required()]),
                Block::make('faq')->schema([Repeater::make('items')->schema([TextInput::make('question')->required(), Textarea::make('answer')->required()])]),
                Block::make('quote')->schema([Textarea::make('quote')->required(), TextInput::make('attribution')]),
                Block::make('cta')->label('Call to action')->schema([TextInput::make('heading'), Textarea::make('body'), TextInput::make('label')->required(), TextInput::make('url')->required()]),
                Block::make('button_group')->schema([TextInput::make('heading'), Repeater::make('items')->minItems(1)->maxItems(4)->schema([TextInput::make('label')->required(), TextInput::make('url')->required()])]),
                Block::make('document_list')->label('Document list')->schema([TextInput::make('heading'), Repeater::make('items')->minItems(1)->maxItems(12)->schema([TextInput::make('label')->required(), TextInput::make('url')->required()])]),
                Block::make('gallery')->schema([TextInput::make('heading'), Select::make('media_ids')->multiple()->options($mediaOptions)->minItems(1)->maxItems(6)->required()->searchable()]),
                Block::make('statistics')->schema([TextInput::make('heading'), Repeater::make('items')->minItems(1)->maxItems(6)->schema([TextInput::make('value')->required(), TextInput::make('label')->required()])]),
                Block::make('timeline')->schema([TextInput::make('heading'), Repeater::make('items')->minItems(1)->maxItems(12)->schema([TextInput::make('label')->required(), Textarea::make('body')->required()])]),
                Block::make('columns')->schema([TextInput::make('heading'), Repeater::make('items')->minItems(2)->maxItems(3)->schema([TextInput::make('label'), Textarea::make('body')->required()])]),
            ]),
            TextInput::make('seo_title')->maxLength(255),
            Textarea::make('seo_description')->maxLength(1000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->sortable(),
            TextColumn::make('publication_state')->badge(),
            TextColumn::make('publish_at')->dateTime()->sortable(),
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
        return ['index' => ListCmsPages::route('/'), 'create' => CreateCmsPage::route('/create'), 'edit' => EditCmsPage::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }
}
