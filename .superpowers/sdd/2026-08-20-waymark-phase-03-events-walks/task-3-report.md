# Phase 3 Task 3 report — Walk domain model and validation

## Delivered

- Added the one-to-one `Walk` event extension, reusable grade/tag relations, existing-user primary/co-leader relations, and portable SQLite/MySQL/MariaDB migrations.
- Added focused nullable persistence seams for all approved walk details, including structured location, transport, kit, informational capacity/availability, attachment/featured-image/GPX metadata paths, private organiser notes, and recap/highlights.
- Added `SaveWalkDetails` and `WalkDetailsData` as a reusable non-controller validation/persistence boundary. It validates event type, leader consistency, coordinate pairing/ranges, metric bounds, transport dependency, URLs, and bounded structured arrays.
- Added `WalkMeasurements`, which reads the installation-wide `SiteProfile` distance/ascent units, and `WalkPublicDetails`, which omits empty optional sections and never exposes private organiser notes. Public pages, uploads, GPX parsing/maps, admin CRUD, duplication, and recap presentation remain out of scope.

## TDD evidence

Each behavior was introduced by a focused failing `WalkDetailsTest` run and then made green, including event/leader/tag persistence; coordinate pairing/ranges; numeric bounds; transport dependency; optional-seam persistence; leader uniqueness; bounded arrays; installation units; clean public data; URL format; and walk-event type.

## Verification

- `php artisan test tests/Feature/Walks tests/Feature/Events/EventFoundationTest.php` — 30 tests, 135 assertions passed.
- `vendor\\bin\\pint` on changed PHP files — passed after applying formatting fixes.
- SQLite in-memory `php artisan migrate:fresh --force` — all migrations, including `create_walks_tables`, ran successfully.
- `php scripts\\verify-repository.php` — passed.
- PHP syntax checks and `git diff --check` — passed.

## Concerns / follow-up boundaries

- The schema deliberately stores nullable location and GPX/attachment references only. Task 6 remains responsible for file validation, parsing, storage operations, derived route data, and maps.
- The recap/highlights columns are reserved only; Task 8 owns update/recap workflows and public presentation.
