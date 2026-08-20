# Waymark Phase 9 — Installer and Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Make Waymark installable, maintainable, recoverable and updateable on ordinary shared hosting by non-developer-oriented administrators.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

---

### Task 1: Pre-install state and setup guard
- Detect uninstalled state without requiring database-backed app boot that cannot succeed yet.
- Route only setup/static error surfaces until installation completes.
- Lock installer after successful install; recovery/reset requires deliberate technical action.
- Commit.

### Task 2: Web setup wizard
- Steps: Welcome, server checks, DB connection, group details, branding preview, first admin, SMTP test, module choices, optional advanced services, install, health check.
- Plain-English failures for PHP version/extensions/writable dirs/upload limits/image library/HTTPS/config security/cron availability.
- Write `.env` when permitted; otherwise provide copyable file content/instructions and re-check.
- Never expose entered passwords after save.
- Commit.

### Task 3: Shared-hosting path/security checks
- Support document-root layouts where app and `.env` live outside public web root.
- Refuse/strongly block production completion if `.env` is publicly retrievable or APP_DEBUG remains on.
- Document control-panel deployment examples generically.
- Commit.

### Task 4: Scheduler/job fallback model
- Implement scheduler heartbeat.
- Cron preferred, not mandatory.
- Define safe admin/manual/request-driven fallback for non-critical jobs when scheduler absent.
- Do not fake timely execution guarantees when cron is unavailable.
- Commit.

### Task 5: System health page
- Plain-language status for platform/PHP/database/storage/disk/email/cron/backups/HTTPS/updates/missing media.
- No raw log viewer.
- Serious issues may create admin banner and email alert; lower priority stays health page.
- Add missing-media repair queue.
- Commit.

### Task 6: Backup creation and retention
- Manual and scheduled backup sets for DB/media/documents/restore-required config.
- Local default plus optional S3-compatible external destination.
- Optional encryption; not mandatory.
- Configurable retention.
- Chunk/resource-aware work on shared hosts.
- Commit.

### Task 7: Backup integrity and guided restore
- Verify archive manifest/checksums/expected components before marking successful.
- Admin guided restore with strong confirmation.
- Standalone disaster-recovery entry point protected by recovery secret/token for broken-admin scenarios.
- Commit.

### Task 8: Maintenance mode
- Group-branded message, optional expected return/contact.
- Secure privileged bypass.
- Used by update/restore orchestration.
- Commit.

### Task 9: Release update metadata/checks
- Stable-channel only v1.
- Periodic passive check; manual check available.
- Admin-friendly release notes, SemVer, security-release flag.
- Compatibility report for PHP/extensions/database/disk before actionable update.
- No auto-update.
- Commit.

### Task 10: Safe update orchestration
- Download to staging location; verify checksum/signature mechanism approved by release pipeline.
- Fresh backup (mandatory for all updates; explicitly never skipped for security update).
- Stage as much as host supports, enter maintenance, swap/apply files, migrations, cache/Filament optimise, health checks.
- On failure restore previous files and DB backup as necessary.
- Test interruption/failure cases using controlled fixtures.
- Commit.

## Phase 9 Gate
A non-developer can install via web wizard on a supported shared-host layout, diagnose common problems, back up and restore, and perform a guarded update without Node/Composer/shell/worker dependency on production.
