# Managed Site and Walk Media Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development` for every behaviour change and `superpowers:verification-before-completion` before each commit.

**Goal:** Make locally managed `SiteMedia` the upload-first source for one Walk featured image, the site logo, and the site favicon while preserving secure external fallbacks, draft resilience, private storage, reference safety, and backup/restore behaviour.

**Architecture:** Extend the existing `SiteMedia` aggregate and ingestion pipeline rather than creating a second media system. Purpose-aware internal media creation is consumed by Walk-authorised and `ManageContent`-authorised owner actions; one `SiteMediaUsage` query protects every reference. Owner actions perform process-first, locked transactional reference changes and post-commit orphan marking. Public view models consume centralized presenters and never inspect storage in Blade.

**Tech Stack:** PHP 8.3, Laravel 13, Eloquent, Filament 5/Livewire, SQLite-focused PHPUnit feature tests, GD-backed existing image processing, private Laravel filesystem storage, Blade, TypeScript/Playwright for two critical browser flows.

**Spec:** `docs/superpowers/specs/2026-09-09-managed-site-and-walk-media-design.md`

**Global Constraints:** Preserve all v1.0.2 path/URL values and both supported deployment layouts. Do not grant Walk leaders `ManageSiteMedia`; Walk actions authorize the persisted Walk first and branding actions require `ManageContent`. Keep files under existing private persistent storage and stream only processed variants. Do not change PWA install icons, public composition, releases, versions, packages, feeds, tags, or unrelated media-library behaviour. No remote fetching, gallery expansion, multiple Walk images, SVG/ICO support, object-storage work, cropper, or source/public writable directory. During tasks, run only the named focused tests, Pint and `php -l` for changed PHP, and `git diff --check`; database matrices, installer matrices, full browser suites, package builds, performance suites, and release qualification remain release-candidate work.

## Concrete File Structure

### New domain and support files

- `app/Domain/SiteMedia/Enums/SiteMediaPurpose.php` — closed purpose enum: `library`, `walk_featured_image`, `site_logo`, `site_favicon`; exposes whether a purpose requires decorative or non-decorative metadata.
- `app/Domain/SiteMedia/Enums/ManagedImageSource.php` — form/application input enum for `managed`, `external`, and `none`; it is not persisted.
- `app/Domain/SiteMedia/Queries/SiteMediaUsage.php` — authoritative lookup for all seven owner-reference columns, returning safe administrator-facing usage labels and a boolean usage decision.
- `app/Domain/SiteMedia/Actions/CreateSiteMedia.php` — internal shared creation boundary for inspected uploads, UUID namespaces, processing, record/audit creation, and failure cleanup; it performs no browser-facing authorization.
- `app/Domain/SiteMedia/Actions/MarkSiteMediaOrphaned.php` — locks and rechecks a purpose-bound record through `SiteMediaUsage` before setting `orphaned_at`.
- `app/Domain/SiteMedia/Actions/DiscardUnattachedSiteMedia.php` — retryable cleanup for purpose-bound orphan records only, with a final usage recheck before delegating physical removal.
- `app/Domain/SiteMedia/Support/SiteMediaProcessingProfiles.php` — maps a server-owned `SiteMediaPurpose` to processing configuration and required healthy variants.
- `app/Domain/Content/Presentation/PublicImageReference.php` — centralized HTTPS/root-relative site-path allow-list and prefix-aware presentation resolver.
- `app/Rules/PublicImageReferenceRule.php` — Laravel rule backed by `PublicImageReference`; forms and domain DTOs consume the same contract.
- `app/Domain/Walks/Data/WalkFeaturedImageInput.php` — normalized managed/external/none request with optional pending upload, external reference, and Walk-specific description.
- `app/Domain/Walks/Actions/UpdateWalkFeaturedImage.php` — Walk-authorised upload, source switch, replacement, removal, alt ownership, transaction, and orphan-lifecycle action.
- `app/Domain/Walks/Presentation/WalkFeaturedImagePresenter.php` — managed-first, safe-external-second Walk image presentation used by cards, details, and SEO.
- `app/Filament/Resources/WalkResource/Support/WalkFeaturedImageFields.php` — reusable Step 4/edit fields and their stable state names.
- `app/Domain/Operations/Data/BrandingImageInput.php` — normalized logo/favicon managed/external/none form input.
- `app/Domain/Operations/Actions/UpdateBrandingImage.php` — `ManageContent`-authorised atomic SiteProfile media update for logo or favicon only.
- `resources/views/filament/forms/components/managed-image-preview.blade.php` — preview/status surface shared by Walk and branding forms; it receives presentation data and source/fallback labels, not storage paths.
- `database/migrations/2026_09_09_100000_add_managed_site_and_walk_media.php` — purpose/orphan metadata, owner references, Walk alt text, indexes, and safe 2,048-character fallback columns.
- `config/site-media.php` — server-owned processing-profile overrides for library, Walk image, transparent PNG logo, and square PNG favicon.
- `tests/Feature/SiteMedia/SiteMediaUsageTest.php` — complete owner-reference matrix and orphan/deletion protection.
- `tests/Feature/SiteMedia/PurposeAwareSiteMediaTest.php` — purpose invariants, profile output, health requirements, codec failure, and cleanup.
- `tests/Unit/Content/PublicImageReferenceTest.php` — centralized external/site-path allow-list and prefix resolution.
- `tests/Feature/Walks/WalkFeaturedImageManagementTest.php` — Walk action authorization, source transitions, sharing, alt ownership, and failure atomicity.
- `tests/Feature/Content/BrandingMediaManagementTest.php` — logo/favicon actions, permissions, presentation, cache, and failure atomicity.

### Existing files modified by the implementation

