<?php

namespace App\Filament\Resources;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\RecurringSeries;
use App\Filament\Resources\RecurringSeriesResource\Pages\CreateRecurringSeries;
use App\Filament\Resources\RecurringSeriesResource\Pages\ListRecurringSeries;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class RecurringSeriesResource extends Resource
{
    protected static ?string $model = RecurringSeries::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Events';

    protected static ?string $navigationLabel = 'Recurring series';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source_event_id')
                ->label('First event')
                ->options(fn (): array => Event::query()->whereNull('recurring_series_id')->orderBy('starts_at')->pluck('title', 'id')->all())
                ->searchable()->required(),
            Select::make('frequency')->options(['weeks' => 'Weeks', 'months' => 'Months'])->required(),
            TextInput::make('interval')->integer()->minValue(1)->maxValue(52)->default(1)->required(),
            TextInput::make('occurrence_count')->integer()->minValue(2)->maxValue(100)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sourceEvent.title')->label('First event'),
            TextColumn::make('frequency'),
            TextColumn::make('interval'),
            TextColumn::make('occurrence_count')->label('Occurrences'),
        ]);
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListRecurringSeries::route('/'),
            'create' => CreateRecurringSeries::route('/create'),
        ];
    }
}
