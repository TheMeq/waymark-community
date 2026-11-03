# Updates, Backups, and Recovery

## Updates

Waymark v1 uses the stable channel only. Configure an HTTPS metadata URL and the matching base64-encoded release-signing public key during setup or with `WAYMARK_RELEASE_METADATA_URL` and `WAYMARK_RELEASE_PUBLIC_KEY_BASE64`.

The feed is accepted only when its OpenSSL SHA-256 signature verifies, its channel is exactly `stable`, its version is stable SemVer, and its HTTPS package URL, SHA-256, size, requirements and release notes pass schema checks. Redirects are not followed. A failed or tampered feed produces a generic failure and no accepted update information.

The scheduler checks daily at 07:00; **System → Updates** also provides a manual check. Admin notices include plain release notes, a prominent security-release flag and separate PHP/extension/MySQL-or-MariaDB/disk compatibility results. Checks never auto-install an update.

An actionable update subsequently requires a fresh backup (including security releases), verified staging, maintenance mode, migrations, health checks and rollback on failure.

Update packages use a signed-feed SHA-256 plus an internal `release-manifest.json`. Every application file is size/checksum verified and staged before maintenance. Packages must contain `VERSION`, the application entry points, production Composer autoload files and compiled frontend manifest; they cannot target `.env`, `storage`, caches, tests, Node modules or other private/runtime paths. The updater then starts an unconditional `pre-update` BackupRun and returns while that bounded backup is queued/running. Cron, request fallback or the System health controls advance the same pipeline. No application file is activated until an administrator continues after the backup is completed and re-verified.

Continuation re-verifies the staged release and safety backup, snapshots affected application files, applies the release and records a private one-time pending-activation token. The token is carried in the server session and a CSRF-protected POST form, never a query string. A follow-up request boots the newly installed `vendor/autoload.php`, application bootstrap and service providers before it runs migrations and optimisation and checks the database, storage and activated `VERSION`. Transition responses use `Referrer-Policy: no-referrer`; rollback and staging material remain in place until activation succeeds.

If fresh activation fails, that new-release request restores only the previous application files and records a protected pending rollback. A second POST request boots the restored old release, rebuilds its tracked schema, imports the pre-update database/private/configuration backup and performs health checks under the old runtime. Maintenance remains active after verified rollback for administrator review. The authority tokens never appear in URLs. If either release cannot boot far enough to serve the transition, open `/waymark-update-recovery.php` and supply the pending activation token by POST. This framework-independent entry point restores the previous application files while retaining the pre-update backup, rollback state and maintenance evidence for manual disaster recovery.

Backup creation, restore, and update installation share one filesystem lock. A second destructive operation is refused while another is active, and start/success/failure events are appended to the private operations audit journal. The lock is independent of the database so it remains effective while a restore is replacing database state.

## Backups

Administrators can start and download a backup from **System health**. Creation is a persisted sequence of bounded steps: cron advances it once per minute, while **Continue backup work** provides the same safe progression when cron is unavailable. The scheduler starts one daily at 02:00 when cron is configured. Only one backup consistency window can run at a time. `WAYMARK_BACKUP_RETENTION_COUNT` controls how many completed archives are retained (14 by default).

Local private storage is the default. An S3-compatible disk can be selected during setup or configured with the standard `AWS_*` settings and `WAYMARK_BACKUP_DISK=s3`. Off-host storage is encouraged for disaster recovery.

Encryption is optional. An encrypted archive requires the exact passphrase at restore time; Waymark cannot recover a forgotten passphrase.

Each run temporarily uses Waymark's maintenance boundary to stop normal HTTP writes. Scheduled/fallback media-changing work is paused until the backup succeeds or fails. The database export runs in one transaction using SQLite snapshot semantics or InnoDB repeatable-read semantics, and private files are size/hash checked both before and after their bounded staging copy. A changed file fails the run as retryable; it can never produce a completed archive.

Backups cover the database, media/uploads, documents, and an allowlisted restoration configuration. New archives contain only `APP_KEY` and `APP_PREVIOUS_KEYS` from the source environment, never source database credentials, canonical URL, or host runtime/storage settings.

Waymark verifies the archive manifest, expected database component, every declared component size and SHA-256 checksum, and safe archive paths before recording a backup as completed or allowing restore.

Before creating an archive, Waymark checks that the local staging area has enough free space for the configured safety margin and the known private/configuration payload. A clear low-space failure occurs before export work begins.

## Recovery

Normal guided restore is available at **System → Restore backup** after fresh password confirmation (and fresh 2FA when enabled). Select a known completed backup and type the exact destructive confirmation `RESTORE WAYMARK`.

If normal administration is broken, open `/recovery`, select a Waymark backup, supply its encryption passphrase when applicable, provide the recovery key established during setup, and type the same destructive confirmation. Keep that key in the group's password manager: only its SHA-256 hash is stored in `WAYMARK_RECOVERY_TOKEN_HASH`, so Waymark cannot display or recover it later.

When a backup is larger than the hosting account's PHP upload/post limit, place it through the hosting control panel or SFTP in the private `storage/app/private/recovery-inbox` directory, then enter only its filename in the recovery form. `WAYMARK_RECOVERY_ARCHIVE_DIRECTORY` may point to another private server directory. Waymark accepts a simple `.zip` or `.zip.enc` filename only, resolves it inside that configured directory, and never accepts an arbitrary request path. Delete the server-side copy after recovery.

Direct portable restore requires the backup Waymark version to exactly equal the running Waymark version. A different-release archive is rejected before safety backup, maintenance or schema changes; install the matching release first. Guided restore verifies the target, starts a mandatory bounded `pre-restore` BackupRun, and pauses. Cron/request fallback/System health can advance it, and the guided page continues only after that run completes and verifies. Restore then replaces the database and private media/documents, merges only the allowlisted source cryptographic identity into the target `.env`, and preserves the target database connection, canonical URL, filesystem, cache, session, queue, and other host settings.

For an exact-version archive on a genuinely empty configured database, standalone recovery runs Waymark's tracked migrations before importing the portable snapshot. Existing compatible target schemas are validated and reused; update rollback deliberately rebuilds the old release schema before importing its safety backup. After health checks pass, restore idempotently completes the private installation marker, so a recovered fresh host cannot expose `/setup`. Guided restore automatically rolls back to its completed safety backup if activation fails. Standalone disaster recovery retains the approved best-effort safety policy because the current database may already be empty or broken. Keep an additional off-host copy before beginning.

## Maintenance mode

Administrators with fresh sensitive-action confirmation can configure the group-branded maintenance message, an optional expected return time, and an optional HTTPS contact/status link under **System → Maintenance mode**. The enabling browser receives a private signed, HTTP-only bypass cookie so an authorised administrator can check the application while visitors receive a `503 Service Unavailable` response. Recovery and the lightweight health endpoint remain reachable.

Restore and update orchestration enter this same filesystem-backed maintenance boundary automatically. Successful operations reopen the site; a failed destructive operation deliberately leaves maintenance active for investigation and recovery.
