# Developer Setup

These steps reproduce the Phase 1 developer environment from a clean source clone. They install developer/release tooling only; production shared-hosting releases will contain compiled assets and production Composer dependencies.

## Prerequisites

- Git 2.x.
- PHP 8.3 or 8.4 with `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`, and `zip` enabled. SQLite extensions are useful for the default fast test suite.
- Composer 2.x.
- Node.js 24.x and npm 11.x for the locked Phase 1 frontend toolchain.
- MySQL or MariaDB for MySQL-compatible development and matrix checks. CI covers MySQL 8.4 and MariaDB 11.4; the Phase 1 local gate was also exercised on MariaDB 12.3.

Production hosts do not require Git, Composer, Node.js, npm, SQLite, Playwright, or developer tests.

Composer's checked-in `config.platform.php` value targets PHP `8.3.0`, the minimum supported runtime. Keep that setting in place when updating dependencies from PHP 8.4 or newer so the committed lockfile remains installable on PHP 8.3. Dependency updates are not complete until the lockfile has also been clean-installed and tested with an actual PHP 8.3 CLI.

## Install from a clean clone

PowerShell:

```powershell
git clone <repository-url> waymark
Set-Location waymark

composer install --no-interaction --prefer-dist
Copy-Item .env.example .env
php artisan key:generate

npm ci
npm run build
php artisan filament:assets
```

After changing Composer requirements or updating the lockfile, verify the minimum-runtime resolution explicitly:

```shell
composer validate --strict
composer prohibits php 8.3.0 --locked
```

The second command must report that no installed package requires a PHP version incompatible with `8.3.0`.

Unix-like shell:

```shell
git clone <repository-url> waymark
cd waymark

composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate

npm ci
npm run build
php artisan filament:assets
```

Do not commit `.env`, generated keys, credentials, dependencies, compiled assets, or published Filament assets. Repository ignore rules cover these paths and `composer verify:repository` checks the policy.

## Configure MySQL or MariaDB

Create a database and a least-privilege application user using values appropriate to the local machine. The example names below are placeholders, not credentials:

```sql
CREATE DATABASE waymark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'waymark'@'localhost' IDENTIFIED BY '<local-password>';
GRANT ALL PRIVILEGES ON waymark.* TO 'waymark'@'localhost';
```

Set the matching untracked `.env` values:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=waymark
DB_USERNAME=waymark
DB_PASSWORD=<local-password>
```

Then initialise the schema:

```shell
php artisan migrate
```

Never run the automated test suite against development or production data. Use a dedicated empty test database for MySQL/MariaDB test runs as described in [`testing.md`](testing.md).

## Verify and run

```shell
composer test
composer verify:repository
npm run build
npm run test:e2e
php artisan serve
```

The default backend suite uses in-memory SQLite for fast feedback. CI and the documented process-environment override in `testing.md` exercise the same suite against MySQL/MariaDB. `npm run test:e2e` has no browser journeys in Phase 1 and succeeds without installing browser binaries; later approved phases add browser and visual tests.

Open the local URL printed by Artisan. The public root remains the Laravel scaffold placeholder until the approved Phase 2 visual implementation. Filament's admin panel is at `/admin` and is available only to explicitly authorised, active users.

## Windows PHP note

If PHP is installed but Composer reports missing extensions, enable the required extension DLLs in the CLI `php.ini` reported by `php --ini`. Confirm the effective configuration with:

```powershell
php --ini
php -m
composer diagnose
```
