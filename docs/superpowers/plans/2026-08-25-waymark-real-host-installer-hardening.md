# Waymark Real-host Installer Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make a fresh Waymark installation resumable, safely recoverable after interrupted MySQL/MariaDB DDL, usable without initial email configuration, and clear and accessible on ordinary shared hosting at root or beneath a URL prefix.

**Architecture:** Retain authoritative Laravel migrations, but execute them incrementally through a persisted installation attempt rather than one long request. A generated schema manifest, database ownership marker, initial-empty proof, and private attempt record classify empty, completed, owned-incomplete, known-Waymark-incomplete, and ambiguous databases before any destructive action. Setup data remains in the existing private session boundary; persisted progress contains no submitted credentials.

**Tech Stack:** Laravel 12, PHP 8.3+, Blade, Filament 4 admin, PDO, PHPUnit, Playwright, MySQL 8.4, MariaDB 11.4.

**Spec:** Approved real-host installer findings supplied on 2026-08-25, plus `docs/superpowers/specs/2026-08-20-waymark-community-design.md` and `docs/superpowers/plans/2026-08-20-waymark-phase-09-operations.md`.

## Global Constraints

- Keep Laravel migrations authoritative; do not make historical migrations blindly idempotent.
- Never erase a completed Waymark installation, an ambiguous database, or unrelated tables.
- Do not require a resident queue worker, Node.js, Composer, or shell access in production.
- Persist only non-secret attempt metadata; never log or return database, SMTP, recovery, application-key, SQL, stack-trace, or absolute-path secrets.
- Preserve domain-root and nested-prefix route generation.
- The installation lock is written only after all final health checks pass.
- Do not tag or publish `v1.0.0` during this correction.

---

### Task 1: Migration-derived fresh-install schema identity and database preflight

**Files:**
- Create: `app/Domain/Operations/Installation/DatabaseInstallationState.php`
- Create: `app/Domain/Operations/Installation/DatabaseInspection.php`
- Create: `app/Domain/Operations/Installation/FreshInstallationSchema.php`
- Create: `resources/installation/fresh-schema.json`
- Modify: `app/Domain/Operations/Installation/Contracts/DatabaseConnectionTester.php`
- Modify: `app/Domain/Operations/Installation/PdoDatabaseConnectionTester.php`
- Test: `tests/Unit/Operations/FreshInstallationSchemaTest.php`
- Test: `tests/Feature/Operations/SetupDatabaseStepTest.php`
- Test: `tests/Integration/Operations/InstallerDatabasePreflightTest.php`

**Interfaces:**
- `FreshInstallationSchema::migrationNames(): array` and `tables(): array` expose a generated manifest of migration names and final table columns.
- `DatabaseInspection` reports success, safe public message, database state, whether reset is safe, and a connection fingerprint.
- `DatabaseConnectionTester::test()` performs connection plus CREATE/INSERT/SELECT/UPDATE/ALTER/DROP probes with a unique `waymark_preflight_*` table and always attempts cleanup.

- [ ] Write tests proving every required permission is exercised, cleanup occurs after success/failure, and the result distinguishes empty, completed, known incomplete, and ambiguous databases.
- [ ] Run each new test and confirm it fails because the richer inspection does not exist.
- [ ] Generate the manifest from a fully migrated schema and make tests compare it with the migration files and actual final table/column inventory.
- [ ] Implement the PDO probes with driver-safe identifier quoting and safe error categories, never raw exception text.
- [ ] Verify on SQLite, MySQL 8.4, and MariaDB 11.4; commit.

### Task 2: Persisted installation-attempt ownership and safe reset

**Files:**
- Create: `app/Domain/Operations/Installation/InstallationAttemptStatus.php`
- Create: `app/Domain/Operations/Installation/InstallationAttemptRecord.php`
- Create: `app/Domain/Operations/Installation/InstallationAttemptStore.php`
- Create: `app/Domain/Operations/Installation/InstallationDatabaseOwnership.php`
- Create: `app/Domain/Operations/Installation/ResetIncompleteInstallation.php`
- Modify: `config/waymark.php`
- Modify: `app/Domain/Operations/Installation/SetupProgress.php`
- Test: `tests/Unit/Operations/InstallationAttemptStoreTest.php`
- Test: `tests/Integration/Operations/IncompleteInstallationRecoveryTest.php`

