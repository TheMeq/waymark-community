# Media and Photo Architecture

## Two media classes

1. **Admin media library** — reusable site/hero/page/news/event imagery.
2. **Community gallery photos** — authenticated, attributed, event/album-linked, moderated uploads.

Do not collapse these workflows into one generic upload bucket.

## Managed site and Walk images

Walk featured images, the site logo and the site favicon use the existing
`SiteMedia` model and secure ingestion pipeline. They are purpose-bound as
`walk_featured_image`, `site_logo` or `site_favicon`; general Media-library
records remain `library` media.

Local upload is the recommended source. An administrator may instead select a
validated HTTPS URL or root-relative site path. A retained external value stays
inactive while healthy managed media exists and becomes the fallback if that
managed presentation later becomes unavailable or is deliberately removed.
Unsafe paths and URLs fail closed.

A Walk has one featured image. Its image description is required when a new
managed or external image is selected. The description stored on the Walk is
the authoritative public alt text; the initially uploaded `SiteMedia` also
receives the description as generic metadata. Editing one Walk's description
does not change shared `SiteMedia` metadata. Logo and favicon media are
decorative because the public logo remains beside the visible group name.

Owner changes are process-first and atomic: a replacement is fully validated
and processed before the Walk or singleton site profile switches reference.
Failed replacement leaves the previous image active. Referenced media cannot
be deleted. Unreferenced purpose-bound media may be marked orphaned for later,
retryable cleanup; forms do not delete physical files during navigation.

## Processing and delivery

Accepted inputs are JPEG, PNG, WebP and AVIF when the installed server codecs
support them. The default limits are 10 MiB, 6000 by 6000 pixels, 24 million
pixels and a 128 MiB processing-memory budget; deployments may lower these
through the documented gallery processing configuration. Declared and decoded
MIME types must agree, orientation is normalised and only transformed output is
stored. SVG, GIF and ICO are not accepted.

Photographic Walk images use the responsive `master`, `large`, `medium` and
`thumbnail` variants. Logos use PNG output so transparency is retained.
Favicons accept one square raster of at least 32 by 32 pixels and produce a PNG
`favicon` variant; roughly 512 by 512 pixels is recommended, not required. PWA
install icons are separate static assets and are unchanged.

Processed files live under private persistent application storage at
`storage/app/private/site-media`. Public pages receive application route URLs
from presenters and never receive storage keys or private paths. The same route
generation remains aware of an installation URL prefix.

## Community upload pipeline

Authenticated + verified email → policy consent check → event/album selection → upload → safe validation → extract capture/orientation metadata → generate processed derivatives → strip EXIF → pending moderation → approve/reject → public gallery.

Original retention is configurable. Shared-host default should not keep unnecessarily huge originals after a safe processed master exists.

Every photo stores uploader and photographer attribution. Alternate photographer can be entered; moderation reviews attribution/caption/image together.

## Persistence and recovery

Managed `SiteMedia` belongs to runtime storage, not a Git checkout or release
archive. Code deployments and Git pulls do not own or overwrite it. Waymark
backups include the database records, owner foreign keys and private processed
variants together, and restore them as one verified recovery set.
