# Waymark Community v1 Scope and Release Audit

This is the tracked traceability record for the approved v1 specification. Exact command output, package hashes and environment versions belong in the final acceptance package generated from the final commit.

## Success-criterion traceability

| ID | Approved success criterion | Implementation | Automated evidence | Manual/release evidence |
| --- | --- | --- | --- | --- |
| SC-01 | A group can operate public content, events, gallery, administration, governance, accounts and moderation without code edits. | Domain actions/queries under `app/Domain/`; Filament resources/pages; public routes/controllers. | Feature suites under `tests/Feature/{Accounts,Content,Events,Gallery,Governance,Holidays,Public,Socials,Walks}` and role/permission tests. | Reference content-entry and staging journey checklist. |
| SC-02 | Public presentation retains the approved visual language. | Public Blade components and Waymark tokens in `resources/`. | `tests/browser/home.spec.ts`, responsive visual baselines and release/staging Playwright matrices. | Side-by-side review with `docs/design/references/approved-homepage-concept.png`. |
| SC-03 | A non-developer can install the release ZIP on supported shared hosting. | Setup wizard, installation guard and shared-host layouts. | Setup feature tests, installer Playwright suite and ZIP-only clean-install harness. | MySQL and MariaDB release-ZIP install evidence. |
| SC-04 | A published Walk automatically reaches listing, homepage and calendar views. | Shared Event/Walk records, public queries, homepage view model and ICS feed. | `PublicWalkPagesTest`, `IcsFeedTest`, homepage configuration tests and browser Walk journeys. | Candidate staging content smoke. |
| SC-05 | Member photos are linked, attributed, processed, moderated and browsable. | Gallery ingestion/processing/moderation actions and public gallery queries. | Gallery upload, schema, image ingestion, moderation, reporting and public gallery suites. | Desktop/mobile upload and moderation checklist. |
| SC-06 | Backup, restore, update and rollback are operationally credible. | Bounded backup/restore pipelines, verified updater, framework-independent recovery. | Operations feature/integration suites and production-package upgrade/rollback matrix. | Retained archives, checksums, recovery rehearsal and known-good evidence. |
| SC-07 | A new developer can understand domains, frontend, docs, tests and release tooling. | Root repository map, architecture and contributor/developer guides. | `documentation-readiness.test.mjs` and repository verification. | Independent source review. |
| SC-08 | A clean source clone reproduces the developer environment. | Tracked lockfiles, `.env.example` and setup commands. | Exact-commit clean-worktree verification and complete source gate. | Recorded tool/runtime versions. |
| SC-09 | The release ZIP is self-contained without development tooling. | Exact-commit release builder, manifest and allow/deny verifier. | Release builder/verifier unit/tooling tests and ZIP-only install harness. | Independently opened/verified artifact and manifest report. |
| SC-10 | Accessibility, performance and security requirements are in the release workflow. | Accessible components, consent/security boundaries, budgets and release browser config. | PHPUnit security/privacy tests, Axe/browser matrix, PWA and performance commands. | Manual accessibility checklist and visual inspection. |
| SC-11 | No out-of-scope management-suite functionality has entered v1. | One-installation architecture and bounded feature domains. | `composer audit:v1`, `V1ScopeAuditorTest` and this tooling traceability check. | Reviewer confirms the exclusions below. |

## Explicit excluded-scope audit

The automated audit scans generic runtime source/configuration for reference-deployment assumptions, forbidden management-domain paths, prohibited management tables and tenancy identifiers. The release review additionally confirms:

- no attendance or trial-walk tracking;
- no RSVP, internal booking or waiting-list management (structured external booking information is display-only);
- no payments or payment processing (descriptive pricing/deposit information is display-only);
- no comments/chat;
- no full mailbox client (contact routing and outbound newsletters are bounded communication features);
- no native app (the PWA is progressively enhanced web delivery);
- no plugin marketplace;
- no multi-tenancy or multi-group installation.

## Generic-core assumption audit

`composer audit:v1` rejects NDWG, Nottingham, Derby, Ramblers and a fixed 20–50 age range in `app/`, `config/`, `database/`, `resources/` or `routes/`. NDWG is mentioned only in approved specification/history and reference-deployment documentation. Group name, identity, colours, logos, contacts, policies and social links remain installation configuration or deliberate demo/reference fixtures.

## Release boundary

Passing this tracked audit does not authorise publication. The final gate must still demonstrate clean source reproducibility, verified self-contained packages, MySQL/MariaDB installs, production-package update/rollback, visual/accessibility/PWA/performance results and clean Git. No public tag, signed release or stable-feed publication occurs before independent acceptance.