**Interfaces:**
- `InstallationAttemptStore` atomically reads/writes one private JSON record with attempt ID, token, connection fingerprint, migration-set hash, stage, status, progress, timestamps, diagnostic ID, safe category/message, and changed-state flag; it accepts no submitted setup values.
- `InstallationDatabaseOwnership` creates and verifies `waymark_installation_attempts` only after proving the target database empty.
- `ResetIncompleteInstallation::handle()` drops tables only when the marker/manifest/history proof succeeds; otherwise it returns a blocked result without mutation.
- `SetupProgress::resetAfterIncompleteInstallation()` preserves reusable non-secret answers, removes all submitted secrets, and returns the wizard to database validation.

- [ ] Write failing tests for an owned interrupted attempt, the exact unrecorded `regeneration_cleanup_status` state, a generic unrecorded DDL state, completed Waymark, unknown tables, and mismatched ownership tokens.
- [ ] Confirm the reset tests fail before implementation and that ambiguous/completed fixtures remain unchanged.
- [ ] Implement atomic private persistence, ownership proof, generated-manifest validation, destructive confirmation, and secret scrubbing.
- [ ] Verify confirmed safe reset removes only proven fresh-install objects and retry starts from database checks; commit.

### Task 3: Bounded staged installation, resume, and safe diagnostics

**Files:**
- Create: `app/Domain/Operations/Installation/InstallationStage.php`
- Create: `app/Domain/Operations/Installation/InstallationDiagnostic.php`
- Create: `app/Domain/Operations/Installation/StartInstallation.php`
- Create: `app/Domain/Operations/Installation/AdvanceInstallation.php`
- Create: `app/Domain/Operations/Installation/InstallationStatus.php`
- Modify: `app/Domain/Operations/Installation/WaymarkInstaller.php`
- Modify: `app/Http/Controllers/SetupController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/setup/step.blade.php`
- Test: `tests/Feature/Operations/SetupInstallationTest.php`
- Test: `tests/Feature/Operations/SetupWizardNavigationTest.php`
- Test: `tests/Integration/Operations/InterruptedMigrationInstallationTest.php`

**Interfaces:**
- `StartInstallation::handle()` validates saved sections/preflight, returns promptly, prevents a second attempt, and creates/resumes the persisted record.
- `AdvanceInstallation::handle()` performs one bounded unit: configuration, database ownership, one migration, group settings, administrator, optional configuration, health checks, or completion.
- GET status returns only stage label, count/total, safe message/actions, and completion/failure flags.
- POST advance, retry, and reset routes are CSRF-protected and generated with named routes so mounted prefixes are preserved.

- [ ] Write failing feature tests for prompt start, duplicate start, stage-by-stage progress, refresh/resume, one migration per advance, final lock timing, safe diagnostic IDs/categories, retry actions, and nested-prefix URLs.
- [ ] Confirm the existing synchronous `migrate` implementation makes the staged tests fail.
- [ ] Extract existing installer side effects into stage methods and run migrations individually in authoritative filename order.
- [ ] Log detailed exceptions with a diagnostic ID while returning no raw SQL, stack trace, credentials, or absolute paths.
- [ ] Add a server-rendered progress page with an accessible live region, bounded JavaScript polling, and a non-JavaScript continue form.
- [ ] Verify interruption after visible DDL becomes a controlled failed attempt and never blind-reruns; commit.

### Task 4: Optional email and later administration

**Files:**
- Create: `app/Domain/Communication/Support/OutboundEmailStatus.php`
- Create: `app/Domain/Communication/Exceptions/EmailNotConfigured.php`
- Create: `app/Filament/Pages/EmailDeliverySettings.php`
- Create: `resources/views/filament/pages/email-delivery-settings.blade.php`
- Modify: `app/Domain/Operations/Installation/SubmitSetupStep.php`
- Modify: `app/Domain/Operations/Installation/WaymarkInstaller.php`
- Modify: `app/Domain/Operations/Health/SystemHealth.php`
- Modify: `app/Providers/FortifyServiceProvider.php`
- Modify: relevant email-sending application actions at their existing service boundaries
- Test: `tests/Feature/Operations/SetupConfigurationStepsTest.php`
- Test: `tests/Feature/Operations/SystemHealthPageTest.php`
- Test: `tests/Feature/Operations/EmailDeliverySettingsTest.php`
- Test: `tests/Feature/Auth/PublicAccountAccessTest.php`

