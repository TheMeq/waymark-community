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

## Phase 1 commands

From the repository root:

```shell
composer test
composer verify:repository
npm ci
npm run build
npm run test:e2e
```

`composer test` runs the backend feature, unit, and architecture suites. `composer verify:repository` checks the required foundation files, forbidden tracked runtime/generated paths, and ignore rules. The repository verifier can also be run directly as `./scripts/verify-repository.sh` on Unix-like systems or `./scripts/Verify-Repository.ps1` in PowerShell.

`npm run test:e2e` is intentionally allowed to pass with no browser tests during Phase 1. Browser journeys and visual baselines are added only in their approved phases. Playwright and its browsers remain developer/release dependencies and are not installed on production hosts.

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
