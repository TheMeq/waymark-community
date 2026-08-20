# Dependency Baseline

Waymark Community starts from Laravel 13 on PHP 8.3 or newer. PHP 8.3 is the minimum declared by `composer.json`; development and release checks may also run on supported newer PHP versions.

Composer resolves dependencies against `config.platform.php` set to `8.3.0`. This keeps `composer.lock` installable on the minimum supported runtime even when `composer update` is run from a PHP 8.4 or newer development machine. Do not remove or raise this platform target without an approved runtime-baseline change. After any dependency update, run `composer validate --strict`, `composer prohibits php 8.3.0 --locked`, and a clean `composer install` plus the backend suite under an actual PHP 8.3 CLI.

The initial Phase 1 lockfiles resolve the approved major versions to:

| Dependency | Locked version | Purpose |
| --- | ---: | --- |
| Laravel framework | 13.26.1 | Application and server-rendered web foundation |
| Filament | 5.7.6 | Admin-panel and CRUD infrastructure only |
| Symfony components | 7.4.x | PHP-8.3-compatible framework components selected by Composer |
| Tailwind CSS | 4.3.3 | Development/release-time public CSS build |
| Alpine.js | 3.16.2 | Progressive enhancement without a public SPA |
| Vite | 8.2.2 | Development/release-time asset compilation |

Filament plugins are intentionally excluded from the foundation. Filament's design system is confined to admin routes and must not define the public frontend.

`composer.lock` and `package-lock.json` are authoritative for exact transitive versions. Composer, Node.js, npm, frontend source packages, and developer test tools are required to build and verify source, but the future shared-hosting release archive must contain production Composer dependencies and compiled assets so none of those tools are required on the destination host.
