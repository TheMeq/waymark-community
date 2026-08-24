# Contributing to Waymark Community

Read `AGENTS.md`, the approved specification, master roadmap and relevant phase/review instruction before changing code. A review correction supplied at an acceptance gate is already approved; keep it bounded and implement it directly.

## Changes

- Keep scope within approved v1. Put optional future ideas in `docs/backlog/`.
- Use test-driven development for behaviour: first prove the missing behaviour, then make the smallest complete change.
- Keep controllers thin, domain logic out of Blade and public presentation independent from Filament.
- Preserve PHP 8.3, MySQL/MariaDB, shared-hosting, accessibility and approved visual contracts.
- Make focused commits with one reviewable responsibility. Do not mix formatting, dependency upgrades or unrelated cleanup.
- Never commit secrets, real member data/media, runtime state, dependencies, generated reports or release archives.

## Before committing

Run the narrow tests first, format changed PHP with Pint, and check `git diff --check`. For a release-affecting change also run:

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

Run the relevant Playwright, database and package matrices described in `docs/development/testing.md`. Never update a visual baseline until the rendering has been inspected against the approved concept.

## Review and commits

Use imperative commit subjects and keep generated evidence out of Git unless it is an approved visual baseline. Summarise changed behaviour, tests and deliberate deviations. Stop at every specified acceptance gate; do not merge, tag, publish or begin a later phase without its independent approval.
