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

Administrators can create and download a backup from **System health**. The scheduler creates one daily at 02:00 when cron is configured. `WAYMARK_BACKUP_RETENTION_COUNT` controls how many completed archives are retained (14 by default).

Local private storage is the default. An S3-compatible disk can be selected during setup or configured with the standard `AWS_*` settings and `WAYMARK_BACKUP_DISK=s3`. Off-host storage is encouraged for disaster recovery.

Encryption is optional. An encrypted archive requires the exact passphrase at restore time; Waymark cannot recover a forgotten passphrase.

Backup must cover database, media/uploads, documents, and restoration-relevant configuration.

Waymark verifies the archive manifest, expected database component, every declared component size and SHA-256 checksum, and safe archive paths before recording a backup as completed or allowing restore.

## Recovery

Normal guided restore is available at **System → Restore backup** after fresh password confirmation (and fresh 2FA when enabled). Select a known completed backup and type the exact destructive confirmation `RESTORE WAYMARK`.

If normal administration is broken, open `/recovery`, upload a Waymark backup, supply its encryption passphrase when applicable, provide the recovery token established during setup, and type the same destructive confirmation. Keep that token in the group's password manager: only its SHA-256 hash is stored in `WAYMARK_RECOVERY_TOKEN_HASH`, so Waymark cannot display or recover it later.

Restore replaces the current database, private media/documents and restoration configuration. Keep an additional off-host copy of the current state before beginning.
