# Three CSV Programme Import Implementation Plan

**Goal:** Replace the bespoke XLSX upload with lossless offline conversion of the supplied workbook to walks.csv, holidays.csv and events.csv, and extend the existing guided CSV importer to support all three.

**Approved decisions:** On 7 November 2026 the user approved keeping name-only leaders and the local reverse-proxy upload fix, removing the XLSX screen, including all worksheets and dates, and allowing date-only historical records with the time shown as not recorded. Undated ideas remain in the CSV but are skipped until dated. Imports remain private drafts; no live import or publication is automatic.

**Architecture:** Extend existing ImportDefinition implementations and guided CSV preview. Add Event.start_time_unknown and private Event.import_source metadata. Store known dates at midnight only as an internal representation when time is unknown; public pages, structured data and calendars must not invent a midnight start. Keep existing leader schema/migration and upload fix intact. CSV source provenance/payload preserves every nonempty workbook cell, including copied material and original formulas, without exposing contact details publicly.

## Tasks

- [x] Offline converter: inspect all sheets, classify walks/overnight holidays/social events, normalise dates and fractions, deduplicate conservative exact matches and retain all source references/cells. Produce private Desktop CSV files plus reconciliation manifest, not repository data. Test with synthetic workbooks and reconcile actual cell coverage.
- [x] Definitions: implement holidays/events and extend walks for name-only leaders, configured units, detailed fields, date-only records, source metadata and duplicate identity independent of leader. Preserve legacy CSV compatibility; reject invalid supplied account links or measurements. TDD focused domain tests.
- [x] Framework/UI: support explicit import_enabled=false skipped rows, warnings, private provenance, preview freshness and safe mutation authorization. Register three types, auto-map generated headers and retain downloadable row reports. Recheck duplicates inside a serialized transaction. TDD skipped-row and UI tests.
- [x] Date precision/schema: add migration and model attributes; show unknown times honestly across public detail, favourites, Leader Hub, holiday children, calendar and structured data. Preserve/edit unknown-time flags in walk/social/holiday editors. Add private source display and tests. Regenerate fresh-install manifest.
- [x] Remove XLSX-only reader/normalizer/service/page/view/tests and direct dependency declaration. Retain unrelated and previously deployed migrations. Update usage documentation; run affected tests and actual three-CSV dry runs against SQLite memory. No generated spreadsheet/member source data in Git.

## Acceptance checks

- Three CSV files contain data from every worksheet, with every nonempty cell accounted for in private source payload or metadata.
- Ambiguous classifications/date inference/incomplete records are explicitly recorded in review notes and reconciliation manifest; no invented leader accounts, dates, holiday durations or confirmed times.
- Current and historical records import as private drafts. Disabled undated/auxiliary records are preserved but skipped without blocking valid rows.
- Date-only historical walk pages can later be published and receive photos through the existing authenticated/moderated upload process; draft galleries remain unavailable publicly.
- Re-import skips duplicates; changes to upload/type/mapping/selection invalidate an earlier preview.
- Existing user modification to AGENTS.md remains untouched. No server data is deleted and no database rollback is performed.


## Completed validation and delivery

- Private output folder: `C:/Users/richa/Desktop/Waymark CSV Import`. Exactly three CSV data files: 607 walks (581 enabled, 26 undated/disabled), 21 holidays and 32 events. Supporting README, manifest and in-memory import-validation report remain outside Git.
- Source reconciliation verified all 9,837 nonempty cells, including their values, across Walks, Historic and Sheet1 against source_payload in the exported CSVs. Copied far-right Historic material was retained privately, not interpreted as additional events.
- Actual files imported into SQLite memory with matching configured grades: 634 private drafts, zero public events, zero invalid enabled rows. Repeated imports created zero records. No actual site import or database rollback occurred.
- Eight offline conversion regression tests passed. Relevant synthetic PHP/UI tests cover all types, source data, name-only ownership, date precision, stale previews, invalid account links, fractions and units.
- Broader related PHP run: 727 tests, 726 passed, 4,385 assertions. The only failure is the already-reported BackupRestoreTest favicon assertion (absolute expected URL versus relative rendered URL), unrelated to these changes.
- After the final shared date-precision validation correction, 239 affected walk/social/holiday/calendar/import tests passed with 1,521 assertions. A separate calendar/schema/date-precision check passed 14 tests, 80 assertions before that correction. PHP formatting, Composer validation and diff checks passed.
- Review issue fixed: every manual create/update path now rejects a non-midnight start marked unknown, and editors can confirm a time by clearing the flag. Private original-cell display is administrator-only and uses readable sheet/row/cell text rather than raw JSON.
- The source workbook's malformed print header/footer generates an openpyxl warning; every nonempty worksheet cell was independently reconciled. No worksheet data was omitted because of that print-setting warning.
- Name-only leader support, its prior migration, local HTTPS proxy configuration and existing site records remain intact. Only XLSX-specific application code, tests and direct dependency declaration were removed.
- Browser automation, MySQL/MariaDB execution and live deployment were not run. User explicitly chose to review and upload the CSVs themselves.

