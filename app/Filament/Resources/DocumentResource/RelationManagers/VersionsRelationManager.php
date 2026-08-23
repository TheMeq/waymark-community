<?php

namespace App\Filament\Resources\DocumentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('version_number')->label('Version')->sortable(),
            TextColumn::make('original_filename')->label('File'),
            TextColumn::make('created_at')->label('Uploaded')->dateTime(),
            IconColumn::make('published_at')->label('Published')->boolean(),
        ])->defaultSort('version_number', 'desc');
    }
}