- `app/Domain/SiteMedia/Models/SiteMedia.php` — purpose/orphan casts and invariants.
- `app/Domain/SiteMedia/Actions/ManagesSiteMedia.php` — include purpose/orphan state in existing SiteMedia audit snapshots without changing capability enforcement.
- `app/Domain/SiteMedia/Data/SiteMediaMetadata.php` — preserve metadata input while purpose stays server-owned.
- `app/Domain/SiteMedia/Actions/UploadSiteMedia.php` — retain the public Media-library facade and `ManageSiteMedia`, delegate creation with `library` purpose.
- `app/Domain/SiteMedia/Actions/PromoteCommunityPhotoToSiteMedia.php` — create promoted records explicitly as `library`.
- `app/Domain/SiteMedia/Actions/DeleteSiteMedia.php` — lock and refuse referenced records before lifecycle/file mutation; expose safe usage labels.
- `app/Domain/SiteMedia/Actions/RegenerateSiteMedia.php` and `app/Domain/SiteMedia/Actions/MarkSiteMediaForRepair.php` — obtain required variants and repair guidance from purpose profiles.
- `app/Domain/SiteMedia/SiteMediaPresenter.php` — continue safe private-route presentation while respecting purpose-specific health/variant requirements.
- `app/Domain/Gallery/Actions/IngestCommunityPhoto.php` — accept an optional server-created processing configuration while retaining the gallery default.
- `app/Domain/Gallery/Data/ImageProcessingConfiguration.php` — carry minimum dimensions, square requirement, and server-selected output MIME without relaxing existing limits.
- `app/Domain/Gallery/Services/GdRasterImageTransformer.php` — use the selected supported output MIME and preserve alpha for PNG output.
- `app/Domain/Walks/Models/Walk.php` — managed media relationship, alt field, and eager-loadable contract.
- `app/Domain/Operations/Models/SiteProfile.php` — logo/favicon relationships.
- `app/Domain/Walks/Data/WalkDetailsData.php` — remove all media fields from the ordinary Walk-details write contract; `WalkFeaturedImageInput` becomes the only image-change contract.
- `app/Domain/Walks/Actions/SaveWalkDraft.php` and `app/Domain/Walks/Actions/UpdateWalk.php` — continue owning non-media Walk fields while the media action owns source/reference fields.
- `app/Domain/Walks/Actions/DuplicateWalk.php` — copy managed reference, retained fallback, and Walk-specific alt only when Featured image is selected.
- `app/Filament/Resources/WalkResource.php` — replace the raw path input with the reusable upload-first section.
- `app/Filament/Resources/WalkResource/Support/WalkFormData.php` — derive stable source, pending-upload, fallback, preview, and description state.
- `app/Filament/Resources/WalkResource/Pages/CreateWalk.php` — Step 4 checkpoint invokes the media action only after the Draft exists; final submit reuses the checkpointed reference.
- `app/Filament/Resources/WalkResource/Pages/EditWalk.php` — invoke the same media action after the ordinary Walk update boundary.
- `app/Domain/Walks/Data/WalkFeaturedImage.php` — remain the nullable presentation value object; remove its demo-only source-selection responsibility.
- `app/Domain/Walks/Queries/PublicWalksQuery.php` — eager load `featuredMedia` without changing filtering/order/pagination.
- `app/ViewModels/PublicWalkCardViewModel.php` and `app/ViewModels/PublicWalkDetailViewModel.php` — consume `WalkFeaturedImagePresenter`; detail supplies the same URL to SEO.
- `app/Filament/Pages/BrandingSettings.php` — upload-first logo/favicon source controls, previews, replace/remove, and external mode.
- `app/Domain/Operations/Actions/UpdateSiteProfile.php` — retain responsibility for ordinary branding fields; media reference/path changes pass through `UpdateBrandingImage`.
- `app/Domain/Operations/Actions/CreateBrandingPreview.php` — render saved managed/external branding through the same resolver without accepting private paths.
- `app/Domain/Content/Queries/PublicBranding.php` — managed-first logo/favicon resolution and actual favicon MIME while preserving cache behaviour.
- `app/Domain/Content/Presentation/PublicSeo.php` — use the resolved branding logo and Walk featured image rather than raw paths.
- `resources/views/layouts/public.blade.php` — render the resolved favicon URL/type only; PWA icon declarations remain unchanged.
- `resources/views/components/public/site-header.blade.php` and `resources/views/components/public/site-footer.blade.php` — continue decorative logo output beside visible group name using resolved presentation data.
- `resources/installation/fresh-schema.json` — generated from authoritative migrations after schema assertions pass.
- `tests/Feature/SiteMedia/SiteMediaTest.php` — model purpose/orphan invariants and Media-library compatibility.
- `tests/Feature/Operations/FreshInstallationSchemaTest.php` — migrated/fresh convergence and migration manifest.
- `tests/Feature/Walks/WalkDraftWizardTest.php` — Step 4 upload/checkpoint/resume/submit retention and failure retention.
- `tests/Feature/Walks/DuplicateWalkTest.php` — selected-feature sharing semantics.
- `tests/Feature/Walks/WalkAdministrationTest.php` — raw-path removal and edit integration.
- `tests/Feature/Public/PublicWalkPagesTest.php` — managed/external/default precedence, alt, route safety, eager loading, and SEO.
- `tests/Feature/Content/NavigationBrandingTest.php` — public logo/favicon/SEO rendering and external compatibility.
- `tests/Feature/Operations/BackupRestoreTest.php` — rows, references, variants, fallbacks, routes, and missing-file failure behaviour after restore.
- `tests/browser/admin-usability.spec.ts` — one critical Walk upload/preview/checkpoint/resume flow with accessible control assertions.
- `tests/browser/branding-settings.spec.ts` — one critical logo/favicon upload/preview/save/head flow.
- `docs/architecture/media.md`, `docs/deployment/backups.md`, and `docs/deployment/reference-content-entry-checklist.md` — implemented administration, processing, fallback, private-storage, and backup contracts.

## Task 1: Establish the schema and model foundation

**Files:**

- Create: `app/Domain/SiteMedia/Enums/SiteMediaPurpose.php`
- Create: `app/Domain/SiteMedia/Enums/ManagedImageSource.php`
- Create: `database/migrations/2026_09_09_100000_add_managed_site_and_walk_media.php`
- Modify: `app/Domain/SiteMedia/Models/SiteMedia.php`
- Modify: `app/Domain/SiteMedia/Actions/ManagesSiteMedia.php`
- Modify: `app/Domain/Walks/Models/Walk.php`
- Modify: `app/Domain/Operations/Models/SiteProfile.php`
- Modify: `tests/Feature/SiteMedia/SiteMediaTest.php`
- Modify: `tests/Feature/Operations/FreshInstallationSchemaTest.php`
- Modify: `resources/installation/fresh-schema.json`

**Interfaces produced:**

- `SiteMediaPurpose: string` with cases `Library`, `WalkFeaturedImage`, `SiteLogo`, and `SiteFavicon`.
- `SiteMediaPurpose::requiredDecorativeState(): ?bool`: `null` for library, `false` for Walk images, `true` for logo/favicon.
- `ManagedImageSource: string` with cases `Managed`, `External`, `None` for application/form state only.
- `SiteMedia::$purpose` is cast to `SiteMediaPurpose`, and `SiteMedia::$orphaned_at` is cast to a nullable datetime.
- `Walk::featuredMedia(): BelongsTo`, `SiteProfile::logoMedia(): BelongsTo`, and `SiteProfile::faviconMedia(): BelongsTo`.

**Step 1: Create failing schema and invariant cases**

Extend `SiteMediaTest` to prove existing rows/default creations use `library`, Walk media cannot save as decorative or without meaningful alt text, logo/favicon save as decorative with `alt_text = null`, purpose-bound records can carry `orphaned_at`, and existing audit snapshots include purpose/orphan state. Extend `FreshInstallationSchemaTest` to require:

