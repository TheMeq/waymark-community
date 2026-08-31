# Developer and Release Testing

Testing is a developer/release responsibility. Deployed shared-host installations do not run the test suite.

Required layers:

- backend unit/feature;
- auth/permission;
- validation;
- browser/end-to-end critical journeys;
- accessibility automation plus manual keyboard checks where appropriate;
- responsive visual regression;
- performance regression/budgets;
- MySQL/MariaDB matrix;
- setup/install;
- Shared Hosting Release ZIP smoke test;
- update/migration/rollback;
- backup/restore;
- PWA/mobile upload;
- photo moderation.

The implementation plan must give exact commands and acceptance criteria.

The release-level manual accessibility pass is documented in [`accessibility-acceptance-checklist.md`](accessibility-acceptance-checklist.md). Automated axe, keyboard, reduced-motion, 200% text-size, and touch-target checks run in the Playwright suite.

Public transfer and rendering budgets are defined in [`performance-budgets.md`](performance-budgets.md) and enforced with `npm run test:performance`.

## Request diagnostics

Development and staging requests record a bounded profile in `storage/logs/waymark-performance-*.log` and expose the same timing summary through the `Server-Timing` response header. Each record includes framework boot and application duration, database query count/time and repeated query shapes, cache activity, cache/session drivers, file-backed driver touchpoints, outbound HTTP host/timing, response status, authentication state, and peak memory. View rendering is included in application duration; Laravel does not expose a reliable separate render duration at this boundary.

Set `WAYMARK_REQUEST_DIAGNOSTICS=true` to enable the profiler explicitly or `false` to disable it. It defaults to enabled only for `local` and `staging` environments. Never enable it routinely in production, and do not add request input, query bindings, credentials, or full outbound URLs to its records.

## Core commands

From the repository root:

```shell
composer test
composer verify:repository
npm ci
npm run build
npm run test:e2e
```

`composer test` runs the backend feature, unit, and architecture suites. `composer verify:repository` checks the required foundation files, forbidden tracked runtime/generated paths, and ignore rules. The repository verifier can also be run directly as `./scripts/verify-repository.sh` on Unix-like systems or `./scripts/Verify-Repository.ps1` in PowerShell.

`npm run test:e2e` runs the full Chromium responsive/browser suite. Release verification also runs `npm run test:e2e:release`, `npm run test:e2e:staging`, `npm run test:e2e:installer`, `npm run test:pwa`, `npm run test:tooling`, `npm run test:performance`, and ZIP-only install/upgrade commands documented in the release guides. Playwright and its browsers remain developer/release dependencies and are not installed on production hosts.

## Database matrix

MySQL and MariaDB are the official v1 database targets. CI runs the backend suite against both engines on PHP 8.3 and 8.4. For a local MySQL-compatible run, configure a dedicated empty test database using placeholders in the process environment or an untracked `.env.testing`; never commit credentials.

Example PowerShell process configuration:

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'waymark_test'
$env:DB_USERNAME = 'waymark_test'
$env:DB_PASSWORD = '<local-password>'
php artisan test
```

The test user must be able to create, alter, and drop tables in the dedicated test database. Do not point `RefreshDatabase` tests at development or production data.
