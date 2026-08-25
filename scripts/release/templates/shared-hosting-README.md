# Waymark Community {{VERSION}}

This is the standard Shared Hosting distribution of Waymark Community.

## Requirements

- PHP 8.3 or newer with the extensions checked by the setup wizard.
- MySQL 8.4 or MariaDB 11.4.
- HTTPS is strongly recommended.

Composer, Node.js, npm and Git are not required on the hosting account at runtime.

## Install

1. Extract `application/` into a private application directory.
2. Point the domain document root at that directory's `public/` folder.
3. Create an empty MySQL or MariaDB database.
4. Visit your domain and follow `/setup`.

The setup wizard writes validated environment values. Do not put credentials in `.env.example`.

## Operator documentation

Deployment, writable-directory and document-root guidance is in `docs/deployment/shared-hosting.md`. Backup, update and recovery procedures are in the other files under `docs/deployment/`. Security reporting and supported-version information is in `SECURITY.md`; release notes are in `CHANGELOG.md`.
