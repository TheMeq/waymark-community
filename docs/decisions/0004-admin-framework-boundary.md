# ADR 0004 — Filament for CRUD, Bespoke for Specialist Workflows

**Decision:** Use Filament for CRUD-heavy administration but custom-build workflows where a generic resource table/form is insufficient (photo moderation, updates, backups/recovery, setup, imports, health).

**Boundary:** Filament visual styling does not define or leak into the public website.
