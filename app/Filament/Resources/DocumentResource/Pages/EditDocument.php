<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Domain\Governance\Actions\ApproveControlledDocument;
use App\Domain\Governance\Actions\PublishDocumentVersion;
use App\Domain\Governance\Actions\UploadDocumentVersion;
use App\Domain\Governance\Models\DocumentVersion;
use App\Filament\Resources\DocumentResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadVersion')->label('Upload version')->form([
                FileUpload::make('document')->storeFiles(false)->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.oasis.opendocument.text'])->maxSize(20480)->required(),
            ])->action(function (array $data): void {
                $upload = $data['document'] ?? null;
                if (! $upload instanceof TemporaryUploadedFile) {
                    throw ValidationException::withMessages(['document' => 'Choose a document file.']);
                }
                /** @var User $actor */
                $actor = auth()->user();
                app(UploadDocumentVersion::class)->handle($actor, $this->getRecord(), $upload);
            }),
            Action::make('approve')->label('Approve controlled document')->visible(fn (): bool => $this->getRecord()->controlled && $this->getRecord()->approval_status !== 'approved')->requiresConfirmation()->action(function (): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(ApproveControlledDocument::class)->handle($actor, $this->getRecord());
                $this->refreshFormData(['approval_status']);
            }),
            Action::make('publishVersion')->label('Publish version')->form([
                Select::make('version_id')->label('Version')->options(fn (): array => $this->getRecord()->versions()->whereNull('published_at')->orderByDesc('version_number')->pluck('version_number', 'id')->map(fn ($number): string => 'Version '.$number)->all())->required(),
            ])->action(function (array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                app(PublishDocumentVersion::class)->handle($actor, $this->getRecord(), DocumentVersion::query()->findOrFail($data['version_id']));
            }),
            DeleteAction::make(),
        ];
    }
}
