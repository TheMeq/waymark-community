# Waymark Phase 1 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish a clean, tested Laravel/Waymark foundation that future phases can build on without restructuring the repository.

**Architecture:** Scaffold Laravel into the approved repository rather than creating a disposable second repo. Establish domain namespaces, first-party authentication foundation, Filament admin boundary, database compatibility, translation readiness, repository hygiene, and development/release tooling before feature work begins.

**Tech Stack:** PHP >=8.3, Laravel ^13.0, Filament ^5.0, Blade, Tailwind ^4, Alpine ^3, MySQL/MariaDB, PHPUnit/Laravel test tooling, Playwright developer tooling for later visual/E2E testing.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

## Global Constraints

- Preserve all existing `docs/`, `AGENTS.md`, `START-HERE-FOR-CODEX.md`, and approved design references.
- Do not replace the root README with Laravel boilerplate.
- Production dependencies and compiled assets must eventually be bundleable in a shared-hosting ZIP.
- `vendor/`, `node_modules/`, `.env`, runtime uploads, logs, caches, tests outputs, and release archives must not be tracked.
- Track `composer.lock` and `package-lock.json`.
- No product-domain feature beyond the minimum foundation is implemented in this phase.

---

### Task 1: Scaffold Laravel safely into the existing repository

**Files:**
- Create/merge: `artisan`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.js`
- Preserve/modify: `.gitignore`, `.env.example`, `README.md`
- Test: `tests/Feature/Foundation/ApplicationBootTest.php`

**Interfaces:**
- Produces a standard Laravel application rooted at the current repository.
- `APP_NAME` default remains `Waymark Community`.
- `APP_TIMEZONE` remains installation-configurable.

- [ ] **Step 1: Create a temporary Laravel 13 skeleton rather than overwriting the repository**

Run:
```bash
composer create-project laravel/laravel:^13.0 /tmp/waymark-laravel --no-interaction
```
Expected: Laravel skeleton created under `/tmp/waymark-laravel`.

- [ ] **Step 2: Copy framework files into the repository while preserving approved project files**

Copy the Laravel skeleton into the current root, excluding `/tmp/waymark-laravel/.git`, its README, and any files that would overwrite approved docs without review. Merge `.gitignore` deliberately rather than replacing it.

- [ ] **Step 3: Restore Waymark configuration contract**

Ensure `.env.example` contains at minimum:
```dotenv
APP_NAME="Waymark Community"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=Europe/London
DB_CONNECTION=mysql
CACHE_STORE=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
```
plus the already-approved optional integration placeholders.

- [ ] **Step 4: Write application boot test**

Create `tests/Feature/Foundation/ApplicationBootTest.php`:
```php
<?php

namespace Tests\Feature\Foundation;

use Tests\TestCase;

final class ApplicationBootTest extends TestCase
{
    public function test_application_boots_and_home_route_responds(): void
    {
        $this->get('/')->assertSuccessful();
    }
}
```

- [ ] **Step 5: Run the focused test and verify the application boots**

Run:
```bash
php artisan test tests/Feature/Foundation/ApplicationBootTest.php
```
Expected: PASS.

- [ ] **Step 6: Verify repository hygiene after scaffold**

Run:
```bash
git status --short
git check-ignore -v .env vendor node_modules 2>/dev/null || true
```
Confirm `.env`, `vendor/`, and `node_modules/` cannot be accidentally tracked.

- [ ] **Step 7: Commit the scaffold**

```bash
git add . ':!.env'
git commit -m "chore: scaffold Waymark Laravel foundation"
```

---

### Task 2: Pin supported runtime and build dependencies

**Files:**
- Modify: `composer.json`, `composer.lock`, `package.json`, `package-lock.json`
- Create: `docs/development/dependency-baseline.md`
- Test: `tests/Feature/Foundation/RuntimeRequirementsTest.php`

**Interfaces:**
- PHP minimum: 8.3.
- Laravel major: 13.
- Filament major: 5.
- Tailwind major: 4.
- Alpine major: 3.
- Exact patch versions are pinned by lockfiles.

- [ ] **Step 1: Add Filament 5 admin panel dependency**

Run:
```bash
composer require filament/filament:^5.0 --with-all-dependencies
```
Do not install unrelated Filament plugins in this phase.

- [ ] **Step 2: Add Alpine and Tailwind development dependencies using the Laravel/Vite-compatible setup**

Run the supported Tailwind 4/Vite setup and add Alpine 3. Keep Node dependencies development/build-time only.

- [ ] **Step 3: Write runtime requirements test**

Create `tests/Feature/Foundation/RuntimeRequirementsTest.php` that asserts `PHP_VERSION_ID >= 80300` and that the application is running Laravel major 13.

- [ ] **Step 4: Run foundation tests**

```bash
php artisan test tests/Feature/Foundation
```
Expected: PASS.

- [ ] **Step 5: Document the dependency baseline**

Record the chosen exact lockfile versions and why Laravel 13/PHP 8.3 is the supported baseline. Note that Laravel 13 requires PHP 8.3 and that Filament 5 is the current admin major selected for this implementation.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock package.json package-lock.json docs/development/dependency-baseline.md tests/Feature/Foundation
git commit -m "chore: pin Waymark dependency baseline"
```

