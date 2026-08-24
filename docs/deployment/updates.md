# Updating Waymark Community

Use only an official stable Shared Hosting Release ZIP advertised by a correctly signed HTTPS release feed. **System → Updates** shows release notes, security priority and PHP/extension/database/disk compatibility; checking never installs automatically.

Before installing, confirm a recent independently downloadable backup and enough free space. Installation starts a mandatory fresh safety backup, stages and verifies every package file, enters maintenance, activates the fresh runtime, runs migrations and performs health checks. Do not close or bypass an operation merely because bounded work is waiting for cron or the manual continuation control.

If activation fails, Waymark restores the old application files and requires a fresh old-runtime rollback request to rebuild/import the previous database and verify the known-good system. Maintenance remains enabled for review. If the framework cannot boot, use `/waymark-update-recovery.php` with the private pending token by POST.

Keep the prior package, current package, checksums, release metadata and pre-update backup until the update is independently verified. Detailed integrity and state-machine behaviour is documented in [`updates-backups-recovery.md`](updates-backups-recovery.md).
