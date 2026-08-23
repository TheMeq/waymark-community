<?php

namespace App\Filament\Resources\PolicyPageResource\RelationManagers;

use App\Domain\Communication\Actions\PublishPolicyVersion;
use App\Domain\Communication\Models\PolicyVersion;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('version_number')->integer()->minValue(1)->required(),
            Textarea::make('body')->rows(16)->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version_number')->label('Version')->sortable(),
                TextColumn::make('publication_state')->badge(),
                TextColumn::make('published_at')->dateTime(),
            ])
            ->headerActions([CreateAction::make()->mutateDataUsing(function (array $data): array {
                $data['publication_state'] = 'draft';

                return $data;
            })])
            ->recordActions([
                Action::make('publish')
                    ->visible(fn (PolicyVersion $record): bool => $record->publication_state !== 'published')
                    ->requiresConfirmation()
                    ->action(function (PolicyVersion $record): void {
                        $actor = auth()->user();

                        if ($actor instanceof User) {
                            app(PublishPolicyVersion::class)->handle($actor, $record);
                        }
                    }),
                EditAction::make()->visible(fn (PolicyVersion $record): bool => $record->publication_state === 'draft'),
                DeleteAction::make()->visible(fn (PolicyVersion $record): bool => $record->publication_state === 'draft'),
            ])
            ->defaultSort('version_number', 'desc');
    }
}
