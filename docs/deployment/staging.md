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
