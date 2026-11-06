<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Queries\EligibleWalkLeadersQuery;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Operations\Imports\ImportPreview;
use App\Domain\Operations\Imports\WalkProgrammeImport as ProgrammeImporter;
use App\Models\User;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class WalkProgrammeImport extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Import walk programme';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 51;

    protected static string|array $routeMiddleware = ['sensitive.confirmed'];

    protected string $view = 'filament.pages.walk-programme-import';

    public ?TemporaryUploadedFile $workbook = null;

    /** @var array<string, string> */
    public array $leaderMapping = [];

    /** @var list<string> */
    public array $selectedRows = [];

    #[Locked]
    public array $sourceLeaders = [];

    #[Locked]
    public array $previewRows = [];

    #[Locked]
    public array $previewSummary = [];

    #[Locked]
    public array $resultSummary = [];

    #[Locked]
    public string $previewFingerprint = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasVerifiedEmail()
            && $user->hasCapability(ModuleCapability::ManageAccounts)
            && $user->hasCapability(ModuleCapability::ManageAllWalks);
    }

    public function mount(): void
    {
        $this->authorizeImport();
    }

    /** @return array<int, string> */
    public function leaderOptions(): array
    {
        return app(EligibleWalkLeadersQuery::class)->options();
    }

    public function updated(string $property): void
    {
        if ($property === 'workbook') {
            $this->sourceLeaders = $this->previewRows = $this->previewSummary = [];
            $this->leaderMapping = $this->selectedRows = [];
        }
        if ($property === 'workbook' || str_starts_with($property, 'leaderMapping') || str_starts_with($property, 'selectedRows')) {
            $this->previewFingerprint = '';
            $this->resultSummary = [];
        }
    }

    public function readWorkbook(): void
    {
        $this->authorizeImport();
        $this->resetErrorBag();
        $this->sourceLeaders = $this->leaderMapping = $this->selectedRows = [];
        $this->previewRows = $this->previewSummary = $this->resultSummary = [];
        $this->previewFingerprint = '';
        $preview = app(ProgrammeImporter::class)->preview($this->workbookPath());
        foreach ($preview->rows as $row) {
            if ($row['status'] === 'excluded') {
                continue;
            }
            $this->selectedRows[] = (string) $row['row'];
            $name = $row['data']['leader_name'];
            if ($name !== null) {
                $key = hash('sha256', $name);
                $this->sourceLeaders[$key] = $name;
                $this->leaderMapping[$key] = '';
            }
        }
        $this->storePreview($preview);
    }

    public function previewImport(): void
    {
        $this->authorizeImport();
        $this->resetErrorBag();
        $this->previewFingerprint = '';
        $this->resultSummary = [];
        $preview = app(ProgrammeImporter::class)->preview($this->workbookPath(), $this->leaderMapping, $this->selectedRows);
        $this->storePreview($preview);
        $this->previewFingerprint = $this->fingerprint($preview);
    }

    public function runImport(): void
    {
        $this->authorizeImport();
        $this->resetErrorBag();
        if ($this->previewFingerprint === '') {
            $this->addError('preview', 'Preview the selected rows and leader mappings before importing.');

            return;
        }
        $path = $this->workbookPath();
        $preview = app(ProgrammeImporter::class)->preview($path, $this->leaderMapping, $this->selectedRows);
        if (! hash_equals($this->previewFingerprint, $this->fingerprint($preview))) {
            $this->previewFingerprint = '';
            $this->storePreview($preview);
            $this->addError('preview', 'The workbook, mappings or matching walks have changed. Preview again before importing.');

            return;
        }
        $this->previewFingerprint = '';
        $result = app(ProgrammeImporter::class)->execute($path, $this->leaderMapping, $this->selectedRows, auth()->user());
        $this->storePreview($result->preview);
        $this->resultSummary = [
            'imported' => $result->imported, 'duplicates' => $result->duplicatesSkipped,
            'invalid' => $result->invalid, 'committed' => $result->committed,
        ];
        if (! $result->committed) {
            $this->addError('preview', 'Correct or deselect invalid rows, then preview again. No walks were imported.');
        }
    }

    private function workbookPath(): string
    {
        $this->validate(['workbook' => ['required', 'file', 'extensions:xlsx', 'max:5120']]);

        return $this->workbook->getRealPath();
    }

    private function storePreview(ImportPreview $preview): void
    {
        $this->previewSummary = [
            'total' => $preview->totalRows(), 'valid' => $preview->validRows(),
            'duplicates' => $preview->duplicateRows(), 'invalid' => $preview->invalidRows(),
            'excluded' => count(array_filter($preview->rows, fn (array $row): bool => $row['status'] === 'excluded')),
        ];
        $this->previewRows = array_map(static fn (array $row): array => [
            'row' => $row['row'], 'status' => $row['status'], 'messages' => $row['messages'], 'data' => $row['data'],
            'candidate' => $row['status'] !== 'excluded' || in_array('Not selected for import.', $row['messages'], true),
        ], $preview->rows);
    }

    private function fingerprint(ImportPreview $preview): string
    {
        return hash('sha256', json_encode([
            hash_file('sha256', $this->workbook->getRealPath()), $this->leaderMapping, $this->selectedRows, $preview->rows,
        ], JSON_THROW_ON_ERROR));
    }

    private function authorizeImport(): void
    {
        abort_unless(self::canAccess(), 403);
        $assurance = app(SensitiveActionAssurance::class);
        $user = auth()->user();
        $session = app('session.store');
        abort_unless($assurance->hasRecentPasswordConfirmation($user, $session)
            && (! $assurance->requiresSecondFactor($user) || $assurance->hasRecentSecondFactorConfirmation($user, $session)), 403);
    }
}
