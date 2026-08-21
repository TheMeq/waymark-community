<?php

namespace App\Filament\Resources\RecurringSeriesResource\Pages;

use App\Domain\Events\Actions\CreateRecurringSeries as CreateRecurringSeriesAction;
use App\Domain\Events\Models\Event;
use App\Filament\Resources\RecurringSeriesResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateRecurringSeries extends CreateRecord
{
    protected static string $resource = RecurringSeriesResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $source = Event::query()->findOrFail((int) $data['source_event_id']);

        return app(CreateRecurringSeriesAction::class)->handle(
            $source,
            (string) $data['frequency'],
            (int) $data['interval'],
            (int) $data['occurrence_count'],
        );
    }
}