- indexed `site_media.purpose` and nullable `site_media.orphaned_at`;
- nullable `walks.featured_image_media_id` with `nullOnDelete()` and nullable text `featured_image_alt_text`;
- nullable `site_profiles.logo_media_id` and `favicon_media_id`, both `nullOnDelete()`;
- 2,048-character capacity for `walks.featured_image_path`, `site_profiles.logo_path`, and `site_profiles.favicon_path`;
- matching migrated and fresh-manifest columns, indexes, foreign keys, and migration names;
- a migration-down case with a value longer than 255 proving rollback either leaves the safe wider column or throws a clear exception before contraction, never truncates the value.

Run: `php artisan test tests/Feature/SiteMedia/SiteMediaTest.php tests/Feature/Operations/FreshInstallationSchemaTest.php`

Expected: failure because the enums, columns, relationships, constraints, and migration manifest do not exist.

**Step 2: Implement the forward-only safe schema contract**

Create the enums and migration. Backfill/default `site_media.purpose` to `library`; index purpose with lifecycle state as supported by repository conventions. Add nullable foreign keys using `nullOnDelete()`. Widen retained fallback values without reading, validating, fetching, or rewriting them. Implement `down()` so it removes new references/metadata but does not contract any 2,048-character column unless every value is proven to fit; when proof is not portable, leave the wider type in place and document that choice in the migration comment.

Add casts, fillable attributes, relationships, and a `saving` invariant driven by `requiredDecorativeState()`. Preserve the existing generic library invariant: library callers still choose decorative or meaningful alt metadata. Extend the current `ManagesSiteMedia::snapshot()` fields with `purpose` and `orphaned_at`. Do not create a persisted source-mode field.

Generate the manifest only after the authoritative migration passes:

`php artisan waymark:installation-schema --write`

Run: `php artisan test tests/Feature/SiteMedia/SiteMediaTest.php tests/Feature/Operations/FreshInstallationSchemaTest.php`

Expected: all schema, retention, rollback-safety, relationship, and invariant cases pass.

**Step 3: Perform narrow hygiene and commit**

Run: `php vendor/bin/pint app/Domain/SiteMedia/Enums/SiteMediaPurpose.php app/Domain/SiteMedia/Enums/ManagedImageSource.php database/migrations/2026_09_09_100000_add_managed_site_and_walk_media.php app/Domain/SiteMedia/Models/SiteMedia.php app/Domain/SiteMedia/Actions/ManagesSiteMedia.php app/Domain/Walks/Models/Walk.php app/Domain/Operations/Models/SiteProfile.php tests/Feature/SiteMedia/SiteMediaTest.php tests/Feature/Operations/FreshInstallationSchemaTest.php`

Run `php -l` once for each changed PHP file, then `git diff --check`.

Commit: `git add app/Domain/SiteMedia/Enums app/Domain/SiteMedia/Models/SiteMedia.php app/Domain/SiteMedia/Actions/ManagesSiteMedia.php app/Domain/Walks/Models/Walk.php app/Domain/Operations/Models/SiteProfile.php database/migrations/2026_09_09_100000_add_managed_site_and_walk_media.php resources/installation/fresh-schema.json tests/Feature/SiteMedia/SiteMediaTest.php tests/Feature/Operations/FreshInstallationSchemaTest.php && git commit -m "feat(media): establish managed image schema"`

## Task 2: Protect references and define the orphan lifecycle

**Files:**

- Create: `app/Domain/SiteMedia/Queries/SiteMediaUsage.php`
- Create: `app/Domain/SiteMedia/Actions/MarkSiteMediaOrphaned.php`
- Create: `app/Domain/SiteMedia/Actions/DiscardUnattachedSiteMedia.php`
- Create: `tests/Feature/SiteMedia/SiteMediaUsageTest.php`
- Modify: `app/Domain/SiteMedia/Actions/DeleteSiteMedia.php`

**Interfaces produced/consumed:**

- `SiteMediaUsage::labelsFor(SiteMedia $media): array<string>` returns concise type-level labels without titles, member details, or private content.
- `SiteMediaUsage::isUsed(SiteMedia $media): bool` is the sole owner-reference decision.
- `MarkSiteMediaOrphaned::handle(SiteMedia $media): bool` locks the current record, returns `false` while used or for `library`, and otherwise timestamps `orphaned_at` idempotently.
- `DiscardUnattachedSiteMedia::handle(User $actor, SiteMedia $media): bool` accepts only purpose-bound orphan records, rechecks usage while locked, and uses the existing retryable deletion/namespace cleanup boundary.
- `DeleteSiteMedia::handle(User $actor, SiteMedia $media): bool` retains `ManageSiteMedia` authorization and refuses a referenced record before state or filesystem mutation.

**Step 1: Create the failing complete usage matrix**

In `SiteMediaUsageTest`, create one case for each reference:

- `cms_pages.hero_media_id`;
- `news_articles.featured_media_id`;
- `testimonials.image_media_id`;
- `committee_roles.public_photo_media_id`;
- `walks.featured_image_media_id`;
- `site_profiles.logo_media_id`;
- `site_profiles.favicon_media_id`.

For every case, assert `isUsed()` is true, the returned label is safe and stable, `DeleteSiteMedia` refuses without changing lifecycle state or files, and `MarkSiteMediaOrphaned` does not timestamp the record. Also prove an unreferenced purpose-bound item becomes orphaned, a `library` item does not, a newly attached reference discovered at cleanup time blocks deletion, and successful orphan cleanup remains retryable after a filesystem failure.

Run: `php artisan test tests/Feature/SiteMedia/SiteMediaUsageTest.php`

Expected: failure because central usage discovery and reference-protected lifecycle actions do not exist.

**Step 2: Implement one usage authority and rechecked cleanup**

Implement `SiteMediaUsage` with Eloquent `exists()` checks, not multi-owner joins, so it remains duplicate-safe and does not hydrate owner data. Update `DeleteSiteMedia` to authorize, lock the media, query usage, and refuse before it changes health/deletion state. Give the refusal an administrator-safe validation/domain message listing only usage types.

Implement orphan marking and discard so every final lifecycle decision locks and rechecks the record and calls `SiteMediaUsage`. Keep physical deletion outside owner forms. Reuse `SiteMediaNamespaceCleaner` and existing deletion audit/retry state instead of introducing a new file remover. A failure to mark or remove old media must not undo a valid owner switch.

Run: `php artisan test tests/Feature/SiteMedia/SiteMediaUsageTest.php tests/Feature/SiteMedia/SiteMediaTest.php`

Expected: the complete usage matrix, refusal-before-mutation, orphan eligibility, concurrency recheck, and retry cases pass.

**Step 3: Perform narrow hygiene and commit**

Run Pint on the five changed PHP files, run `php -l` on them, then run `git diff --check`.

Commit: `git add app/Domain/SiteMedia/Queries/SiteMediaUsage.php app/Domain/SiteMedia/Actions/MarkSiteMediaOrphaned.php app/Domain/SiteMedia/Actions/DiscardUnattachedSiteMedia.php app/Domain/SiteMedia/Actions/DeleteSiteMedia.php tests/Feature/SiteMedia/SiteMediaUsageTest.php && git commit -m "feat(media): protect referenced site media"`

