<?php

namespace App\Filament\Resources\HolidayResource\Pages;

use App\Domain\Events\Actions\PublishEvent;
use App\Domain\Holidays\Actions\UpdateHoliday;
use App\Filament\Resources\HolidayResource;
use App\Models\User;
use Filament\Actions\Action;
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
        return [Action::make('publish')->action(function (): void {
            /** @var User $user */
            $user = auth()->user();
            app(PublishEvent::class)->handle($this->getRecord()->event, $user);
        })];
    }
}
