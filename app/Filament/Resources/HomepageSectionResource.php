<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Support\HomepageSectionRegistry;
use App\Filament\Resources\HomepageSectionResource\Pages\CreateHomepageSection;
use App\Filament\Resources\HomepageSectionResource\Pages\EditHomepageSection;
use App\Filament\Resources\HomepageSectionResource\Pages\ListHomepageSections;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class HomepageSectionResource extends Resource
{
    protected static ?string $model = HomepageSection::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Homepage';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('section_key')->options(array_combine(array_keys(HomepageSectionRegistry::sections()), array_map(fn (string $key): string => str($key)->replace('_', ' ')->title()->toString(), array_keys(HomepageSectionRegistry::sections()))))->required()->unique(ignoreRecord: true),
            Toggle::make('enabled')->default(true),
            TextInput::make('sort_order')->numeric()->minValue(0)->required(),
            Select::make('layout_variant')->options(['default' => 'Default', 'compact' => 'Compact', 'walks_first' => 'Walks first', 'feature_first' => 'Feature first', 'resources_first' => 'Resources first', 'featured' => 'Featured', 'wide' => 'Wide'])->required(),
            TextInput::make('heading')->maxLength(255),
            Textarea::make('supporting_copy')->maxLength(1000),
            TextInput::make('cta_label')->maxLength(100),
            TextInput::make('cta_url')->maxLength(2048),
            Select::make('content_mode')->options(['automatic' => 'Automatic', 'pinned' => 'Pinned override'])->required(),
            Select::make('pinned_type')->options(['site_media' => 'Site media', 'event' => 'Event', 'community_photo' => 'Community photo', 'cms_page' => 'CMS page', 'testimonial' => 'Testimonial', 'news_article' => 'News article']),
            TextInput::make('pinned_id')->numeric(),
            DateTimePicker::make('visible_from'),
            DateTimePicker::make('visible_until'),
            Select::make('empty_behavior')->options(['hide' => 'Hide section', 'message' => 'Show concise empty state'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('section_key')->label('Section')->sortable(),
            IconColumn::make('enabled')->boolean(),
            TextColumn::make('sort_order')->label('Order')->sortable(),
            TextColumn::make('layout_variant')->label('Layout'),
        ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->authorizeReorder(fn (): bool => self::canManage())
            ->beforeReordering(fn (ListHomepageSections $livewire, array $order): mixed => $livewire->captureHomepageSectionState($order))
            ->afterReordering(fn (ListHomepageSections $livewire): mixed => $livewire->snapshotHomepageSectionReorder())
            ->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListHomepageSections::route('/'), 'create' => CreateHomepageSection::route('/create'), 'edit' => EditHomepageSection::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }
}
