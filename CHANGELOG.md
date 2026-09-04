# Changelog

All notable Waymark Community changes are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/) and releases follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.2] - 2026-09-04

### Added

- Added a volunteer-oriented administration dashboard, task-focused navigation, dismissible first-run guidance and a five-step Add Walk workflow using the existing Walk permissions and save process.
- Added bounded local/staging request diagnostics and OPcache visibility in System Health to support shared-host troubleshooting without making OPcache mandatory.

### Changed

- Reduced repeated application work for public configuration, navigation, redirects, gallery presentation, leader options and health summaries using request-scoped and shared-host-safe caching.

### Fixed

- Kept failure details and retry actions out of healthy automatic installer progress, while preserving resumable no-JavaScript and interrupted-connection recovery.
- Applied stored homepage hero focal points and an intentional packaged default crop so the image remains useful across desktop, tablet and mobile layouts.

## [1.0.1] - 2026-08-25

### Fixed

- Improved real shared-host installation reliability, including setup beneath supported URL prefixes and subdirectories.
- Made initial email delivery optional, with clear consequences and a supported way to configure and test it later.
- Clarified required fields and recovery-key guidance, added secure recovery-key generation, and corrected module checkbox sizing and alignment.
- Replaced the single long installation request with persisted, resumable stages and safe non-sensitive diagnostics.
- Added database capability checks plus controlled detection, reset and retry for interrupted fresh installations without changing the migration-defined schema.

## [1.0.0] - 2026-08-25

### Added

- Initial Waymark Community v1 release: shared-host installer, public walking-group site, administration, operations/recovery, portability and reproducible shared-hosting packaging.

## Release-note convention

Move reviewed entries from **Unreleased** into `## [x.y.z] - YYYY-MM-DD`, grouped under Added, Changed, Deprecated, Removed, Fixed and Security as applicable. Write administrator-facing impact and required actions plainly. Mark database migrations, PHP/extension requirement changes, security urgency and any backup/update prerequisite. Do not publish a version heading or Git tag before its acceptance and release authority.
