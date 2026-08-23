<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\Testimonial;
use App\Filament\Resources\TestimonialResource\Pages\CreateTestimonial;
use App\Filament\Resources\TestimonialResource\Pages\EditTestimonial;
use App\Filament\Resources\TestimonialResource\Pages\ListTestimonials;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Textarea::make('quote')->required()->maxLength(5000), TextInput::make('display_name')->required(), Select::make('image_media_id')->relationship('imageMedia', 'alt_text')->searchable()->preload(), TextInput::make('member_since'), Toggle::make('active')->default(true), Toggle::make('featured'), TextInput::make('sort_order')->numeric()->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('display_name')->searchable(), TextColumn::make('quote')->limit(80), IconColumn::make('active')->boolean(), IconColumn::make('featured')->boolean(), TextColumn::make('sort_order')->sortable()])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListTestimonials::route('/'), 'create' => CreateTestimonial::route('/create'), 'edit' => EditTestimonial::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }
}
