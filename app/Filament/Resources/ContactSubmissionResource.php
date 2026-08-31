<?php

namespace App\Filament\Resources;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Models\ContactSubmission;
use App\Filament\Resources\ContactSubmissionResource\Pages\ListContactSubmissions;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ContactSubmissionResource extends Resource
{
    protected static ?string $model = ContactSubmission::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Recent contact submissions';

    protected static ?int $navigationSort = 70;

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('created_at')->dateTime()->sortable(), TextColumn::make('department.public_label')->label('Department'), TextColumn::make('name')->searchable(), TextColumn::make('email'), TextColumn::make('message')->limit(100), TextColumn::make('retention_expires_at')->dateTime()])->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageCommunications);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListContactSubmissions::route('/')];
    }
}
