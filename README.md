# Waymark Community

Waymark Community is a reusable, self-hosted website for one walking group per installation. **Waymark Community** is the public product name; **Waymark** is the internal project and PHP namespace. NDWG is the first reference deployment, not the product.

The v1 implementation is complete through Phase 10 and is awaiting the final independent acceptance gate. No public v1 tag or release has been published.

## Product contract

- Server-rendered public pages with progressive enhancement and WCAG 2.2 AA intent.
- Walks, Socials, Holidays, calendar, Gallery, accounts, CMS, governance, communications, search, SEO, privacy, PWA, backup/recovery and safe updates.
- Volunteer-friendly Filament administration that does not influence the public visual system.
- MySQL and MariaDB production support on ordinary shared PHP hosting.
- One active group per installation; no tenancy, attendance, bookings, payments, chat or membership-management suite.

## Source/developer checkout

The source checkout contains application source, tests, release tooling and frontend sources. Build requirements are Git, PHP 8.3 or 8.4 with Composer 2, Node.js 24/npm 11, SQLite for fast tests, and MySQL 8.4 plus MariaDB 11.4 for the release matrix.

```shell
composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate
npm ci
npm run build
composer test
composer verify:repository
npm run test:tooling
```

PowerShell users should replace `cp` with `Copy-Item`. Full setup and test-matrix commands are in [`docs/development/setup.md`](docs/development/setup.md) and [`docs/development/testing.md`](docs/development/testing.md).

The tracked dependency baseline currently resolves Laravel 13.26.1, Filament 5.7.6 and Fortify 1.38.0 while Composer resolves dependencies for the minimum PHP 8.3.0 platform.

## Shared Hosting Release ZIP

The production distribution is generated from an exact clean commit:

```shell
php scripts/build-release.php --version=1.0.0
php scripts/verify-release.php dist/waymark-community-1.0.0-shared-hosting.zip
```

The ZIP contains production Composer dependencies, compiled frontend assets, migrations and installer/update/recovery entry points. Runtime requirements are PHP 8.3+ with the documented extensions, MySQL or MariaDB, writable storage and a web server. The host does **not** need Git, Composer, Node.js, npm, Docker, Redis, developer tests or a resident worker. See the [shared-host installation guide](docs/deployment/shared-hosting.md) and [release packaging guide](docs/deployment/release-packaging.md).

## Repository map

- `app/Domain/` — account, content, event, gallery, governance, holiday, operations, social and walk domain behaviour.
- `app/Filament/` — administration resources/pages only.
- `app/Http/` — thin HTTP controllers, requests and middleware.
- `app/Providers/`, `app/Support/` — framework integration and narrowly scoped cross-cutting support.
- `config/`, `database/`, `routes/` — runtime configuration, schema/seeders and delivery registration.
- `resources/` — Blade views and source CSS/JavaScript for the approved visual system.
- `public/` — public entry points and build output in generated distributions.
- `tests/` — PHP, browser, accessibility, visual, integration and release-tooling tests.
- `scripts/` — repository, performance, package build/verification and extraction tooling.
- `docs/` — approved specification/plans, architecture, design, developer, deployment and backlog material.

## Governing references

Read [`AGENTS.md`](AGENTS.md), the [approved specification](docs/superpowers/specs/2026-08-20-waymark-community-design.md), [master roadmap](docs/superpowers/plans/2026-08-20-waymark-master-roadmap.md), [approved homepage concept](docs/design/references/approved-homepage-concept.png), and the relevant implementation/release plan before changing behaviour. Acceptance gates and visual fidelity are product requirements.
