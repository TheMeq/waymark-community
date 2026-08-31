<?php

namespace App\Filament\Resources;

use App\Domain\Holidays\Models\Holiday;
use App\Filament\Resources\HolidayResource\Pages\CreateHoliday;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Filament\Resources\HolidayResource\Pages\ListHolidays;
use App\Filament\Resources\HolidayResource\RelationManagers\ChildrenRelationManager;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Events';

    protected static ?string $navigationLabel = 'Holidays';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('slug')->required()->maxLength(255),
            DateTimePicker::make('starts_at')->required(),
            DateTimePicker::make('ends_at')->required(),
            Textarea::make('summary')->maxLength(65535)->columnSpanFull(),
            Textarea::make('description')->maxLength(65535)->columnSpanFull(),
            TextInput::make('destination')->maxLength(255),
            Checkbox::make('show_child_events_in_global_calendar')->label('Show child walks and socials in the global calendar')->default(true),
            Textarea::make('accommodation')->maxLength(5000),
            Select::make('pricing_type')->options(['free' => 'Free', 'tbc' => 'TBC', 'fixed' => 'Fixed', 'from' => 'From']),
            TextInput::make('price_amount')->numeric()->minValue(0),
            TextInput::make('currency')->maxLength(3),
            TextInput::make('deposit_amount')->numeric()->minValue(0),
            Textarea::make('pricing_notes')->maxLength(5000),
            TextInput::make('capacity')->integer()->minValue(0)->maxValue(65535),
            TextInput::make('availability')->maxLength(255),
            DateTimePicker::make('booking_deadline'),
            TextInput::make('booking_status')->maxLength(255),
            Textarea::make('booking_instructions')->maxLength(5000),
            TextInput::make('booking_url')->url()->maxLength(2048),
            Textarea::make('booking_contact')->maxLength(5000),
            Textarea::make('travel_details')->maxLength(10000),
            Textarea::make('itinerary_notes')->maxLength(65535)->columnSpanFull(),
            TextInput::make('featured_image_path')->maxLength(2048),
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
            TextColumn::make('destination')->searchable(),
            TextColumn::make('event.status')->label('Status'),
        ])->recordActions([EditAction::make()]);
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListHolidays::route('/'),
            'create' => CreateHoliday::route('/create'),
            'edit' => EditHoliday::route('/{record}/edit'),
        ];
    }

    /** @return array<int, class-string> */
    public static function getRelations(): array
    {
        return [ChildrenRelationManager::class];
    }
}
