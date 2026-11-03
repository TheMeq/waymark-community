<?php

namespace App\Filament\Pages;

use App\Filament\Forms\PreparesContentFormData;
use App\Filament\Forms\ReportsFormValidation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

abstract class CreateResourceRecord extends CreateRecord
{
    use PreparesContentFormData;
    use ReportsFormValidation;

    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (ValidationException $exception) {
            throw $this->visibleValidation($exception);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareResourceData($data);
    }

    protected function afterCreate(): void
    {
        $this->completeResourceImage();
    }
}
