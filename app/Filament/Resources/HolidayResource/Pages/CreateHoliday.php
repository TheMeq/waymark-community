<?php

namespace App\Filament\Resources\HolidayResource\Pages;

use App\Domain\Holidays\Actions\CreateHoliday as CreateHolidayAction;
use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\HolidayResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class CreateHoliday extends CreateRecord
{
    protected static string $resource = HolidayResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(CreateHolidayAction::class)->handle($user, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Holiday draft created';
    }
}
