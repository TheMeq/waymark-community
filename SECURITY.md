# Security Policy — Waymark Community

The platform is intended to hold account data, member-uploaded photographs, contact-form submissions, private committee information, and infrastructure credentials. Security is therefore part of normal product quality.

## Never commit sensitive data

Do not commit `.env`, passwords, API keys, membership exports, production databases, private documents, real user uploads, logs, or backups.

## Production defaults

- `APP_DEBUG` must be disabled.
- `.env` and application internals should be outside the public document root wherever possible.
- Setup must verify sensitive files are not publicly retrievable.
- Privileged configuration changes require re-authentication.
- Uploads are type/size validated and executable uploads are prohibited.
- Public photo derivatives have EXIF removed.

A formal vulnerability-reporting contact will be added when the product receives its final public identity and release location.
