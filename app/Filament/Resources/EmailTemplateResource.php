<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Models\EmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;
    protected static string|\UnitEnum|null $navigationGroup = 'Communication';
    protected static ?string $navigationLabel = 'Email templates';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('template_key')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('subject')->required()->maxLength(255),
            Textarea::make('intro_text')->required(),
            TextInput::make('action_label')->maxLength(255),
            Textarea::make('closing_text'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('template_key')->searchable(), TextColumn::make('subject')->searchable()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function canViewAny(): bool { return self::canManage(); }
    public static function canCreate(): bool { return self::canManage(); }
    public static function canEdit($record): bool { return self::canManage(); }
    public static function canDelete($record): bool { return self::canManage(); }

    public static function getPages(): array
    {
        return ['index' => ListEmailTemplates::route('/'), 'create' => CreateEmailTemplate::route('/create'), 'edit' => EditEmailTemplate::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageCommunications);
    }
}