## Task 3: Extract purpose-aware secure ingestion

**Files:**

- Create: `app/Domain/SiteMedia/Actions/CreateSiteMedia.php`
- Create: `app/Domain/SiteMedia/Support/SiteMediaProcessingProfiles.php`
- Create: `config/site-media.php`
- Create: `tests/Feature/SiteMedia/PurposeAwareSiteMediaTest.php`
- Modify: `app/Domain/SiteMedia/Actions/UploadSiteMedia.php`
- Modify: `app/Domain/SiteMedia/Actions/PromoteCommunityPhotoToSiteMedia.php`
- Modify: `app/Domain/SiteMedia/Actions/RegenerateSiteMedia.php`
- Modify: `app/Domain/SiteMedia/Actions/MarkSiteMediaForRepair.php`
- Modify: `app/Domain/SiteMedia/SiteMediaPresenter.php`
- Modify: `app/Domain/Gallery/Actions/IngestCommunityPhoto.php`
- Modify: `app/Domain/Gallery/Data/ImageProcessingConfiguration.php`
- Modify: `app/Domain/Gallery/Services/GdRasterImageTransformer.php`

**Interfaces produced/consumed:**

- `CreateSiteMedia::handle(User $actor, UploadedFile $upload, SiteMediaMetadata $metadata, SiteMediaPurpose $purpose): SiteMedia` is an internal application boundary. It applies server-owned purpose rules, creates an initially orphaned purpose-bound record, records the actor in existing audits, and cleans its reserved namespace on failure. It does not call `ManagesSiteMedia`.
- `UploadSiteMedia::handle(User $actor, UploadedFile $upload, SiteMediaMetadata $metadata): SiteMedia` keeps its existing signature and `ManageSiteMedia` gate, then delegates with `SiteMediaPurpose::Library`.
- `SiteMediaProcessingProfiles::configurationFor(SiteMediaPurpose $purpose): ImageProcessingConfiguration` and `requiredVariantsFor(SiteMediaPurpose $purpose): array<string>` are server-owned; client state never supplies purpose, paths, keys, variants, or MIME. Library, Walk, and logo require `master`, `large`, `medium`, and `thumbnail`; favicon requires only a `favicon` PNG variant.
- `IngestCommunityPhoto::handle(UploadedFile $upload, ?string $reservedDirectory = null, ?ImageProcessingConfiguration $configuration = null): ProcessedCommunityPhoto` retains the gallery default when configuration is omitted.
- `ImageProcessingConfiguration` exposes `minimumWidth`, `minimumHeight`, `requiresSquare`, selected `outputMimeType`, and optional `requiredOutputMimeType` alongside current byte/dimension/pixel/memory/variant limits. When `requiredOutputMimeType` is set, unsupported output fails instead of falling back.

**Step 1: Create failing purpose and security cases**

In `PurposeAwareSiteMediaTest`, cover:

- library facade still requires `ManageSiteMedia`, produces current variants/format, and records `library`;
- direct internal creation applies the supplied server purpose but cannot be invoked through request-controlled purpose input;
- Walk upload is non-decorative and copies meaningful submitted description into SiteMedia metadata;
- logo/favicon are decorative with cleared alt text;
- PNG input with alpha produces PNG logo output with alpha retained;
- favicon rejects non-square and decoded dimensions below 32x32, accepts a valid square raster, and emits the required PNG variant;
- JPEG/PNG/WebP/AVIF input keeps declared/decoded MIME, codec, byte, dimension, pixel, memory, orientation, and transformed-output safeguards;
- SVG, ICO, GIF, mismatched, malformed, unsupported-codec, and unsafe path cases fail with human-readable errors;
- server inability to encode required PNG fails before owner mutation rather than silently using JPEG;
- a processing/record/audit failure cleans the newly reserved namespace and leaves no healthy active reference;
- health and repair rules depend on purpose-required variants, and no-source records instruct replace/re-upload rather than regeneration.

Run: `php artisan test tests/Feature/SiteMedia/PurposeAwareSiteMediaTest.php tests/Feature/SiteMedia/SiteMediaCapabilityTest.php`

Expected: failure because purpose profiles and the internal creator are absent and gallery processing is global.

**Step 2: Implement the shared internal creator and profiles**

Move only the secure creation body from `UploadSiteMedia` into `CreateSiteMedia`: UUID namespace reservation beneath `site-media/`, existing raster inspection, processed output, storage-reference validation, transactional record/audit creation, and compensating cleanup. Mark purpose-bound records orphaned at creation and general library records non-orphaned. Apply purpose decorative state inside this server boundary.

Extend the existing processing configuration rather than forking ingestion. Permit a non-empty server-defined variant map while the library/Walk/logo profiles explicitly retain `master`, `large`, `medium`, and `thumbnail`. Logo sets required PNG output. Favicon defines one `favicon` variant, requires square decoded geometry of at least 32x32, and sets required PNG output; config/help may recommend about 512x512 but must not require that recommendation. If a required PNG encoder is unavailable, configuration fails rather than using the current generic JPEG/PNG fallback. Preserve alpha in PNG transformation. Make required health variants purpose-specific while presentation continues to fail closed for a missing/unsafe requested variant. Keep general media operations behind `ManagesSiteMedia` and promotion explicitly `library`.

Run: `php artisan test tests/Feature/SiteMedia/PurposeAwareSiteMediaTest.php tests/Feature/SiteMedia/SiteMediaCapabilityTest.php tests/Feature/SiteMedia/SiteMediaTest.php`

Expected: purpose/security/format cases pass and the existing library capability contract remains unchanged.

**Step 3: Perform narrow hygiene and commit**

Run Pint on changed PHP files in this task, `php -l` on each, and `git diff --check`.

Commit: `git add app/Domain/SiteMedia app/Domain/Gallery/Actions/IngestCommunityPhoto.php app/Domain/Gallery/Data/ImageProcessingConfiguration.php app/Domain/Gallery/Services/GdRasterImageTransformer.php config/site-media.php tests/Feature/SiteMedia/PurposeAwareSiteMediaTest.php && git commit -m "feat(media): support purpose-aware ingestion"`

## Task 4: Implement Walk media actions and safe external references

**Files:**

- Create: `app/Domain/Content/Presentation/PublicImageReference.php`
- Create: `app/Rules/PublicImageReferenceRule.php`
- Create: `app/Domain/Walks/Data/WalkFeaturedImageInput.php`
- Create: `app/Domain/Walks/Actions/UpdateWalkFeaturedImage.php`
- Create: `tests/Unit/Content/PublicImageReferenceTest.php`
- Create: `tests/Feature/Walks/WalkFeaturedImageManagementTest.php`
- Modify: `app/Domain/Walks/Data/WalkDetailsData.php`
- Modify: `app/Domain/Walks/Actions/SaveWalkDraft.php`
- Modify: `app/Domain/Walks/Actions/UpdateWalk.php`
- Modify: `app/Domain/Walks/Actions/DuplicateWalk.php`
- Modify: `tests/Feature/Walks/DuplicateWalkTest.php`

