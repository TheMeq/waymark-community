# Shared Hosting Deployment Requirements

Shared hosting is an official target, not a fallback.

## Destination host requirements

- supported PHP version with the required extensions, including GD and EXIF for community-photo processing;
- MySQL or MariaDB;
- web server capable of serving Laravel public entry point;
- writable storage directories;
- SMTP or PHP mail fallback;
- optional cron.

## Not required on destination

- Node.js
- npm
- Composer
- Docker
- Redis
- shell/SSH
- permanent queue worker
- developer tests

## Preferred layout

Application internals and `.env` live outside the public document root. Domain points to Laravel `public/` where host allows. Where document root cannot be changed, release/setup guidance should support a safe split layout where only public assets/entry point are web-accessible.

Setup must check that `.env` and internals are not publicly retrievable.

### Configurable document root

In the hosting control panel, unpack the release into an application directory outside the public document root. Configure the domain's document root as that application's `public` directory. A generic layout is:

```text
account-root/
├── applications/waymark/       application, vendor, storage and .env
│   └── public/                  domain document root
└── public_html/                 unused by this domain
```

Do not copy `.env`, `vendor`, `storage`, `bootstrap`, `config`, or application source into another publicly served directory.

### Fixed `public_html` document root

Some hosts do not allow the domain document root to change. Keep the release in a private application directory, copy only the contents of its `public` directory into `public_html`, and set `WAYMARK_APPLICATION_ROOT` to the absolute private application directory using the host's environment-variable facility. The bundled front controller uses that setting for `vendor`, `bootstrap`, `storage`, maintenance, and update paths.

```text
account-root/
├── applications/waymark/       private application root and .env
└── public_html/                 copied public entry point and compiled assets only
```

If the host cannot configure an environment variable, edit only the deployed `public_html/index.php` application-root assignment to point to the private application directory. Never put that host-specific path into the source repository or `.env` inside `public_html`.

Before installation can finish, the setup wizard checks that the detected document root does not contain the application or `.env`, and that production debug output is disabled. Treat a warning that the configured and detected public paths differ as a prompt to verify the split layout in the file manager.

## Release artifact

Normal administrators install a prebuilt Shared Hosting Release ZIP with production PHP dependencies and compiled frontend assets already present.
