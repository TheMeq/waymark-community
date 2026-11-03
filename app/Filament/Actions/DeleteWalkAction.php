<?php

namespace App\Filament\Actions;

use App\Domain\Walks\Actions\DeleteWalk;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Gate;

final class DeleteWalkAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Delete walk')
            ->modalHeading('Delete walk?')
            ->modalDescription('Remove this walk from public pages and administration lists. Its records, photos and files will be retained.')
            ->visible(fn (Walk $record): bool => Gate::allows('delete', $record))
            ->using(function (Walk $record): bool {
                /** @var User $actor */
                $actor = auth()->user();

                return app(DeleteWalk::class)->handle($record, $actor);
            })
            ->successNotificationTitle('Walk deleted');
    }
}
