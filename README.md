# Waymark Community

Waymark Community is a reusable, self-hosted walking-group website platform. Internally the project is referred to as **Waymark**. Nottingham & Derby Walking Group (NDWG) is the reference installation, not the product itself.

## Status

**Design approved. The Phase 1 foundation is implemented and stops at its acceptance/review gate.**

Phase 1 contains the Laravel application scaffold, dependency locks, domain boundaries, public-auth backend, Filament admin boundary, single-installation SiteProfile, localisation/error foundations, and developer verification tooling. It intentionally contains no Walks, Events, Gallery, CMS, or other product module implementation. Phase 2 must not begin until Phase 1 is reviewed, and Phase 2 itself retains the mandatory visual-fidelity gate before broader modules.

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

Codex should always start with `START-HERE-FOR-CODEX.md` and the current approved phase plan. The Phase 2 public-visual milestone has a mandatory review gate against the approved concept art before broad feature implementation continues.

## Developer setup

See [`docs/development/setup.md`](docs/development/setup.md) for prerequisites and clean-clone commands. The shortest verification sequence after setup is:

```shell
composer test
composer verify:repository
npm ci
npm run build
```

## Repository map

- `app/Domain/` — approved product-domain roots; business behavior grows here in later phases.
- `app/Http/` — HTTP delivery and coordination.
- `app/Providers/` — Laravel, Fortify, and Filament integration boundaries.
- `app/Support/` — narrowly scoped cross-cutting application support only.
- `config/`, `database/`, `routes/` — framework configuration, schema, and route registration.
- `resources/` — server-rendered views plus source CSS/JavaScript; the custom public visual system begins in Phase 2.
- `tests/` — backend, architecture, and future browser/release tests.
- `scripts/` — cross-platform repository verification tooling.
- `docs/` — approved specification, roadmaps, phase plans, architecture decisions, visual references, and development/deployment guidance.
