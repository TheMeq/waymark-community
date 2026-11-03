<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\CommitteeHubLink;
use App\Filament\Resources\CommitteeHubLinkResource\Pages\CreateCommitteeHubLink;
use App\Filament\Resources\CommitteeHubLinkResource\Pages\EditCommitteeHubLink;
use App\Filament\Resources\CommitteeHubLinkResource\Pages\ListCommitteeHubLinks;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CommitteeHubLinkResource extends Resource
{
    protected static ?string $model = CommitteeHubLink::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Committee Hub links';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('label')->required(), TextInput::make('url')->required(), Textarea::make('notes'), TextInput::make('sort_order')->numeric()->required(), Toggle::make('active')->default(true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('label')->searchable(), TextColumn::make('url'), TextColumn::make('sort_order')->sortable(), IconColumn::make('active')->boolean()])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return self::canManage();
    }

    public static function canCreate(): bool
    {
        return self::canManage();
    }

    public static function canEdit($record): bool
    {
        return self::canManage();
    }

    public static function canDelete($record): bool
    {
        return self::canManage();
    }

    public static function getPages(): array
    {
        return ['index' => ListCommitteeHubLinks::route('/'), 'create' => CreateCommitteeHubLink::route('/create'), 'edit' => EditCommitteeHubLink::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageGovernance);
    }
}
