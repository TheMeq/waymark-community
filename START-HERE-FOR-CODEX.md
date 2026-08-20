# Start Here for Codex

This is the approved design-and-planning baseline for **Waymark Community** (internal project name: **Waymark**). NDWG is the first/reference installation, not the product.

Before writing code:

1. Read `AGENTS.md` in full.
2. Read `docs/superpowers/specs/2026-08-20-waymark-community-design.md`.
3. Inspect `docs/design/references/approved-homepage-concept.png`.
4. Read `docs/superpowers/plans/2026-08-20-waymark-master-roadmap.md`.
5. Read the current phase plan beginning with `docs/superpowers/plans/2026-08-20-waymark-phase-01-foundation.md`.
6. Read the architecture, repository, shared-hosting, testing, and release documents referenced by that phase.

## Execution rule

Implement one phase at a time. Do not jump ahead because later work looks easy. Every phase has its own acceptance gate.

**Phase 2 contains a mandatory visual-fidelity gate.** The public homepage shell must materially match the approved concept before backend breadth is allowed to hide visual drift. A generic Laravel, Bootstrap, Filament, or template-looking frontend is a failed implementation.

Use test-driven development for behaviour. Keep commits small and phase-scoped. Record new ideas in `docs/backlog/future-ideas.md` unless they are required by the approved v1 specification.
