<?php

namespace App\Filament\Resources\HomepageSectionResource\Pages;

use App\Domain\Content\Actions\SaveHomepageSection;
use App\Domain\Content\Models\HomepageSection;
use App\Filament\Resources\HomepageSectionResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditHomepageSection extends EditRecord
{
    protected static string $resource = HomepageSectionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        /** @var HomepageSection $record */
        return app(SaveHomepageSection::class)->handle($actor, $record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
