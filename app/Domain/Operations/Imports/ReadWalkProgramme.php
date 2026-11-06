<?php

namespace App\Domain\Operations\Imports;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;
use ZipArchive;

final class ReadWalkProgramme
{
    /** @return list<array{row: int, values: array<string, mixed>}> */
    public function read(string $path): array
    {
        $this->validateArchive($path);
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Walks') {
                    continue;
                }
                $headers = null;
                $rows = [];
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number > 5000 || $row->getNumCells() > 128) {
                        $this->fail('The Walks sheet must contain at most 5,000 rows and 128 columns.');
                    }
                    $values = array_map(static fn ($cell) => $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue(), $row->getCells());
                    if ($headers === null) {
                        $labels = array_map(fn ($value): string => $this->header(is_string($value) ? $value : ''), $values);
                        if (in_array('title', $labels, true) && in_array('date', $labels, true) && in_array('walk leader name', $labels, true)) {
                            $nonempty = array_filter($labels);
                            if (count($nonempty) !== count(array_unique($nonempty))) {
                                $this->fail('The Walks sheet contains duplicate column headings.');
                            }
                            $headers = $labels;
                        } elseif ($number > 30) {
                            $this->fail('Could not find the programme column headings in the first 30 rows.');
                        }

                        continue;
                    }
                    $mapped = [];
                    foreach ($headers as $index => $header) {
                        if ($header !== '') {
                            $mapped[$header] = $values[$index] ?? null;
                        }
                    }
                    if (trim((string) ($mapped['title'] ?? '')) !== '' && $this->header((string) $mapped['title']) !== 'title') {
                        $rows[] = ['row' => $number, 'values' => $mapped];
                    }
                }
                if ($headers === null) {
                    $this->fail('The Walks sheet has no recognised programme column headings.');
                }

                return $rows;
            }
            $this->fail('This workbook does not contain a Walks sheet.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->fail('The workbook could not be read. Upload a valid, unencrypted XLSX file.');
        } finally {
            $reader->close();
        }
    }

    private function header(string $value): string
    {
        return Str::lower(Str::squish(str_replace(['†', '*'], '', $value)));
    }

    private function validateArchive(string $path): void
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) < 1 || filesize($path) > 5_242_880) {
            $this->fail('Choose an XLSX file no larger than 5 MB.');
        }
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            $this->fail('Choose a valid XLSX workbook.');
        }
        try {
            if ($archive->numFiles > 2000 || $archive->locateName('xl/workbook.xml') === false || $archive->locateName('[Content_Types].xml') === false) {
                $this->fail('Choose a valid XLSX workbook with no more than 2,000 archive entries.');
            }
            $total = 0;
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $entry = $archive->statIndex($i);
                $total += $entry['size'];
                if ($total > 67_108_864 || ($entry['encryption_method'] ?? 0) !== 0) {
                    $this->fail('The expanded workbook must be unencrypted and no larger than 64 MB.');
                }
            }
        } finally {
            $archive->close();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['workbook' => $message]);
    }
}
