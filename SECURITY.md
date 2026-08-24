# Security Policy — Waymark Community

## Supported versions

Before the first public v1 release, only the final reviewed release candidate is supported. After release, the current stable v1 patch line receives security fixes; older development snapshots and modified third-party deployments are not supported versions.

## Reporting a vulnerability

Report vulnerabilities privately through the repository host's private security-advisory channel or directly to the maintainer contact published with the release. Do not open a public issue, include real member data, or test against a live group without written permission. Include affected version, impact, reproduction steps and any suggested mitigation. Receipt will be acknowledged privately and disclosure coordinated after a fix is available.

## Deployment boundary

- Never commit or send `.env`, passwords, keys, member exports, production databases, private documents, uploads, logs or backups.
- Run with `APP_DEBUG=false`; keep `.env`, application source and private storage outside the public document root.
- Use a supported PHP/database release, HTTPS, strong unique secrets, least-privilege database credentials and reliable off-host backups.
- Complete the setup exposure checks, configure cron where possible, monitor System health and apply verified stable updates.
- Privileged operations require fresh re-authentication and, when enabled, a fresh two-factor challenge.
- Uploads are allowlisted, MIME inspected, safely named and published only as processed derivatives with EXIF removed.

Operational support covers unmodified official Shared Hosting Release ZIPs on the documented runtime. Hosting-account compromise, custom code and unsupported server changes remain the deployer's responsibility, but suspected Waymark vulnerabilities should still be reported privately.
