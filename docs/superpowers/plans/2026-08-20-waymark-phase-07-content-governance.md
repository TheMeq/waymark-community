# Waymark Phase 7 — Content, Governance and Communication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Deliver the controlled CMS and serious organisational content without allowing the platform to become a free-form page builder or helpdesk.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

---

### Task 1: Constrained CMS block model
- Implement page title/slug/hero/visibility/scheduled publish and approved blocks: rich text, image+text, callout, FAQ, quote, CTA, button group, document list, simple gallery, stats, timeline, limited columns.
- No arbitrary HTML/layout builder.
- Add secure preview and expiring/revocable share-review links.
- Commit.

### Task 2: Curated homepage manager
- Implement approved section registry, enable/disable, drag reorder, limited layout variants, headings/copy/CTA fields, pin/override, scheduled visibility, automatic fallback, configurable empty behaviour.
- Preserve Phase 2 visual components and tokens.
- Add branding/homepage previous-value snapshots to audit seam.
- Commit.

### Task 3: Branding, navigation, footer and terminology settings
- Logo/favicon/colours/hero defaults/approved typography options/social links/affiliation.
- Contrast-aware colour warnings and safe foreground selection.
- Guardrailed navigation reorder/rename/hide/add internal/external link.
- Curated footer sections.
- Public terminology aliases without changing internal module names.
- Full-site unsaved branding preview with desktop/tablet/mobile preview sizes.
- Commit.

### Task 4: Testimonials and news
- Curated testimonials only.
- News: title/slug/body/image/author/primary category/tags/scheduled publish/optional expiry/homepage feature.
- No comments.
- Commit.

### Task 5: Versioned document library
- Flat admin-managed categories.
- Documents with versions/current version/description/visibility/publish date/optional public history/download count.
- Controlled-document draft approval, approver, review date and optional review email reminder.
- Commit.

### Task 6: Committee and meetings
- Committee roles, order, dates, active status, public fields, private contact fields.
- Structured meeting record with date/title/agenda/internal notes/approved minutes attachment and per-meeting visibility.
- Public NDWG-style meeting archive can show date/title/minutes while private notes stay restricted.
- Commit.

### Task 7: Committee Hub
- Restricted hub aggregates relevant contacts, documents, meetings, notes/links.
- No tasks/chat/project management.
- Commit.

### Task 8: Contact departments and routed forms
- Configurable departments with public label/description/destination address.
- Default public contact form hides destination address.
- Temporary configurable retention of submissions; email route is primary.
- No helpdesk statuses/assignment/reply history.
- Add spam protections seam from Phase 8.
- Commit.

### Task 9: Email templates and newsletter
- Accessible branded system templates with editable key copy, not arbitrary HTML builder.
- Built-in simple announcement/newsletter send now/scheduled with simple role/status audiences and opt-in rules.
- External newsletter integration seam only; no marketing automation.
- Email delivery failures remain logs/health signals, not mail-management queue.
- Commit.

## Phase 7 Gate
Admins can manage public/serious content without source edits, but the design remains curated and the platform has not become a page builder, helpdesk, board-management tool or email client.
