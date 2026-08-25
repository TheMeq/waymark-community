# Waymark Community {{VERSION}} - public_html package

This package is for Shared Hosting where the domain document root cannot be changed.

## Install

1. Extract every file directly into an empty `public_html`, `htdocs`, `www` or equivalent document root.
2. Create an empty MySQL or MariaDB database in the hosting control panel.
3. Visit your website.
4. Follow the Waymark Community `/setup` wizard.

Do not move files out of `application/` and do not edit `index.php`.

## Requirements and safety

- PHP 8.3 or newer and the extensions checked by setup.
- MySQL 8.4 or MariaDB 11.4.
- `application/storage/` and `application/bootstrap/cache/` must be writable by PHP.
- HTTPS is strongly recommended before entering administrator credentials.

Composer, Node.js, npm, Git and shell access are not required at runtime.

The included Apache rules must deny HTTP access to `application/`. If setup or System Health reports that internal application files are exposed, stop and ask the hosting provider to enable `.htaccess` overrides or use the standard package with its document root pointed at `public/`.

Backup, update, recovery and document-root guidance is bundled under `application/docs/deployment/`. Security reporting information is in `application/SECURITY.md`; release notes are in `application/CHANGELOG.md`.
