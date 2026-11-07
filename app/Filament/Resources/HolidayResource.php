<?php

namespace App\Filament\Resources;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Holidays\Models\Holiday;
use App\Filament\Actions\PublishEventAction;
use App\Filament\Forms\ContentImageFields;
use App\Filament\Forms\ImportedEventFields;
use App\Filament\Resources\HolidayResource\Pages\CreateHoliday;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Filament\Resources\HolidayResource\Pages\ListHolidays;
use App\Filament\Resources\HolidayResource\RelationManagers\ChildrenRelationManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

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
            TextInput::make('slug')->required()->maxLength(255)->visibleOn('edit'),
            DateTimePicker::make('starts_at')->required(),
            ImportedEventFields::timeUnknown(),
            ImportedEventFields::source(),
            DateTimePicker::make('ends_at')->required()->afterOrEqual('starts_at'),
            Textarea::make('summary')->maxLength(65535)->columnSpanFull(),
            Textarea::make('description')->maxLength(65535)->columnSpanFull(),
            TextInput::make('destination')->maxLength(255),
            Checkbox::make('show_child_events_in_global_calendar')->label('Show child walks and socials in the global calendar')->default(true),
            Textarea::make('accommodation')->maxLength(5000),
            Select::make('pricing_type')->live()->options(['free' => 'Free', 'tbc' => 'TBC', 'fixed' => 'Fixed', 'from' => 'From']),
            TextInput::make('price_amount')->numeric()->minValue(0)->required(fn (Get $get): bool => in_array($get('pricing_type'), ['fixed', 'from'], true)),
            TextInput::make('currency')->length(3)->required(fn (Get $get): bool => in_array($get('pricing_type'), ['fixed', 'from'], true)),
            TextInput::make('deposit_amount')->numeric()->minValue(0),
            Textarea::make('pricing_notes')->maxLength(5000),
            TextInput::make('capacity')->integer()->minValue(0)->maxValue(65535),
            TextInput::make('availability')->maxLength(255)->datalist(['Places available', 'Limited places', 'Sold out']),
            DateTimePicker::make('booking_deadline'),
            TextInput::make('booking_status')->maxLength(255),
            Textarea::make('booking_instructions')->maxLength(5000),
            TextInput::make('booking_url')->url()->maxLength(2048),
            Textarea::make('booking_contact')->maxLength(5000),
            Textarea::make('travel_details')->maxLength(10000),
            Textarea::make('itinerary_notes')->maxLength(65535)->columnSpanFull(),
            ...ContentImageFields::components('Holiday image'),
            Repeater::make('attachments')->schema([
                TextInput::make('path')->required()->maxLength(2048),
                TextInput::make('name')->required()->maxLength(255),
            ])->defaultItems(0)->maxItems(10),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('event.starts_at', 'desc')->columns([
            TextColumn::make('event.title')->label('Title')->searchable(),
            TextColumn::make('event.starts_at')->label('Starts')->dateTime()->sortable(),
            TextColumn::make('destination')->searchable(),
            TextColumn::make('event.status')->label('Status'),
            TextColumn::make('availability'),
        ])->recordActions([
            EditAction::make()->label(fn (Holiday $record): string => $record->event->status === EventStatus::Draft ? 'Continue draft' : 'Edit'),
            PublishEventAction::make('holiday', 'Holiday published'),
            Action::make('soldOut')->label('Mark sold out')
                ->visible(fn (Holiday $record): bool => $record->availability !== 'Sold out' && Gate::allows('update', $record))
                ->requiresConfirmation()
                ->modalDescription('The holiday will show as sold out. You can change availability in the edit form if places become available again.')
                ->action(function (Holiday $record): void {
                    Gate::authorize('update', $record);
                    app(SaveHolidayDetails::class)->handle($record->event, ['availability' => 'Sold out']);
                    Notification::make()->success()->title('Holiday marked sold out')->send();
                }),
        ]);
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
