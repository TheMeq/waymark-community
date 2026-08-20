# Waymark Community

Waymark Community is a reusable, self-hosted walking-group website platform. Internally the project is referred to as **Waymark**. Nottingham & Derby Walking Group (NDWG) is the reference installation, not the product itself.

## Status

**Design approved. Implementation roadmap and Phase 1 foundation plan are included.**

No application code is intentionally included yet. Codex should begin with the approved Phase 1 foundation plan and must not skip the visual-fidelity gate before building later product modules.

## Read first

1. `AGENTS.md`
2. `docs/superpowers/specs/2026-08-20-waymark-community-design.md`
3. `docs/superpowers/plans/2026-08-20-waymark-master-roadmap.md`
4. `docs/superpowers/plans/2026-08-20-waymark-phase-01-foundation.md`
5. `docs/design/visual-system.md`
6. `docs/design/references/approved-homepage-concept.png`
7. `docs/architecture/overview.md`
8. `docs/development/repository-layout.md`
9. `docs/deployment/shared-hosting.md`

## Product principles

- One walking group per self-hosted installation.
- NDWG-specific assumptions must never enter the generic platform core.
- Shared hosting is a first-class production target.
- The public site must closely follow the approved visual direction rather than default framework styling.
- The admin experience is built for volunteers, not developers.
- Configuration should make a group feel distinct without allowing each installation to become an unrelated custom site.
- Data belongs to the group and must remain portable, backed up, and recoverable.
- New ideas found during implementation go to the backlog unless required by an already-approved v1 requirement.

## Intended implementation stack

The implementation baseline is Laravel 13.x on PHP 8.3+, Blade, Tailwind CSS 4.x, Alpine.js 3.x, Filament 5.x for CRUD-heavy administration, Leaflet 1.9.x stable for optional mapping, and MySQL/MariaDB. Exact patch versions are pinned by dependency lockfiles when Phase 1 scaffolding is executed.

## Distribution model

The source repository and the production installation package are different artifacts.

A release pipeline will eventually produce a self-contained **Shared Hosting Release ZIP** containing production Composer dependencies and compiled frontend assets so the destination server does not require Node.js, Composer, Docker, Redis, or a permanent queue worker.

## Start implementation

Codex should start with `START-HERE-FOR-CODEX.md`, then execute Phase 1 only. The Phase 2 public-visual milestone has a mandatory review gate against the approved concept art before broad feature implementation continues.