**Interfaces produced/consumed:**

- `PublicImageReference::isAllowed(mixed $reference): bool` accepts only HTTPS URLs or safe root-relative site paths and rejects HTTP, protocol-relative URLs, executable schemes, traversal after decoding, drive/UNC paths, `/storage`, `/application`, and arbitrary filesystem paths.
- `PublicImageReference::resolve(mixed $reference): ?string` returns the HTTPS URL unchanged or resolves an allowed root-relative path through the existing prefix-aware `PublicUrl`; invalid input returns `null`.
- `PublicImageReferenceRule` delegates to that exact allow-list for Laravel/Filament messages.
- `WalkFeaturedImageInput::from(array $state): self` consumes `featured_image_source`, `featured_image_upload`, `featured_image_external_url`, and `featured_image_alt_text`, normalizes empty values, and enforces mutually exclusive state.
- `UpdateWalkFeaturedImage::handle(User $actor, Walk $walk, WalkFeaturedImageInput $input): Walk` authorizes `WalkPolicy::update` on the persisted Walk before media creation and again on the locked current row before the reference switch.

**Step 1: Create failing reference and Walk transition cases**

`PublicImageReferenceTest` must prove HTTPS and root-relative prefix resolution, including `/images/demo/`, and rejection of HTTP, `//host`, encoded/double-encoded traversal, Windows drive/UNC, local absolute paths, `/storage`, `/application`, non-image executable schemes, whitespace tricks, and over-2,048-character input.

`WalkFeaturedImageManagementTest` must prove:

- an active verified Walk owner with update authority uploads to their persisted Draft without `ManageSiteMedia` and gains no Media-library access;
- unrelated, inactive, and otherwise unauthorized users fail before record/file creation or owner mutation; an unverified actor cannot create the initial Draft and therefore cannot reach creation-flow upload, while later edits follow the repository's existing `WalkPolicy::update` decision without duplicating or strengthening it inside this feature;
- initial managed upload writes description to both non-decorative `SiteMedia.alt_text` and authoritative `walks.featured_image_alt_text`;
- changing only a Walk description updates only the Walk field, never shared SiteMedia metadata;
- managed upload retains an existing valid path as inactive fallback;
- external switch validates and stores the external reference and description, clears managed ownership, then marks the old media orphaned only after central usage recheck;
- managed removal can either reveal retained fallback or clear media/path/alt according to the explicit input;
- clearing an external value never deletes or mutates an unrelated managed record;
- replacement processing or transaction failure keeps the previous active media and fallback, with the new namespace removed or represented only by an orphan candidate;
- two simulated saves leave one valid winner and never orphan active/shared media;
- selecting Featured image in duplication copies `featured_image_media_id`, `featured_image_path`, and `featured_image_alt_text`; replacing one copy does not mutate the other; omitting the group copies none of them.
- upload creates the existing `uploaded` SiteMedia audit with purpose; attach/detach/orphan lifecycle writes SiteMedia audit actions with Walk ID/slot context and no storage paths; failed cleanup retains the existing failure audit.

Run: `php artisan test tests/Unit/Content/PublicImageReferenceTest.php tests/Feature/Walks/WalkFeaturedImageManagementTest.php tests/Feature/Walks/DuplicateWalkTest.php`

Expected: failure because source normalization, centralized references, and Walk media transitions do not exist.

**Step 2: Implement the owner-authorised transaction**

Implement the rule and input DTO with exact state names. Remove `featured_image_path` from general `WalkDetailsData`/`UpdateWalk`/draft mass-assignment paths so browser state cannot bypass the media action. In `UpdateWalkFeaturedImage`, authorize the Walk before invoking `CreateSiteMedia`; process a pending managed upload completely; lock and re-authorize the fresh Walk plus involved media inside the owner transaction; attach/clear `orphaned_at`; persist authoritative Walk alt/source fields; commit; then call `MarkSiteMediaOrphaned` for the former media.

On a later description-only edit of managed media, update only `walks.featured_image_alt_text`. Never change shared `SiteMedia.alt_text`. For external or none transitions, perform no upload and no filesystem deletion. Keep all error paths from changing the previous active reference. Extend duplication only inside the existing Featured image option.

Run: `php artisan test tests/Unit/Content/PublicImageReferenceTest.php tests/Feature/Walks/WalkFeaturedImageManagementTest.php tests/Feature/Walks/DuplicateWalkTest.php`

Expected: reference safety, authorization, atomic source transitions, alt ownership, sharing, and duplication cases pass.

**Step 3: Perform narrow hygiene and commit**

Run Pint on changed PHP files, `php -l` on each, and `git diff --check`.

Commit: `git add app/Domain/Content/Presentation/PublicImageReference.php app/Rules/PublicImageReferenceRule.php app/Domain/Walks tests/Unit/Content/PublicImageReferenceTest.php tests/Feature/Walks/WalkFeaturedImageManagementTest.php tests/Feature/Walks/DuplicateWalkTest.php && git commit -m "feat(walks): manage featured image sources"`

## Task 5: Integrate upload-first Walk create/edit and Draft checkpoints

**Files:**

- Create: `app/Filament/Resources/WalkResource/Support/WalkFeaturedImageFields.php`
- Create: `resources/views/filament/forms/components/managed-image-preview.blade.php`
- Modify: `app/Filament/Resources/WalkResource.php`
- Modify: `app/Filament/Resources/WalkResource/Support/WalkFormData.php`
- Modify: `app/Filament/Resources/WalkResource/Pages/CreateWalk.php`
- Modify: `app/Filament/Resources/WalkResource/Pages/EditWalk.php`
- Modify: `tests/Feature/Walks/WalkDraftWizardTest.php`
- Modify: `tests/Feature/Walks/WalkAdministrationTest.php`
- Modify: `tests/browser/admin-usability.spec.ts`

**Interfaces produced/consumed:**

- `WalkFeaturedImageFields::components(): array<Component>` returns accessible source radio, upload, external input, description, remove/fallback decision, and preview/status components.
- `WalkFeaturedImageFields::stateNames(): array<string>` returns exactly `featured_image_source`, `featured_image_upload`, `featured_image_external_url`, `featured_image_alt_text`, `featured_image_remove_fallback`, and `featured_image_preview` for Step 4 checkpoint filtering.
- `WalkFormData::from(Walk $walk): array` derives the active mode by managed-valid first, safe retained path second, otherwise none; it does not persist mode.
- Create/edit pages construct `WalkFeaturedImageInput` and call `UpdateWalkFeaturedImage` only after the persisted owner/non-media save boundary succeeds.

**Step 1: Create failing Livewire/Filament and browser cases**

Extend `WalkDraftWizardTest`/`WalkAdministrationTest` to prove:

