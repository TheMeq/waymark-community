# Backing Up Waymark Community

Start and download backups under **System health**. Cron normally advances bounded work; **Continue backup work** provides the same safe fallback. A consistency window prevents concurrent writes while the database and private files are captured and checksum verified.

Backups contain the database, media/uploads, documents and allowlisted restoration identity. They do not copy source database credentials or host-specific connection/storage configuration. Optional encryption protects the archive, but a lost passphrase cannot be recovered.

Managed Walk featured images, logos and favicons are included in this same
contract. A backup captures their `SiteMedia` database rows, Walk/SiteProfile
references, retained external fallbacks and required processed files beneath
private storage. Restore replaces the verified database and private-file set
together. Manifest SHA-256 values cover every included private component; the
backup destination directory itself is excluded to prevent recursive backups.

## Storage in supported layouts

- **Standard shared hosting:** persistent media is under the application's
  `storage/app/private/site-media` directory while the web document root points
  to `application/public`.
- **Drop-in `public_html`:** persistent media is under
  `public_html/application/storage/app/private/site-media`, protected with the
  rest of the application.

Do not create a public storage symlink for managed media. Waymark serves only
approved processed variants through application routes, including beneath an
application URL prefix. Application source and public files do not need to be
writable; only the established runtime storage and cache directories do.

Backups, restores, managed uploads and public delivery require no production
Node.js, Composer, Docker, Redis, S3 or resident worker. Cron is useful for
advancing bounded operations but the documented manual continuation remains
available on shared hosting.

- Keep at least one tested off-host copy separate from the hosting account.
- Download and independently preserve an archive before updates or risky host work.
- Review retention (`WAYMARK_BACKUP_RETENTION_COUNT`) and free staging space.
- Verify the archive and periodically rehearse recovery on an isolated environment.
- Store recovery keys and encryption passphrases in the group's password manager, not beside the archive.

See [`updates-backups-recovery.md`](updates-backups-recovery.md) for transaction consistency, capacity, manifest and orchestration details.
