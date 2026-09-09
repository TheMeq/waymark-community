# Waymark Managed Site and Walk Media Design

**Status:** Design for independent review; implementation has not started.

**Baseline:** Post-v1.0.2 development branch `codex/add-walk-auto-slug` at `c521bc843588a2b456f4bcd2be65deb38bafcd7c`.

## Purpose

Make locally managed imagery the normal choice for one Walk featured image, the site logo, and the site favicon. Waymark is self-hosted software, so these important visual assets should not disappear because an unrelated website or remote account becomes unavailable.

The product rule is:

> Local upload first. External URL optional.

The feature extends Waymark's existing `SiteMedia` subsystem. It does not introduce another upload store, a second media model, or a replacement media library.

## Goals

- Let an authorised Walk creator upload, preview, replace, remove, and describe one featured image without seeing an internal storage path.
- Preserve the managed featured image through Walk draft checkpoints, resume, final submission, and later editing.
- Let an authorised administrator manage a local logo and one-source local favicon from Branding settings.
- Retain deliberate secure external URL/site-path support and all existing stored values.
- Store managed files under persistent private application storage and serve only approved processed variants.
- Make replacement atomic from the owner's perspective: a failed replacement leaves the old image active.
- Protect referenced media from deletion and make purpose-bound orphaned media detectable without deleting it during form navigation.
- Preserve managed references and files together through backup and restore.
- Work in both supported deployment layouts and beneath an application URL prefix.

## Non-goals

- Walk galleries or more than one featured image per Walk.
- A general-purpose media-library redesign.
- A cropper, image editor, or arbitrary focal-point editing beyond capabilities already exposed by `SiteMedia`.
- CDN, S3, or new filesystem configuration work.
- Downloading or importing existing remote images.
- Unrelated uploads, historical deduplication, or conversion of existing media-library records.
- Raw SVG support or ICO generation.
- Replacing the PWA install icons. They are currently separate static application assets in `PwaController` and remain outside this feature.
- Version, package, feed, tag, release, or release-qualification work.

## Existing Architecture and Constraints

### SiteMedia

`App\Domain\SiteMedia\Models\SiteMedia` already records a generated UUID storage key, private disk, processed variant paths, MIME type, dimensions, size, alt/decorative state, focal point, processing state, health state, and regeneration-cleanup state. Model and database invariants require meaningful alt text unless a record is decorative, constrain focal points, and reject unsafe processed paths.

`UploadSiteMedia` generates a UUID namespace beneath `site-media/`, delegates decoding and variant creation to the existing image-ingestion pipeline, creates the record and audit in a transaction, and removes the new namespace on failure. `SiteMediaStorageReference` permits only generated raster paths on the configured private gallery disk. Client filenames never become storage identities.

`SiteMediaPresenter` and `SiteMediaStreamController` resolve a named processed variant and stream it through an application route. They do not disclose arbitrary private paths. The stream sets the recorded image MIME type and `nosniff`; missing or unsafe variants fail closed.

The current accepted raster formats are JPEG, PNG, WebP, and AVIF, subject to the server's decoder/encoder support. Upload inspection compares declared and decoded MIME types, enforces configured byte, dimension, pixel, and memory limits, normalises orientation, and writes only transformed output. SVG and ICO are not supported. SVG remains unsupported in this feature.

The current processing configuration is gallery-wide and normally chooses JPEG output. That is appropriate for photographic Walk images, but it can discard logo transparency and does not define a favicon-specific PNG output. The shared ingestion implementation therefore needs a purpose-aware processing profile; it must not be forked into another ingestion system.

### Current references and deletion

Existing content models reference `site_media` directly through nullable foreign keys with `nullOnDelete()` semantics: CMS page heroes, news featured media, testimonial images, and committee-role public photos. This establishes the repository convention: deleting a media record must not delete its owning content.

There is no central usage query today. `DeleteSiteMedia` changes a record to removed/deletion-pending and deletes its namespace without first checking those references. The Media library's confirmation text calls an item unused, but the action does not prove that invariant. This feature must add the missing reference protection before adding more owners.

`RegenerateSiteMedia` already demonstrates safe namespace replacement: it creates a replacement first, transactionally switches the retained record to the new namespace, and tracks cleanup of the previous namespace for retry. Regeneration is available only when a safe source `CommunityPhoto` exists; ordinary direct `SiteMedia` uploads do not necessarily retain a regenerable source. Logo, favicon, and Walk replacement therefore remain re-upload operations rather than promising regeneration from an unavailable original.

