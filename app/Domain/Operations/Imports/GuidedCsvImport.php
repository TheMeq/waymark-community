<?php

namespace App\Domain\Operations\Imports;

use App\Domain\Operations\Imports\Contracts\ImportDefinition;
use Illuminate\Support\Facades\DB;

final readonly class GuidedCsvImport
{
    private const int MAXIMUM_BYTES = 5_242_880;

    private const int MAXIMUM_ROWS = 5_000;

    public function __construct(private ImportDefinitionRegistry $definitions) {}

    /** @return list<string> */
    public function headers(string $path): array
    {
        return $this->read($path)['headers'];
    }

    /** @return array<string, array{label: string, required: bool}> */
    public function fields(string $definition): array
    {
        return $this->definitions->find($definition)->fields();
    }

    /** @param array<string, string> $mapping */
    public function preview(string $definition, string $path, array $mapping): ImportPreview
    {
        $importDefinition = $this->definitions->find($definition);
        $csv = $this->read($path);
        $this->validateMapping($importDefinition, $csv['headers'], $mapping);

        $identities = [];
        $previewRows = [];
        foreach ($csv['rows'] as $index => $row) {
            $values = [];
            foreach ($importDefinition->fields() as $field => $metadata) {
                $source = $mapping[$field] ?? null;
                $values[$field] = $source === null || $source === '' ? null : ($row[$source] ?? null);
            }

            $assessment = $importDefinition->assess($values);
            $status = 'valid';
            $messages = $assessment['messages'];
            if ($messages !== []) {
                $status = 'invalid';
            } elseif ($importDefinition->duplicateExists($assessment['normalized'])) {
                $status = 'duplicate';
                $messages[] = 'A matching record already exists in Waymark.';
            } elseif ($assessment['identity'] !== null && isset($identities[$assessment['identity']])) {
                $status = 'duplicate';
                $messages[] = 'A matching record already appears in this CSV.';
            }

            if ($assessment['identity'] !== null) {
                $identities[$assessment['identity']] = true;
            }
            $previewRows[] = [
                'row' => $index + 2,
                'status' => $status,
                'messages' => $messages,
                'data' => $values,
                'normalized' => $assessment['normalized'],
            ];
        }

        return new ImportPreview($csv['headers'], $previewRows);
    }

    /** @param array<string, string> $mapping */
    public function execute(string $definition, string $path, array $mapping): ImportResult
    {
        $importDefinition = $this->definitions->find($definition);
        $preview = $this->preview($definition, $path, $mapping);
        if ($preview->invalidRows() > 0) {
            return new ImportResult(0, $preview->duplicateRows(), $preview->invalidRows(), false, $preview);
        }

        $imported = 0;
        $duplicates = $preview->duplicateRows();
        DB::transaction(function () use ($preview, $importDefinition, &$imported, &$duplicates): void {
            foreach ($preview->rows as $row) {
                if ($row['status'] !== 'valid') {
                    continue;
                }
                if ($importDefinition->duplicateExists($row['normalized'])) {
                    $duplicates++;

                    continue;
                }
                $importDefinition->import($row['normalized']);
                $imported++;
            }
        });

        return new ImportResult($imported, $duplicates, 0, true, $preview);
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, string|null>>}
     */
    private function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException('Choose a readable CSV file.');
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > self::MAXIMUM_BYTES) {
            throw new \InvalidArgumentException('The CSV must be between 1 byte and 5 MB.');
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \InvalidArgumentException('The CSV could not be opened.');
        }

        try {
            $rawHeaders = fgetcsv($stream, 0, ',', '"', '');
            if (! is_array($rawHeaders) || $rawHeaders === []) {
                throw new \InvalidArgumentException('The CSV needs a header row.');
            }
            $headers = array_map(function (mixed $header, int $index): string {
                $value = trim((string) $header);

                return $index === 0 ? preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value : $value;
            }, $rawHeaders, array_keys($rawHeaders));
            if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) {
                throw new \InvalidArgumentException('CSV headers must be unique and non-empty.');
            }

            $rows = [];
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if (count($values) === 1 && trim((string) $values[0]) === '') {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new \InvalidArgumentException('Every CSV row must contain the same number of columns as the header.');
                }
                $rows[] = array_combine($headers, array_map(fn (mixed $value): ?string => trim((string) $value) === '' ? null : trim((string) $value), $values));
                if (count($rows) > self::MAXIMUM_ROWS) {
                    throw new \InvalidArgumentException('A guided import may contain at most 5,000 rows.');
                }
            }
        } finally {
            fclose($stream);
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('The CSV does not contain any data rows.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $mapping
     */
    private function validateMapping(ImportDefinition $definition, array $headers, array $mapping): void
    {
        foreach ($definition->fields() as $field => $metadata) {
            $source = $mapping[$field] ?? null;
            if ($metadata['required'] && ($source === null || $source === '')) {
                throw new \InvalidArgumentException('Map every required field before previewing the import.');
            }
            if ($source !== null && $source !== '' && ! in_array($source, $headers, true)) {
                throw new \InvalidArgumentException('A mapped CSV column no longer exists.');
            }
        }
    }
}
