# ADR 0003 — Server Rendered + Progressive Enhancement

**Decision:** Blade is the public UI foundation; Alpine.js enhances interactions. Tailwind is build-time only.

**Why:** Accessibility, shared-host simplicity, resilience, maintainability, and avoiding unnecessary SPA complexity.

**Consequence:** Core workflows must not require a heavy client-side framework.
