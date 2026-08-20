# Waymark Phase 6 — Gallery, Media and PWA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Deliver the social photo experience: attributed event-linked uploads, optimisation, moderation, public galleries and mobile/PWA upload flow.

**Architecture:** Community photos and admin media are separate domains/workflows. Every community photo belongs to an event or deliberate special album. Processing produces web-safe derivatives and removes sensitive EXIF.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

---

### Task 1: Community photo and special-album schema
- Model photo ownership, event/album association, uploader, photographer display attribution, caption, moderation status, capture timestamp, focal point, featured state and processed variants.
- Enforce database/application invariant: no orphan community photo.
- Add special albums; no “general gallery”.
- Commit.

### Task 2: Photo policy consent
- Create versioned photo-upload policy acceptance record.
- First upload requires current-policy acceptance; later batches show reminder without repeated checkbox until version changes.
- Email must be verified before upload.
- Commit.

### Task 3: Resource-aware image processing
- Validate allow-listed raster formats by MIME and decoded content.
- Extract orientation/capture time then strip EXIF.
- Produce configurable master/large/medium/thumb variants; never serve original huge upload directly.
- Add setting for source retention, default storage-efficient/off for shared-hosting-oriented installs.
- Generate WebP/AVIF only where server libraries support it cleanly.
- Test memory/error behaviour using representative large fixtures without requiring production cron.
- Commit.

### Task 4: Batch upload with per-file retry
- Build event-aware upload endpoint/actions and Alpine mobile UI.
- Selecting upload from an event preselects it; direct upload prioritises today/recent events.
- Upload each file independently and permit retry of failed files without reselecting successful files.
- Do not implement chunked/resumable transport in v1.
- Commit.

### Task 5: Deferred processing seam
- Small upload processing can run synchronously.
- Larger batches create resumable application job records processed by scheduler/cron when present with safe fallback/manual processing.
- No permanent queue worker dependency.
- Add job-type retry policy.
- Commit.

### Task 6: Bespoke moderation workflow
- Build custom admin moderation page rather than generic CRUD table.
- Approve/reject/bulk actions, edit caption/attribution, move association, rotate, remove published, choose cover/featured.
- Configure organiser-own-event moderation vs global moderator/admin capability.
- Audit meaningful moderation actions.
- Commit.

### Task 7: Reporting/removal workflow
- Public photo action supports reasons: in photo, privacy, copyright, inappropriate, other.
- Published uploader can request removal; pending uploader can delete directly.
- Report enters moderator queue with photo reference/context.
- Commit.

### Task 8: Public gallery and accessible lightbox
- Album-first and recent-stream browsing.
- Real pagination with lazy images and optional Load More progressive enhancement.
- Capture-date ordering fallback to upload order; moderator manual override.
- Rich accessible lightbox with keyboard/swipe, caption, photographer, event context, report action.
- Configurable optimised download.
- Commit.

### Task 9: Holiday gallery aggregation and featured memories
- Child event photos stay linked to child event.
- Parent holiday automatically aggregates approved child photos.
- Organiser/admin can curate featured memories from approved photos only.
- Homepage Recent Adventures and featured-photo queries become real data.
- Commit.

### Task 10: Admin media library
- Separate site-managed media from community photos.
- Reusable hero/news/CMS assets with alt text/decorative flag, focal point and generated sizes.
- Allow admins to deliberately promote approved gallery photo into featured site usage without duplicating unsafe originals.
- Commit.

### Task 11: Basic PWA
- Generate manifest from installation identity/branding.
- Installable shell, icons, basic static-asset caching, graceful offline page.
- No push/background photo sync.
- Verify progressive fallback when service worker unsupported.
- Commit.

## Phase 6 Gate
No anonymous/orphan photos; public images are optimised/EXIF-stripped; moderation is efficient; gallery is accessible/performance-safe; mobile photo flow works without requiring a native app or resident queue worker.