### Current permissions

`UploadSiteMedia`, metadata updates, regeneration, repair, and deletion all use `ManagesSiteMedia`, which requires `ModuleCapability::ManageSiteMedia`. The Media library uses the same capability. Branding settings require `ModuleCapability::ManageContent`.

Walk leaders normally have Walk create/update authority but not general SiteMedia administration. Granting `ManageSiteMedia` to enable a Walk upload would broaden access incorrectly. `User::hasCapability()` already fails closed for inactive accounts. Initial draft creation also requires verified email through `WalkPolicy::create`; subsequent featured-image changes must use the existing Walk create/update policy decision rather than reproduce capability logic in a form.

### Current Walk and branding presentation

Walks currently store nullable `featured_image_path`. The admin form exposes it as `Featured image path`, and draft Step 4 checkpoints it with the other Walk-detail fields. Public card and detail view models call `WalkFeaturedImage`, which accepts only an existing `/images/demo/` raster reference. Cards otherwise retain their approved default image; details omit the image section.

`SiteProfile` currently stores `logo_path` and `favicon_path`. Branding settings expose URL fields, `PublicBranding` resolves them, and the public header/footer render the logo decoratively beside the site name. The public layout emits the favicon link. Branding output is cached and `SiteProfile` changes already invalidate that cache.

The path columns for Walk featured image, logo, and favicon are currently 255-character strings. Branding validation permits 2,048 characters. The feature's forward migration must align retained external-value storage with the 2,048-character validation contract; it must not truncate values on migration or rollback.

### Persistent storage, updates, and backup

The configured `local` disk is rooted at `storage/app/private`. `SiteMedia` namespaces therefore live outside public files and outside source-controlled deployment content. Production update verification forbids release packages from targeting `.env`, `storage`, and public storage links, so code replacement and both supported ZIP layouts leave runtime storage in place.

`CreateBackup` exports every database table and includes every file on the local private disk except the backup directory itself. `RestoreBackup` restores the database, replaces the private-disk contents with the verified `private/` components, and then performs its health checks. Consequently, new SiteMedia rows, owner foreign keys, and `site-media/` variants naturally participate together, provided this feature continues using the existing private disk and adds focused proof of the three new owner cases.

## Design Decisions

### 1. Persistence and media purpose

Managed images continue to use `SiteMedia` records and generated `site-media/{uuid}/...` namespaces on the existing private disk.

Add a constrained media purpose with these values:

- `library` — existing/general media-library records;
- `walk_featured_image`;
- `site_logo`;
- `site_favicon`.

Existing records are backfilled to `library`. Purpose is application-enforced in the same style as other domain state strings and is included in SiteMedia audit snapshots. It controls required variants, allowed owner operations, and orphan eligibility; it is not a new media hierarchy or browsing taxonomy.

Add nullable `orphaned_at` metadata. New purpose-bound media starts cleanup-eligible until an owning reference is committed. A successful owner assignment clears `orphaned_at`. Removing or replacing the final reference sets it only after the owner transaction commits. General `library` items do not become automatic orphans merely because no content currently references them.

No physical file deletion occurs because a user moves between wizard steps, navigates away, or closes a browser. Orphan metadata makes abandoned purpose-bound records discoverable. Physical removal remains a separate, retryable SiteMedia lifecycle operation that rechecks references immediately before deletion.

### 2. Shared upload boundary

The image-security and persistence body currently inside `UploadSiteMedia` becomes a narrowly reusable internal SiteMedia creation boundary. It retains:

- actual raster inspection and decoder checks;
- configured size, dimension, pixel, and memory limits;
- UUID namespace allocation;
- processed variant generation;
- SiteMedia record and audit creation;
- failure cleanup; and
- SiteMedia storage-reference validation.

The existing `UploadSiteMedia` remains the Media-library facade and retains the `ManageSiteMedia` requirement. Domain-specific owner actions authorise their own aggregate first and then call the same internal creation boundary:

- Walk featured-image actions require the existing Walk create/update authority for the persisted Walk;
- branding logo/favicon actions require `ManageContent`.

