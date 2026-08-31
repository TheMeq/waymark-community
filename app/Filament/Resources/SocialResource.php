<?php

namespace App\Filament\Resources;

use App\Domain\Socials\Models\Social;
use App\Filament\Resources\SocialResource\Pages\CreateSocial;
use App\Filament\Resources\SocialResource\Pages\EditSocial;
use App\Filament\Resources\SocialResource\Pages\ListSocials;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class SocialResource extends Resource
{
    protected static ?string $model = Social::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Events';

    protected static ?string $navigationLabel = 'Socials';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('slug')->required()->maxLength(255),
            DateTimePicker::make('starts_at')->required(),
            DateTimePicker::make('ends_at'),
            Textarea::make('summary')->maxLength(65535)->columnSpanFull(),
            Textarea::make('description')->maxLength(65535)->columnSpanFull(),
            TextInput::make('venue_name')->maxLength(255),
            Textarea::make('venue_address')->maxLength(5000),
            TextInput::make('cost')->maxLength(255),
            TextInput::make('booking_status')->maxLength(255),
            Textarea::make('booking_instructions')->maxLength(5000),
            TextInput::make('booking_url')->url()->maxLength(2048),
            TextInput::make('contact_name')->maxLength(255),
            Textarea::make('contact_details')->maxLength(5000),
            TextInput::make('capacity')->integer()->minValue(0)->maxValue(65535),
            TextInput::make('availability')->maxLength(255),
            Textarea::make('accessibility_notes')->maxLength(5000),
            Textarea::make('transport_notes')->maxLength(5000),
            Repeater::make('attachments')->schema([
                TextInput::make('path')->required()->maxLength(2048),
                TextInput::make('name')->required()->maxLength(255),
            ])->defaultItems(0)->maxItems(10),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('event.title')->label('Title')->searchable(),
            TextColumn::make('event.starts_at')->label('Starts')->dateTime()->sortable(),
            TextColumn::make('event.status')->label('Status'),
            TextColumn::make('venue_name')->label('Venue'),
        ])->recordActions([EditAction::make()]);
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListSocials::route('/'),
            'create' => CreateSocial::route('/create'),
            'edit' => EditSocial::route('/{record}/edit'),
        ];
    }
}
