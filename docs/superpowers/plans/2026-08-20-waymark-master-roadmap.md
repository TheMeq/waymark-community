# Waymark Community Master Implementation Roadmap

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan phase-by-phase. Do not execute multiple phases without passing the prior phase gate.

**Goal:** Build Waymark Community v1 as a polished, self-hosted, shared-hosting-compatible walking-group platform whose first/reference deployment is NDWG.

**Architecture:** One group per installation. Laravel provides the application/domain foundation; Blade renders the public site; Tailwind and Alpine provide the custom visual system and progressive enhancement; Filament is restricted to admin CRUD infrastructure. Domain logic is organised by bounded product areas and stays reusable outside HTML controllers.

**Tech Stack:** Laravel 13.x, PHP >=8.3, Blade, Tailwind CSS 4.x, Alpine.js 3.x, Filament 5.x, MySQL/MariaDB, Leaflet 1.9.x stable, Vite build tooling, developer-side browser/accessibility/visual regression tooling.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

## Global Constraints

- External product name is **Waymark Community**; internal project name is **Waymark**.
- NDWG is a reference installation, never a hard-coded product assumption.
- One active group per installation.
- Production must run on ordinary shared PHP hosting without Node.js, Composer, Docker, Redis, a permanent worker, or test tooling.
- MySQL and MariaDB are the official v1 database targets.
- Public UI is server-rendered first; JavaScript is progressive enhancement.
- Public visual fidelity to `docs/design/references/approved-homepage-concept.png` is a release requirement.
- Filament visual styling must not leak into the public site.
- Accessibility target is WCAG 2.2 AA where applicable.
- No attendance tracking, payments, comments/chat, native app, mailbox client, plugin marketplace, multi-group tenancy, or full membership-management system in v1.
- New ideas go to `docs/backlog/future-ideas.md` unless needed by an approved v1 requirement.
- Repository hygiene and release reproducibility are product requirements.

---

## Phase Sequence

### Phase 1 — Foundation and repository scaffold
Plan: `2026-08-20-waymark-phase-01-foundation.md`

Deliver a clean Laravel application foundation inside this repository, exact supported dependency constraints, domain folders, auth/admin skeleton, dual-database CI strategy, quality tooling, and release-safe repository hygiene.

**Gate:** clean clone can be developed from documented steps; unit/feature baseline passes on supported developer database; no runtime/build junk is tracked; no public UI design work has drifted from the approved approach.

### Phase 2 — Visual system and approved public shell
Plan: `2026-08-20-waymark-phase-02-visual-foundation.md`

Implement design tokens, public layout/components, responsive navigation, homepage shell with fixture data, mobile priorities, accessibility behaviours, and visual regression baselines.

**MANDATORY VISUAL GATE:** compare desktop/tablet/mobile screenshots against `approved-homepage-concept.png`. Do not proceed to broad feature work if the public frontend reads as generic Laravel/Filament/Bootstrap UI.

### Phase 3 — Event foundation and Walks
Plan: `2026-08-20-waymark-phase-03-events-walks.md`

Implement common event lifecycle, grading, tags, walk fields, leader ownership, duplication, locations, optional GPX/Leaflet, walk admin, listings, filters, detail pages, past-adventure behaviour, and event updates.

### Phase 4 — Socials, Holidays, recurrence, calendar
Plan: `2026-08-20-waymark-phase-04-socials-holidays-calendar.md`

Implement flexible socials, parent holidays with child events, external booking blocks, pricing/capacity metadata, simple recurrence with separate occurrences, list/calendar views, and revision-aware ICS feeds.

### Phase 5 — Accounts, permissions, leaders, favourites
Plan: `2026-08-20-waymark-phase-05-accounts-permissions.md`

Implement public registration, email verification, optional 2FA, lightweight profiles, public display names, Ramblers verification metadata, fixed roles/configurable module permissions, leader profiles/hub, favourites, account export/deletion flows, and installation ownership.

### Phase 6 — Gallery, media, moderation, PWA upload
Plan: `2026-08-20-waymark-phase-06-gallery-media-pwa.md`

Implement event/album-bound photo uploads, image optimisation, EXIF handling, moderation, reporting/removal, public galleries/lightbox, featured memories, media library, focal points, event-aware mobile upload, and basic PWA installation/offline shell.

### Phase 7 — CMS, news, governance, committee, contact
Plan: `2026-08-20-waymark-phase-07-content-governance.md`

Implement constrained CMS blocks, curated homepage management, testimonials, news, versioned documents, controlled-document approvals, committee and meeting records, committee hub, contact departments, routed forms, newsletters, and concise copy rules.

### Phase 8 — Search, SEO, privacy, accessibility, performance
Plan: `2026-08-20-waymark-phase-08-quality-discovery.md`

Implement site-wide search, filters, sitemap/robots/canonicals, structured metadata, redirects, sharing previews, campaign handling, consent management, policy linkage, accessibility acceptance, image-heavy performance, print styles, and developer-side performance/accessibility regression checks.

### Phase 9 — Installer, shared-hosting operations, backup/update/recovery
Plan: `2026-08-20-waymark-phase-09-operations.md`

Implement `/setup`, secure configuration checks, system health, cron-optional scheduled work, backups, external storage option, integrity verification, guided restore/disaster recovery, maintenance mode, update metadata/preflight/staging/rollback, and admin-friendly operational warnings.

### Phase 10 — Distribution, migration tools, staging and release hardening
Plan: `2026-08-20-waymark-phase-10-release.md`

Implement import/export framework, portability package, shared-hosting ZIP build/verification, release metadata/checksums, staging safeguards, install/upgrade smoke tests, final MySQL/MariaDB matrix, browser matrix, visual baselines, documentation, and v1 release checklist.

---

## Review Rhythm

Each phase must end with:

1. all phase tests passing;
2. `git status --short` reviewed for accidental generated/runtime files;
3. documentation updated for any architectural decision made during implementation;
4. explicit comparison against the phase acceptance criteria;
5. a focused commit/review checkpoint before the next phase.

Any implementation discovery that would change an approved product requirement must stop and be raised for design review. Do not silently broaden scope.
