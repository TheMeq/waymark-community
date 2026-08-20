<?php

namespace App\Filament\Actions;

use App\Domain\Walks\Actions\DuplicateWalk;
use App\Domain\Walks\Enums\DuplicateWalkCopyGroup;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Gate;

final class DuplicateWalkAction
{
    public static function make(): Action
    {
        return Action::make('duplicateWalk')
            ->label('Duplicate')
            ->modalHeading('Duplicate walk')
            ->modalDescription('Review the new title, slug and date. The duplicate starts as a draft.')
            ->modalSubmitActionLabel('Create draft')
            ->schema([
                TextInput::make('title')->label('New title')->required()->maxLength(255),
                TextInput::make('slug')->label('New slug')->required()->maxLength(255),
                DateTimePicker::make('starts_at')->label('New start')->required(),
                DateTimePicker::make('ends_at')->label('New end'),
                CheckboxList::make('copy_groups')
                    ->label('Copy from original')
                    ->options(DuplicateWalkCopyGroup::options())
                    ->default(DuplicateWalkCopyGroup::defaults())
                    ->columns(2),
            ])
            ->visible(fn (Walk $record): bool => Gate::allows('duplicate', $record))
            ->action(function (Walk $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();

                app(DuplicateWalk::class)->handle($record, $actor, $data);
            })
            ->successNotificationTitle('Draft created');
    }
}
