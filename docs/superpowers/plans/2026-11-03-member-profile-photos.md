# Member profile photos implementation plan

**Goal:** Let verified, active members upload, preview, replace and remove profile photos using the existing image workflow.

**Decision:** The user selected immediate publication on 3 November 2026. This overrides the general moderation requirement for profile photos only. Gallery moderation is unchanged. No member directory is introduced.

**Architecture:** Keep member photo ownership in Accounts, separate from administrator site media and event-linked gallery records. Reuse IngestCommunityPhoto for MIME/geometry/memory checks, generated private storage and EXIF-free image variants. A dedicated photo form and action avoid partially saving unrelated account fields. External URLs use the existing PublicImageReference rule. Existing public leader profiles resolve the saved photo, with demo-photo compatibility.

## Tasks
- [x] Write failing feature tests for upload, public stream, preview, replacement/removal, unsafe inputs, verified ownership and account deletion.
- [x] Add ProfilePhoto model/migration, a validated input, update/cleanup action and presentation query. Keep small thumbnail/medium variants and no original.
- [x] Wire authenticated photo updates and safe public streaming; replace the profile placeholder with upload/external/none controls, saved and selected-file previews, and inline validation.
- [x] Wire leader presentation and deletion cleanup; update the fresh-install schema manifest.
- [x] Run account/gallery regression tests, JavaScript checks and build. Review the final diff. Stop at the feature acceptance gate; do not deploy.

## Review focus
- Unverified or disabled users cannot change photos; supplied user/media IDs cannot target another member.
- Invalid or oversized uploads preserve the current photo and leave no files behind.
- Replacement/removal invalidates old URLs and removes private files; missing files return 404.
- Upload mode without a new file preserves a saved upload, but requires a file for a new photo.
- No image clears external and managed photos; account deletion also removes photo files.

## Verification
- Full PHP suite: 1,229 tests passed (7,115 assertions).
- JavaScript tooling and service worker checks: 39 passed; asset build passed.
- Desktop/tablet/mobile profile browser tests cover upload preview, immediate publication, leader display, removal, accessibility and no-JavaScript keyboard submission. Profile visual baselines added; unverified-account baselines refreshed.
- Review corrections: isolated profile-photos UUID namespace on a private configured disk; 255-character external URL limit matches the existing user column; detached file records are private and retained for bounded cron/fallback cleanup retries.
- Feature remains local; no server deployment performed.

## Pre-push review — 3 November 2026

- Rechecked upload validation, ownership, immediate-publication messaging, private storage references, replacement/deletion cleanup, and scheduled/fallback retries.
- Account/gallery/auth/usability regression group: 301 tests passed (1,587 assertions).
- Site-media, fresh-install schema and fallback regression group: 63 tests passed (371 assertions).
- Service worker checks: 6 passed. Asset build and PHP formatting checks passed.
- Profile-photo browser checks passed at desktop, tablet and mobile, including accessibility, public leader display, removal and no-JavaScript submission. Cleared cookie/focus overlays before snapshots and refreshed the mobile baseline for the approved footer/copy changes.
- Final combined account-profile/profile-photo browser run: 6 passed; mobile account-profile baseline also refreshed for the approved footer and link labels.
