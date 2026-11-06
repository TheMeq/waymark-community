# Importing a walk programme

Administrators can open **System → Import walk programme**, or follow **Import XLSX walk programme** from **Imports and exports**. Recent password confirmation is required, plus a fresh two-factor challenge if enabled.

1. Upload an `.xlsx` workbook, up to 5 MB, and choose **Read workbook**.
2. Review the detected future walks and the original spreadsheet row numbers.
3. For each programme leader name, optionally select an active, verified account with permission to manage walks. Otherwise keep **Name only — admin managed**.
4. Deselect any incomplete or unwanted rows. Correct errors in the source workbook and upload it again where necessary.
5. Choose **Preview selected rows**, then **Create private drafts**.

Every created walk is a private draft. Linked leaders can find their drafts in the Leader Hub; administrators manage name-only and unconfirmed leaders. Review the description, starting point, grade, distance, practical information and any source notes before publication. No map, route or photograph is invented from the spreadsheet.

In the walk editor, administrators can later select a **Linked leader account**. This transfers management access to that account. Clearing the link returns management to the administrator making the change. The original textual name remains available in the editor; a linked account's public display preferences control its public attribution. A name or account must be supplied before publication.

## Workbook format

- Only the worksheet named **Walks** is read. Historic and other sheets are ignored.
- Programme headings must appear in the first 30 rows. The importer recognises the supplied programme's columns, including `Title`, `Date`, `Walk leader name`, `Start Time`, `Difficulty`, `Dist Miles`, `Distance km`, ascent and starting-location fields. Line breaks, dagger markers and asterisks in headings are normalised.
- Past dates, undated ideas and entries without a walking grade are excluded. A walking grade is a candidate filter, so review the selection for unusual programme entries.
- Unknown grades must be configured in Waymark or corrected in the workbook. Missing or invalid dates/times and invalid measurements are reported in the preview. No start time is guessed.
- Excel date/time cells and explicit `YYYY-MM-DD`, `DD/MM/YYYY` or `DD-MM-YYYY` dates are supported. Plain time strings use `HH:MM` or `HH:MM:SS`.
- Unicode fractions such as `10½` and mixed fractions such as `10 1/2` become decimals. Distances/ascent are converted to the installation's configured units and rounded to two decimal places. Miles/feet are used when supplied, otherwise kilometres/metres are used.
- Recognisable What3Words references are separated from the combined starting-location text. Other source information is retained in private organiser notes for review.
- Calculations are not executed. Formula cells can use the workbook's last saved result; save the workbook in Excel after recalculating if those results are missing or stale.

## Duplicate and error handling

Walks with the same title and start date/time are treated as duplicates, ignoring case, repeated whitespace and leader changes. Duplicates within the workbook and in Waymark (including deleted walks) are skipped. Existing records are never updated by an import. A title or start-time change can represent a different walk and requires manual review.

All selected rows must be valid before any drafts are created. Unselected and excluded rows do not block the import. Changing the upload, mapping or selection requires a fresh preview. New matches created since preview also require another preview.

Workbook limits are 5,000 rows and 128 columns on the Walks sheet, 2,000 archive entries and 64 MB expanded data. Encrypted workbooks are unsupported. Uploads use Waymark's temporary upload storage; the importer does not publish or permanently attach the workbook.

## Deployment

Deploy the updated application and production dependencies, run the database migration, and clear application caches using the established deployment process. The migration adds `walks.primary_leader_name` and makes the account link optional. The fresh-install schema manifest is updated too. OpenSpout, already a Filament dependency, is now declared directly without changing its locked version.

The importer does not run automatically during deployment. An administrator must upload, preview and explicitly create drafts. This change does not import historical content.