The internal boundary is not a controller/form API and does not accept an `authorised=true` boolean or an arbitrary owner supplied by the browser. This preserves one ingestion pipeline without granting Walk leaders access to the Media library or unrelated SiteMedia records.

### 3. Purpose-aware processing profiles

Purpose selects a server-owned processing profile; clients cannot submit variant paths, MIME output, purpose, or storage keys.

- `walk_featured_image` uses the existing photographic processing limits and responsive content variants.
- `site_logo` uses the same inspection, limit, decode, orientation, and storage code but produces PNG-capable processed variants so transparent raster logos remain understandable. It never enables SVG.
- `site_favicon` requires a square decoded raster at least 32 by 32 pixels, recommends approximately 512 by 512 or larger in the UI, and produces a local PNG favicon variant from that one source. Administrators do not provide ICO or multiple sizes. The public favicon link uses the generated variant's actual URL and `image/png` type.
- `library` retains current behaviour.

The health checker determines required variants from the record's purpose rather than assuming every SiteMedia item has the gallery's four variants. Presentation still returns `null` when the requested processed variant is absent or unsafe. Where no retained safe source exists, repair guidance says to replace/re-upload the asset rather than offering regeneration that cannot succeed.

#### Alt-text ownership contract

- A `walk_featured_image` SiteMedia record is always non-decorative.
- On initial Walk image upload, the submitted image description is written to both `SiteMedia.alt_text`, satisfying the SiteMedia model invariant and providing generic media metadata, and `walks.featured_image_alt_text`, which is the authoritative owner-specific public alt text.
- If that SiteMedia record is later shared, including through Walk duplication, editing one Walk's image description updates only that Walk's `featured_image_alt_text`. It must not mutate the shared `SiteMedia.alt_text` or alter another owner's presentation.
- `site_logo` and `site_favicon` SiteMedia records are explicitly decorative, with `is_decorative = true` and no SiteMedia alt text.

### 4. Data model and foreign keys

Forward migrations from v1.0.2 add:

| Table | Column | Contract |
|---|---|---|
| `site_media` | `purpose` | Non-null string, default/backfill `library`, indexed with lifecycle state |
| `site_media` | `orphaned_at` | Nullable timestamp used only for purpose-bound orphan eligibility |
| `walks` | `featured_image_media_id` | Nullable foreign key to `site_media`, `nullOnDelete()` |
| `walks` | `featured_image_alt_text` | Nullable text; required by new UI when a new managed or external Walk image is active |
| `site_profiles` | `logo_media_id` | Nullable foreign key to `site_media`, `nullOnDelete()` |
| `site_profiles` | `favicon_media_id` | Nullable foreign key to `site_media`, `nullOnDelete()` |

Add the corresponding `belongsTo` relationships. Owner deletion never cascades from SiteMedia. `nullOnDelete()` is a defensive database fallback consistent with existing SiteMedia references; normal application deletion is stricter and refuses to remove referenced media.

Retain `walks.featured_image_path`, `site_profiles.logo_path`, and `site_profiles.favicon_path`. Widen these columns safely to 2,048 characters so secure external URLs accepted by application validation fit the database on MySQL/MariaDB as well as SQLite. Do not clear, rewrite, fetch, or inspect remote content during migration.

Migration `down()` must not perform a lossy contraction from 2,048 to 255 characters. If a reversible contraction cannot prove all stored values fit, it must fail clearly or leave the wider safe type in place; it must never silently truncate user data. Waymark's update rollback restores the pre-update database backup and does not justify a destructive schema contraction.

The generated fresh-install schema manifest is updated after the authoritative forward migrations. A migrated v1.0.2 database and a fresh installation must have the same columns, indexes, constraints, and recorded migration set.

### 5. Active-source contract

No separate source-mode column is required. Active state is deterministic and is displayed explicitly:

1. a valid managed SiteMedia reference is active;
2. otherwise a valid retained external URL/site path is active;
3. otherwise the Walk/detail has no specific image and keeps its existing default/omission behaviour.

The form never presents both sources as competing editable inputs. It shows one active mode and an explicit control to switch modes. Managed upload and external input are mutually exclusive write operations:

- choosing and saving a managed upload sets the managed reference; an existing legacy value is retained as an inactive fallback unless the administrator explicitly clears it;
- choosing and saving an external image clears the managed reference, saves the validated external value, and makes the old managed item orphan-eligible only if no other owner uses it;
- removing managed media offers an explicit choice: reveal the retained external fallback when one exists, or clear both sources and use no specific image;
- clearing an external image never deletes a managed record.

