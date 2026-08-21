<?php

namespace App\Filament\Resources\HolidayResource\Pages;

use App\Domain\Events\Actions\PublishEvent;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\AssignHolidayChild;
use App\Domain\Holidays\Actions\UpdateHoliday;
use App\Filament\Resources\HolidayResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditHoliday extends EditRecord
{
    protected static string $resource = HolidayResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...$this->getRecord()->event->only(['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at'])];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(UpdateHoliday::class)->handle($record, $user, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('attachChild')
                ->label('Attach itinerary event')
                ->form([
                    Select::make('child_event_id')
                        ->label('Walk or social')
                        ->options(fn (): array => Event::query()
                            ->whereIn('type', [EventType::Walk, EventType::Social])
                            ->whereNull('parent_event_id')
                            ->where('starts_at', '>=', $this->getRecord()->event->starts_at)
                            ->whereRaw('coalesce(ends_at, starts_at) <= ?', [$this->getRecord()->event->ends_at])
                            ->orderBy('starts_at')->pluck('title', 'id')->all())
                        ->searchable()->required(),
                ])
                ->action(function (array $data): void {
                    app(AssignHolidayChild::class)->handle(
                        $this->getRecord()->event,
                        Event::query()->findOrFail((int) $data['child_event_id']),
                    );
                }),
            Action::make('publish')->action(function (): void {
                /** @var User $user */
                $user = auth()->user();
                app(PublishEvent::class)->handle($this->getRecord()->event, $user);
            }),
        ];
    }
}
