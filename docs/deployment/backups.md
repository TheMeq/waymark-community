# Backing Up Waymark Community

Start and download backups under **System health**. Cron normally advances bounded work; **Continue backup work** provides the same safe fallback. A consistency window prevents concurrent writes while the database and private files are captured and checksum verified.

Backups contain the database, media/uploads, documents and allowlisted restoration identity. They do not copy source database credentials or host-specific connection/storage configuration. Optional encryption protects the archive, but a lost passphrase cannot be recovered.

- Keep at least one tested off-host copy separate from the hosting account.
- Download and independently preserve an archive before updates or risky host work.
- Review retention (`WAYMARK_BACKUP_RETENTION_COUNT`) and free staging space.
- Verify the archive and periodically rehearse recovery on an isolated environment.
- Store recovery keys and encryption passphrases in the group's password manager, not beside the archive.

See [`updates-backups-recovery.md`](updates-backups-recovery.md) for transaction consistency, capacity, manifest and orchestration details.
