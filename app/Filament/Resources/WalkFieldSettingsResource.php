<?php

namespace App\Filament\Resources;

use App\Domain\Walks\Models\WalkFieldSettings;
use App\Filament\Resources\WalkFieldSettingsResource\Pages\EditWalkFieldSettings;
use App\Filament\Resources\WalkFieldSettingsResource\Pages\ListWalkFieldSettings;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WalkFieldSettingsResource extends Resource
{
    protected static ?string $model = WalkFieldSettings::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Group settings';

    protected static ?string $navigationLabel = 'Walk fields';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                CheckboxList::make('field_configuration')
                    ->label('Enabled optional fields')
                    ->options(WalkFieldSettings::OPTIONAL_FIELDS)
                    ->columns(2)
                    ->formatStateUsing(static fn (?array $state): array => array_keys(array_filter($state ?? [])))
                    ->dehydrateStateUsing(static function (array $state): array {
                        $fields = array_keys(WalkFieldSettings::OPTIONAL_FIELDS);

                        return array_combine(
                            $fields,
                            array_map(static fn (string $field): bool => in_array($field, $state, true), $fields),
                        );
                    })
                    ->helperText('Disabled fields are hidden from walk forms and public pages. Existing walk information is kept.'),
                Toggle::make('leaders_can_publish_directly')
                    ->label('Walk leaders can publish their own walks directly')
                    ->helperText('When disabled, a leader submission is held for approval. Administrators can always publish.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('updated_at')->label('Last updated')->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListWalkFieldSettings::route('/'),
            'edit' => EditWalkFieldSettings::route('/{record}/edit'),
        ];
    }
}