The UI states which source is active and whether a retained fallback will become active after removal. Rendering uses the same precedence centrally and never guesses in Blade.

### 6. Reference and usage protection

Introduce one `SiteMediaUsage` query/service as the authority for whether a media record is referenced. It checks all existing owners and the new owners:

- CMS page `hero_media_id`;
- news article `featured_media_id`;
- testimonial `image_media_id`;
- committee role `public_photo_media_id`;
- Walk `featured_image_media_id`;
- SiteProfile `logo_media_id` and `favicon_media_id`.

`DeleteSiteMedia` locks the media record, invokes this query, and refuses before changing lifecycle state or files when any reference exists. The result supplies concise usage labels for the administrator without exposing private owner data. Every orphan-cleanup attempt invokes the same query again inside its final decision boundary. New SiteMedia owner fields added later must extend this query and its contract test.

Sharing a managed image reference is safe. In particular, the existing Walk duplication option for Featured image copies the managed reference, retained fallback, and Walk-specific alt text when selected. Replacing or removing the image on one Walk does not modify the SiteMedia record or affect the other Walk; cleanup is blocked while any reference remains.

### 7. Atomic replacement and removal

Walk and branding replacement follow the same ordering:

1. Authorise the owner operation.
2. Fully validate and process the new upload into a new purpose-bound SiteMedia record and namespace.
3. Start an owner transaction and lock the Walk or singleton SiteProfile.
4. Re-authorise/revalidate the current owner state, set the new reference, persist related alt/source state, and clear the new record's `orphaned_at` value.
5. Commit the owner switch.
6. Only after commit, check whether the prior media is still used; if not, mark it orphaned for later cleanup.

If processing or the owner transaction fails, the prior reference stays active. The new record/namespace is removed through the SiteMedia cleanup boundary where possible; a crash can leave only a purpose-bound record already marked as an orphan, never an active broken reference.

Removal transactionally clears only the requested owner reference and source values. It does not call filesystem deletion from a Filament form. It then marks the former media orphaned only when the central usage query reports no remaining owner.

## Walk Featured Image Experience

### Form presentation

Replace the raw `Featured image path` field on Walk create/edit with a `Featured image` section:

- Local upload is the default and recommended mode.
- The file control supports Filament's accessible choose-file and drag/drop behaviour where available.
- A pending local upload has a temporary preview; saved managed media uses `SiteMediaPresenter` for its preview.
- The section supports Replace image and Remove image.
- A meaningful image description is required for a newly selected managed image or external image. The concise help text explains that it is read by people who cannot see the image.
- `Use an external image instead` reveals a secure external URL/site-path input and explains that local upload keeps the image under the group's control.
- Internal disks, storage keys, namespaces, and `featured_image_path` are not shown.

External entries use the repository's safe public-URL convention: an HTTPS URL or a root-relative site path with no protocol-relative form or traversal. New values allow up to 2,048 characters. Arbitrary filesystem paths, `storage/` paths, executable schemes, protocol-relative URLs, traversal, and insecure remote HTTP URLs are rejected.

Existing stored values are not changed during upgrade. The presentation resolver is expanded from its current demo-only behaviour to a unified Walk image presenter:

- managed media is presented through `SiteMediaPresenter`;
- otherwise a retained value is rendered only when it satisfies the safe public-URL/site-path rules;
- the existing verified `/images/demo/` descriptions remain supported;
- unsafe or unavailable managed presentation returns no image rather than a broken source.

For upgraded external images that have no stored description, presentation uses a concise title-based fallback and the next authorised edit requests a proper description. New or changed image selections cannot be saved without the required description.

### Draft lifecycle

The image section remains in Step 4 of the five-step Add Walk wizard. By that point, the approved resilient flow has already persisted and authorised a Draft Walk after Step 1.

- Before checkpoint, Filament/Livewire temporary upload handling may show a preview but has not created an ownerless SiteMedia record.
- Advancing from Step 4 validates the image mode and description, processes a pending upload, and atomically attaches it to that persisted Draft.
- The managed reference, retained fallback, and alt text are part of the Step 4 checkpoint state loaded by resume.
- Back/Next navigation does not remove an attachment.
- Final submission reuses the same Draft and reference; it does not upload the file again.
- Editing an existing Walk uses the same owner action and policy.
- Failed upload or final submission leaves the last successful Draft checkpoint, including its active image, intact.

