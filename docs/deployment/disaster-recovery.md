# Restore and Disaster Recovery

Restore with the exact Waymark version that created the backup. A different-version archive is rejected before destructive work; install the matching verified release first.

For normal recovery, use **System → Restore backup** after fresh password and, if enabled, two-factor confirmation. Waymark creates and verifies a mandatory safety backup before replacing data. For an unbootable administration area, open `/recovery`, provide the private recovery token, select an approved archive (or a filename placed in the private recovery inbox), and type `RESTORE WAYMARK`.

After restore, verify the installation lock, homepage, administrator login, representative records/media, outgoing mail, cron heartbeat and System health. Keep maintenance enabled until checks pass. Preserve the original host data and archive off-host until sign-off.

If a restore fails, do not repeatedly retry against production. Preserve the failure state and logs, return to the known-good safety copy where available, and reproduce on an isolated exact-version environment. The full schema/import/configuration and updater rollback rules are in [`updates-backups-recovery.md`](updates-backups-recovery.md).
