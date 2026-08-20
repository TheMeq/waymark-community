# ADR 0001 — One Group Per Installation

**Decision:** One active walking group per self-hosted installation.

**Why:** Avoid tenant security/routing/provisioning complexity. Each group owns its own data and infrastructure. Generic configuration still prevents NDWG-specific hard-coding.

**Consequence:** Multi-group tenancy is explicitly out of v1 and would require a future architectural project rather than accidental partial support.
