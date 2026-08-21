<?php

namespace App\Filament\Resources\HolidayResource\RelationManagers;

use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\RemoveHolidayChild;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Itinerary events';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('type'),
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('status'),
            ])
            ->recordActions([
                Action::make('detach')
                    ->requiresConfirmation()
                    ->action(fn (Event $record): Event => app(RemoveHolidayChild::class)->handle($this->getOwnerRecord()->event, $record)),
            ]);
    }
}
