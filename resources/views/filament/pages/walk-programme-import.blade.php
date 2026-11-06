<x-filament-panels::page>
    <section class="max-w-7xl rounded-xl border p-5" aria-labelledby="workbook-heading">
        <h2 id="workbook-heading" class="text-lg font-semibold">Upload programme</h2>
        <p class="mt-2 text-sm">XLSX, up to 5 MB. Future dated walks from the Walks sheet only. Historical entries, undated ideas and entries without a walking grade are excluded.</p>
        <div class="mt-4">
            <label for="programme-workbook" class="block text-sm font-medium">Programme workbook</label>
            <input id="programme-workbook" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" wire:model="workbook" class="mt-2 block w-full">
            @error('workbook') <p class="mt-2 text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
            <p wire:loading wire:target="workbook" class="mt-2 text-sm" role="status">Uploading workbook…</p>
        </div>
        <x-filament::button class="mt-4" wire:click="readWorkbook" wire:loading.attr="disabled">Read workbook</x-filament::button>
    </section>

    @if ($previewSummary !== [])
        <section class="mt-6 max-w-7xl rounded-xl border p-5" aria-labelledby="leader-mapping-heading">
            <h2 id="leader-mapping-heading" class="text-lg font-semibold">Leader mapping</h2>
            <p class="mt-2 text-sm">Keep the programme name, or link an active registered leader. Name-only and unconfirmed leaders remain managed by administrators. Linking an account gives that leader access to the draft.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($sourceLeaders as $key => $name)
                    <div wire:key="leader-{{ $key }}">
                        <label for="leader-{{ $key }}" class="block text-sm font-medium">{{ $name }}</label>
                        <select id="leader-{{ $key }}" wire:model.live="leaderMapping.{{ $key }}" class="mt-1 block min-h-11 w-full rounded-lg border px-3">
                            <option value="">Name only — admin managed</option>
                            @foreach ($this->leaderOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="mt-6 max-w-7xl rounded-xl border p-5" aria-labelledby="programme-preview-heading">
            <h2 id="programme-preview-heading" class="text-lg font-semibold">Programme preview</h2>
            <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div><dt>Ready</dt><dd class="text-xl font-semibold">{{ $previewSummary['valid'] }}</dd></div>
                <div><dt>Duplicates</dt><dd class="text-xl font-semibold">{{ $previewSummary['duplicates'] }}</dd></div>
                <div><dt>Invalid</dt><dd class="text-xl font-semibold">{{ $previewSummary['invalid'] }}</dd></div>
                <div><dt>Excluded</dt><dd class="text-xl font-semibold">{{ $previewSummary['excluded'] }}</dd></div>
            </dl>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="p-2">Import</th><th class="p-2">Row</th><th class="p-2">Walk</th><th class="p-2">Leader</th><th class="p-2">Status and review notes</th></tr></thead>
                    <tbody>
                        @foreach ($previewRows as $row)
                            <tr class="border-b align-top" wire:key="programme-row-{{ $row['row'] }}">
                                <td class="p-2">
                                    @if ($row['candidate'])
                                        <input type="checkbox" value="{{ $row['row'] }}" wire:model.live="selectedRows" aria-label="Import row {{ $row['row'] }}: {{ $row['data']['title'] }}" class="min-h-5 min-w-5">
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="p-2">{{ $row['row'] }}</td>
                                <td class="p-2"><strong>{{ $row['data']['title'] }}</strong><br>{{ $row['data']['starts_at'] ?? 'Date or time unconfirmed' }}<br>{{ implode(' · ', array_filter([$row['data']['grade'], $row['data']['distance'] ?? null, $row['data']['ascent'] ?? null])) }}<br>{{ $row['data']['location'] ?? '' }}</td>
                                <td class="p-2">{{ $row['data']['leader_name'] ?? 'To be confirmed' }}
                                    @if ($row['data']['linked_account'] ?? null)
                                        <br><span>Linked: {{ $this->leaderOptions()[$row['data']['linked_account']] ?? 'Account unavailable' }}</span>
                                    @endif
                                </td>
                                <td class="p-2"><strong>{{ ucfirst($row['status']) }}</strong>
                                    @foreach ($row['messages'] as $message)
                                        <p class="mt-1">{{ $message }}</p>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('selection') <p class="mt-3 text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
            @error('preview') <p class="mt-3 text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
            <div class="mt-5 flex flex-wrap gap-3">
                <x-filament::button color="gray" wire:click="previewImport" wire:loading.attr="disabled">Preview selected rows</x-filament::button>
                <x-filament::button wire:click="runImport" wire:loading.attr="disabled" :disabled="$previewFingerprint === '' || $previewSummary['invalid'] > 0 || $previewSummary['valid'] === 0">Create private drafts</x-filament::button>
            </div>
            <p class="mt-3 text-sm">Correct invalid data in the workbook and upload it again, or deselect those rows. Existing walks are skipped and never overwritten. All imported walks remain private drafts until reviewed and published.</p>
            @if ($resultSummary !== [])
                <p class="mt-4 font-medium" role="status">
                    @if ($resultSummary['committed'])
                        Created {{ $resultSummary['imported'] }} private {{ Str::plural('draft', $resultSummary['imported']) }}; skipped {{ $resultSummary['duplicates'] }} {{ Str::plural('duplicate', $resultSummary['duplicates']) }}.
                        <a class="underline" href="{{ \App\Filament\Resources\WalkResource::getUrl() }}">Review walks</a>
                    @else
                        No walks were imported.
                    @endif
                </p>
            @endif
        </section>
    @endif
</x-filament-panels::page>