- raw `featured_image_path`, private disk, storage key, and namespace inputs are absent;
- upload is the recommended/default new selection, external controls appear only in explicit external mode, and source/fallback status is clear;
- meaningful description is associated with a new managed/external selection and errors are announced without mutating the prior image;
- pending Livewire upload previews but creates no SiteMedia before Step 4 checkpoint;
- advancing Step 4 creates and attaches exactly one SiteMedia to the already persisted, authorized Draft;
- Back/Next and hydration retain the same attachment; leaving and resuming restores managed reference, fallback, preview, and Walk alt;
- final submission reuses the Draft/reference and does not upload again;
- a failed final submit retains the last successful image checkpoint;
- edit replacement/removal uses the same action and renders the saved managed preview.

Add one focused Playwright scenario in `admin-usability.spec.ts`: upload a valid fixture in Step 4, observe temporary preview and accessible status/error wiring, checkpoint, leave, resume through the readable Draft route, and confirm the same saved preview/description before submission. Reuse existing login/database setup and do not duplicate PHP domain cases in the browser.

Run PHP: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php`

Expected: failure because the resource still exposes the raw path and the wizard does not use media state/action.

After the PHP failure is established, run only the new browser case by its exact title:

`npx playwright test tests/browser/admin-usability.spec.ts --grep "persists a managed Walk image through draft resume"`

Expected: failure at the missing upload-first controls.

**Step 2: Implement the form and checkpoint wiring**

Build the reusable field group using Filament `FileUpload::storeFiles(false)` so temporary uploads are not persisted as SiteMedia before checkpoint. Use an explicit `Radio` source control, `PublicImageReferenceRule`, conditional visibility, a required Walk description for new/changed active images, and a `ViewField` that receives presentation/status data only. Maintain labels, help/error association, keyboard operation, live status, focus ring, and 44px touch targets through existing admin conventions.

In Create Walk, include the exact state names in Step 4, save ordinary Step 4 fields first, then call the media action for the persisted Draft. On final submit, exclude media transient fields and reuse the checkpointed relationship. In Edit Walk, save ordinary fields and then invoke the same media action. Catch domain/validation failures into the owning form without clearing temporary state or the last successful checkpoint.

Run PHP: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Walks/WalkFeaturedImageManagementTest.php`

Run browser: `npx playwright test tests/browser/admin-usability.spec.ts --grep "persists a managed Walk image through draft resume"`

Expected: both focused PHP and critical browser flows pass.

**Step 3: Perform narrow hygiene and commit**

Run Pint on changed PHP files, `php -l` on each, and `git diff --check`. Do not update broad visual baselines because the public UI has not changed in this task.

Commit: `git add app/Filament/Resources/WalkResource.php app/Filament/Resources/WalkResource/Support app/Filament/Resources/WalkResource/Pages/CreateWalk.php app/Filament/Resources/WalkResource/Pages/EditWalk.php resources/views/filament/forms/components/managed-image-preview.blade.php tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/browser/admin-usability.spec.ts && git commit -m "feat(walks): integrate managed image workflow"`

## Task 6: Present managed and external Walk images publicly

**Files:**

- Create: `app/Domain/Walks/Presentation/WalkFeaturedImagePresenter.php`
- Modify: `app/Domain/Walks/Data/WalkFeaturedImage.php`
- Modify: `app/Domain/Walks/Queries/PublicWalksQuery.php`
- Modify: `app/ViewModels/PublicWalkCardViewModel.php`
- Modify: `app/ViewModels/PublicWalkDetailViewModel.php`
- Modify: `app/Domain/Content/Presentation/PublicSeo.php`
- Modify: `tests/Unit/Walks/WalkFeaturedImageTest.php`
- Modify: `tests/Feature/Public/PublicWalkPagesTest.php`

**Interfaces produced/consumed:**

- `WalkFeaturedImagePresenter::present(Walk $walk, string $variant = 'master'): ?WalkFeaturedImage` selects healthy managed presentation first, then a safe external/site-path fallback, then no image. Cards request `medium`; detail and event SEO share `large`.
- `WalkFeaturedImage` remains a readonly value containing public URL and alt text; it no longer reads the filesystem or selects sources.
- Public query/view models provide `featuredMedia` eagerly and expose nullable presentation arrays/objects to Blade.

**Step 1: Create failing precedence, compatibility, and query cases**

Extend the unit and public feature tests to prove:

- healthy managed media wins over a retained external path and uses `walks.featured_image_alt_text`;
- missing/unhealthy/unsafe managed presentation falls back to a safe retained external reference;
- safe upgraded external values remain unchanged and render without network access;
- `/images/demo/` retains its known description compatibility;
- upgraded external image without stored alt receives a concise title-based fallback, while a new/change action still requires a proper description;
- unsafe external or absent presentation gives the existing default card image and detail omission without broken markup;
- cards retain approved rhythm, details retain responsive rounded output, and detail SEO receives the same active URL;
- public listing/detail queries eager load `featuredMedia` without changing filters, pagination, order, or duplicate behaviour;
- generated private media route and safe root-relative fallback preserve an application URL prefix.

Run: `php artisan test tests/Unit/Walks/WalkFeaturedImageTest.php tests/Feature/Public/PublicWalkPagesTest.php`

Expected: failure because public view models know only the demo-path resolver and do not load managed media.

**Step 2: Implement centralized public presentation**

Implement the presenter using `SiteMediaPresenter` and `PublicImageReference`. Do not place source precedence, storage checks, route construction, or alt fallback in Blade. Use authoritative Walk alt first, SiteMedia alt only as compatibility metadata, the existing demo description for verified demo assets, and the Walk title fallback only for upgraded external rows missing description.

Eager load the relationship at query boundaries. Change card/detail/SEO mapping only enough to consume the presenter; preserve Phase 2 visual composition and card/detail absence behaviour.

Run: `php artisan test tests/Unit/Walks/WalkFeaturedImageTest.php tests/Feature/Public/PublicWalkPagesTest.php`

Expected: all precedence, fallback, alt, SEO, prefix, and eager-loading cases pass.

**Step 3: Perform narrow hygiene and commit**

Run Pint on changed PHP files, `php -l` on each, and `git diff --check`.

Commit: `git add app/Domain/Walks/Presentation/WalkFeaturedImagePresenter.php app/Domain/Walks/Data/WalkFeaturedImage.php app/Domain/Walks/Queries/PublicWalksQuery.php app/ViewModels/PublicWalkCardViewModel.php app/ViewModels/PublicWalkDetailViewModel.php app/Domain/Content/Presentation/PublicSeo.php tests/Unit/Walks/WalkFeaturedImageTest.php tests/Feature/Public/PublicWalkPagesTest.php && git commit -m "feat(walks): present managed featured images"`

## Task 7: Implement branding media actions and public resolution

**Files:**

