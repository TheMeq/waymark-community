<?php

namespace App\Filament\Resources\WalkResource\Pages;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\Events\Actions\AddEventUpdate;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Actions\StoreWalkGpx;
use App\Domain\Walks\Actions\SubmitWalkForPublication;
use App\Domain\Walks\Actions\UpdateWalk;
use App\Domain\Walks\Actions\UpdateWalkFeaturedImage;
use App\Domain\Walks\Actions\UpdateWalkRecap;
use App\Domain\Walks\Data\WalkFeaturedImageInput;
use App\Domain\Walks\Models\WalkFieldSettings;
use App\Filament\Resources\WalkResource;
use App\Filament\Resources\WalkResource\Support\WalkFeaturedImageFields;
use App\Filament\Resources\WalkResource\Support\WalkFormData;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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

        $walk = app(UpdateWalk::class)->handle(
            $record,
            $user,
            Arr::except($data, WalkFeaturedImageFields::stateNames()),
        );

        return app(UpdateWalkFeaturedImage::class)->handle(
            $user,
            $walk,
            $this->featuredImageInput($walk, $data),
        );
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

    /** @param array<string, mixed> $state */
    private function featuredImageInput(Model $walk, array $state): WalkFeaturedImageInput
    {
        $source = $state['featured_image_source'] ?? 'none';
        $input = Arr::only($state, WalkFeaturedImageFields::stateNames());
        unset($input['featured_image_preview'], $input['featured_image_remove_fallback']);
        $input['featured_image_upload'] = WalkFeaturedImageFields::uploadedFile($input['featured_image_upload'] ?? null);

        if ($source === 'managed') {
            $input['featured_image_external_url'] = null;
        } elseif ($source === 'external') {
            $input['featured_image_upload'] = null;
        } elseif (($state['featured_image_remove_fallback'] ?? null) === 'reveal'
            && filled($walk->featured_image_media_id)
            && PublicImageReference::isAllowed($walk->featured_image_path)) {
            $input = [
                'featured_image_source' => 'external',
                'featured_image_upload' => null,
                'featured_image_external_url' => $walk->featured_image_path,
                'featured_image_alt_text' => $state['featured_image_alt_text'] ?? $walk->featured_image_alt_text,
            ];
        } else {
            $input = [
                'featured_image_source' => 'none',
                'featured_image_upload' => null,
                'featured_image_external_url' => null,
                'featured_image_alt_text' => null,
            ];
        }

        return WalkFeaturedImageInput::from($input);
    }
}
