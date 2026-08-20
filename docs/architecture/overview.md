# Architecture Overview

## Runtime shape

```text
Browser
  ├─ server-rendered Blade HTML
  ├─ compiled Tailwind CSS
  └─ Alpine.js progressive enhancement
         │
         ▼
Laravel application
  ├─ Core identity/settings/auth
  ├─ Events (Walks/Socials/Holidays)
  ├─ Gallery/media/moderation
  ├─ CMS/news/homepage
  ├─ Governance/documents/committee
  └─ Operations/setup/backups/updates/health
         │
         ├─ MySQL/MariaDB
         └─ Local/S3-compatible storage
```

## Domain boundaries

Use domain-oriented organisation where complexity warrants it without forcing ceremonial DDD. Core business behaviour must be reusable independently from a specific HTTP controller so a future API can call the same operations.

Suggested major boundaries:

- Events
- Gallery
- Membership/Identity
- Content
- Governance
- Operations

## Shared hosting

The production runtime assumes PHP + MySQL/MariaDB + web server + writable storage. Cron is recommended but optional. No Node/Composer/Docker/Redis/permanent worker is required on the destination host.

## Data ownership

One installation owns one group's data. No central shared backend and no cross-installation dependencies are permitted for core operation.
