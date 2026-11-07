<?php

namespace App\Filament\Forms;

use App\Domain\Accounts\Enums\ModuleCapability;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;

final class ImportedEventFields
{
    public static function timeUnknown(): Checkbox
    {
        return Checkbox::make('start_time_unknown')->label('Start time not recorded')->default(false)
            ->helperText('Only the date will be shown publicly. Uncheck when you confirm a start time.');
    }

    public static function source(): Textarea
    {
        return Textarea::make('import_source_record')->label('Imported source record')
            ->formatStateUsing(fn (?Model $record): string => self::sourceText($record))
            ->visible(fn (?Model $record): bool => $record?->event?->import_source !== null
                && (auth()->user()?->hasCapability(ModuleCapability::ManageAccounts) ?? false))
            ->disabled()->dehydrated(false)->rows(8)->columnSpanFull();
    }

    public static function sourceText(?Model $record): string
    {
        $source = $record?->event?->import_source ?? [];
        $sections = [];
        if (filled($source['review_notes'] ?? null)) {
            $sections[] = 'Review notes: '.$source['review_notes'];
        }
        foreach ($source['payload'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lines = [($row['sheet'] ?? 'Source').', row '.($row['row'] ?? '?')];
            foreach ($row['cells'] ?? [] as $coordinate => $cell) {
                if (is_array($cell)) {
                    $lines[] = $coordinate.(filled($cell['header'] ?? null) ? ' · '.$cell['header'] : '').': '.(string) ($cell['value'] ?? '');
                }
            }
            $sections[] = implode("\n", $lines);
        }

        return implode("\n\n", $sections);
    }
}
