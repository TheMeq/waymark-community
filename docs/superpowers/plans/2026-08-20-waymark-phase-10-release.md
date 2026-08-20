# Waymark Phase 10 — Release and v1 Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Produce a reproducible Waymark Community v1 release suitable for NDWG staging and later installation by other walking groups.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

---

### Task 1: Guided import framework
- CSV field mapping, validation preview, dry-run, duplicate detection and error report.
- Ship only import definitions actually required for v1/reference setup.
- Existing NDWG content remains manual clean rebuild; do not scrape legacy site.
- Commit.

### Task 2: Full portability export
- Export core records/configuration plus media/documents and machine-readable manifest.
- Make large export generation scheduler/fallback friendly.
- Validate import/restore semantics separately from backup format.
- Commit.

### Task 3: Shared Hosting Release ZIP builder
**Files:** release scripts under `scripts/`, release manifest schema, docs.

Build from a clean workspace:
1. validate source repository;
2. `composer install --no-dev --prefer-dist --optimize-autoloader` into package tree;
3. `npm ci && npm run build` in build workspace;
4. run full developer test suite before packaging;
5. include compiled public assets and production vendor;
6. exclude `.git`, `.env`, node_modules, tests, local logs/uploads/backups, coverage and developer-only artefacts;
7. write version/build/min PHP/schema metadata;
8. create `waymark-community-<version>-shared-hosting.zip`;
9. create SHA-256 checksum.

- Commit.

### Task 4: Package-content verification
- Automated test opens ZIP and asserts required production files exist.
- Assert forbidden secrets/dev/runtime paths do not exist.
- Assert installer and migrations are present.
- Assert production dependencies/compiled assets present.
- Fail release if validation fails.
- Commit.

### Task 5: Clean install smoke test from release ZIP
- Extract only the ZIP into a fresh shared-host-like fixture environment.
- No source repo/vendor/node_modules from developer tree may be borrowed.
- Complete web/manual automated test setup against MySQL and MariaDB variants.
- Verify public homepage/admin login/uploads basic path.
- Commit.

### Task 6: Upgrade/rollback smoke matrix
- Build prior-version fixture/release candidate.
- Upgrade using production updater.
- Verify migrations/data/media retained.
- Inject controlled failure and verify rollback returns known-good state.
- Commit.

### Task 7: Browser/accessibility/visual/performance release matrix
- Current + previous major Chrome/Edge/Firefox/Safari strategy where CI infrastructure permits.
- Desktop/tablet/mobile screenshot baselines for critical public pages.
- WCAG automated checks and documented manual checklist.
- Performance budget checks.
- PWA install/offline smoke.
- Commit.

### Task 8: NDWG staging readiness
- Environment-aware staging banner/noindex/email trapping/separate analytics.
- Create reference content-entry checklist, not automated scrape.
- Verify concept-art visual comparison with representative real fixture content.
- Commit.

### Task 9: Documentation and contributor readiness
- README quick start/current architecture/repository map.
- CONTRIBUTING coding/test/commit/review rules.
- SECURITY reporting/support boundaries.
- Shared-host installation/update/backup/recovery docs.
- Release notes/changelog structure.
- Ensure no document contains stale generic “Walking Group Platform” naming except historical rationale where intentional.
- Commit.

### Task 10: v1 scope and release audit
- Trace every spec success criterion to implementation/test evidence.
- Search for prohibited scope: attendance, payment, comments/chat, mailbox, native app, plugin marketplace, multi-tenancy.
- Search for NDWG/Nottingham/Derby/20–50 assumptions in generic core and remove or move to fixtures/config.
- Verify source Git clean and release ZIP self-contained.
- Tag/publish only after all checks pass.

## v1 Release Gate
Waymark Community v1 is releasable only when the source clone is reproducible, shared-hosting ZIP installs without developer tooling, visual baselines match the approved direction, MySQL/MariaDB paths pass, update/rollback is demonstrated, and no out-of-scope management-suite functionality has crept in.
