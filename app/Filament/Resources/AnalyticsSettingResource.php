<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Models\AnalyticsSetting;
use App\Filament\Resources\AnalyticsSettingResource\Pages\CreateAnalyticsSetting;
use App\Filament\Resources\AnalyticsSettingResource\Pages\EditAnalyticsSetting;
use App\Filament\Resources\AnalyticsSettingResource\Pages\ListAnalyticsSettings;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AnalyticsSettingResource extends Resource
{
    protected static ?string $model = AnalyticsSetting::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Analytics';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('provider')->options(['none' => 'None', 'plausible' => 'Plausible', 'ga4' => 'Google Analytics 4'])->required()->default('none')->live(),
            TextInput::make('tracking_id')->label('Provider identifier')->maxLength(255)->helperText('Plausible site domain or GA4 measurement ID. Custom scripts are not accepted.'),
            Toggle::make('enabled')->helperText('Analytics remains blocked until the visitor grants optional analytics consent.'),
            TextInput::make('singleton_key')->default('public')->hidden()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('provider'), TextColumn::make('tracking_id')->label('Identifier'), IconColumn::make('enabled')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return self::canManage();
    }

    public static function canCreate(): bool
    {
        return self::canManage() && ! AnalyticsSetting::query()->where('singleton_key', 'public')->exists();
    }

    public static function canEdit($record): bool
    {
        return self::canManage();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListAnalyticsSettings::route('/'), 'create' => CreateAnalyticsSetting::route('/create'), 'edit' => EditAnalyticsSetting::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageContent);
    }
}
