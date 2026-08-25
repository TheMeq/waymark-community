# Contributing to Waymark Community

Thanks for taking an interest in Waymark Community. A contribution does not have to be a large feature to be worthwhile: bug fixes, accessibility improvements, tests, documentation, clearer wording and reports from real walking-group use can all make the project better.

## Small fixes and larger proposals

If you have found a typo, a broken link, an obvious bug or a focused accessibility problem, a small pull request is welcome. You do not need to work through the project's historical phase process before fixing something clear and contained.

For a significant feature or a change to product behaviour, please start a GitHub issue before investing a lot of time. Waymark has deliberately narrow boundaries—it is a website for one walking group per installation, not a payments platform, attendance system, social network or full membership suite—and an early conversation can prevent a good piece of work heading in the wrong direction.

## Development principles

These guidelines help changes remain useful on the modest hosting and volunteer time available to many groups:

- Use test-driven development for behaviour changes: demonstrate the missing or incorrect behaviour first, then make the smallest complete correction.
- Keep controllers thin and keep domain logic out of Blade templates. Core operations should not depend on a particular HTML screen.
- Keep Filament in the administration area. The approved public visual system must remain independent from admin-framework styling.
- Preserve PHP 8.3 compatibility, MySQL and MariaDB support, and operation on ordinary shared PHP hosting.
- Treat WCAG 2.2 AA accessibility and the approved responsive visual direction as part of the feature, not optional polish.
- Prefer focused commits with one reviewable purpose. Avoid mixing a useful change with unrelated formatting, renaming or dependency updates.
- Never commit secrets, real member information or photographs, production databases, uploads, logs, backups, runtime state, generated reports, `vendor/`, `node_modules/` or release archives.

Optional ideas that do not belong in the current product scope can be recorded under `docs/backlog/` rather than partially implemented.

## Getting set up

The short version is:

```shell
composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

PowerShell users can use `Copy-Item .env.example .env`. The full PHP extension list, database setup and Windows notes are in [developer setup](docs/development/setup.md).

## Testing your change

Run the narrowest relevant test while you work. A PHP feature or domain change will usually start with a filtered PHPUnit or Artisan test; a public interaction may need a focused Playwright test. Format changed PHP with Pint and run `git diff --check` before committing.

Changes that affect releases, shared behaviour or the production build need the wider checks:

```shell
composer validate --strict
composer prohibits php 8.3.0 --locked
composer test
composer verify:repository
npm ci
npm run build
npm run test:pwa
npm run test:tooling
```

The MySQL, MariaDB, browser, accessibility, visual and package matrices are explained in [developer and release testing](docs/development/testing.md). Inspect visual changes at the affected desktop, tablet and mobile sizes before updating a baseline.

## Making a pull request easy to review

A useful pull request explains the problem, why the change takes the chosen approach and how it was checked. Keep it focused, include tests for behaviour, and add screenshots when the public or administration interface changes. Mention any deliberate trade-offs or parts you could not verify.

Imperative commit subjects make the history easier to scan. Generated test evidence should stay out of Git unless it is an approved visual baseline.

## Maintainer workflow

Maintainers and agentic contributors should also follow [`AGENTS.md`](AGENTS.md), the approved specification and any active implementation or review instruction. Those documents contain the formal acceptance gate and release rules; outside contributors do not need to understand the old Phase 1–10 sequence before making a contained contribution.
