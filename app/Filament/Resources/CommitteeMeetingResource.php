<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\CommitteeMeeting;
use App\Filament\Resources\CommitteeMeetingResource\Pages\CreateCommitteeMeeting;
use App\Filament\Resources\CommitteeMeetingResource\Pages\EditCommitteeMeeting;
use App\Filament\Resources\CommitteeMeetingResource\Pages\ListCommitteeMeetings;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CommitteeMeetingResource extends Resource
{
    protected static ?string $model = CommitteeMeeting::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Group';

    protected static ?string $navigationLabel = 'Meetings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('title')->required(), DatePicker::make('meeting_date')->required(), Textarea::make('agenda'), KeyValue::make('attendee_metadata'), Textarea::make('internal_notes'), Select::make('minutes_document_version_id')->relationship('minutesVersion', 'original_filename')->searchable()->preload(), Select::make('visibility')->options(['public' => 'Public title/date/minutes', 'committee' => 'Committee only'])->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('meeting_date')->date()->sortable(), TextColumn::make('title')->searchable(), TextColumn::make('visibility')->badge()])->defaultSort('meeting_date', 'desc')->recordActions([EditAction::make(), DeleteAction::make()]);
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
        return ['index' => ListCommitteeMeetings::route('/'), 'create' => CreateCommitteeMeeting::route('/create'), 'edit' => EditCommitteeMeeting::route('/{record}/edit')];
    }

    private static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageGovernance);
    }
}
