<?php

namespace App\Filament\Resources\WalkResource\Pages;

use App\Domain\Events\Actions\AddEventUpdate;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Actions\StoreWalkGpx;
use App\Domain\Walks\Actions\SubmitWalkForPublication;
use App\Domain\Walks\Actions\UpdateWalk;
use App\Domain\Walks\Actions\UpdateWalkRecap;
use App\Domain\Walks\Models\WalkFieldSettings;
use App\Filament\Resources\WalkResource;
use App\Filament\Resources\WalkResource\Support\WalkFormData;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class EditWalk extends EditRecord
{
    protected static string $resource = WalkResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return WalkFormData::from($this->getRecord());
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
            Action::make('uploadGpx')
                ->label('Upload GPX')
                ->visible(fn (): bool => WalkFieldSettings::current()->isEnabled('gpx'))
                ->form([
                    FileUpload::make('gpx')->label('GPX file')->storeFiles(false)->required(),
                ])
                ->action(function (array $data): void {
                    Gate::authorize('update', $this->getRecord());
                    $upload = $data['gpx'] ?? null;

                    if (! $upload instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages([
                            'gpx' => 'Choose a GPX file to upload.',
                        ]);
                    }

                    try {
                        app(StoreWalkGpx::class)->handle($this->getRecord(), $upload);
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages([
                            'mountedActions.0.data.gpx' => $exception->errors()['gpx'] ?? ['The GPX file could not be uploaded.'],
                        ]);
                    }
                }),
            Action::make('addUpdate')
                ->label('Add organiser update')
                ->form([
                    Textarea::make('message')->label('Update')->required()->maxLength(5000)->rows(4),
                    Toggle::make('is_significant')->label('Mark as a significant public change'),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    app(AddEventUpdate::class)->handle($this->getRecord()->event, $user, $data);
                }),
            Action::make('viewUpdateHistory')
                ->label('Update history')
                ->modalHeading('Organiser update history')
                ->modalContent(fn () => view('filament.walks.update-history', [
                    'updates' => $this->getRecord()->event->updates()->with('author')->get(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
            Action::make('saveRecap')
                ->label('Add walk recap')
                ->visible(fn (): bool => WalkFieldSettings::current()->isEnabled('recap') && ($this->getRecord()->event->isCompleted() || $this->getRecord()->event->status === EventStatus::Completed))
                ->fillForm(fn (): array => $this->getRecord()->only(['recap', 'highlights']))
                ->form([
                    Textarea::make('recap')->maxLength(20000)->rows(8),
                    Textarea::make('highlights')->maxLength(5000)->rows(4),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    app(UpdateWalkRecap::class)->handle($this->getRecord(), $user, $data);
                }),
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
