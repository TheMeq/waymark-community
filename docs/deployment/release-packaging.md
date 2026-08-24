# Release packaging

Waymark's shared-hosting artifact is built from an exact clean Git commit, never from the developer working directory:

```sh
php scripts/build-release.php --version=1.0.0
```

Use `--plan` to inspect the locked build and verification sequence without creating an artifact. Use `--output=<directory>` to place the ZIP and checksum outside the default ignored `dist/` directory.

The builder creates a temporary `git archive HEAD` workspace, installs the tracked Composer and npm lockfiles, builds frontend assets, runs repository/PHP/PWA/tooling verification, prepares a production-only application tree, and installs optimized non-development Composer dependencies there. It refuses a dirty source worktree and removes its temporary workspace after success or failure.

The generated `waymark-community-<version>-shared-hosting.zip` contains `release-manifest.json` plus files under `application/`. The manifest follows [`scripts/release/release-manifest.schema.json`](../../scripts/release/release-manifest.schema.json) and records the source commit, build time, minimum PHP version, supported database baselines, latest migration, and the size and SHA-256 of every application file. The adjacent `.sha256` file authenticates the whole ZIP.

The builder verifies the finished archive before reporting success. An existing artifact can be checked independently with:

```sh
php scripts/verify-release.php dist/waymark-community-1.0.0-shared-hosting.zip
```

The artifact includes production `vendor/`, compiled `public/build/` assets, installer and migrations, `.env.example`, and writable-directory placeholders. It excludes Git metadata, secrets, Node modules, tests, reports, runtime uploads/media, backups, logs, caches, sessions, generated views, local databases, and design/developer reference material.

Release clean-install smoke tests take the verified ZIP as their only application source. They do not run Composer or npm in the extracted application and cover the web installer, homepage, administrator login, and a real site-media upload:

```sh
PHASE10_INSTALL_DB_DRIVER=mysql npm run test:release-install -- --archive=dist/waymark-community-1.0.0-shared-hosting.zip
PHASE10_INSTALL_DB_DRIVER=mariadb npm run test:release-install -- --archive=dist/waymark-community-1.0.0-shared-hosting.zip
```

The database connection variables use the `PHASE10_INSTALL_DB_` prefix documented by the script. Each run requires its own empty test database. Node and Playwright are the external verification harness only; neither is present in or required by the installed Waymark runtime.
