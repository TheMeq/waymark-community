<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Walks\Models\Grade;
use App\Filament\Resources\GradeResource\Pages\CreateGrade;
use App\Filament\Resources\GradeResource\Pages\EditGrade;
use App\Filament\Resources\GradeResource\Pages\ListGrades;
use App\Models\User;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Group settings';

    protected static ?string $navigationLabel = 'Grading';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::creationFormComponents());
    }

    /** @return array<int, ColorPicker|Textarea|TextInput> */
    public static function creationFormComponents(bool $includeDisplayOrder = true): array
    {
        $components = [];

        if ($includeDisplayOrder) {
            $components[] = TextInput::make('display_order')
                ->label('Display order')
                ->integer()
                ->minValue(0)
                ->required();
        }

        return [
            ...$components,
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
        ];
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

    public static function canCreate(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && $actor->hasCapability(ModuleCapability::ManageEventConfiguration);
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
