# Programme CSV imports

Waymark imports walks, holidays and events through **System → Imports and exports → Guided CSV import**. Select the matching import type for each file, upload it and choose **Read CSV columns**. Generated column names map automatically. Review the dry run, then choose **Import valid private drafts**. Upload the three files separately.

Imports require an active authorised administrator, recent password confirmation and a fresh second-factor challenge when enabled. The account also needs permission to manage the selected event type. Every created record remains a private draft; uploading a CSV never publishes it.

## Preparing the supplied programme

The offline developer tool `scripts/convert-walk-programme.py` reads every worksheet and produces `walks.csv`, `holidays.csv`, `events.csv`, a reconciliation manifest and a README. It needs Python and openpyxl only on the conversion machine; the server only receives CSV files and needs neither tool.

```shell
python scripts/convert-walk-programme.py "Walks Programme.xlsx" "Programme CSV Import"
```

The converter is tailored to the supplied workbook layouts: Walks has a preamble before its header, Historic has different headings and copied data to the right, and Sheet1 has a headerless legacy layout. Raw nonempty cells, original column headings, formulas where present and source sheet/row references remain in each record's private source payload. Header/preamble and otherwise empty calendar-slot rows attach to adjacent records without generating fake events. Source-cell coverage and values are verified against the workbook before conversion succeeds.

Titles, descriptions, dates, leaders, grades, locations, grid references, What3Words, distances, ascent and practical information map into the appropriate application fields where the source is clear. Holidays and social events use their own destination, venue, booking and contact fields. Unmapped or ambiguous material remains in private source data. Unicode fractions become decimals; source distance/ascent units are explicit and converted to the installation's configured units at import.

The converter flags tentative classification, recovered historical dates, contradictory finish times and ambiguous measurements in `review_notes`. An ambiguous measurement such as “4 max” is left unconfirmed rather than turned into an exact public distance. Original wording remains preserved. Correct classifications or details before importing where necessary. Exact repeated records are merged conservatively, retaining all source provenance and descriptive text; different confirmed start times remain separate.

## Dates and incomplete records

- Historical and future dates are supported. There is no future-only filter.
- Known date and unknown start time: `start_time_unknown=true`. The CSV may use `YYYY-MM-DD` or midnight as an internal date placeholder. Public pages display **time not recorded**, calendar files use date precision and structured data does not invent a midnight start. Administrators can uncheck **Start time not recorded** when confirming an actual time.
- No usable date: keep the row with `import_enabled=false`. It is reported as skipped and does not block dated records. Enter a date and enable it when ready.
- All enabled rows must validate before any are created. Unknown configured grades, malformed values and invalid explicit account links must be corrected. Review notes alone are warnings, not validation errors.

The supplied export uses Easy, Leisurely, Moderate, Strenuous and Technical. Those labels must exist in the installation's grading scheme, or the CSV grade values must be mapped manually to its scheme before import. Missing grades may remain blank in drafts.

## Leaders and private source information

Walks can have a `leader_name` without a linked account. Leave `leader_email` blank for these: the importing administrator manages the draft. A supplied email must match an eligible active, verified walk leader; similar names are never linked automatically and no accounts are created.

An administrator can later link an eligible account in the walk editor. This transfers management access to that account; unlinking returns management to the administrator. Linked accounts retain their public display-name/profile preferences. Name-only leaders can be managed and published only by administrators. Unconfirmed leaders must be completed before a walk can be published.

Administrators can read **Imported source record** in the walk, holiday or event editor. This shows review notes and original sheet/row/cell values. Raw source records are private; they are not part of public descriptions or galleries. The generated files also contain original contact information and should be kept as private working files.

## Duplicates and safe re-import

Duplicates are matched by event type, normalised title and date/time, independently of leader changes. Date-only records also match records whose time was subsequently confirmed. Walks, holidays and social events do not collide with each other. Existing records, including deleted walks, are skipped and never overwritten. Skipped existing records are not enriched with source data: use the CSV as reference for any desired manual updates.

Changing the upload, import type or mapping requires a new preview. Matches created since preview require another preview. The preview table shows the first 100 rows; the downloaded row report covers the entire CSV, including disabled and duplicate rows. CSV limits remain 5 MB and 5,000 rows.

## Deployment and photos

Deploy the application, install locked production PHP dependencies, run migrations and clear application caches using the normal process. The new migration adds private event source metadata and the unknown-start-time flag. Keep the earlier name-only-leader migration and local HTTPS proxy fix; do not roll back the database to remove the XLSX screen. No source data, uploaded photos or existing walks are deleted by this replacement.

Published/completed public historical records can receive photos through the normal authenticated, verified-account and moderation workflow. Private drafts cannot receive public gallery uploads until reviewed and published. This change performs no automatic import or publication.
