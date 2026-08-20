# Updates, Backups, and Recovery

## Updates

- stable channel only in v1;
- passive admin update notice;
- admin-friendly release notes;
- security releases clearly flagged;
- never auto-install;
- compatibility preflight before action;
- fresh backup before update, mandatory fresh backup for security release;
- staged verified release;
- maintenance mode;
- migrations;
- health check;
- rollback on failure.

## Backups

Manual + scheduled, configurable retention, local + optional S3-compatible destination, optional encryption, integrity verification.

Backup must cover database, media/uploads, documents, and restoration-relevant configuration.

## Recovery

Normal guided restore in admin plus guarded standalone recovery path for broken-admin scenarios. Strong recovery secret and destructive confirmation required.
