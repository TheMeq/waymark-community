# AGENTS.md — Waymark

## Purpose

This repository contains **Waymark Community** (internal project name: **Waymark**), a generic self-hosted walking-group platform. NDWG is the first/reference deployment; NDWG is not the product.

All implementation work must follow the approved design specification in `docs/superpowers/specs/2026-08-20-waymark-community-design.md`, the master roadmap in `docs/superpowers/plans/2026-08-20-waymark-master-roadmap.md`, the current phase plan, and the approved visual reference in `docs/design/references/approved-homepage-concept.png`.


## Product naming

- External/public product name: **Waymark Community**.
- Internal code/repository name: **Waymark**.
- NDWG is the first/reference installation only.
- Suggested repository name: `waymark`.
- PHP/project-specific namespace: `Waymark` where needed.
- Shared-hosting releases use `waymark-community-<version>-shared-hosting.zip`.

## Hard product constraints

1. One active walking group per installation.
2. Do not hard-code NDWG, Nottingham, Derby, Ramblers, the 20–50 age range, colours, logos, contact addresses, social accounts, committee structure, or any other installation-specific detail.
3. Production must be compatible with ordinary shared PHP hosting.
4. Production must not require Node.js, Docker, Redis, Composer, a permanent queue worker, shell access, or developer test tooling.
5. MySQL and MariaDB are the official v1 database targets.
6. Public UI is server-rendered first. JavaScript is progressive enhancement.
7. Accessibility target: WCAG 2.2 AA where applicable.
8. Public visual fidelity is a product requirement, not optional polish.
9. No attendance tracking, payments, native app, comments/chat, full mailbox client, plugin marketplace, multi-group tenancy, or full membership-management system in v1.
10. New feature ideas discovered during implementation belong in `docs/backlog/` unless necessary to satisfy an approved v1 requirement.

## Visual fidelity

The approved concept artwork is the primary visual reference for the public website:

`docs/design/references/approved-homepage-concept.png`

Do not reinterpret the public site as a generic Laravel, Bootstrap, Filament, admin-template, or WordPress-style site.

The implementation must preserve the concept's visual language, including:

- overall composition and section hierarchy;
- photographic emphasis;
- typography hierarchy;
- generous whitespace;
- card dimensions and density;
- rounded corners;
- restrained borders and shadows;
- navigation proportions;
- colour balance;
- event-card presentation;
- gallery presentation;
- CTA hierarchy;
- footer scale;
- responsive visual priority.

Filament is an admin implementation choice only. Its design language must not leak into the public frontend.

Where specification and artwork differ:

- functional behaviour follows the written specification;
- visual direction follows the approved artwork;
- genuine conflict requires clarification rather than silent redesign.

## Copy rules

Do not narrate the interface. Do not add filler copy merely because a layout has whitespace.

Avoid copy such as:

- “Click the button below to view upcoming walks.”
- “This section shows our latest photos.”
- “Add a heading here so it appears on the homepage.”

Prefer labels over explanations and useful context over narration. Admin help is just-in-time only where a user could reasonably misunderstand a field or consequence.

## Architecture rules

- Keep controllers thin.
- Do not place business logic in Blade templates.
- Model meaningful domain boundaries explicitly.
- Prefer focused Actions/Services/Queries with narrow interfaces over generic utility classes.
- Avoid generic dumping grounds named `Misc`, `Common`, `Helpers`, or `Utils` unless a narrowly defined responsibility is documented.
- Do not create a new top-level architectural layer merely because a feature needs one class.
- Features that change together should live together.
- Large files are a signal to reassess responsibility boundaries.
- Core business operations must be callable independently of a specific HTML controller so future API/PWA clients can reuse them.
- Optional external integrations may enhance the platform but core operation may not depend on them.

## Repository hygiene

Never commit:

- `.env` or real credentials;
- production databases or member exports;
- real member photographs/uploads;
- runtime backups;
- logs, caches, sessions, generated reports;
- `vendor/`;
- `node_modules/`;
- IDE/user-machine state;
- test output/coverage artifacts;
- generated production release archives.

Track dependency lockfiles when implementation begins.

The source repository is not the production package. Production packages are generated reproducibly by release automation.

## Development discipline

- Work from the master roadmap and the current approved phase plan. Implement phases sequentially and stop at every phase gate.
- Use test-driven development for behaviour changes.
- Every implementation task must produce an independently testable result.
- Do not start later phases because earlier code merely compiles; meet phase acceptance criteria first.
- Visual milestones require visual review and regression baselines.
- Comprehensive testing belongs to developer/release infrastructure, not deployed installations.

## Shared-hosting discipline

- Assume limited execution time and memory.
- Cron is preferred but not mandatory.
- File/database cache must be supported without Redis.
- Image processing must be resource-aware.
- Long-running operations should be resumable/chunked at the application-job level where necessary, without requiring a resident worker daemon.
- Release packages must include production PHP dependencies and compiled frontend assets.

## Security and privacy

- Use framework CSRF, validation, authentication, and password reset protections.
- Sensitive admin changes require re-authentication; if the user has 2FA enabled, use a fresh 2FA challenge too.
- Validate uploads by allow-list, MIME inspection, size, and safe generated names.
- Strip EXIF from published photos after extracting only required metadata such as capture time/orientation.
- Photo uploads are authenticated, attributed, and moderated.
- Logs remain technical; normal admins see plain-language health state, not stack traces.
