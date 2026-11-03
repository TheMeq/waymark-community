# Media and Photo Architecture

## Two media classes

1. **Admin media library** — reusable site/hero/page/news/event imagery.
2. **Community gallery photos** — authenticated, attributed, event/album-linked, moderated uploads.

Do not collapse these workflows into one generic upload bucket.

## Managed site and Walk images

Walk featured images, the site logo, site favicon and default hero use the existing
`SiteMedia` model and secure ingestion pipeline. They are purpose-bound as
`walk_featured_image`, `site_logo`, `site_favicon` or `site_hero`; general Media-library
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
decorative because the public logo remains beside the visible group name. The
default hero is decorative background photography behind the hero text.

Owner changes are process-first and atomic: a replacement is fully validated
and processed before the Walk or singleton site profile switches reference.
Failed replacement leaves the previous image active. Referenced media cannot
be deleted. Unreferenced purpose-bound media may be marked orphaned for later,
retryable cleanup; forms do not delete physical files during navigation.

The Branding screen offers Upload, External and None for the default hero, with
an upload preview and saved-image status, using the same fallback-removal rules
as logo and favicon. Existing `hero_default_path` values remain external
fallbacks; `site_profiles.hero_media_id` references a managed upload. The public
hero and homepage sharing metadata resolve the managed `large` variant first.
Clearing both sources leaves no default photograph rather than substituting a
demo image; an explicitly pinned homepage hero still takes precedence.

## Processing and delivery

Accepted inputs are JPEG, PNG, WebP and AVIF when the installed server codecs
support them. The default limits are 10 MiB, 6000 by 6000 pixels, 24 million
pixels and a 128 MiB processing-memory budget; deployments may lower these
through the documented gallery processing configuration. Declared and decoded
MIME types must agree, orientation is normalised and only transformed output is
stored. SVG, GIF and ICO are not accepted.

Photographic Walk and default hero images use the responsive `master`, `large`, `medium` and
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

## Content image forms

Holiday images, page heroes, news featured images, testimonial portraits,
committee photos and image-and-text blocks use the same Upload / External URL /
None controls with previews. New uploads use the resource module capability,
the existing private raster-processing pipeline and the `content_image` purpose.
They do not require separate Media-library permission. Existing media-library
references and holiday fallback paths remain readable.

A prepared upload remains an orphan candidate until its owner saves. Failed
validation preserves the previous owner reference; unattached processed files
remain reference-checked cleanup candidates. Saved block references are included
in media usage checks, including soft-deleted content. External URLs are
validated without server-side downloads. None clears the selected image; when
a local image has a retained external fallback, the form offers to clear both.

Resource create/edit pages share a validation boundary that reports errors
visibly and maps domain fields to their form state paths. Page/news Builder
state is converted to the canonical flat block format on save and back to
Builder state on edit.
