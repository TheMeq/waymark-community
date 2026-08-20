# Waymark Phase 3 — Events and Walks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Deliver the event foundation and a complete Walks module backed by admin-managed data and the approved public components.

**Architecture:** Common event lifecycle is shared; Walk stores walk-specific fields in a focused extension/relationship rather than turning the base event table into a nullable mega-table. Queries/actions feed public view models and Filament resources.

**Tech Stack:** Laravel/Eloquent, Blade, Filament 5, Leaflet 1.9.x stable, MySQL/MariaDB.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

---

### Task 1: Event lifecycle and ownership
**Files:** `app/Domain/Events/Models/Event.php`, enums `EventType.php`, `EventStatus.php`, migrations, factories, `PublishEvent.php`, `ChangeEventStatus.php`, tests.

- [ ] Write tests for fixed statuses: Draft, PendingApproval, Published, Changed, Postponed, Cancelled, Completed, Archived.
- [ ] Implement event identity, slug, publication fields, start/end, summary/description, visibility and organiser ownership.
- [ ] Implement date-based past/completed query behaviour with explicit override support.
- [ ] Implement status-change audit event hook/interface consumed later by audit logging.
- [ ] Run tests on MySQL and MariaDB CI matrix where available.
- [ ] Commit.

### Task 2: Configurable grading, tags and optional-field settings
**Files:** `Grade.php`, `Tag.php`, `WalkFieldSettings.php`, migrations, Filament resources, tests.

- [ ] Test grade ordering/name/description/colour and safe rendering independent of colour.
- [ ] Test reusable tags.
- [ ] Test installation-level enable/disable of optional walk fields without deleting existing data.
- [ ] Build Filament CRUD with validation and restrained help text.
- [ ] Commit.

### Task 3: Walk domain model and validation
**Files:** `Walk.php`, `WalkDetails.php` or equivalent focused model, migrations, actions, request/data objects, tests.

Required v1 fields/optionals must cover the approved spec: distance, ascent, duration, leader/co-leaders, terrain, meeting location, parking, public transport, toilets, cafe/pub, dogs, accessibility, kit checklist/notes, W3W, OS grid ref, attachments, private organiser notes.

- [ ] Write cross-field validation tests.
- [ ] Implement models/actions and units using installation-wide regional settings.
- [ ] Do not make optional fields produce empty public sections.
- [ ] Commit.

### Task 4: Walk leader ownership and direct-publish policy
**Files:** policies, actions, Filament Walk resource tests.

- [ ] Test leader creates/edits own walk.
- [ ] Test leader cannot edit another leader’s walk without wider permission.
- [ ] Test NDWG-style setting allows leaders to publish directly.
- [ ] Test alternate installation setting requires Pending Approval.
- [ ] Commit.

### Task 5: Walk duplication workflow
**Files:** `DuplicateWalk.php`, Filament custom action/page, tests.

- [ ] Test duplication always resets dates, lifecycle/publication state and gallery linkage.
- [ ] Test selectable copy groups: core details, location, GPX, attachments, featured image, optional fields.
- [ ] Implement review screen/checklist.
- [ ] Commit.

### Task 6: Structured meeting location and optional Leaflet/GPX
**Files:** location value/model, GPX storage/parser service, map Blade/Alpine module, tests.

- [ ] Test structured place/address/postcode/coordinates/W3W/OS ref storage.
- [ ] Test walk can publish with no coordinates/GPX.
- [ ] Test valid GPX is stored, downloadable, basic bounds/distance extracted where practical.
- [ ] Test invalid GPX fails clearly.
- [ ] Render Leaflet route only when data exists; tile provider comes from config/settings.
- [ ] Commit.

### Task 7: Public Walk list/filter/detail pages
**Files:** controllers/queries/view models/views, tests/browser tests.

- [ ] Implement chronological list as default.
- [ ] Implement filters for date, distance, ascent, grade, leader, location, tags, public transport and relevant time grouping.
- [ ] Reuse Phase 2 visual components rather than redesigning cards.
- [ ] Implement rich detail page with conditional sections.
- [ ] Add grading-guide public page.
- [ ] Add print CSS for walk detail.
- [ ] Run feature/accessibility/visual smoke tests.
- [ ] Commit.

### Task 8: Past adventures, updates and related content seam
**Files:** event update model/action/view, retrospective fields, related-content service interface, tests.

- [ ] Test dated organiser update notices.
- [ ] Test completed walk keeps original route/details and can add optional recap/highlights.
- [ ] Add related-content interface using tags/type/location signals; admin override may be completed in content phase.
- [ ] Commit.

## Phase 3 Gate
Walks must be creatable/publishable from admin and automatically appear correctly in list/detail/home fixture replacement seams without HTML editing. No GPX requirement. All permission and cross-field validation tests pass.
