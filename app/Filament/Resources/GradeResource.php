<?php

namespace App\Filament\Resources;

use App\Domain\Walks\Models\Grade;
use App\Filament\Resources\GradeResource\Pages\CreateGrade;
use App\Filament\Resources\GradeResource\Pages\EditGrade;
use App\Filament\Resources\GradeResource\Pages\ListGrades;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class GradeResource extends Resource
{
    protected static ?string $model = Grade::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Grading';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('display_order')
                    ->label('Display order')
                    ->integer()
                    ->minValue(0)
                    ->required(),
                TextInput::make('name')
                    ->maxLength(255)
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->rows(3)
                    ->maxLength(1000)
                    ->required()
                    ->helperText('Explain the practical difficulty so walkers can choose confidently.'),
                ColorPicker::make('colour')
                    ->label('Accent colour')
                    ->nullable()
                    ->helperText('Optional. The grade label and description remain the meaning.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_order')->label('Order')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('description')->limit(80),
            ])
            ->defaultSort('display_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListGrades::route('/'),
            'create' => CreateGrade::route('/create'),
            'edit' => EditGrade::route('/{record}/edit'),
        ];
    }
}
