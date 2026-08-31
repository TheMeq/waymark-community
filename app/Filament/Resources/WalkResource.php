<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Queries\EligibleWalkLeadersQuery;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\Models\Walk;
use App\Domain\Walks\Queries\CurrentWalkFieldSettings;
use App\Filament\Actions\DuplicateWalkAction;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Filament\Resources\WalkResource\Pages\ListWalks;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('slug')->required()->maxLength(255),
                DateTimePicker::make('starts_at')->required(),
                DateTimePicker::make('ends_at'),
                Textarea::make('summary')->maxLength(65535)->columnSpanFull(),
                Textarea::make('description')->maxLength(65535)->columnSpanFull(),
                Select::make('primary_leader_id')
                    ->label('Primary leader')
                    ->options(fn (): array => app(EligibleWalkLeadersQuery::class)->options())
                    ->getOptionLabelUsing(fn (int|string $value): ?string => app(EligibleWalkLeadersQuery::class)->existingLabel($value))
                    ->searchable()
                    ->required(),
                Select::make('co_leader_ids')
                    ->label('Co-leaders')
                    ->multiple()
                    ->options(fn (): array => app(EligibleWalkLeadersQuery::class)->options())
                    ->getOptionLabelsUsing(fn (array $values): array => app(EligibleWalkLeadersQuery::class)->existingLabels($values))
                    ->searchable()
                    ->visible(fn (): bool => self::optionalFieldEnabled('co_leaders')),
                Select::make('grade_id')
                    ->label('Grade')
                    ->options(fn (): array => Grade::query()->orderBy('display_order')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Select::make('tag_ids')
                    ->label('Tags')
                    ->multiple()
                    ->options(fn (): array => Tag::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                TextInput::make('distance')->numeric()->minValue(0.01)->maxValue(999999.99),
                TextInput::make('ascent')->numeric()->minValue(0)->maxValue(999999.99),
                TextInput::make('estimated_duration_minutes')->integer()->minValue(1)->maxValue(65535),
                TextInput::make('capacity')->integer()->minValue(0)->maxValue(65535),
                TextInput::make('availability')->maxLength(255),
                Textarea::make('terrain_notes')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('terrain_notes')),
                TextInput::make('meeting_location_name')->label('Meeting location')->maxLength(255),
                Textarea::make('meeting_address')->maxLength(5000),
                TextInput::make('meeting_postcode')->maxLength(32),
                TextInput::make('latitude')->numeric()->visible(fn (): bool => self::optionalFieldEnabled('coordinates')),
                TextInput::make('longitude')->numeric()->visible(fn (): bool => self::optionalFieldEnabled('coordinates')),
                TextInput::make('what3words')->label('What3Words')->maxLength(255)->visible(fn (): bool => self::optionalFieldEnabled('what3words')),
                TextInput::make('os_grid_reference')->label('OS grid reference')->maxLength(255)->visible(fn (): bool => self::optionalFieldEnabled('os_grid_reference')),
                Textarea::make('directions')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('directions')),
                Toggle::make('is_public_transport_friendly')->label('Public transport friendly')->visible(fn (): bool => self::optionalFieldEnabled('public_transport')),
                TextInput::make('public_transport_station_stop')->label('Nearest station or stop')->maxLength(255)->visible(fn (): bool => self::optionalFieldEnabled('public_transport')),
                Textarea::make('public_transport_notes')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('public_transport')),
                TextInput::make('public_transport_url')->label('Public transport link')->url()->maxLength(255)->visible(fn (): bool => self::optionalFieldEnabled('public_transport')),
                Textarea::make('parking_notes')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('parking_notes')),
                Textarea::make('toilet_information')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('toilet_information')),
                Textarea::make('cafe_pub_information')->label('Café or pub information')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('cafe_pub_information')),
                Textarea::make('dog_guidance')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('dog_guidance')),
                Textarea::make('accessibility_notes')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('accessibility_notes')),
                TagsInput::make('kit_checklist')->label('Kit checklist')->visible(fn (): bool => self::optionalFieldEnabled('kit_checklist')),
                Textarea::make('kit_notes')->maxLength(5000)->visible(fn (): bool => self::optionalFieldEnabled('kit_notes')),
                TextInput::make('featured_image_path')->label('Featured image path')->maxLength(255),
                Repeater::make('attachments')
                    ->label('Attachment metadata')
                    ->schema([
                        TextInput::make('path')->required()->maxLength(2048),
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('mime_type')->maxLength(255),
                        TextInput::make('size_bytes')->integer()->minValue(0),
                    ])
                    ->defaultItems(0)
                    ->maxItems(10)
                    ->visible(fn (): bool => self::optionalFieldEnabled('attachments')),
                Textarea::make('private_organiser_notes')->maxLength(10000)->visible(fn (): bool => self::optionalFieldEnabled('private_organiser_notes')),
            ]);
    }

    private static function optionalFieldEnabled(string $field): bool
    {
        return app(CurrentWalkFieldSettings::class)->get()->isEnabled($field);
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

        if ($user instanceof User && $user->hasCapability(ModuleCapability::ManageAllWalks)) {
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
