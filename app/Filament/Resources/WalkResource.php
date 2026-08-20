<?php

namespace App\Filament\Resources;

use App\Domain\Walks\Models\Walk;
use App\Filament\Actions\DuplicateWalkAction;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Filament\Resources\WalkResource\Pages\ListWalks;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class WalkResource extends Resource
{
    protected static ?string $model = Walk::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Events';

    protected static ?string $navigationLabel = 'Walks';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('slug')->required()->maxLength(255),
                DateTimePicker::make('starts_at')->required(),
                DateTimePicker::make('ends_at'),
                Textarea::make('summary')->maxLength(65535),
                Textarea::make('description')->maxLength(65535),
                Select::make('primary_leader_id')
                    ->label('Primary leader')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('distance')->numeric()->minValue(0.01)->maxValue(999999.99),
                TextInput::make('ascent')->numeric()->minValue(0)->maxValue(999999.99),
                TextInput::make('estimated_duration_minutes')->integer()->minValue(1)->maxValue(65535),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.title')->label('Title')->searchable(),
                TextColumn::make('event.starts_at')->label('Starts')->dateTime()->sortable(),
                TextColumn::make('event.status')->label('Status'),
                TextColumn::make('primaryLeader.name')->label('Leader'),
            ])
            ->recordActions([
                EditAction::make(),
                DuplicateWalkAction::make(),
            ]);
    }

    /** @return Builder<Walk> */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['event', 'primaryLeader']);
        $user = auth()->user();

        if ($user?->is_admin) {
            return $query;
        }

        return $query->whereHas('event', fn (Builder $query): Builder => $query->where('organiser_id', $user?->id));
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListWalks::route('/'),
            'create' => CreateWalk::route('/create'),
            'edit' => EditWalk::route('/{record}/edit'),
        ];
    }
}