**Interfaces:**
- Saved mail data includes `configured: bool`; skipped setup writes `WAYMARK_MAIL_CONFIGURED=false` with no invented SMTP credentials.
- `OutboundEmailStatus::configured()` is the shared application check used before user-visible mail workflows.
- The admin page validates/tests SMTP before atomically updating supported environment values.

- [ ] Write failing tests for configured SMTP, skip without mail tester invocation, environment output, health warning, later successful configuration/test, reset-link messaging, registration/verification handling, and representative admin mail actions.
- [ ] Implement conditional mail validation and the concise consequence text.
- [ ] Implement the admin configuration page using the existing environment writer and mail tester; never redisplay stored passwords.
- [ ] Add graceful application-boundary guards so no disabled workflow claims mail was sent or exposes transport errors; commit.

### Task 5: Installer field guidance, recovery key, errors, and module checkbox

**Files:**
- Create: `app/Domain/Operations/Installation/GenerateRecoveryKey.php`
- Modify: `resources/views/setup/step.blade.php`
- Modify: `app/Http/Controllers/SetupController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Operations/SetupConfigurationStepsTest.php`
- Test: `tests/Feature/Operations/SetupWizardNavigationTest.php`
- Test: `tests/browser/installer.spec.ts`

**Interfaces:**
- POST recovery-key generation returns a cryptographically random validator-compliant key only to the current setup session/form response; it does not log or persist plaintext beyond that boundary.
- Every required label uses a visible `*`, one page-level accessible explanation, an `aria-describedby` hint where needed, and a field-local validation message.
- Module options use a dedicated `.wm-setup-checkbox` presentation with a normal checkbox and at least a 44px label target.

- [ ] Write failing feature tests for required markers/help, exact administrator/recovery rules, `Recovery key` terminology, secure generation, confirmation acknowledgement, and field-local errors.
- [ ] Write failing browser assertions for checkbox dimensions/alignment/focus/checked/disabled states and 200% resize.
- [ ] Implement only the installer form treatment, concise approved guidance, secure key generation/copy control, focus management, and responsive styles.
- [ ] Verify desktop/tablet/mobile keyboard and axe behavior; commit.

### Task 6: Real-server root/nested release regressions

**Files:**
- Modify: `tests/tooling/phase10-release-clean-install.mjs`
- Modify: `tests/tooling/release-clean-install.test.mjs`
- Modify: `tests/browser/installer.spec.ts`
- Modify: `playwright.installer.config.ts`
- Modify: release verification documentation only where commands or supported behavior changed

**Interfaces:**
- Release harness can seed empty, ambiguous, owned-partial, exact observed unrecorded-DDL, and generic partial-DDL databases.
- Harness drives staged progress at `/setup` and a nested mount, reconnects during progress, and confirms no runtime Composer/Node dependency.

- [ ] Add tests for every approved real-host gate case and confirm old synchronous/connection-only behavior fails them.
- [ ] Exercise standard and public-html packages at root, plus public-html beneath a real prefix, on MySQL 8.4 and MariaDB 11.4.
- [ ] Confirm owner login, setup lockout, internal-path protection, optional/configured mail, recovery reset/retry, and safe diagnostics.
- [ ] Commit.

### Task 7: Acceptance verification and corrected artifacts

**Files:**
- Generated outside tracked source: corrected shared-hosting ZIP, public-html ZIP, checksums, exact-commit source-review ZIP, checksum, and manifest.

- [ ] Run `composer validate --strict`, PHP 8.3 dependency guard, full PHPUnit on SQLite/MySQL/MariaDB, repository verification, Pint on changed PHP, and `git diff --check`.
- [ ] Run `npm ci`, production build, PWA/tooling tests, full Playwright, installer visual/accessibility/resize/keyboard checks, and performance budgets.
- [ ] Run clean exact-commit build and actual archive installation matrix on MySQL 8.4 and MariaDB 11.4 at root and nested prefix.
- [ ] Build both production packages from the final exact commit, recalculate SHA-256 values, test ZIP integrity/content, and create a safe source-review ZIP/manifest.
- [ ] Confirm clean Git status, no tag/publication, and stop for independent acceptance.
