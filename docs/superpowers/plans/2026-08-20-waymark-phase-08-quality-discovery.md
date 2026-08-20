# Waymark Phase 8 — Discovery, Privacy and Quality Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Make Waymark discoverable, accessible, privacy-conscious and fast while remaining shared-hosting friendly.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

---

### Task 1: Database-backed site-wide search
- Search public events, pages, news, documents and gallery albums.
- Friendly case-insensitive partial matching across approved fields.
- Add content-type/date/difficulty/location/document-category filters where relevant.
- No Elasticsearch/fuzzy engine.
- Ensure private/draft/member-only records never enter public search.
- Commit.

### Task 2: SEO foundations
- Editable title/meta description, canonical URLs, XML sitemap, robots handling.
- Structured data appropriate to organisation/events/articles where valid.
- Open Graph/share metadata for events/news/albums.
- Keep useful rich archives indexable and thin expired records noindexable according to rule.
- Commit.

### Task 3: Redirect manager and unavailable content
- Automatic old-slug redirects.
- Admin redirect CRUD within safe guardrails.
- Removed/unpublished known content can show smart unavailable page or admin-selected replacement redirect.
- Branded helpful 404 with search/useful links.
- Commit.

### Task 4: Campaign and external analytics integration
- Preserve/read UTM campaign parameters without building analytics storage.
- Admin-configurable external analytics provider IDs/snippets from approved provider adapters.
- Do not inject optional analytics before required consent.
- Commit.

### Task 5: Cookie/privacy consent and policy linkage
- Essential vs optional consent preferences and settings panel.
- Versioned legal/policy pages; store consent-to-version only where needed.
- Newsletter consent timestamps/history at sensible granularity.
- Starter legal/help templates flagged for group review.
- Accessibility statement template includes WCAG target/known limitations/contact route.
- Commit.

### Task 6: Anti-spam and upload security
- Rate limits and honeypot/invisible defaults for public forms.
- Optional Turnstile-like adapter via configuration.
- Strong allow-list/MIME/size/generated-name upload validation across documents/media.
- Commit.

### Task 7: Accessibility acceptance suite
- Automated developer checks for public critical journeys and admin critical forms where practical.
- Manual-test checklist for keyboard, focus, 200% zoom, reduced motion, colour-independent state, form errors, modal/lightbox focus, mobile touch targets.
- Ensure branding editor warns on contrast failures.
- Commit.

### Task 8: Performance budgets and regression checks
- Define public CSS/JS/page-image budgets and Core Web Vitals expectations in docs.
- Lazy-load non-critical images, responsive sources, cache common public settings/navigation/home queries using file/database cache by default.
- Support Redis only as optional cache backend.
- Add developer-side Lighthouse/performance regression job with non-flaky thresholds.
- Commit.

### Task 9: Print and staging SEO safeguards
- Print-friendly event/detail/document styles.
- Staging environment automatically emits noindex and suppresses sitemap/public analytics behaviour.
- Admin visibly indicates staging state.
- Commit.

## Phase 8 Gate
Public content is findable without leaking private data, optional tracking respects consent, WCAG/performance checks are release tooling rather than production dependencies, and image-heavy pages remain mobile-friendly.
