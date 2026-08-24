<x-filament-panels::page>
    <section class="max-w-5xl rounded-xl border p-5" aria-labelledby="guided-import-heading">
        <h2 id="guided-import-heading" class="text-lg font-semibold">Guided CSV import</h2>
        <p class="mt-2 text-sm text-gray-600">Preview and dry run every mapped row before Waymark writes private drafts.</p>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div>
                <label class="block font-medium" for="import-definition">Record type</label>
                <select id="import-definition" wire:model="definition" class="mt-2 block min-h-11 w-full rounded-lg border px-3">
                    @foreach ($this->definitions() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-medium" for="csv-file">CSV file</label>
                <input id="csv-file" wire:model="csvFile" type="file" accept=".csv,text/csv,text/plain" class="mt-2 block min-h-11 w-full rounded-lg border p-2">
                @error('csvFile') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-filament::button class="mt-4" wire:click="inspectCsv" wire:loading.attr="disabled">Read CSV columns</x-filament::button>

        @if ($headers !== [])
            <fieldset class="mt-6 border-t pt-5">
                <legend class="font-semibold">Field mapping</legend>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    @foreach ($this->fields() as $field => $metadata)
                        <div>
                            <label class="block text-sm font-medium" for="mapping-{{ $field }}">
                                {{ $metadata['label'] }} @if ($metadata['required']) <span aria-label="required">*</span> @endif
                            </label>
                            <select id="mapping-{{ $field }}" wire:model="mapping.{{ $field }}" class="mt-1 block min-h-11 w-full rounded-lg border px-3">
                                <option value="">Do not import</option>
                                @foreach ($headers as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                @error('mapping') <p class="mt-3 text-sm text-danger-600">{{ $message }}</p> @enderror
                <x-filament::button class="mt-5" wire:click="previewImport" wire:loading.attr="disabled">Preview and dry run</x-filament::button>
            </fieldset>
        @endif

        @if ($previewSummary !== [])
            <section class="mt-6 border-t pt-5" aria-labelledby="preview-heading">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="preview-heading" class="font-semibold">Import preview</h3>
                    <button type="button" class="text-sm font-medium underline" wire:click="downloadImportReport">Download row report</button>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border p-3"><dt class="text-sm text-gray-600">Total</dt><dd class="text-xl font-semibold">{{ $previewSummary['total'] }}</dd></div>
                    <div class="rounded-lg border p-3"><dt class="text-sm text-gray-600">Valid</dt><dd class="text-xl font-semibold">{{ $previewSummary['valid'] }}</dd></div>
                    <div class="rounded-lg border p-3"><dt class="text-sm text-gray-600">Duplicates</dt><dd class="text-xl font-semibold">{{ $previewSummary['duplicates'] }}</dd></div>
                    <div class="rounded-lg border p-3"><dt class="text-sm text-gray-600">Invalid</dt><dd class="text-xl font-semibold">{{ $previewSummary['invalid'] }}</dd></div>
                </dl>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr class="border-b"><th class="p-2">Row</th><th class="p-2">Status</th><th class="p-2">Data</th><th class="p-2">Messages</th></tr></thead>
                        <tbody>
                            @foreach ($previewRows as $row)
                                <tr class="border-b align-top">
                                    <td class="p-2">{{ $row['row'] }}</td>
                                    <td class="p-2 font-medium">{{ ucfirst($row['status']) }}</td>
                                    <td class="p-2">{{ implode(' · ', array_filter($row['data'])) }}</td>
                                    <td class="p-2">{{ implode(' ', $row['messages']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 text-sm">Imports are all-or-nothing when any row is invalid. Matching duplicates are reported and skipped.</p>
                <x-filament::button class="mt-3" wire:click="runImport" wire:loading.attr="disabled" :disabled="$previewSummary['invalid'] > 0">Import valid private drafts</x-filament::button>
                @if ($resultSummary !== [])
                    <p class="mt-3 font-medium">
                        @if ($resultSummary['committed'])
                            Imported {{ $resultSummary['imported'] }} {{ Str::plural('row', $resultSummary['imported']) }}; skipped {{ $resultSummary['duplicates'] }} {{ Str::plural('duplicate', $resultSummary['duplicates']) }}.
                        @else
                            No rows were imported.
                        @endif
                    </p>
                @endif
            </section>
        @endif
    </section>
</x-filament-panels::page>