## Changed files

- `.gitignore`
- `app/Domain/Content/Presentation/PublicSeo.php`
- `app/Domain/Events/Calendar/IcsCalendar.php`
- `app/Domain/Events/Models/Event.php`
- `app/Domain/Events/Validation/ValidStartTimePrecision.php`
- `app/Domain/Holidays/Actions/CreateHoliday.php`
- `app/Domain/Holidays/Actions/UpdateHoliday.php`
- `app/Domain/Operations/Imports/Contracts/ImportDefinition.php`
- `app/Domain/Operations/Imports/CsvImportSupport.php`
- `app/Domain/Operations/Imports/Definitions/EventImportDefinition.php`
- `app/Domain/Operations/Imports/Definitions/HolidayImportDefinition.php`
- `app/Domain/Operations/Imports/Definitions/WalkImportDefinition.php`
- `app/Domain/Operations/Imports/GuidedCsvImport.php`
- `app/Domain/Operations/Imports/ImportDefinitionRegistry.php`
- `app/Domain/Operations/Imports/ImportPreview.php`
- `app/Domain/Operations/Imports/NormaliseWalkProgrammeRow.php`
- `app/Domain/Operations/Imports/ReadWalkProgramme.php`
- `app/Domain/Operations/Imports/WalkProgrammeImport.php`
- `app/Domain/Socials/Actions/CreateSocial.php`
- `app/Domain/Socials/Actions/UpdateSocial.php`
- `app/Domain/Walks/Actions/SaveWalkDraft.php`
- `app/Domain/Walks/Actions/UpdateWalk.php`
- `app/Filament/Forms/ImportedEventFields.php`
- `app/Filament/Pages/ImportsExports.php`
- `app/Filament/Pages/WalkProgrammeImport.php`
- `app/Filament/Resources/HolidayResource.php`
- `app/Filament/Resources/HolidayResource/Pages/EditHoliday.php`
- `app/Filament/Resources/SocialResource.php`
- `app/Filament/Resources/SocialResource/Pages/EditSocial.php`
- `app/Filament/Resources/WalkResource.php`
- `app/Filament/Resources/WalkResource/Pages/CreateWalk.php`
- `app/Filament/Resources/WalkResource/Support/WalkFormData.php`
- `app/ViewModels/FavouritesPageViewModel.php`
- `app/ViewModels/LeaderHubPageViewModel.php`
- `app/ViewModels/PublicEventCardViewModel.php`
- `app/ViewModels/PublicHolidayDetailViewModel.php`
- `app/ViewModels/PublicSocialDetailViewModel.php`
- `app/ViewModels/PublicWalkDetailViewModel.php`
- `composer.json`
- `composer.lock`
- `database/migrations/2026_11_07_100000_add_event_import_source_and_date_precision.php`
- `docs/product/walk-programme-import.md`
- `docs/superpowers/plans/2026-11-07-three-csv-programme-import.md`
- `resources/installation/fresh-schema.json`
- `resources/views/filament/pages/imports-exports.blade.php`
- `resources/views/filament/pages/walk-programme-import.blade.php`
- `resources/views/holidays/show.blade.php`
- `resources/views/socials/show.blade.php`
- `resources/views/walks/show.blade.php`
- `scripts/convert-walk-programme.py`
- `scripts/test_convert_walk_programme.py`
- `tests/Feature/Operations/CsvEventDefinitionsTest.php`
- `tests/Feature/Operations/CsvProgrammePageTest.php`
- `tests/Feature/Operations/CsvProgrammeReplacementTest.php`
- `tests/Feature/Operations/WalkProgrammeImportPageTest.php`
- `tests/Feature/Operations/WalkProgrammeImportTest.php`
- `tests/Feature/Public/DateOnlyEventPresentationTest.php`
- `tests/Support/ProgrammeWorkbook.php`