- Create: `app/Domain/Operations/Data/BrandingImageInput.php`
- Create: `app/Domain/Operations/Actions/UpdateBrandingImage.php`
- Create: `tests/Feature/Content/BrandingMediaManagementTest.php`
- Modify: `app/Domain/Operations/Actions/UpdateSiteProfile.php`
- Modify: `app/Domain/Operations/Actions/CreateBrandingPreview.php`
- Modify: `app/Domain/Content/Queries/PublicBranding.php`
- Modify: `app/Domain/Content/Presentation/PublicSeo.php`
- Modify: `resources/views/layouts/public.blade.php`
- Modify: `resources/views/components/public/site-header.blade.php`
- Modify: `resources/views/components/public/site-footer.blade.php`
- Modify: `tests/Feature/Content/NavigationBrandingTest.php`

**Interfaces produced/consumed:**

- `BrandingImageInput::from(array $state, SiteMediaPurpose $purpose): self` accepts only `SiteLogo` or `SiteFavicon` and normalized source/upload/external/remove state.
- `UpdateBrandingImage::handle(User $actor, SiteProfile $profile, SiteMediaPurpose $purpose, BrandingImageInput $input): SiteProfile` requires `ManageContent`, rejects other purposes, and performs the same process-first, locked owner switch, then post-commit orphan recheck.
- `PublicBranding::forProfile(SiteProfile $profile): array` returns resolved `logo_url`, `favicon_url`, and `favicon_type`; managed media wins, safe fallback is second, and invalid presentation is nullable. Managed logo requests `medium`; managed favicon requests `favicon` and reports `image/png`.

**Step 1: Create failing authorization, transition, and public-output cases**

In `BrandingMediaManagementTest`, prove:

- a content manager can upload/replace/remove logo/favicon without `ManageSiteMedia`, while other users and inactive users fail before creation/mutation;
- actions accept only `site_logo`/`site_favicon` and create decorative records with no SiteMedia alt;
- a failed upload/switch leaves old branding active;
- managed selection retains inactive external fallback, external selection clears the managed reference, and removal either reveals fallback or clears both according to explicit input;
- successful owner switch precedes orphan marking, and shared usage prevents orphaning/deletion;
- upload creates the existing `uploaded` SiteMedia audit with purpose; attach/detach/orphan lifecycle writes SiteMedia audit actions with SiteProfile ID/slot context and no storage paths; failed cleanup/deletion retains existing failure/removal audits;
- profile cache invalidates on reference/path change;
- managed logo resolves through a private route and is used in header, footer, and organization SEO with `alt=""` beside the visible group name/accessibly named home link;
- managed favicon resolves through a PNG route and the head emits `type="image/png"`;
- safe existing external values remain compatible, invalid values fail closed, and PWA manifest icon URLs/definitions are unchanged;
- root-relative managed/external URLs preserve the application prefix.

Extend `NavigationBrandingTest` for final HTML/SEO/head assertions.

Run: `php artisan test tests/Feature/Content/BrandingMediaManagementTest.php tests/Feature/Content/NavigationBrandingTest.php`

Expected: failure because SiteProfile has references but no authorized media transition or managed-first public resolution.

**Step 2: Implement branding owner actions and public output**

Normalize branding input and restrict purposes in both DTO and action. Require `ManageContent`, process through `CreateSiteMedia`, lock/re-authorize the singleton profile, atomically switch the requested reference/path, clear the new orphan timestamp, commit, and then recheck the former media before marking orphaned. Keep normal text/color/social settings in `UpdateSiteProfile`; prevent it from mass-updating managed reference fields.

Resolve managed logo/favicon via `SiteMediaPresenter`, then safe retained path via `PublicImageReference`. Return actual favicon type for managed PNG. Feed the same resolved logo into organization SEO. Preserve empty logo alt and visible group name. Do not touch `PwaController`, its static manifest icons, or service-worker shell cache.

Run: `php artisan test tests/Feature/Content/BrandingMediaManagementTest.php tests/Feature/Content/NavigationBrandingTest.php`

Expected: permissions, transitions, cache, public logo/SEO/favicon, prefix, and unchanged-PWA cases pass.

**Step 3: Perform narrow hygiene and commit**

Run Pint on changed PHP files, `php -l` on each, and `git diff --check`.

Commit: `git add app/Domain/Operations/Data/BrandingImageInput.php app/Domain/Operations/Actions/UpdateBrandingImage.php app/Domain/Operations/Actions/UpdateSiteProfile.php app/Domain/Operations/Actions/CreateBrandingPreview.php app/Domain/Content/Queries/PublicBranding.php app/Domain/Content/Presentation/PublicSeo.php resources/views/layouts/public.blade.php resources/views/components/public/site-header.blade.php resources/views/components/public/site-footer.blade.php tests/Feature/Content/BrandingMediaManagementTest.php tests/Feature/Content/NavigationBrandingTest.php && git commit -m "feat(branding): manage local logo and favicon"`

## Task 8: Integrate upload-first branding controls and critical browser proof

**Files:**

- Modify: `app/Filament/Pages/BrandingSettings.php`
- Modify: `resources/views/filament/pages/branding-settings.blade.php`
- Modify: `resources/views/filament/forms/components/managed-image-preview.blade.php`
- Modify: `tests/Feature/Content/BrandingMediaManagementTest.php`
- Modify: `tests/browser/branding-settings.spec.ts`

**Interfaces produced/consumed:**

- Branding form state uses `logo_source`, `logo_upload`, `logo_path`, `logo_remove_fallback`, `favicon_source`, `favicon_upload`, `favicon_path`, and `favicon_remove_fallback`.
- Saved preview data comes from `PublicBranding`; pending preview remains a Filament/Livewire temporary URL and is never passed to public presentation.
- `BrandingSettings::save()` updates ordinary settings through `UpdateSiteProfile` and each image through `UpdateBrandingImage` with fixed server-selected purposes.

**Step 1: Create failing page and critical browser cases**

Extend the branding feature test to prove the form is upload-first, hides private paths, exposes external fields only after explicit source selection, states the active/fallback source, recommends square roughly-512px favicon input while enforcing only square and >=32px, and maps action errors to accessible fields without losing prior saved preview.

Add one focused Playwright scenario in `branding-settings.spec.ts`: as a content manager, upload/preview/save a transparent logo and square favicon, verify the saved previews after reload, visit the public page, and assert decorative logo plus local PNG favicon head output. Include accessible labels, keyboard activation, associated error/status announcements, and confirm a non-square favicon leaves the old saved favicon active.

Run PHP: `php artisan test tests/Feature/Content/BrandingMediaManagementTest.php`

Expected: failure because Branding settings still exposes ordinary URL controls only.

Run browser: `npx playwright test tests/browser/branding-settings.spec.ts --grep "uploads managed logo and favicon"`

Expected: failure at the missing upload controls.

**Step 2: Implement the upload-first settings UI**

