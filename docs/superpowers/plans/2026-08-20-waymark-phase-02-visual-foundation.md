# Waymark Phase 2 — Visual Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans task-by-task.

**Goal:** Reproduce the approved Waymark Community public visual language before broad backend feature work begins.

**Architecture:** Blade owns semantic markup; Tailwind/design tokens own styling; Alpine provides optional interactions. Fixture/view-model data drives the homepage shell so the final real data can later replace fixtures without changing the component system.

**Tech Stack:** Blade, Tailwind 4.x, Alpine 3.x, Vite, Playwright screenshots, axe-compatible developer accessibility checks.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

## Global Constraints
- `docs/design/references/approved-homepage-concept.png` is the primary visual reference.
- No Bootstrap/public Filament styling.
- No filler copy that narrates the interface.
- WCAG 2.2 AA target applies from the component foundation onward.

---

### Task 1: Define design tokens and public theme contract
**Files:** `resources/css/app.css`, `resources/views/layouts/public.blade.php`, `app/Domain/Operations/Support/BrandTheme.php`, `tests/Unit/Operations/BrandThemeTest.php`, `docs/design/visual-system.md`

**Produces:** semantic tokens for surfaces, text, primary/accent brand, borders, radii, shadow, typography, spacing and safe foreground selection.

- [ ] Write failing tests for contrast-safe foreground selection and default Waymark/installation theme fallback.
- [ ] Implement `BrandTheme::fromSiteProfile(SiteProfile $profile): BrandTheme`.
- [ ] Define CSS custom properties consumed by Tailwind/public CSS.
- [ ] Update visual-system documentation with exact token names; avoid hard-coded NDWG colours in components.
- [ ] Run unit tests and production CSS build.
- [ ] Commit.

### Task 2: Build shared public layout primitives
**Files:** `resources/views/components/public/button.blade.php`, `.../section-heading.blade.php`, `.../event-card.blade.php`, `.../badge.blade.php`, `.../metadata-row.blade.php`, `.../photo-card.blade.php`, `.../site-header.blade.php`, `.../site-footer.blade.php`, component tests.

- [ ] Write rendering tests for semantic elements, accessible names, focusable actions and difficulty text independent of colour.
- [ ] Implement components using design tokens only.
- [ ] Ensure responsive gutters and typography scale match the approved concept hierarchy.
- [ ] Add Story/demo route available only in local/testing environment to review components together.
- [ ] Run component tests and build.
- [ ] Commit.

### Task 3: Build responsive navigation and dismissible site banner
**Files:** public header/banner Blade components, Alpine modules, tests.

- [ ] Test desktop nav structure and mobile accessible menu semantics.
- [ ] Implement mobile hamburger with prominent Upcoming Walks/Join/Account actions.
- [ ] Implement dismissible banner whose dismissal is remembered per banner version in browser storage/cookie without requiring an account.
- [ ] Respect reduced motion.
- [ ] Test keyboard operation and no-JS fallback.
- [ ] Commit.

### Task 4: Reproduce the approved homepage using fixtures
**Files:** `app/Http/Controllers/HomeController.php`, `app/ViewModels/HomepageViewModel.php`, `resources/views/home.blade.php`, `database/seeders/DemoHomepageSeeder.php` or test fixtures, browser tests.

- [ ] Create deterministic fixture content representing hero, What’s On, walks, holiday spotlight, recent adventures, photo CTA, New Here, testimonial and footer.
- [ ] Implement homepage section composition matching the approved concept's visual hierarchy.
- [ ] Keep fixture data outside Blade and avoid final database coupling at this stage.
- [ ] Implement mobile priority order explicitly.
- [ ] Verify keyboard focus order after responsive reordering remains logical.
- [ ] Commit.

### Task 5: Establish screenshot and accessibility baselines
**Files:** `tests/browser/home.spec.*`, `tests/browser/accessibility.spec.*`, `tests/visual/baselines/` or approved Playwright snapshot location, `docs/design/responsive-design.md`.

- [ ] Capture desktop baseline around 1440px viewport.
- [ ] Capture representative tablet baseline.
- [ ] Capture representative mobile baseline.
- [ ] Add automated axe/accessibility checks for homepage/header/footer.
- [ ] Add checks for overflow at 200% text zoom where automation can reasonably assert it.
- [ ] Document the visual acceptance screenshots and tolerances.
- [ ] Commit.

## MANDATORY VISUAL GATE
- [ ] Open the approved concept beside the desktop implementation screenshot.
- [ ] Confirm photographic emphasis, whitespace, typography hierarchy, card density, rounded treatment, navigation proportions, colour balance, CTA hierarchy and footer scale are materially similar.
- [ ] Confirm tablet/mobile feel deliberately designed rather than merely stacked desktop.
- [ ] Confirm the public site does not resemble Filament, Bootstrap or a generic template.
- [ ] Do not start Phase 3 until this gate is explicitly accepted.
