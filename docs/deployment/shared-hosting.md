# Shared Hosting Deployment Requirements

Shared hosting is an official target, not a fallback.

## Destination host requirements

- supported PHP version with the required extensions, including GD and EXIF for community-photo processing;
- MySQL or MariaDB;
- web server capable of serving Laravel public entry point;
- writable storage directories;
- SMTP or PHP mail fallback;
- optional cron.

PHP must provide `ctype`, `curl`, `dom`, `exif`, `fileinfo`, `filter`, `gd`, `hash`, `intl`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml` and `zip`.

## Choose a release ZIP

Waymark provides two packages generated from the same exact source commit:

- `waymark-community-1.0.1-shared-hosting.zip` is preferred when the domain document root can point at the application's `public/` directory.
- `waymark-community-1.0.1-public-html.zip` is the drop-in option when the hosting document root is fixed.

Verify the selected ZIP against its adjacent SHA-256 and retain both files. Do not deploy from a source checkout.

For either layout, create an empty MySQL/MariaDB database with a least-privilege account, visit `/setup`, and follow the wizard. The installer checks PHP/extensions, paths, sensitive-file exposure, database and mail, writes the private environment, runs migrations and creates the installation owner. After setup, remove the downloaded archive, confirm setup is locked, and complete homepage/admin/media/email/System-health smoke checks.

Never copy an existing `.env` between hosts. Use new secrets and database credentials, and keep the recovery key in the group's password manager.

## Not required on destination

- Node.js
- npm
- Composer
- Docker
- Redis
- shell/SSH
- permanent queue worker
- developer tests

## Preferred configurable-document-root package

Application internals and `.env` live outside the public document root. Extract the `application/` directory from `waymark-community-1.0.1-shared-hosting.zip` into a private application location, make `storage/` and `bootstrap/cache/` writable, then point the domain at its `public/` directory.

Setup must check that `.env` and internals are not publicly retrievable.

A generic layout is:

```text
account-root/
├── applications/waymark/       application, vendor, storage and .env
│   └── public/                  domain document root
└── public_html/                 unused by this domain
```

Do not copy `.env`, `vendor`, `storage`, `bootstrap`, `config`, or application source into another publicly served directory.

## Drop-in fixed-document-root package

When the document root cannot be changed, upload `waymark-community-1.0.1-public-html.zip` and extract every file directly into an empty `public_html`, `htdocs`, `www`, or equivalent directory. Do not move files, copy a nested public directory, or edit `index.php`. Make `application/storage/` and `application/bootstrap/cache/` writable, then visit the domain.

```text
public_html/
├── index.php                    public front controller
├── .htaccess                    routing and directory-listing protection
├── build/, images/, ...         public assets
└── application/                 protected application, vendor, storage and private .env
```

This layout depends on Apache-compatible `.htaccess` overrides denying all requests to `/application/`. Before installation can finish, Waymark requests representative internal paths and blocks setup unless they are inaccessible. System Health continues to report this protection. If it reports exposure, stop using the site, ask the host to enable `.htaccess` overrides, or switch to the preferred package with a private application root.

## Scheduler and cron

Cron is strongly preferred. Configure the hosting control panel to run the release package's PHP executable and `artisan schedule:run` once per minute from the private application root. The scheduler writes a private heartbeat so Waymark can distinguish a working cron entry from one that has never run or has become stale.

When cron is unavailable, Waymark may run at most one deferred-photo job and one personal-data-export job from a throttled safe request or the manual `waymark:run-fallback` command. This keeps non-critical work moving on a limited host, but it does not make newsletters or reminders timely and it does not claim that work will happen when nobody visits the site. Scheduled communications retain their explicit manual commands until cron is configured.

## Release artifact

Both prebuilt ZIPs contain production PHP dependencies, compiled assets, installer, updater and recovery support. Neither requires Composer or Node at runtime.
