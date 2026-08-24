<?php

namespace App\Domain\Operations\Imports;

final readonly class ImportPreview
{
    /**
     * @param  list<string>  $headers
     * @param  list<array{row: int, status: string, messages: list<string>, data: array<string, string|null>, normalized: array<string, mixed>}>  $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {}

    public function totalRows(): int
    {
        return count($this->rows);
    }

    public function validRows(): int
    {
        return $this->countStatus('valid');
    }

    public function duplicateRows(): int
    {
        return $this->countStatus('duplicate');
    }

    public function invalidRows(): int
    {
        return $this->countStatus('invalid');
    }

    public function errorReportCsv(): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('The import report could not be created.');
        }

        fputcsv($stream, ['row', 'status', 'messages'], ',', '"', '');
        foreach ($this->rows as $row) {
            fputcsv($stream, [$row['row'], $row['status'], implode('; ', $row['messages'])], ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new \RuntimeException('The import report could not be read.');
        }

        return $contents;
    }

    private function countStatus(string $status): int
    {
        return count(array_filter($this->rows, fn (array $row): bool => $row['status'] === $status));
    }
}
