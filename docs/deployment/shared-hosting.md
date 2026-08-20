# Shared Hosting Deployment Requirements

Shared hosting is an official target, not a fallback.

## Destination host requirements

- supported PHP version and required extensions;
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

## Release artifact

Normal administrators install a prebuilt Shared Hosting Release ZIP with production PHP dependencies and compiled frontend assets already present.
