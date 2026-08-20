# Media and Photo Architecture

## Two media classes

1. **Admin media library** — reusable site/hero/page/news/event imagery.
2. **Community gallery photos** — authenticated, attributed, event/album-linked, moderated uploads.

Do not collapse these workflows into one generic upload bucket.

## Community upload pipeline

Authenticated + verified email → policy consent check → event/album selection → upload → safe validation → extract capture/orientation metadata → generate processed derivatives → strip EXIF → pending moderation → approve/reject → public gallery.

Original retention is configurable. Shared-host default should not keep unnecessarily huge originals after a safe processed master exists.

Every photo stores uploader and photographer attribution. Alternate photographer can be entered; moderation reviews attribution/caption/image together.
