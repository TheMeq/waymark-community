# ADR 0002 — Shared Hosting Is a First-Class Target

**Decision:** The supported production baseline includes ordinary shared PHP hosting.

**Consequences:** No production Node/Composer/Docker/Redis/permanent queue-worker requirement. Release ZIP contains production dependencies and compiled assets. Cron is preferred but optional. Operations must account for modest memory/time limits.
