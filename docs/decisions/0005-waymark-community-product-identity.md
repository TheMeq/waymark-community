# ADR 0005 — Waymark Community product identity

## Status
Accepted — 2026-08-20

## Decision
The external product name is **Waymark Community**. The internal project/code name is **Waymark**.

NDWG is the first/reference installation only. Installation-specific branding must never be embedded into the generic platform core.

## Conventions
- Repository: `waymark`
- PHP/project namespace: `Waymark` where project-specific naming is needed
- Release package: `waymark-community-<semver>-shared-hosting.zip`
- Default PWA/product label: `Waymark Community`
- Optional public attribution: `Powered by Waymark Community`

## Consequence
Human-facing product surfaces can use the full distinctive name while code remains concise and stable.
