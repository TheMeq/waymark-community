<?php

namespace App\Filament\Resources\SocialResource\Pages;

use App\Domain\Events\Actions\PublishEvent;
use App\Domain\Socials\Actions\UpdateSocial;
use App\Filament\Resources\SocialResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditSocial extends EditRecord
{
    protected static string $resource = SocialResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $event = $this->getRecord()->event;

        return [...$data, ...$event->only(['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at'])];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(UpdateSocial::class)->handle($record, $user, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')->action(function (): void {
                /** @var User $user */
                $user = auth()->user();
                app(PublishEvent::class)->handle($this->getRecord()->event, $user);
            }),
        ];
    }
}
