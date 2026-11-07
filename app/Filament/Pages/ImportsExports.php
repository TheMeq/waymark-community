<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Operations\Imports\GuidedCsvImport;
use App\Domain\Operations\Imports\ImportDefinitionRegistry;
use App\Domain\Operations\Imports\ImportPreview;
use App\Domain\Operations\Portability\Actions\CreatePortabilityExport;
use App\Domain\Operations\Portability\Models\PortabilityExportRun;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ImportsExports extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Imports and exports';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Imports and exports';

    protected static ?int $navigationSort = 50;

    protected static string|array $routeMiddleware = ['sensitive.confirmed'];

    protected string $view = 'filament.pages.imports-exports';

    public string $definition = 'walks';

    public ?TemporaryUploadedFile $csvFile = null;

    /** @var list<string> */
    public array $headers = [];

    /** @var array<string, string> */
    public array $mapping = [];

    /** @var array{total: int, valid: int, duplicates: int, invalid: int}|array{} */
    public array $previewSummary = [];

    /** @var list<array{row: int, status: string, messages: list<string>, data: array<string, string|null>}> */
    public array $previewRows = [];

    /** @var array{imported: int, duplicates: int, invalid: int, committed: bool}|array{} */
    public array $resultSummary = [];

    public string $reportCsv = '';

    #[Locked]
    public string $previewFingerprint = '';

    public string $portabilityMessage = '';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'imports-exports';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts);
    }

    /** @return array<string, string> */
    public function definitions(): array
    {
        return collect(app(ImportDefinitionRegistry::class)->all())
            ->mapWithKeys(fn ($definition): array => [$definition->key() => $definition->label()])
            ->all();
    }

    /** @return array<string, array{label: string, required: bool}> */
    public function fields(): array
    {
        return app(GuidedCsvImport::class)->fields($this->definition);
    }

    public function inspectCsv(): void
    {
        $this->authorizeCsvImport();
        $this->validate(['csvFile' => ['required', 'file', 'extensions:csv', 'max:5120']]);
        $this->resetPreview();
        $this->headers = [];
        $this->mapping = [];

        try {
            $this->headers = app(GuidedCsvImport::class)->headers($this->csvPath());
            $this->mapping = [];
            foreach ($this->fields() as $field => $metadata) {
                $match = collect($this->headers)->first(
                    fn (string $header): bool => Str::lower($header) === Str::lower($field),
                );
                $this->mapping[$field] = $match ?? '';
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('csvFile', $exception->getMessage());
        }
    }

    public function previewImport(): void
    {
        $this->authorizeCsvImport();
        $this->resetPreview();

        try {
            $preview = app(GuidedCsvImport::class)->preview(
                $this->definition,
                $this->csvPath(),
                $this->mapping,
            );
            $this->storePreview($preview);
            $this->previewFingerprint = $this->fingerprint($preview);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('mapping', $exception->getMessage());
        }
    }

    public function runImport(): void
    {
        $this->authorizeCsvImport();
        if ($this->previewFingerprint === '') {
            $this->addError('mapping', 'Preview the current CSV, import type and mapping before importing.');

            return;
        }
        try {
            $preview = app(GuidedCsvImport::class)->preview($this->definition, $this->csvPath(), $this->mapping);
            if (! hash_equals($this->previewFingerprint, $this->fingerprint($preview))) {
                $this->previewFingerprint = '';
                $this->storePreview($preview);
                $this->addError('mapping', 'The CSV, import type, mapping or existing records changed. Preview again.');

                return;
            }
            $this->previewFingerprint = '';
            $result = app(GuidedCsvImport::class)->execute(
                $this->definition,
                $this->csvPath(),
                $this->mapping,
            );
            $this->storePreview($result->preview);
            $this->resultSummary = [
                'imported' => $result->imported,
                'duplicates' => $result->duplicatesSkipped,
                'invalid' => $result->invalid,
                'committed' => $result->committed,
                'skipped' => $result->preview->skippedRows(),
            ];

            Notification::make()
                ->{$result->committed ? 'success' : 'danger'}()
                ->title($result->committed
                    ? "Imported {$result->imported} row".($result->imported === 1 ? '' : 's')
                    : 'Import refused — correct every invalid row and preview again')
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('mapping', $exception->getMessage());
        }
    }

    public function downloadImportReport(): StreamedResponse
    {
        abort_if($this->reportCsv === '', 404);
        $contents = $this->reportCsv;

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            'waymark-'.$this->definition.'-import-report.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /** @return list<PortabilityExportRun> */
    public function portabilityExports(): array
    {
        return PortabilityExportRun::query()->latest('id')->limit(20)->get()->all();
    }

    public function startPortabilityExport(): void
    {
        try {
            app(CreatePortabilityExport::class)->start();
            $this->portabilityMessage = 'Export queued';
            Notification::make()->success()->title('Export queued')->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->portabilityMessage = $exception->getMessage();
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
    }

    public function advancePortabilityExport(): void
    {
        $run = PortabilityExportRun::query()->whereIn('status', ['queued', 'running'])->oldest('id')->first();
        if (! $run instanceof PortabilityExportRun) {
            return;
        }

        try {
            app(CreatePortabilityExport::class)->advance($run);
            $this->portabilityMessage = 'Export advanced';
            Notification::make()->success()->title('Export advanced')->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->portabilityMessage = 'Export failed — review system health and retry';
            Notification::make()->danger()->title('Export failed — review system health and retry')->send();
        }
    }

    private function csvPath(): string
    {
        if (! $this->csvFile instanceof TemporaryUploadedFile) {
            throw new \InvalidArgumentException('Choose a CSV file first.');
        }

        return $this->csvFile->getRealPath();
    }

    private function storePreview(ImportPreview $preview): void
    {
        $this->previewSummary = [
            'total' => $preview->totalRows(),
            'valid' => $preview->validRows(),
            'duplicates' => $preview->duplicateRows(),
            'invalid' => $preview->invalidRows(),
            'skipped' => $preview->skippedRows(),
        ];
        $this->previewRows = array_map(
            fn (array $row): array => [
                'row' => $row['row'],
                'status' => $row['status'],
                'messages' => $row['messages'],
                'data' => Arr::only($row['data'], ['title', 'starts_at', 'leader_name', 'leader_email', 'grade', 'distance', 'destination', 'venue_name', 'review_notes']),
            ],
            array_slice($preview->rows, 0, 100),
        );
        $this->reportCsv = $preview->errorReportCsv();
    }

    private function resetPreview(): void
    {
        $this->previewFingerprint = '';
        $this->previewSummary = [];
        $this->previewRows = [];
        $this->resultSummary = [];
        $this->reportCsv = '';
        $this->resetErrorBag();
    }

    public function updated(string $property): void
    {
        if ($property === 'definition' || $property === 'csvFile') {
            $this->headers = $this->mapping = [];
            $this->resetPreview();
        } elseif (str_starts_with($property, 'mapping')) {
            $this->previewFingerprint = '';
            $this->resultSummary = [];
        }
    }

    private function fingerprint(ImportPreview $preview): string
    {
        return hash('sha256', json_encode([
            $this->definition, hash_file('sha256', $this->csvPath()), $this->mapping, $preview->rows,
        ], JSON_THROW_ON_ERROR));
    }

    private function authorizeCsvImport(): void
    {
        abort_unless(self::canAccess(), 403);
        $actor = auth()->user();
        $capability = match ($this->definition) {
            'walks' => ModuleCapability::ManageAllWalks,
            'holidays' => ModuleCapability::ManageHolidays,
            'events' => ModuleCapability::ManageSocials,
            default => null,
        };
        abort_unless($capability !== null && $actor->hasCapability($capability), 403);
        $assurance = app(SensitiveActionAssurance::class);
        $session = app('session.store');
        abort_unless($assurance->hasRecentPasswordConfirmation($actor, $session)
            && (! $assurance->requiresSecondFactor($actor) || $assurance->hasRecentSecondFactorConfirmation($actor, $session)), 403);
    }
}
