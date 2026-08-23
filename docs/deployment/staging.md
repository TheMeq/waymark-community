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

These safeguards do not replace the separate database, uploads, secrets, and mail-trapping configuration required for a staging deployment.