The change does not alter slug generation, inline Grade/Tag creation, leader eligibility, draft visibility, or publication transitions.

### Public rendering

Public card and detail view models receive the Walk relationship with its featured media eagerly loaded and call the unified presenter. Blade receives nullable presentation data only; it contains no storage checks or source-selection logic.

- Cards use the managed/external image when available and otherwise preserve the approved default card image and rhythm.
- Details render the active image responsively with the approved rounded treatment and omit the image section cleanly when presentation fails.
- Detail SEO receives the same active image URL.
- The public image description comes from `walks.featured_image_alt_text`, with the SiteMedia description and existing legacy resolver used only as compatibility fallbacks.

## Logo Experience

Branding settings replace the ordinary Logo URL as the primary control with an upload-first Logo section:

- preview the active managed or external logo against the existing neutral/light admin surface;
- upload or replace a local raster logo;
- remove it explicitly;
- choose `Use an external logo instead` to reveal the existing URL/site-path value;
- state which source is active and whether an inactive external fallback is retained.

Managed logo media is stored with purpose `site_logo` and decorative metadata. In the current public header and footer the logo sits beside the group name and the enclosing home link already has an accessible name, so the image keeps `alt=""` to avoid announcing the group name twice. The visible group name remains present; the logo must not become the sole accessible identity.

`PublicBranding` resolves the managed logo through `SiteMediaPresenter` before the retained external value. The same resolved URL is used by public header/footer output and organisation SEO metadata. Saving or changing the managed reference uses the existing `SiteProfile` cache invalidation path.

Existing external logo values remain unchanged and continue as the fallback. SVG is not accepted for managed upload because the ingestion pipeline has no SVG sanitiser. Existing external/site-path values still pass the established safe URL rules, but this feature does not weaken content security or claim that externally hosted SVG is locally validated.

## Favicon Experience

Branding settings provide one upload-first Favicon section:

- recommend a square source around 512 by 512 pixels or larger;
- validate that decoded content is square and at least 32 by 32 pixels;
- show a preview of the active favicon;
- allow replace, remove, and an explicit external fallback mode;
- hide storage paths and browser-size details.

The shared processor creates the managed PNG favicon variant. No ICO, Apple touch icon, or manual multi-size input is required. Favicons are decorative and do not require alt text.

When a managed favicon is active, the public layout emits a local `SiteMedia` stream URL with `type="image/png"`. When it is absent, a safe retained external favicon continues to work. The existing PWA manifest's static 192- and 512-pixel application icons and service-worker shell cache remain unchanged; they are install icons, not the configurable browser favicon covered here.

## Permissions and Audit

| Operation | Required authority |
|---|---|
| Upload/replace/remove a Draft Walk image | Existing update authority for that persisted Draft Walk |
| Upload an image while creating a Walk | Successful initial Draft creation followed by existing update authority for that Draft |
| Upload/replace/remove an existing Walk image | Existing `WalkPolicy::update` result for that Walk |
| Upload/replace/remove logo or favicon | `ModuleCapability::ManageContent` |
| Use the general Media library | Existing `ModuleCapability::ManageSiteMedia` |
| Delete/regenerate/repair general media | Existing `ModuleCapability::ManageSiteMedia`, plus new reference protection for deletion |

All capabilities continue to fail closed for inactive users through `User::hasCapability()`. Initial Walk creation continues to require verified email. The feature does not add a new role or grant `ManageSiteMedia` to Walk leaders.

Domain actions enforce permissions; hiding a Filament control is not sufficient. The Walk action confirms that the requested media purpose is `walk_featured_image` and that the target Walk is the authorised owner. Branding actions accept only `site_logo` or `site_favicon`. Upload, reference switch, removal, failed cleanup, and later physical deletion remain auditable through SiteMedia and existing branding/Walk audit conventions.

## Security, Validation, and Accessibility

