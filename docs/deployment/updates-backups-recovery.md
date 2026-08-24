# Updates, Backups, and Recovery

## Updates

Waymark v1 uses the stable channel only. Configure an HTTPS metadata URL and the matching base64-encoded release-signing public key during setup or with `WAYMARK_RELEASE_METADATA_URL` and `WAYMARK_RELEASE_PUBLIC_KEY_BASE64`.

The feed is accepted only when its OpenSSL SHA-256 signature verifies, its channel is exactly `stable`, its version is stable SemVer, and its HTTPS package URL, SHA-256, size, requirements and release notes pass schema checks. Redirects are not followed. A failed or tampered feed produces a generic failure and no accepted update information.

The scheduler checks daily at 07:00; **System → Updates** also provides a manual check. Admin notices include plain release notes, a prominent security-release flag and separate PHP/extension/MySQL-or-MariaDB/disk compatibility results. Checks never auto-install an update.

An actionable update subsequently requires a fresh backup (including security releases), verified staging, maintenance mode, migrations, health checks and rollback on failure.

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

## Maintenance mode

Administrators with fresh sensitive-action confirmation can configure the group-branded maintenance message, an optional expected return time, and an optional HTTPS contact/status link under **System → Maintenance mode**. The enabling browser receives a private signed, HTTP-only bypass cookie so an authorised administrator can check the application while visitors receive a `503 Service Unavailable` response. Recovery and the lightweight health endpoint remain reachable.

Restore and update orchestration enter this same filesystem-backed maintenance boundary automatically. Successful operations reopen the site; a failed destructive operation deliberately leaves maintenance active for investigation and recovery.