---

### Task 3: Establish repository/domain namespaces before feature growth

**Files:**
- Create: `app/Domain/Events/.gitkeep`
- Create: `app/Domain/Gallery/.gitkeep`
- Create: `app/Domain/Membership/.gitkeep`
- Create: `app/Domain/Content/.gitkeep`
- Create: `app/Domain/Governance/.gitkeep`
- Create: `app/Domain/Operations/.gitkeep`
- Create: `app/Support/.gitkeep`
- Modify: `docs/development/repository-layout.md`
- Test: `tests/Architecture/RepositoryArchitectureTest.php`

**Interfaces:**
- Product-domain code goes under `App\Domain\<Domain>`.
- HTTP delivery remains under `App\Http`.
- Cross-cutting narrowly defined support code may use `App\Support`; it is not a dumping ground.

- [ ] **Step 1: Create only the approved bounded-domain roots**

Create the six domain directories above. Do not pre-create dozens of empty technical-layer directories.

- [ ] **Step 2: Write architecture test guarding banned dumping-ground roots**

Test that `app/Helpers`, `app/Utils`, `app/Misc`, and `app/Common` do not exist.

- [ ] **Step 3: Update repository layout documentation**

Document the namespace rule and examples of where an Event action, Gallery policy, or Operations service belongs.

- [ ] **Step 4: Run architecture tests**

```bash
php artisan test tests/Architecture/RepositoryArchitectureTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domain app/Support docs/development/repository-layout.md tests/Architecture
git commit -m "chore: establish Waymark domain boundaries"
```

---

### Task 4: Establish authentication and admin-panel boundary

**Files:**
- Create/modify: `app/Models/User.php`
- Create: `app/Providers/Filament/AdminPanelProvider.php`
- Create: `app/Domain/Membership/Enums/AccountStatus.php`
- Create: `app/Domain/Membership/Enums/MembershipStatus.php`
- Create migration: users foundation fields required by the approved account model
- Test: `tests/Feature/Auth/AdminPanelAccessTest.php`
- Test: `tests/Feature/Auth/PublicAccountAccessTest.php`

**Interfaces:**
- Public accounts and admin accounts use the same `User` identity model.
- Admin-panel access is permission/role gated and never inferred from email domain.
- Membership status is separate from account status.

- [ ] **Step 1: Add first-party public authentication backend suitable for custom Blade screens**

Prefer Laravel Fortify or the current first-party Laravel authentication mechanism that supports email verification and optional 2FA without imposing a public SPA framework. Do not install a React/Vue starter kit.

- [ ] **Step 2: Define account and membership enums**

`AccountStatus`: `Active`, `Suspended`, `Disabled`.

`MembershipStatus`: `Unverified`, `Verified`, `Lapsed`, `NotMember`.

- [ ] **Step 3: Configure Filament `/admin` panel**

Use `Waymark Community` product identity in admin chrome while allowing installation branding later. Ensure production access requires an authorised user.

- [ ] **Step 4: Write failing admin access tests before policy implementation**

Tests must prove:
- guest cannot access `/admin`;
- ordinary authenticated account cannot access `/admin`;
- authorised admin account can access `/admin`.

- [ ] **Step 5: Implement minimum authorisation needed to satisfy tests**

Do not build the full permission editor yet; use a minimal explicit foundation that Phase 5 will extend.

- [ ] **Step 6: Run auth tests**

```bash
php artisan test tests/Feature/Auth
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app config database routes tests composer.json composer.lock
git commit -m "feat: establish authentication and admin boundary"
```

---

### Task 5: Establish installation/group settings root without multi-tenancy

**Files:**
- Create: `app/Domain/Operations/Models/SiteProfile.php`
- Create: migration for `site_profiles`
- Create: `app/Domain/Operations/Actions/GetSiteProfile.php`
- Create: `app/Domain/Operations/Actions/UpdateSiteProfile.php`
- Test: `tests/Feature/Operations/SiteProfileTest.php`

