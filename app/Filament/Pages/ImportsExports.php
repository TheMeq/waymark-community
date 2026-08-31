<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Imports\GuidedCsvImport;
use App\Domain\Operations\Imports\ImportDefinitionRegistry;
use App\Domain\Operations\Imports\ImportPreview;
use App\Domain\Operations\Portability\Actions\CreatePortabilityExport;
use App\Domain\Operations\Portability\Models\PortabilityExportRun;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Str;
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
        $this->validate(['csvFile' => ['required', 'file', 'max:5120']]);
        $this->resetPreview();

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
        $this->resetPreview();

        try {
            $this->storePreview(app(GuidedCsvImport::class)->preview(
                $this->definition,
                $this->csvPath(),
                $this->mapping,
            ));
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('mapping', $exception->getMessage());
        }
    }

    public function runImport(): void
    {
        try {
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
        ];
        $this->previewRows = array_map(
            fn (array $row): array => [
                'row' => $row['row'],
                'status' => $row['status'],
                'messages' => $row['messages'],
                'data' => $row['data'],
            ],
            array_slice($preview->rows, 0, 100),
        );
        $this->reportCsv = $preview->errorReportCsv();
    }

    private function resetPreview(): void
    {
        $this->previewSummary = [];
        $this->previewRows = [];
        $this->resultSummary = [];
        $this->reportCsv = '';
        $this->resetErrorBag();
    }
}
