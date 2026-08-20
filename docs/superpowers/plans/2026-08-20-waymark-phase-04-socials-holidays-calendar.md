# Waymark Phase 4 — Socials, Holidays and Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Complete the public event ecosystem beyond walks and provide calendar/feed functionality.

**Architecture:** Social and Holiday extend common Event behaviour. Holiday child events keep their own identities. Recurrence creates concrete occurrence events. Calendar feeds operate from common event queries and stable UIDs.

**Tech Stack:** Laravel, Blade, Filament, RFC5545-compatible ICS generation implemented/tested without a resident service.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

---

### Task 1: Flexible Social event fields
- Create social extension model/settings and Filament resource.
- Cover optional venue, cost, booking/contact block, capacity status, accessibility/transport and attachments.
- Test empty optional fields disappear publicly.
- Reuse common event cards/detail layout.
- Commit.

### Task 2: Holiday parent model and external booking block
- Create Holiday details for destination, accommodation, organiser, pricing/deposit, informational capacity, booking status/deadline/instructions/link, travel and itinerary.
- Test booking remains informational only; no attendee/payment records are created.
- Build dedicated public holiday listing/detail and homepage spotlight query.
- Commit.

### Task 3: Holiday child events
- Add parent_event_id relationship constrained to valid holiday-parent cases.
- Test child dates against holiday dates with warning/error rules from spec.
- Add per-holiday setting controlling global calendar visibility of child events.
- Build itinerary rendering and aggregate-child-gallery seam for Phase 6.
- Commit.

### Task 4: Simple recurrence with separate occurrences
- Create `RecurringSeries` definition supporting weekly, monthly and every-N-weeks/months only.
- Generate concrete events; each occurrence can be changed/cancelled independently.
- Do not implement arbitrary RFC recurrence-rule editor.
- Test generated occurrence identity and edit independence.
- Commit.

### Task 5: List/calendar public views
- Build combined What’s On list and month calendar.
- Add dedicated filters/views for walks, socials, holidays and all events.
- Ensure calendar is accessible by keyboard and list remains available as the primary fallback.
- Commit.

### Task 6: Revision-aware ICS feeds
- Generate stable UID per event occurrence.
- Maintain sequence/revision on time/location/status changes.
- Emit cancellation semantics rather than silently dropping cancelled events.
- Provide combined and type-filtered feeds.
- Add tests that parse generated ICS and assert UID/SEQUENCE/status behaviour.
- Commit.

## Phase 4 Gate
Walks, socials and holidays render consistently, holiday parent/child relationships work, recurrence creates real independent events, and list/calendar/ICS present the same authoritative event state.