- Trust decoded raster content, never a client filename or extension.
- Preserve exact declared/decoded MIME matching, server codec checks, upload-error handling, file-size limits, dimension limits, pixel budget, processing-memory budget, orientation normalisation, and transformed-output storage.
- Accept only JPEG, PNG, WebP, or AVIF inputs supported by the deployed server. Produce only supported raster output. SVG, GIF, ICO, HTML, PDFs, and other content are rejected.
- Keep source and processed files on private storage. Never expose an arbitrary source file, storage key supplied by a request, local filesystem path, or unapproved variant.
- Stream only safe generated paths with the correct MIME type and `X-Content-Type-Options: nosniff`; unavailable or unhealthy presentation fails closed.
- Display human-readable validation messages in the owning form and leave the previous active media unchanged.
- Require meaningful Walk image descriptions for new/changed images. Do not use filenames as alt text.
- Keep logo images decorative only while adjacent visible site-name text and an accessible home-link name remain.
- Favicons have no alt-text requirement.
- Preserve accessible file-control labels, keyboard operation, error association, status announcements, touch target sizing, and WCAG 2.2 AA contrast/focus behaviour.

## Backup, Restore, Deployment, and Upgrade

### Backup and restore

No second backup component is introduced. The database exporter already includes all tables, and local private backup enumeration already includes `site-media/` variants. The implementation must prove this contract with a backup/restore integration scenario containing:

- a Walk referencing managed featured media;
- a SiteProfile referencing managed logo and favicon media;
- the corresponding private processed variants; and
- retained legacy/external fallback values.

After restore, the database relationships and private files must resolve through the normal SiteMedia routes. Missing media files must produce a clean absent/broken-health state, not expose a path. Backup manifest hashes continue to protect every included private file.

Because both production layouts use the application's private `storage/` tree and the same database/route layer, no layout-specific media store or public symlink is added.

### Deployments and shared hosting

- Standard layout keeps media beneath the private application's `storage/app/private/site-media` area while the document root points to `application/public`.
- Drop-in `public_html` keeps media beneath `public_html/application/storage/app/private/site-media`, protected with the rest of the application and served through the public front controller.
- Application code and public source remain non-web-writable; only established runtime storage and cache directories require write access.
- Release archives continue to contain placeholders rather than runtime media and cannot overwrite storage during upgrade.
- Route generation continues to use Waymark's application-prefix-aware helpers, so media, logo, and favicon URLs work below a URL prefix.
- Production requires no Node.js, Composer, Docker, Redis, S3, shell access, or resident worker for upload and presentation.

### Forward upgrade

The upgrade from v1.0.2 uses new forward migrations only. Published historical migrations remain unchanged. The migrations:

- add nullable owner references and nullable Walk alt text;
- add purpose/orphan metadata with a safe default for current SiteMedia rows;
- widen retained external-value columns without truncation;
- create indexes/constraints supported by SQLite, MySQL, and MariaDB;
- perform no network or filesystem import; and
- leave every existing URL/path value intact.

No migration attempts to download a remote image, validate remote availability, or turn it into locally hosted content. Administrators opt into local management by uploading a replacement after upgrade.

## Failure and Concurrency Semantics

- The owner's current image remains active until new processing and the owner-reference transaction both succeed.
- Owner rows and involved media rows are locked during the reference switch so two saves cannot orphan the winning media or activate a partially processed item.
- A stale or unauthorised Walk/branding request fails before owner mutation.
- A failed new upload cleans its reserved namespace and does not leave a healthy active record.
- A crash between media creation and attachment leaves a purpose-bound orphan candidate, not an invalid owner reference.
- Failure to mark or physically remove old media never rolls back the already valid new owner reference; lifecycle state records that cleanup remains outstanding.
- Deletion always rechecks all known usages, so a concurrent new reference prevents removal.
- Public presentation returns the safe external fallback or the existing no-image/default behaviour if managed presentation is missing; it never exposes a private path.

## Acceptance Scenarios

### A. Walk managed upload

An authorised creator starts a Walk, uploads and previews one featured image in Step 4, advances to checkpoint it, leaves, resumes the Draft, and sees the same managed image and description. Final submission keeps the same reference. The public card and detail use the local processed image route after publication.

### B. Walk external fallback

An upgraded Walk with a safe legacy/external reference and no managed reference continues to render it. Upgrade neither downloads nor rewrites the resource. An administrator can explicitly retain it or replace it with a managed upload.

### C. Walk replacement

The current image remains active while the replacement is validated and processed. A failed replacement leaves the current reference untouched. A successful replacement commits the new reference, then marks the former media orphaned only if no other owner uses it.