Use Filament `FileUpload::storeFiles(false)` for pending previews, fixed-purpose action calls, explicit managed/external/none controls, the centralized external rule, current managed/external preview presentation, and replace/remove/fallback status. Keep the existing neutral/light admin preview surface. Do not expose disks, keys, namespaces, storage paths, browser-size variants, SVG, or ICO. Preserve the ordinary branding save/preview behaviour and cache invalidation.

Run PHP: `php artisan test tests/Feature/Content/BrandingMediaManagementTest.php tests/Feature/Content/NavigationBrandingTest.php`

Run browser: `npx playwright test tests/browser/branding-settings.spec.ts --grep "uploads managed logo and favicon"`

Expected: the focused page/domain/public assertions and single critical browser flow pass.

**Step 3: Perform narrow hygiene and commit**

Run Pint on changed PHP files, `php -l` on each, and `git diff --check`. No full Playwright or broad visual baseline run belongs in this task.

Commit: `git add app/Filament/Pages/BrandingSettings.php resources/views/filament/pages/branding-settings.blade.php resources/views/filament/forms/components/managed-image-preview.blade.php tests/Feature/Content/BrandingMediaManagementTest.php tests/browser/branding-settings.spec.ts && git commit -m "feat(branding): add upload-first media controls"`

## Task 9: Prove backup/restore completeness and document the feature

**Files:**

- Modify: `tests/Feature/Operations/BackupRestoreTest.php`
- Modify: `docs/architecture/media.md`
- Modify: `docs/deployment/backups.md`
- Modify: `docs/deployment/reference-content-entry-checklist.md`

**Interfaces produced/consumed:**

- Existing `CreateBackup` and `RestoreBackup` remain unchanged unless the failing focused test proves a concrete omission; they must continue to export every database table and every private local file except the backup directory.
- Documentation describes actual implemented controls, supported raster inputs/configured limits, purpose outputs, retained fallback/source precedence, private storage for both layouts, and backup/restore coverage.

**Step 1: Create the failing end-to-end backup fixture**

Extend `BackupRestoreTest` with a focused scenario that creates:

- a Walk whose managed featured media, authoritative Walk alt, and retained external fallback all differ;
- singleton SiteProfile references for managed logo and favicon plus retained external fallbacks;
- purpose-correct SiteMedia rows and actual required processed variants beneath the existing local private disk.

Create a backup, then mutate references/rows and remove the variant files. Restore the backup and assert all SiteMedia rows, purposes, orphan state, Walk/SiteProfile foreign keys, alt/fallback values, and private files return together. Request the normal stream/public routes and assert the Walk image, logo, and PNG favicon resolve. Remove one restored variant and assert health/presentation fails closed without revealing its private path. Assert backup manifest hashes include every test variant and exclude the backup directory.

Run: `php artisan test tests/Feature/Operations/BackupRestoreTest.php --filter=managed_site_and_walk_media`

Expected: initially fail only if fixture factories/presentation or backup enumeration does not yet express the new owner/files contract; do not broaden the backup implementation without that evidence.

**Step 2: Make the smallest evidence-driven adjustment and document reality**

If the existing backup code already passes once the fixture is complete, leave production backup/restore code untouched. If the test exposes a real omission, change only `CreateBackup` or `RestoreBackup` at the demonstrated boundary and add that exact production file to this task's commit.

Update the three documents with:

- uploading, describing, previewing, replacing, removing, and resuming one Walk featured image;
- choosing local upload or explicit HTTPS/root-relative fallback and what retained fallback means;
- logo/favicon upload, replacement/removal, transparent PNG logo behaviour, square >=32px favicon rule, roughly-512px recommendation, and no SVG/ICO need;
- accepted JPEG/PNG/WebP/AVIF inputs subject to installed codecs and configured byte/dimension/pixel/memory limits;
- private `storage/app/private/site-media` ownership in standard and `public_html` layouts, no public symlink/source write requirement, and prefix-aware served routes;
- database records and private processed variants being included/restored together by Waymark backups;
- PWA install icons remaining distinct and unchanged.

Run: `php artisan test tests/Feature/Operations/BackupRestoreTest.php --filter=managed_site_and_walk_media`

Expected: the focused rows/files/routes/hash/failure case passes.

**Step 3: Run the bounded implementation review set**

Run only these accumulated focused PHP files once, because later tasks changed their integration boundaries:

`php artisan test tests/Feature/SiteMedia/SiteMediaTest.php tests/Feature/SiteMedia/SiteMediaUsageTest.php tests/Feature/SiteMedia/PurposeAwareSiteMediaTest.php tests/Feature/Operations/FreshInstallationSchemaTest.php tests/Unit/Content/PublicImageReferenceTest.php tests/Feature/Walks/WalkFeaturedImageManagementTest.php tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/DuplicateWalkTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/Unit/Walks/WalkFeaturedImageTest.php tests/Feature/Public/PublicWalkPagesTest.php tests/Feature/Content/BrandingMediaManagementTest.php tests/Feature/Content/NavigationBrandingTest.php tests/Feature/Operations/BackupRestoreTest.php`

Run the two critical browser cases only:

- `npx playwright test tests/browser/admin-usability.spec.ts --grep "persists a managed Walk image through draft resume"`
- `npx playwright test tests/browser/branding-settings.spec.ts --grep "uploads managed logo and favicon"`

Run Pint on PHP files changed since Task 8, `php -l` on each, and `git diff --check`. Confirm `git status --short` contains only files assigned to this task before committing.

**Step 4: Self-review against the approved acceptance contract and commit**

Trace every design acceptance scenario A–L to the focused tests above and verify explicitly:

- initial Walk upload writes both alt fields, later owner-specific edits do not mutate shared media, and logo/favicon are decorative;
- Walk authorization precedes internal ingestion and no Walk role gained `ManageSiteMedia`; branding remains `ManageContent`;
- every old/new owner appears in `SiteMediaUsage`, and delete/orphan cleanup rechecks it;
- processing/transaction failure leaves the previous owner reference active and forms never delete physical media;
- temporary uploads persist only at a committed owner checkpoint;
- migration is network-free/data-preserving and down cannot silently contract fallback columns;
- favicon output is PNG while PWA icons are untouched;
- private persistent storage and backup/restore rows plus files are proven together;
- no scope-guarded feature, public redesign, release, version, package, feed, or tag change appears in the diff.

Commit: `git add app/Domain/Operations/Backups/Actions/CreateBackup.php app/Domain/Operations/Backups/Actions/RestoreBackup.php tests/Feature/Operations/BackupRestoreTest.php docs/architecture/media.md docs/deployment/backups.md docs/deployment/reference-content-entry-checklist.md && git commit -m "docs(media): prove and document managed image operations"`

## Implementation Completion Gate

Stop after Task 9. Provide the nine commit SHAs, focused PHP/browser command results, changed-file list, clean `git status`, and any evidence-backed deviation. Do not run database-engine matrices, installers, full Playwright, package generation, performance qualification, versioning, tagging, feed changes, or publication. Those require a separately approved release-candidate gate.