**Interfaces:**
- Exactly one canonical `SiteProfile` row exists per installation; the database prevents additional profile identities.
- It stores group identity/configuration, not tenant routing.
- API:
```php
GetSiteProfile::handle(): SiteProfile
UpdateSiteProfile::handle(array $validated): SiteProfile
```

- [ ] **Step 1: Write tests proving the canonical site profile is the only installation identity**
- [ ] **Step 2: Create migration/model with group name, short name, contact email, timezone, locale, regional units, start year, branding placeholders, affiliation placeholders, and module configuration JSON where appropriate**
- [ ] **Step 3: Implement `GetSiteProfile` with a safe missing-profile failure for pre-install state**
- [ ] **Step 4: Implement validated update action**
- [ ] **Step 5: Run tests on MySQL-compatible developer database**
- [ ] **Step 6: Commit**

---

### Task 6: Establish translation, error handling, and copy foundations

**Files:**
- Create: `lang/en/*.php` or `resources/lang/en/*.php` according to Laravel 13 convention
- Modify: public error handling/views
- Create: `resources/views/errors/404.blade.php`
- Create: `resources/views/errors/503.blade.php`
- Test: `tests/Feature/Foundation/LocalisationTest.php`

**Interfaces:**
- System UI strings use translation keys.
- CMS/database content remains single-language in v1.
- Public errors are branded later by Phase 2 tokens but must not expose stack traces.

- [ ] **Step 1: Add translation files for common actions/status words**
- [ ] **Step 2: Replace any newly introduced hard-coded system UI labels with translation keys**
- [ ] **Step 3: Add basic 404/503 templates that are structurally ready for Phase 2 styling**
- [ ] **Step 4: Test English locale fallback and production-safe error output**
- [ ] **Step 5: Commit**

---

### Task 7: Establish developer-side test/build scripts

**Files:**
- Modify: `composer.json`
- Modify: `package.json`
- Create: `playwright.config.*`
- Create: `tests/browser/.gitkeep`
- Create: `scripts/verify-repository.sh` and Windows-friendly equivalent if the chosen scripting approach needs one
- Modify: `docs/development/testing.md`

**Interfaces:**
- `composer test` runs backend/architecture tests.
- `npm run build` creates production frontend assets.
- `npm run test:e2e` is developer-side only.
- production package does not require these tools.

- [ ] **Step 1: Define repeatable Composer test script**
- [ ] **Step 2: Configure Playwright as developer dependency for future browser/visual tests**
- [ ] **Step 3: Add repository verification script checking for forbidden tracked runtime paths and required baseline files**
- [ ] **Step 4: Run backend tests and frontend production build**

```bash
composer test
npm ci
npm run build
```
Expected: both succeed.

- [ ] **Step 5: Verify generated frontend assets are ignored by source Git if that is the selected release policy**
- [ ] **Step 6: Commit**

---

### Task 8: Document clean-clone developer setup and validate it

**Files:**
- Modify: `README.md`
- Create/modify: `docs/development/setup.md`
- Modify: `REPOSITORY-MANIFEST.md`

**Interfaces:**
- A new contributor can go from clean clone to running tests without undocumented tribal knowledge.

- [ ] **Step 1: Document exact prerequisites and setup commands**
- [ ] **Step 2: Document MySQL/MariaDB local database creation and `.env` setup using placeholders only**
- [ ] **Step 3: Document asset build and tests**
- [ ] **Step 4: Re-run setup from a clean temporary clone/worktree**
- [ ] **Step 5: Run `composer test` and `npm run build` in that clean environment**
- [ ] **Step 6: Update repository manifest**
- [ ] **Step 7: Commit**

---

## Phase 1 Acceptance Gate

Before Phase 2:

- [ ] Laravel 13 application boots from repository root.
- [ ] PHP >=8.3 requirement is explicit and tested.
- [ ] Filament admin panel exists but has not leaked into public styling.
- [ ] Public auth foundation supports email verification and optional 2FA path without a SPA framework.
- [ ] Site installation identity is modelled as one `SiteProfile`, not multi-tenancy.
- [ ] Domain roots are established and dumping-ground directories are absent.
- [ ] `composer.lock` and `package-lock.json` are tracked.
- [ ] `.env`, `vendor`, `node_modules`, runtime data, test output, and release packages are ignored.
- [ ] `composer test` passes.
- [ ] `npm run build` passes.
- [ ] Clean-clone setup has been exercised from documentation.
- [ ] `git status --short` contains no accidental runtime/build artefacts.
