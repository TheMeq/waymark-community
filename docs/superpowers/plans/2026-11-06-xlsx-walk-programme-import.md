# XLSX Walk Programme Import Implementation Plan

**Goal:** Import future walks from the Walks worksheet through an administrator preview into private drafts, supporting name-only leaders and later account linking.

**Approved scope:** User approved XLSX upload, preview, leader mapping, duplicate detection, decimal fractions, and admin-managed/publishable name-only leaders on 6 November 2026. Historical imports are excluded.

**Architecture:** Extend the existing operations/import area with a dedicated programme importer and page, leaving CSV import intact. Read XLSX safely with a maintained streaming PHP reader bundled in production dependencies. Use existing walk domain validation and private draft status. Keep a textual leader name independently of an optional eligible user link; transfer management ownership when an admin links an account.

## Constraints and decisions

- Shared PHP hosting, server-rendered Filament admin, no resident worker or runtime Node/Composer requirement.
- Restrict import to administrators with operations access and permission to manage all walks.
- Use Walks worksheet and detect its headers after programme introduction rows; ignore Historic and Sheet1.
- Preview all titled rows with original row numbers; exclude past, undated and non-walk rows by default. Grade identifies candidates, with manual exclusion for ambiguous entries.
- Missing/invalid start time blocks that row; missing leader name/account does not block a private draft. Require a name or linked leader before publication.
- Convert Excel dates/times in site timezone, Unicode fractions to decimal, miles/feet into configured site units, grades by configured name. Unknown grades require correction.
- Map each distinct source leader name to an optional eligible account. Preserve source names without guessing aliases or creating accounts.
- Duplicate checks use normalized title and start, independently of leader; repeated upload never updates existing walks.
- Require a fresh preview matching upload/mappings/selection before commit; revalidate server-side at commit. Recheck duplicates inside a serialized import transaction.
- Keep uploaded files temporary and apply file, row and archive limits; never evaluate formulas or follow external links.

## Tasks

- [x] 1. Add leader-name storage and optional user link, wire validation/forms/public display/ownership and publication, and cover name-only creation, linking, permissions and display with regression tests.
- [x] 2. Add bounded XLSX reader and programme normalization; test header detection, worksheet selection, dates/times, fractions, unit conversions, exclusions and malformed inputs.
- [x] 3. Add preview/execute domain service; test private drafts, leader mapping validation, missing data, duplicate detection across reuploads/leader changes, all selected-row validation and transactions.
- [x] 4. Add secured admin upload/mapping/selection/preview/commit screen linked from Imports/Exports; test access, stale preview protection, validation and end-to-end Livewire import.
- [x] 5. Validate targeted walk/import/public regressions, format changed PHP, inspect actual workbook in a read-only preview and document usage. Review final diff; leave deployment and actual data import to the user.

## Review focus

- Dates crossing daylight-saving changes must retain local start times.
- Ambiguous social/holiday rows must not silently become walks.
- Changing a mapping or selection after preview must prevent stale execution.
- Unlinked leaders must not break public pages or grant account access by matching names.
- Concurrent/repeated imports must not overwrite or duplicate prior imported walks.

## Validation

Use focused PHPUnit feature tests first; broaden to walks/accounts/public/operations because leader nullability affects shared behaviour. Do not run browser/release/deployment tooling by default. Read the supplied workbook without creating application data.

## Completed verification

- Importer/page/CSV compatibility tests: 27 passed, 139 assertions before final leader-review changes. New XLSX coverage comprises 18 tests.
- Final affected-area command: `php vendor/bin/phpunit tests/Feature/Walks tests/Feature/Accounts tests/Feature/Public tests/Feature/Operations tests/Architecture --no-progress`, with a temporary CLI PHP configuration enabling installed extensions. Result: 609 tests, 608 passed, 3,857 assertions; one unrelated failure in `BackupRestoreTest::test_managed_site_and_walk_media_are_restored_with_owners_fallbacks_and_private_variants`. Its restored homepage assertion expects an absolute favicon URL while the page renders a relative favicon URL. No favicon implementation/test changes were made.
- Fresh-install manifest regenerated from migrations in SQLite memory and its convergence test passed.
- Rollback tested with a name-only walk: restores the event organiser as primary account before reverting nullability. Missing valid fallback fails before removing data. MySQL/MariaDB execution was not run.
- PHP formatting, Composer manifest validation and `git diff --check` passed.
- Actual supplied workbook read using the new importer with matching grades configured: 190 titled entries, 21 valid future walks, 1 invalid future walk, 168 excluded. Row 148 has equal 11:00 start/finish times; correct the workbook or deselect it. No live/development application data was imported.
- Independent review issues addressed: linked public-name preferences, admin-only name-only leader management/publication, rollback fallback and preserving a null account while continuing an imported draft.
- Ruling: description remains plain text because existing walk views escape it and preserve line breaks. Source names remain stored for admin use; linked accounts control public attribution.
- Deployment, browser review and actual database migration/import remain outside this implementation run.

## Changed files

New:

- `app/Domain/Operations/Imports/ReadWalkProgramme.php`
- `app/Domain/Operations/Imports/NormaliseWalkProgrammeRow.php`
- `app/Domain/Operations/Imports/WalkProgrammeImport.php`
- `app/Filament/Pages/WalkProgrammeImport.php`
- `resources/views/filament/pages/walk-programme-import.blade.php`
- `database/migrations/2026_11_06_100000_add_primary_leader_name_to_walks_table.php`
- `tests/Feature/Operations/WalkProgrammeImportTest.php`
- `tests/Feature/Operations/WalkProgrammeImportPageTest.php`
- `tests/Support/ProgrammeWorkbook.php`
- `docs/product/walk-programme-import.md`
- `docs/superpowers/plans/2026-11-06-xlsx-walk-programme-import.md`

Modified:

- `app/Domain/Walks/Actions/DuplicateWalk.php`
- `app/Domain/Walks/Actions/SubmitWalkForPublication.php`
- `app/Domain/Walks/Actions/UpdateWalk.php`
- `app/Domain/Walks/Data/WalkDetailsData.php`
- `app/Domain/Walks/Models/Walk.php`
- `app/Filament/Resources/WalkResource.php`
- `app/Filament/Resources/WalkResource/Pages/CreateWalk.php`
- `app/ViewModels/PublicWalkCardViewModel.php`
- `app/ViewModels/PublicWalkDetailViewModel.php`
- `resources/views/filament/pages/imports-exports.blade.php`
- `resources/installation/fresh-schema.json`
- `tests/Feature/Accounts/LeaderProfilesTest.php`
- `tests/Feature/Walks/WalkDraftWizardTest.php`
- `tests/Feature/Walks/WalkLeaderAssignmentTest.php`
- `composer.json`
- `composer.lock`

The existing user modification to `AGENTS.md` was preserved.
