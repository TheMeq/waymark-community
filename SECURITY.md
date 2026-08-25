# Security Policy — Waymark Community

## Reporting a security problem

Thank you for taking the time to report a possible vulnerability responsibly. Please report it privately through [GitHub's private vulnerability reporting form](https://github.com/TheMeq/waymark-community/security/advisories/new). Do not open a public issue for a suspected security problem.

A helpful report includes:

- the affected Waymark version;
- a clear description of the problem;
- steps that reproduce it;
- the likely impact; and
- a suggested fix or mitigation, if you have one.

Please do not include real member information unless it is essential and you have permission to share it. Do not test against a live walking-group installation without the owner's permission. We do not publish a guaranteed response or fix timetable, but private reports are the supported way to raise suspected vulnerabilities.

## Supported versions

The current stable v1 patch release receives security fixes. At present that public release is `v1.0.0`; the `v1.0.1` maintenance candidate is not supported as a release until it is independently accepted and published.

Older development snapshots, superseded patch releases and independently modified deployments are not supported release versions. If you are unsure whether a problem comes from Waymark, the hosting account or a local modification, please still report it privately; that distinction can be worked out safely afterwards.

## Running Waymark safely

Start with an official release package and verify its SHA-256 checksum before installation. Keep PHP and the database on supported versions, use HTTPS, and run production installations with `APP_DEBUG=false`.

The preferred hosting layout keeps `.env`, application source and private storage outside the public document root. If the drop-in `public_html` package is used, its protected `application/` directory must not be publicly retrievable; the setup wizard and System Health check this boundary. Stop and correct the host configuration if either reports exposure.

Use strong, unique secrets and a least-privilege database account. Keep reliable off-host backups, protect the recovery token and any backup passphrases, configure cron where the host permits it, review System Health, and apply only updates accepted through the signed stable feed.

Waymark asks for fresh re-authentication before sensitive administration. A fresh two-factor challenge is also required when the account has 2FA enabled. Do not weaken these controls in a custom deployment.

Uploads should continue through Waymark's allow-listed, MIME-inspected processing path. Published photographs use safely generated names and processed derivatives with EXIF removed; do not bypass that path by placing member files directly in public storage.

Never commit, send or publish `.env`, passwords, keys, member exports, production databases, private documents, uploads, logs or backups when asking for help.

## What this policy covers

Private reports about vulnerabilities in Waymark itself are welcome. Operational support applies to unmodified official Shared Hosting Release ZIPs on the documented runtime.

A compromised hosting account, unsupported server configuration or insecure custom code may sit outside the Waymark codebase, but a reporter is not expected to diagnose that boundary before getting in touch. Report the suspected issue privately and include what you know without exposing member data.
