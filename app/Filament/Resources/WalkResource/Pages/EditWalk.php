<?php

namespace App\Filament\Resources\WalkResource\Pages;

use App\Domain\Walks\Actions\SubmitWalkForPublication;
use App\Domain\Walks\Actions\UpdateWalk;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditWalk extends EditRecord
{
    protected static string $resource = WalkResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $event = $this->getRecord()->event;

        return [
            ...$data,
            ...$event->only(['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at']),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(UpdateWalk::class)->handle($record, $user, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForPublication')
                ->label('Submit for publication')
                ->action(function (): void {
                    /** @var User $user */
                    $user = auth()->user();
                    app(SubmitWalkForPublication::class)->handle($this->getRecord(), $user);
                    $this->refreshFormData(['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at']);
                }),
        ];
    }
}
