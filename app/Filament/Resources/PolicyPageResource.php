<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Models\PolicyPage;
use App\Filament\Resources\PolicyPageResource\Pages\CreatePolicyPage;
use App\Filament\Resources\PolicyPageResource\Pages\EditPolicyPage;
use App\Filament\Resources\PolicyPageResource\Pages\ListPolicyPages;
use App\Filament\Resources\PolicyPageResource\RelationManagers\VersionsRelationManager;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PolicyPageResource extends Resource
{
    protected static ?string $model = PolicyPage::class;
    protected static string|\UnitEnum|null $navigationGroup = 'Communication';
    protected static ?string $navigationLabel = 'Policy pages';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('policy_key')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('title')->required()->maxLength(255),
            TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            Textarea::make('review_notice')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable(), TextColumn::make('policy_key'), TextColumn::make('currentVersion.version_number')->label('Current version')])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function canViewAny(): bool { return self::canManage(); }
    public static function canCreate(): bool { return self::canManage(); }
    public static function canEdit($record): bool { return self::canManage(); }
    public static function canDelete($record): bool { return self::canManage(); }

    public static function getPages(): array
    {
        return ['index' => ListPolicyPages::route('/'), 'create' => CreatePolicyPage::route('/create'), 'edit' => EditPolicyPage::route('/{record}/edit')];
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageCommunications);
    }
}