### D. Walk removal

Removing managed media clears only the Walk reference. The user explicitly chooses whether a retained safe external fallback becomes active or all Walk-specific image configuration is cleared. Other Walk data and shared media usages remain unchanged.

### E. Logo

An authorised content administrator uploads and previews a raster logo, saves it, and sees the local image in the public header/footer and organisation SEO. Replacement and removal use atomic/reference-safe behaviour; a retained external logo remains compatible.

### F. Favicon

An authorised content administrator uploads one valid square source. Waymark produces a PNG variant, and the public head references its local media URL. The browser favicon no longer depends on a third-party site. Non-square or too-small input is rejected clearly.

### G. Invalid upload

A malformed, mismatched, unsupported, over-limit, or undecodable upload is rejected with a human-readable error. It creates no active SiteMedia record, leaves no usable failed namespace, and does not replace the prior owner reference.

### H. Permissions

An active authorised Walk leader can upload for a Walk they may update. An unrelated or inactive user cannot. Walk upload does not provide the Media library or general SiteMedia administration. Branding remains restricted to `ManageContent`.

### I. Backup and restore

A supported backup contains the SiteMedia rows, Walk/SiteProfile references, and required private variants. Restoring it returns the Walk image, logo, and favicon together, and their local URLs resolve in both supported layouts.

### J. Upgrade

Existing v1.0.2 Walk/logo/favicon values and current SiteMedia usages remain intact. New references are nullable, no remote requests occur, and no existing image is silently selected, removed, or internalised.

### K. Fresh install

The generated fresh-schema manifest matches the authoritative migrated schema, including purpose/orphan metadata, nullable foreign keys, widened fallback columns, constraints, indexes, and migration names.

### L. Deployment

Git pulls, application code replacement, and standard or `public_html` package updates do not remove private managed media. Public URLs preserve an installation prefix, protected application paths remain inaccessible, and no new runtime tool or writable-source requirement is introduced.

## Focused Implementation Verification

Implementation should proceed with focused TDD at each domain boundary. The eventual implementation verification covers:

- purpose-aware SiteMedia validation, processing profile, path safety, health requirements, and failure cleanup;
- central usage discovery and deletion refusal for every existing and new owner type;
- Walk upload, external mode, description validation, replacement, removal, sharing/duplication, and public precedence;
- Draft Step 4 checkpoint, leave/resume, final submission, and failed-submission retention;
- Walk owner and unauthorised-user permission cases without `ManageSiteMedia` leakage;
- logo/favicon `ManageContent` authorisation, preview, replacement/removal, public branding, SEO, and cache invalidation;
- square favicon validation, managed PNG head output, and external fallback;
- malformed/mismatched/unsupported and over-limit upload rejection;
- backup/restore of references plus files and post-restore public delivery;
- v1.0.2 forward migration data retention and SQLite/MySQL/MariaDB schema contract;
- fresh-schema manifest convergence;
- standard and `public_html` storage/path assumptions, including a nested application prefix; and
- accessibility assertions for labels, descriptions, errors, status announcements, keyboard use, and image alternatives.

Use targeted PHP, Livewire/Filament, storage, migration, backup, and public-rendering tests while implementing. Browser coverage should be limited to the critical upload/preview/draft-resume and branding/head flows. Full database matrices, installer matrices, full Playwright, packaging, performance, and release qualification remain release-candidate work unless a concrete focused failure requires broader diagnosis.

## Documentation to Update During Implementation

Implementation will later update the relevant administrator and deployment documentation to explain:

- adding, describing, replacing, and removing one Walk featured image;
- choosing local upload or an explicit external image;
- logo and favicon upload/replacement/removal;
- supported raster formats and configured size/dimension limits;
- square favicon guidance and the lack of an ICO/SVG requirement;
- why local media is recommended and how retained external fallback behaves; and
- that Waymark backups include managed SiteMedia database records and private variants.

Those documentation changes are not part of this design-only commit.

## Scope Guard

This design changes no public visual composition beyond supplying the approved existing image placements with managed data. It adds no gallery, multiple-image Walk model, media editor, external-object-storage configuration, remote import, historical deduplication, unrelated upload type, release work, or general media-library redesign.

Implementation planning begins only after this specification passes independent review.
