<?php

namespace App\Filament\Pages;

use App\Domain\SiteMedia\Enums\ContentImageSlot;
use App\Filament\Forms\ContentImageFields;
use App\Filament\Forms\PreparesContentFormData;
use App\Filament\Forms\ReportsFormValidation;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

abstract class EditResourceRecord extends EditRecord
{
    use PreparesContentFormData;
    use ReportsFormValidation;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (ValidationException $exception) {
            throw $this->visibleValidation($exception);
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareResourceData($data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillResourceData($data);
    }

    protected function afterSave(): void
    {
        $this->completeResourceImage();
        if (ContentImageSlot::tryFrom(static::getResource()::getModel()) !== null) {
            $this->refreshFormData([...ContentImageFields::stateNames(), 'blocks']);
        }
    }
}
