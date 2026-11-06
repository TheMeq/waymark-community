<?php

namespace Tests\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

trait ProgrammeWorkbook
{
    private array $programmePaths = [];

    protected function programmeRow(string $title, array $overrides = []): array
    {
        return array_replace([
            'Title' => $title, 'Date' => '2026-11-07', 'Start Time' => '10:00',
            'Walk leader name' => 'Visiting Leader', 'Difficulty' => 'Moderate',
            'Description' => 'Ridge and valley circuit', 'Additional details' => null,
            'Dist Miles' => 8.5, 'Distance km' => null, 'Ascent feet' => null, 'Ascent metres' => null,
            'Google Satnav & What3Words' => null, 'Starting location' => null,
            'Starting postcode' => null, 'Starting gridref' => null,
            'Est finish time (not guaranteed)' => '14:30', 'Dog friendly' => null,
            'Public Trans' => null, 'Toilets available' => null, 'Refreshments available' => null,
        ], $overrides);
    }

    protected function workbook(array $rows, string $sheet = 'Walks'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'waymark-programme-').'.xlsx';
        $this->programmePaths[] = $path;
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($sheet);
        $writer->addRow(Row::fromValues(['Programme heading']));
        for ($i = 0; $i < 6; $i++) {
            $writer->addRow(Row::fromValues([]));
        }
        $writer->addRow(Row::fromValues(array_keys($rows[0])));
        foreach ($rows as $row) {
            $styles = [];
            foreach (array_values($row) as $index => $value) {
                if ($value instanceof \DateTimeInterface) {
                    $styles[$index] = (new Style)->setFormat($value->format('Y') < 1900 ? 'hh:mm' : 'yyyy-mm-dd');
                }
            }
            $writer->addRow(Row::fromValuesWithStyles(array_values($row), null, $styles));
        }
        $writer->close();

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->programmePaths as $path) {
            @unlink($path);
            @unlink(substr($path, 0, -5));
        }
        parent::tearDown();
    }
}
