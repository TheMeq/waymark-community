<?php

namespace App\Filament\Actions;

use App\Domain\Events\Actions\PublishEvent;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Holidays\Models\Holiday;
use App\Domain\Socials\Models\Social;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class PublishEventAction
{
    public static function make(string $label, string $notificationTitle): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->visible(fn (Holiday|Social $record): bool => $record->event->status === EventStatus::Draft && Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalHeading('Publish '.$label.'?')
            ->modalDescription('This '.$label.' will become visible on public pages.')
            ->action(function (Holiday|Social $record) use ($notificationTitle): void {
                Gate::authorize('update', $record);
                app(PublishEvent::class)->handle($record->event, auth()->user());
                Notification::make()->success()->title($notificationTitle)->send();
            });
    }
}
