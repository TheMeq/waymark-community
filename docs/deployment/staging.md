# Staging Environment

Staging uses the same code as production but independent data, uploads, secrets, analytics, and email behaviour.

Required barriers:

- visually obvious STAGING indicator;
- automatic `noindex`;
- suppress public sitemap/indexing;
- outgoing email disabled, redirected, or trapped;
- separate analytics;
- production secrets never reused casually.

Use staging for committee review, manual content build, visual review, and final cutover rehearsal.

Set `APP_ENV=staging` for a staging installation. `WAYMARK_STAGING=true` is an explicit override for review environments that cannot use that application-environment name. Either setting activates the public `noindex,nofollow` metadata, full robots exclusion, sitemap suppression, analytics suppression, and the persistent admin banner.

When staging mode is active, Waymark preserves the `array` or `log` mail transports and forces any externally delivering transport to `log`. This application barrier complements, but does not replace, a host-level mail trap and separate staging recipient/domain configuration.

Use a separate database, uploads, secrets and cache/session namespace. Public analytics is suppressed entirely in staging even when consent and an analytics setting exist, so the production property cannot be polluted. Follow the [reference content-entry checklist](reference-content-entry-checklist.md) for the first deployment and record the representative desktop, tablet and mobile visual review.
