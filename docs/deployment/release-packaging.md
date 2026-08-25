# Release packaging

Waymark's two shared-hosting artifacts are built from an exact clean Git commit, never from the developer working directory:

```sh
php scripts/build-release.php
```

Use `--plan` to inspect the locked build and verification sequence without creating an artifact. Use `--output=<directory>` to place the ZIP and checksum outside the default ignored `dist/` directory.

The builder creates a temporary detached exact-commit clone, installs the tracked Composer and npm lockfiles, builds frontend assets, runs repository/PHP/PWA/tooling verification, prepares a production-only application tree, and installs optimized non-development Composer dependencies there. It refuses a dirty source worktree and removes its temporary workspace after success or failure.

The output contains:

- `waymark-community-<version>-shared-hosting.zip`, with `release-manifest.json` plus a private `application/` tree whose `public/` directory becomes the document root;
- `waymark-community-<version>-public-html.zip`, with public entry points/assets at ZIP root and the protected runtime under `application/` for direct extraction into a fixed web root.

Each manifest follows [`scripts/release/release-manifest.schema.json`](../../scripts/release/release-manifest.schema.json), declares its layout, and records the source commit, build time, minimum PHP version, database baselines, latest migration, `application_file_count`, and the size and SHA-256 of every application file. The adjacent `.sha256` authenticates the whole ZIP.

The builder verifies the finished archive before reporting success. An existing artifact can be checked independently with:

```sh
php scripts/verify-release.php dist/waymark-community-1.0.1-shared-hosting.zip
php scripts/verify-release.php dist/waymark-community-1.0.1-public-html.zip
```

Release preparation uses a runtime application + operator documentation allow-list. Both artifacts include production `vendor/`, compiled assets, installer and migrations, a generated production-safe `.env.example`, an operator README, deployment guides, and writable-directory placeholders. They exclude Git metadata, secrets, npm/build configuration, Waymark tests and Playwright/Codex/contributor files, reports, runtime uploads/media, backups, logs, caches, sessions, generated views, local databases, and design/developer reference material. Third-party Composer package contents beneath `vendor/` remain Composer's production distribution.

Release clean-install smoke tests take the verified ZIP as their only application source. They do not run Composer or npm in the extracted application and cover the web installer, homepage, administrator login, and a real site-media upload:

```sh
PHASE10_INSTALL_DB_DRIVER=mysql npm run test:release-install -- --archive=dist/waymark-community-1.0.1-shared-hosting.zip
PHASE10_INSTALL_DB_DRIVER=mariadb npm run test:release-install -- --archive=dist/waymark-community-1.0.1-shared-hosting.zip
PHASE10_INSTALL_DB_DRIVER=mysql npm run test:release-install -- --archive=dist/waymark-community-1.0.1-public-html.zip
PHASE10_INSTALL_DB_DRIVER=mariadb npm run test:release-install -- --archive=dist/waymark-community-1.0.1-public-html.zip
PHASE10_INSTALL_DB_DRIVER=mysql npm run test:release-install -- --archive=dist/waymark-community-1.0.1-public-html.zip --url-prefix=/demo-site/ndwg/
PHASE10_INSTALL_DB_DRIVER=mariadb npm run test:release-install -- --archive=dist/waymark-community-1.0.1-public-html.zip --url-prefix=/demo-site/ndwg/
```

The public-html matrix uses the repository's PHP 8.3 Apache fixture so real `.htaccess` routing, nested URL-prefix handling and `/application/` denial are exercised. The database connection variables use the `PHASE10_INSTALL_DB_` prefix documented by the script. Set `PHASE10_INSTALL_EMAIL_MODE` to `configured` or `later`. Set `PHASE10_INSTALL_DB_SCENARIO` to `exact-partial`, `generic-partial`, or `ambiguous` to exercise controlled recovery/blocking; omit it for an empty-database installation. Each run requires its own empty test database before any optional fixture is applied. Node, Playwright and Docker are external verification tools only; none is present in or required by the installed Waymark runtime.

## Production-package upgrade matrix

The builder can create an exact prior-commit candidate without checking out or modifying that commit:

```sh
php scripts/build-release.php --version=1.0.0 --commit=2bb55886924aa412d26d8157bb0d4a698cef237e
```

For the 1.0.1 gate, use the immutable ZIP downloaded from the public `v1.0.0` GitHub release rather than rebuilding the prior fixture. Set `WAYMARK_PREVIOUS_RELEASE_ARCHIVE` and `WAYMARK_CURRENT_RELEASE_ARCHIVE`, then run `tests/Integration/Operations/ProductionReleasePackageMatrixTest.php` once per matching layout. The matrix extracts the prior production package, migrates it, creates representative structured data and private media, stages the current production package through the same release verifier and application-file transaction as the updater, and runs the current migrations. Its controlled-failure path restores the file transaction and pre-update database, then proves that the 1.0.0 runtime boots with its data and media intact. This complements—not replaces—the updater's safety-backup, fresh-request activation, and framework-independent recovery tests.
