# Waymark Community — Product & Technical Design Specification

**Date:** 20 August 2026  
**Status:** Approved design baseline; implementation plan is the next gated artifact.  
**Reference deployment:** Nottingham & Derby Walking Group (NDWG).  
**Product model:** Generic self-hosted platform, one walking group per installation.

## 1. Purpose

Build a modern, social, photo-led website and administration platform for walking groups. NDWG is the first real deployment and reference case, but the application must be reusable by unrelated walking groups without source-code changes.

The platform is not a central SaaS. Every group hosts its own independent copy, database, uploads, configuration, users, backups, and email infrastructure.

The public site should present the organisation primarily as an active outdoor community rather than as an administrative noticeboard. Walks, social events, weekends away, photography, and welcoming information are the public-facing priority. Governance, policies, committee information, and operational resources remain first-class but visually secondary.

## 2. Design principles

1. **One group per install.** Do not introduce multi-tenant complexity in v1.
2. **NDWG is not the product.** Installation-specific facts are data/configuration, never hard-coded behaviour.
3. **Shared hosting is a first-class target.** The application must be practical on ordinary PHP/MySQL hosting.
4. **Visual quality is functional scope.** The approved concept is a required design target, not optional inspiration.
5. **Server rendered first.** JavaScript progressively enhances; essential browsing/forms still work without advanced browser APIs.
6. **Social without becoming a social network.** Photos, events, and community personality are encouraged; comments, chat, reactions, member directory, and internal messaging are intentionally excluded.
7. **Useful automation over admin burden.** Events archive automatically, homepage sections can fall back automatically, images optimise automatically, and scheduled tasks reduce repetitive work where possible.
8. **Configuration within guardrails.** Groups can tailor branding, labels, navigation, homepage composition, grading, modules, and content without unrestricted theme/page-builder freedom.
9. **Do not narrate obvious UI.** Copy should add value, not describe controls or headings the visitor can already see.
10. **Data ownership and recoverability.** Backups, exports, migration, safe updates, and disaster recovery are product requirements.
11. **Accessibility target.** WCAG 2.2 AA where applicable, including public and admin interfaces.
12. **Performance target.** Image-heavy experiences must remain fast on mobile and modest hosting.

## 3. Explicit v1 exclusions

The following are deliberately outside v1 unless an agreed requirement cannot function without them:

- multi-group/multi-tenant hosting within one installation;
- full paid membership management;
- attendance tracking or enforcement of trial-walk counts;
- walk RSVP/booking/waiting-list system;
- online payments;
- native mobile application;
- push notifications/background PWA sync;
- public comments/discussion/reactions;
- user-to-user messaging;
- public member directory;
- full mailbox/email client in admin;
- task/project-management system;
- plugin marketplace or user-installable third-party plugins;
- full public API surface;
- advanced built-in analytics suite;
- live transport data integration;
- rich weather automation/warnings;
- complex per-walk risk assessment workflow;
- full route/elevation analysis engine;
- dark mode in v1;
- arbitrary custom CSS/themes;
- unrestricted page builder;
- automatic software updates.

New ideas discovered during development go into the backlog unless necessary to deliver a requirement already contained here.

## 4. Technology direction

Approved architecture:

- Laravel as the backend framework;
- Blade for server-rendered public UI;
- Tailwind CSS as a build-time styling tool;
- Alpine.js for progressive enhancement;
- Filament for CRUD-heavy administration, with bespoke specialist screens where workflows justify it;
- MySQL and MariaDB as official v1 databases;
- Leaflet for optional embedded maps with configurable tile provider;
- standard Laravel storage abstraction for local and optional S3-compatible storage.

Node.js is a development/release dependency only. Exact framework/package versions must be pinned by the implementation plan against then-current stable versions and validated against common shared-host capabilities.


### 4.1 Implementation baseline

Implementation planning starts from the following supported baseline and must pin exact patch versions in lockfiles when scaffolding occurs:

- Laravel 13.x;
- PHP 8.3 minimum, with supported newer PHP versions validated by CI/release tests;
- Filament 5.x for admin CRUD/panel infrastructure only;
- Blade for server-rendered public UI;
- Tailwind CSS 4.x as a development/build-time dependency only;
- Alpine.js 3.x for progressive enhancement;
- Leaflet 1.9.x stable for v1 mapping unless a later stable 2.x release is deliberately approved before implementation;
- MySQL and MariaDB as official v1 database targets;
- Vite/build tooling only in development and release automation.

The release package must contain compiled assets and production Composer dependencies, so these build tools are not production requirements. Dependency lockfiles are authoritative for exact versions.

## 5. Installation model

Exactly one active walking group is configured per installation. The application may model an explicit Group/Site identity record for clean ownership and configuration, but there is no tenant switcher, tenant routing, cross-group data boundary, or multi-group admin UI.

Group configuration includes at minimum:

- group name and short name;
- logo, favicon, hero imagery;
- primary/accent branding colours;
- approved typography choice(s);
- contact information;
- structured home area plus free-text coverage area;
- social links;
- affiliation details;
- membership/join wording;
- regional units/date/time format;
- installation timezone;
- enabled modules;
- public terminology labels;
- navigation/footer configuration;
- public analytics and privacy configuration.

## 6. Public visual system

The approved reference is `docs/design/references/approved-homepage-concept.png`.

The target character is contemporary outdoors/community design:

- large authentic photography;
- warm off-white/light neutral surfaces;
- charcoal typography;
- restrained moss/sage brand colour plus a controlled accent;
- confident display headings;
- generous whitespace;
- rounded cards;
- subtle borders/shadows;
- occasional subtle topographic/trail motifs;
- strong event metadata hierarchy;
- people and candid group photography alongside landscapes;
- restrained copy.

The public site must not look like a default Bootstrap, generic Laravel, Filament, WordPress, or enterprise admin template.

Filament styling is restricted to the admin surface.

### 6.1 Design tokens

Implementation must establish semantic tokens for at least:

- surfaces/backgrounds;
- text primary/muted/inverse;
- brand primary/secondary/accent;
- success/warning/error/info;
- border colours;
- radii;
- spacing scale;
- shadow levels;
- type scale;
- container widths;
- responsive breakpoints;
- focus styles.

Colour customisation is contrast-aware. Admin colour pickers warn when combinations fail accessibility targets and the platform should choose safe foreground colours where possible.

### 6.2 Visual approval sequence

Before broad dynamic wiring, implement and review:

1. design tokens;
2. shared public components;
3. a static homepage reproduction using fixture/demo data;
4. responsive desktop/tablet/mobile reproduction;
5. visual approval against the concept;
6. only then bind sections to live modules.

Visual regression baselines preserve approved composition at representative desktop, tablet, and mobile viewports.

## 7. Public navigation and homepage

Navigation is configurable within guardrails: admins may reorder top-level items, rename public labels, hide disabled modules, and add limited internal/external links. Arbitrarily deep custom menus are excluded.

Mobile uses an accessible hamburger menu with prominent high-value actions such as Upcoming Walks, Join, and Account.

Homepage is a curated configurable composition of approved sections, not an unrestricted page builder. Admins may:

- enable/disable approved sections;
- drag/drop reorder sections;
- choose from a small approved layout-variant set;
- edit useful headings/supporting copy/CTA labels;
- pin featured content or use automatic content;
- schedule section visibility;
- configure automatic fallback content;
- choose whether an empty section hides or presents a concise empty state.

NDWG default composition should broadly follow:

1. header/navigation;
2. large photographic hero;
3. What's On mixed event cards;
4. upcoming walks/discovery;
5. recent adventures/gallery content;
6. photo-upload/community CTA;
7. holiday spotlight;
8. New Here/joining content;
9. testimonial;
10. optional news/group update;
11. join CTA;
12. curated footer.

Mobile may reorder these sections for utility rather than simply stacking desktop order.

Hero is one configurable hero in v1 (image, headline, short copy, primary/optional secondary CTA). Data design should not prevent a future rotating hero, but no carousel is implemented now.

A site-wide temporary announcement banner supports message, optional link, severity, start/expiry dates, and dismissal remembered in the browser. A genuinely new banner version must not be suppressed by dismissal of an older notice.

## 8. Common event model

Walks, Socials, and Holidays share an event foundation. Common concerns include:

- title and slug;
- summary and rich description;
- start/end date/time;
- status/lifecycle;
- visibility/publication state;
- organiser relationships;
- structured location;
- featured image/focal point;
- tags;
- attachments where permitted;
- updates/notices;
- gallery relationship;
- homepage/related-content relationships;
- SEO/share metadata;
- audit metadata.

Fixed event lifecycle:

- Draft;
- Pending Approval;
- Published;
- Updated/Changed;
- Postponed;
- Cancelled;
- Completed;
- Archived.

Workflow may bypass states. NDWG leaders can publish their own walks immediately; other installations may require approval.

Completed/past state is inferred automatically from dates but organisers/admins can override for unusual cases.

Significant public changes support a visible latest update and an admin-visible change history. Calendar publishing preserves stable event identity and revision/cancellation information.

## 9. Walk module

Walks are the central domain type.

### 9.1 Admin fields

A walk uses core fields plus installation-configurable optional fields. Supported public concepts include:

- title;
- date/time;
- primary leader;
- optional co-leaders;
- featured image;
- distance;
- ascent;
- configurable difficulty grade;
- estimated duration;
- summary/description;
- terrain notes;
- meeting/start location name;
- address/postcode;
- optional coordinates/map pin;
- optional What3Words;
- optional OS grid reference;
- directions;
- parking notes;
- structured public-transport-friendly flag and nearest station/stop/notes/links;
- toilet information;
- café/pub-stop information;
- dog guidance where relevant;
- accessibility notes where relevant;
- structured configurable kit checklist plus free-text kit notes;
- optional GPX upload;
- optional embedded route map;
- GPX download where provided;
- limited attachments;
- tags;
- private organiser notes;
- dated organiser updates;
- optional post-event recap/highlights.

Optional information is omitted cleanly on public pages; empty headings must not render.

Risk-assessment management is not a per-walk workflow in v1. Generic safety/risk templates/guidance live in the document library.

### 9.2 Walk listing

Default is chronological list/card presentation with a separate calendar view. Filters include applicable combinations of:

- date;
- distance;
- ascent;
- difficulty;
- leader;
- location;
- tags;
- public transport;
- weekend/evening where derived/useful.

Useful metadata must be visible without opening every item. Difficulty never relies on colour alone.

### 9.3 Grading

Each installation defines its own grading scheme: label, description, display order, colour/accent, and guidance. A dedicated public grading guide explains the scheme in practical terms.

### 9.4 Duplication

Leaders can duplicate a prior walk. The duplicate workflow lets the user choose which reusable information to copy (core details, location, GPX, attachments, featured image, optional fields). Date, publication status, galleries, and other instance-specific data are reset.

### 9.5 Past adventures

Past walks remain public/useful where appropriate. Their page emphasis may shift toward gallery, featured memories, optional recap, and route/history while retaining original information. Rich archive pages can remain indexable; thin/empty expired records may be `noindex`.

## 10. Maps and GPX

Leaflet is the v1 embedded map library with configurable tile provider. A walk may render meeting point and optional GPX route. GPX support in v1 includes storing/downloading the file, parsing enough data to draw the route, map bounds, and basic route information where reliable. Rich elevation/ascent/waypoint analysis is deferred.

No GPX or embedded map is required to publish a walk.

## 11. Social events

Socials use the common event model with optional fields rather than a heavy dedicated subsystem. They can represent pub nights, meals, BBQs, talks, bowling, Christmas events, or informal meetups.

Potential optional fields include venue, cost, organiser, capacity status, booking information, accessibility/transport notes, limited attachments, and gallery.

## 12. Recurring events

Simple recurrence supports weekly, monthly, or every-N-weeks style patterns. A recurring series definition generates separate event occurrences. Each occurrence is independently editable, cancellable, archivable, and gallery-capable.

Complex recurrence rules are deferred.

## 13. Holidays / weekends away

Holidays receive a dedicated public area and may be spotlighted on the homepage.

A holiday can contain:

- destination;
- dates;
- featured imagery;
- accommodation;
- description;
- organiser;
- structured optional pricing (free/TBC/fixed/from/deposit/currency/notes);
- informational capacity/status;
- booking deadline/status;
- structured external booking link/contact/instructions;
- travel information;
- itinerary;
- limited attachments;
- gallery;
- optional child walks/socials.

Booking/payment remains external in v1.

A holiday can act as parent to child walk/social events. Per holiday, admins choose whether child events appear in global calendars. Photos should belong to the most specific child event where possible, while the parent holiday page aggregates approved child galleries into a combined trip gallery.

## 14. Calendar and feeds

Provide separate list and calendar views plus ICS/iCal subscriptions.

Views/feeds include:

- combined What's On;
- Walks only;
- Socials only;
- Holidays only.

Stable event UIDs plus revision/cancellation handling ensure changed events update rather than duplicate in subscriber calendars.

## 15. Accounts, membership, and roles

Account strategy: lightweight community accounts plus privileged volunteer/admin roles. Paid/Ramblers membership remains external.

Normal profile data:

- real/account name;
- email;
- password;
- public display name (default privacy-friendly pattern such as first name + surname initial);
- optional phone;
- optional profile photo;
- communication preferences;
- membership-verification metadata;
- favourites.

No member directory.

Registration is open with staged trust. A user may enter the account before email verification, but meaningful identity-dependent actions such as photo upload require verified email.

New-user onboarding is concise and explains how the group works, how to choose a walk, the trial-walk policy, photo rules, and membership context.

### 15.1 Trial-walk policy

NDWG allows newcomers up to three walks before requiring Ramblers membership. The platform communicates this policy in Join/New Here/onboarding content but does not record or enforce attendance.

### 15.2 Ramblers verification

Verification is admin-only. Users do not request it. Admins may check whatever external membership source they have access to and record:

- status;
- verifier;
- verified date;
- source/method if useful;
- optional review date.

Verification does not unlock much by default. Permissions remain granular so an installation can restrict selected resources if later needed.

Users should be encouraged to register with the same email used for Ramblers where appropriate, but email equality is not automatically authoritative unless a future trusted integration explicitly makes it so.

### 15.3 Roles

Ship fixed conceptual roles with configurable capabilities:

- Registered User;
- Verified Member;
- Walk Leader;
- Moderator;
- Administrator.

Permissions are module-aware. Walk leaders have ownership rights for their own walks; moderators/admins can be broader. Do not implement arbitrary custom roles in v1.

First admin is Installation Owner, but not immortal. Ownership transfer is an explicit re-authenticated, audited workflow. Admin promotion/creation is high-risk, re-authenticated, audited, and notifies existing admins.

Optional 2FA exists in v1 but is not mandatory. Sensitive admin changes require password re-authentication and a fresh 2FA challenge when that user has 2FA enabled.

Password recovery is standard email reset plus basic login throttling.

### 15.4 Account lifecycle

Long-inactive accounts are flagged for optional admin review; nothing is automatically deleted. Admin review supports leave alone, deactivate, or mark reviewed.

Users may request account deletion. Admin workflow deletes what is appropriate and deactivates/anonymises historical relationships where deletion would break event/gallery history. Users may request an export of their personal data; generation produces a secure authenticated time-limited download that expires automatically.

## 16. Favourites

Logged-in users can save suitable public items such as walks, socials, and holidays for quick access. No reminder workflow in v1.

## 17. Walk leader profiles and hub

Public leader profile is optional/minimal: display name, optional photo, short introduction, upcoming walks. There is no general member directory.

Walk Leader Hub provides:

- own upcoming/draft/past walks;
- create/duplicate shortcuts;
- private organiser notes;
- photo moderation for own events where enabled;
- relevant leader documents/guidance.

No recce/project/task management in v1.

## 18. Galleries and photos

Every photo must belong to exactly one meaningful source context: a walk, social, holiday/child event, or admin-created special album. No unlinked/general orphan photo pool exists.

### 18.1 Upload rules

- anonymous uploads prohibited;
- user must be authenticated and email-verified;
- first upload requires acceptance of current photo-upload policy version;
- later uploads show concise reminder rather than repeating full checkbox bureaucracy;
- default photographer attribution is uploader, with optional alternate photographer credit;
- optional caption;
- image, caption, and attribution are moderated as one submission.

Pending user uploads may be deleted by uploader. Once published, the uploader uses a removal-request workflow rather than silently deleting published history.

### 18.2 Moderation

Full moderation controls include:

- approve/reject;
- bulk approve/reject;
- move to correct event/album;
- edit caption;
- correct attribution;
- rotate where appropriate;
- select cover/featured photos;
- remove published photos;
- review report/removal requests.

Moderation permissions are configurable. NDWG default: event organisers can moderate their own event photos; dedicated moderators/admins can moderate all.

Anyone viewing a public photo can submit a structured report/removal request with reasons such as “I am in this photo”, privacy, copyright, inappropriate content, or other.

### 18.3 Gallery browsing

Public gallery supports both:

- a recent visual stream;
- structured album/event browsing.

Use real pagination underneath, image lazy-loading, and optional Load More progressive enhancement. Avoid infinite scrolling as the only navigation model.

Order by capture date where safely extracted; fall back to upload order; moderators/organisers can override ordering.

Organisers/admins may choose Featured Memories/highlights. Homepage may show recent adventure cards plus a small strip of featured memories.

### 18.4 Lightbox

Accessible lightbox supports previous/next, keyboard, mobile swipe, caption, photographer attribution, event/album link, report/removal action, and correct focus management. No comments/reactions/EXIF display.

Photo download availability is installation-configurable. NDWG default: enabled, serving processed web-optimised versions rather than original source files.

### 18.5 Image processing

Large source phone images must never be served directly to public pages. Generate predefined responsive variants such as master/large/medium/thumbnail; exact pixel sizes and quality are implementation-plan decisions.

Modern WebP/AVIF variants may be generated where hosting libraries support them. Source retention is configurable, and the shared-hosting-oriented default should favour replacing enormous originals with a sensible processed master or discarding the original after successful processing.

Extract required capture/orientation metadata, then strip EXIF from final stored/published images.

Support per-image focal point so wide/square/card crops preserve the important subject.

Each image uploads independently so a failed file can be retried without resubmitting the batch. Small uploads may process immediately; large batches can defer into resumable scheduled job work. The site must remain functional without a permanent queue worker.

## 19. PWA and mobile

v1 is a responsive website with basic PWA capabilities:

- installable manifest;
- group-specific app name/icon/branding;
- static asset caching;
- graceful offline page;
- mobile-first interaction design where useful.

No push notifications or sophisticated background/offline sync in v1.

Mobile photo flow should support Take Photo / Choose from Library where browser permits, preselect event when launched from an event, prioritise today's/recent events when launched generically, batch upload, progress, and per-file retry.

Progressive enhancement is mandatory: unsupported browser features fall back to ordinary HTML/file inputs.

## 20. CMS and homepage content

General informational pages use a constrained block library, potentially including:

- rich text;
- image + text;
- callout;
- FAQ accordion;
- quote/testimonial;
- CTA/button group;
- document list;
- simple gallery;
- statistics;
- timeline;
- limited approved column layouts.

No arbitrary grid/page-builder or custom code/CSS blocks.

CMS pages support draft, secure preview, optional scheduled publication, approved per-module workflow where configured, archive/trash, SEO metadata, and redirects when slugs change.

Content tone: friendly and conversational on public/community surfaces, formal where governance requires it. Do not over-explain obvious interface elements.

## 21. Testimonials and news

Testimonials are admin-curated only in v1: quote, display name, optional photo, optional “member since”.

News supports title, slug, body, featured image, author, one primary category, optional tags, publish now/scheduled, optional expiry from active listing, homepage featuring, SEO/share metadata. No comments.

## 22. Documents and governance

Document library is a dedicated versioned system with admin-managed flat categories. Each document may include:

- title;
- description;
- category;
- versions;
- current published version;
- visibility;
- publication/effective date;
- optional public historical versions;
- anonymous aggregate download count;
- optional review date;
- controlled-document flag;
- approval metadata where controlled.

Controlled policies/governance documents use draft + approval and can record approver and optional review date. Dashboard flags approaching/overdue review; optional email reminders can be configured for selected categories/documents.

Legal/policy pages support versioning and link consent to a specific policy version only where real consent is required (e.g. newsletter/photo policy), not for every informational page.

Starter templates are provided for privacy, cookies, photo policy, accessibility statement, and terms/site use, clearly marked for group review rather than presented as legal advice.

Accessibility statement template can mention platform target WCAG 2.2 AA, known limitations, and contact route.

## 23. Committee

Structured committee records support role, person, ordering, start/end dates, active/inactive status, optional public info, and private internal contact data.

Committee Hub is restricted and brings together committee-only documents, meeting information, private contacts, notes, and useful links. It is not a collaboration/task/chat suite.

Committee meetings are structured records with date/title, optional agenda, appropriate internal attendee/notes data, approved minutes attachment, and configurable visibility. NDWG normal default can expose simple meeting archive and approved minutes publicly while internal notes stay restricted.

History/About content may use a structured timeline block for milestones without a dedicated historical archive subsystem.

## 24. Contact and communication

Admins define configurable contact departments (e.g. General, Walks, Membership, Holidays, Treasurer, Website) with public labels, descriptions, and destination email addresses. Public forms normally route without exposing email addresses; explicit public address display can be configured where desired.

Contact submissions are emailed and retained temporarily in admin for a configurable short period as a safety net, then automatically deleted. v1 does not become a helpdesk.

Future shared mailbox/admin email client is backlog only.

### 24.1 Email

SMTP-first setup; PHP mail may be fallback. Setup tests mail before completion where possible.

Branded transactional templates are platform-controlled structurally with editable key content. v1 email delivery failure management remains infrastructure/log oriented rather than becoming an admin resend queue.

Notifications are email-only in v1. Architecture may be extensible, but no in-site notification centre.

User communication preferences remain simple categories (newsletters/group news, photo moderation outcomes, membership-related communication, etc.) while required transactional/security mail cannot be opted out of.

### 24.2 Newsletter

Built-in simple announcement/newsletter capability plus future external-provider integration. Supports send now/scheduled and simple role/status audience filters, not marketing segmentation. Newsletter consent is timestamped/version-aware where needed.

## 25. Search, SEO, sharing, redirects

Site-wide public search covers events, pages, news, documents, and gallery albums, with relevant filters and friendly case-insensitive partial matching. Do not require Elasticsearch or a search service.

SEO includes:

- editable titles/descriptions;
- clean slugs;
- canonical URLs;
- XML sitemap;
- robots controls;
- Open Graph/social metadata;
- structured data for organisation/events/articles where appropriate;
- visibility-aware indexing;
- sensible archive indexing;
- automatic redirect creation on slug change plus admin redirect manager;
- UTM/campaign parameters preserved for external analytics.

Past rich content may stay indexable; thin/empty records may be noindexed.

Deleted/unpublished previously public content gets a friendly unavailable state and admins may point it to an explicit replacement/redirect where appropriate.

Branded 404 includes search and useful links.

Deeper content pages use breadcrumbs; top-level sections need not.

Related-content suggestions may be automatic based on type/tags/location with admin override/pinning.

Public share actions use rich Open Graph previews; no deep social API/feed integration.

Analytics is externally configurable (e.g. GA/Matomo/Plausible-like providers) rather than a built-in analytics warehouse. Optional analytics must respect consent configuration.

## 26. Accessibility

Target WCAG 2.2 AA where applicable. Requirements include semantic markup, meaningful heading order, keyboard functionality, visible focus, skip links, accessible form labels/errors, screen-reader-friendly status, colour-independent meaning, reduced-motion support, responsive zoom/text behaviour, accessible modals/lightboxes, adequate touch targets, and contrast-aware branding.

Admin/site-managed images support meaningful alt text and decorative flags. Large community galleries do not force members to author bespoke alt text for every snapshot; context/caption/event information should be used appropriately without fake/generated descriptive verbosity.

Accessibility testing is part of developer/release validation, not deployed runtime requirements.

## 27. Performance

Define release-time budgets for JS/CSS size, image derivatives, page weight, and Core Web Vitals. Use responsive images, lazy loading, caching, efficient database queries, and restrained JS.

Cache architecture supports file/database as shared-host default and optional Redis when available.

Gallery pages use pagination/lazy loading rather than unbounded infinite lists.

Automated developer/release checks detect substantial performance regressions.

## 28. Admin experience

Admin is for volunteers and committee members, not developers. Filament is appropriate for CRUD-heavy management, with bespoke screens for specialist workflows including moderation, backups/recovery, updates, setup, import previews, and system health.

Approximate admin IA:

- Dashboard;
- Events: Walks, Socials, Holidays, Recurring Events;
- Community: Photo Moderation, Galleries, Users, Testimonials;
- Content: Homepage, Pages, News, Media Library, Navigation, Redirects;
- Group: Documents, Committee, Meetings, Walk Leader Hub, Committee Hub, Contact Departments;
- Configuration: Group Details, Branding, Modules, Grading, Regional Settings, Email, Privacy/Cookies, Analytics, Integrations;
- System: Backups, Updates, Health, Imports/Exports, Audit.

Disabled modules disappear from public/admin navigation and dependent behaviours degrade cleanly.

Dashboard is role-aware rather than user-customisable.

Admin forms use a shared accessible design system, context-aware validation, concise just-in-time help, autosave for long forms, clear saved/unsaved state, secure preview, and temporary shareable review links. Empty states provide one useful next action without tutorial-like over-explanation.

Tables have sensible defaults plus remembered per-user optional columns/sorting. Module-specific bulk actions are allowed with safeguards. Small reversible actions may offer short-lived Undo.

Content lifecycle distinguishes Active, Archived, Trash, and Permanent Delete. Most destructive actions soft-delete first; dangerous operations require stronger confirmation.

General admin audit log records meaningful actions, with richer history only where explicitly required (events, documents, branding/homepage snapshots). Logs themselves remain technical/server-side; normal admins see health state, not stack traces.

## 29. Media library

Admin-managed site media is distinct from member gallery submissions. Site media can be reused across events/pages/news/homepage. Approved gallery photos may be deliberately reused by admins as cover/featured imagery; automatic promotional reuse is avoided.

Support focal point and web optimisation. Broken references fail gracefully publicly and appear in an admin repair/health queue.

## 30. Branding and terminology

Group customisation includes logo, colours, hero imagery, favicon, selected approved typography, social links, footer details, optional affiliation branding, and public-facing terminology labels.

Public pages remain structurally similar across installations. Arbitrary theme overrides/custom CSS are not supported in v1. Platform controls layout/component structure and can improve it safely in future releases.

Branding editor supports live component preview and secure full-site preview before commit. Preview can switch representative desktop/tablet/mobile widths.

Product/platform attribution is subtle on public site and more visible in setup/admin/update/docs. Public group branding is dominant.

Product marketing name is intentionally decoupled from code/domain naming so a later branding choice does not force architectural renames.

## 31. Localisation and regional settings

v1 UI ships English only but all system-facing text must be localisation-ready through translation resources. CMS content remains single-language in v1.

One installation-wide timezone.

Installation-wide regional settings include distance/ascent units, date format, 12/24-hour time, first day of week, and locale-aware numeric display.

## 32. Privacy and consent

Built-in consent management distinguishes essential and optional cookies and blocks optional analytics where required until consent. Cookie preferences can be revisited.

Relevant consent records store timestamp and policy version where justified.

Photo uploads are public after approval; users are informed accordingly through versioned photo policy. Photo report/removal workflow is always available publicly.

Contact form retention is short/configurable. User data export and deletion requests are supported. EXIF is stripped from public photos. No unnecessary download/user-level document tracking.

## 33. Spam and upload security

Default anti-abuse: rate limiting, validation, honeypot/invisible low-friction checks. Optional provider integration (Turnstile/reCAPTCHA-like) can be configured if needed.

Uploads use allow-listed types, MIME inspection, size limits, generated names, no executable uploads, and private/non-public storage where appropriate. Member photos remain unpublished until moderation.

## 34. Setup wizard

Primary installation path is guided `/setup`; developer/manual installation remains supported.

Wizard flow:

1. welcome;
2. server compatibility/preflight;
3. database setup/test;
4. group details;
5. branding with simple live preview;
6. first administrator;
7. SMTP/mail test;
8. module choices with sensible defaults;
9. optional Advanced integrations/settings;
10. install/migrate/seed;
11. final health/security checks;
12. installer lockout.

Preflight checks PHP version/extensions, database connectivity, writable storage, upload limits, GD/Imagick capability, HTTPS, document-root/config safety, disk space where measurable, and optional cron.

Errors are plain-English remediation instructions rather than framework exceptions.

Starter/demo mode may seed clearly marked sample content and provides one-click removal before launch.

`.env` is canonical infrastructure secret/configuration. Prefer application and `.env` outside public document root. Installer verifies `.env` is not web-readable and `APP_DEBUG` is off in production.

## 35. Shared hosting constraints

Production cannot require:

- Node.js runtime;
- Composer on destination host;
- Docker;
- Redis;
- permanent queue worker;
- shell/SSH;
- developer test tooling.

Cron is preferred but optional. Core site remains usable without cron; non-critical scheduled work has safe fallbacks/manual triggers where necessary.

Resource-heavy tasks must be considerate of PHP time/memory limits. Larger work may be broken into small resumable scheduled units.

Frontend assets and production Composer dependencies are included in release package.

## 36. Scheduled jobs

Potential scheduled work includes image batch processing, backups, temporary contact cleanup, scheduled publishing, update checks, personal-data export generation, newsletters, and document-review reminders.

Job types define their own retry/backoff policies. Exhausted failures surface as plain-language health warnings/manual retry opportunities where appropriate, while detailed errors remain in logs.

## 37. Backups

Built-in backups support:

- manual creation;
- scheduled creation;
- configurable retention;
- database;
- media/uploads;
- documents;
- restoration-relevant configuration;
- local storage;
- optional S3-compatible external destination;
- optional encryption;
- archive/content integrity verification.

Off-host storage should be encouraged, not required.

Large shared-host backups must avoid single enormous web requests where practical.

## 38. Restore and disaster recovery

Normal admin supports guided restore of known valid backups through heavily guarded workflow. Separate recovery entry point supports cases where normal admin is broken. Recovery requires a strong recovery secret/token and clear destructive confirmation.

Do not treat archive creation alone as success; verify expected structure/content/checksums where appropriate.

## 39. Updates

Semantic Versioning: `MAJOR.MINOR.PATCH`.

Admins receive passive stable-channel update notices and concise release notes. Security releases are prominently flagged and recommended but never auto-installed. Security update always forces a fresh pre-update backup.

Before update, provide compatibility report and plain-English guidance for unsupported PHP/extensions/database/disk conditions.

Update flow:

1. fetch/check metadata;
2. compatibility preflight;
3. download/stage release;
4. verify release integrity/checksum/signature mechanism chosen by implementation;
5. create fresh backup;
6. enter maintenance mode (safe shared-host default);
7. apply application files;
8. run migrations;
9. rebuild caches/config as needed;
10. health check;
11. reopen on success;
12. rollback files/database to known-good backup on failure.

Database migrations are reversible where practical, but backup restore is ultimate rollback boundary.

More capable hosting may stage more work pre-maintenance to reduce downtime; shared-host simple safe window is default.

## 40. Maintenance mode

Group-branded maintenance page supports custom message, optional expected return time, optional contact route. Authorised admins have secure bypass for testing during maintenance/update/recovery.

## 41. System health

Admin health page reports plain-language status for platform version, PHP compatibility, database, writable storage, disk space where possible, email configuration/test, cron last-run, latest backup, HTTPS, and updates. Missing media references may appear in repair queue.

Serious issues can trigger persistent admin banner and admin email alerts; lower-priority notices stay on dashboard/health page.

Detailed logs remain server-side.

## 42. Staging

Staging uses same application code with separate database/uploads/secrets/analytics/email behaviour. Clearly display STAGING. Automatically emit `noindex`, suppress public sitemap/indexing, and warn if production SEO settings are attempted. Outgoing email is disabled, redirected, or trapped to prevent contacting real members.

## 43. Distribution and release packaging

The Git repository is source. A Shared Hosting Release ZIP is a generated product artifact.

Release package contains application code, production Composer dependencies, compiled frontend assets, migrations, setup/updater/recovery support, configuration template, storage skeleton, and version/build metadata.

Release package excludes `.git`, `.env`, source development dependencies where not needed at runtime, Node modules, tests, reports, IDE state, logs, backups, real data/uploads, and development-only reference assets/docs where not required by installer/admin help.

Also publish source/developer distribution.

Release build is reproducible and automated: clean workspace, locked dependency install, frontend build, full developer tests, accessibility/performance/visual checks, production tree assembly, package-content validation, package smoke install, ZIP creation, checksum generation, version metadata.

A broken/incomplete/leaky release package fails the release pipeline.

Machine-readable release metadata contains at least version, channel, build identifier, build time, minimum PHP, and schema/application compatibility information needed by updater.

## 44. Repository structure and hygiene

Repository layout should remain conventional enough for Laravel contributors while reflecting meaningful domains so large features do not become unstructured `Models`/`Controllers` dumps.

Target direction (refined by implementation plan rather than blindly precreated):

```text
app/
  Domain/
    Events/
    Gallery/
    Membership/
    Content/
    Governance/
    Operations/
  Http/
  Providers/
  Support/
resources/
  views/
    components/
    events/
    gallery/
    pages/
    news/
    account/
    layouts/
  css/
  js/
  lang/
database/
routes/
public/
storage/
tests/
docs/
scripts/
```

Rules:

- obvious home for every file;
- no generic miscellaneous dumping grounds;
- controllers thin;
- templates contain presentation, not business logic;
- domain operations reusable outside web controllers;
- repository map documented;
- lockfiles tracked;
- runtime/user/secrets/generated artifacts ignored;
- clean clone can reproduce developer environment from documentation;
- published release ZIP is sufficient for installation without repository/source dependencies.

## 45. Testing and release quality

All comprehensive testing is developer/release-side only. Deployed installations do not require PHPUnit/Pest, Playwright, Lighthouse, Node, or other test tooling.

Developer/release suite includes:

- backend unit/feature tests;
- permission/authorisation tests;
- validation tests;
- critical frontend interaction tests;
- end-to-end browser tests for major journeys;
- WCAG-oriented automated checks plus documented manual keyboard review where needed;
- responsive visual regression baselines;
- performance regression checks;
- MySQL/MariaDB test matrix;
- setup/install testing;
- shared-host release ZIP smoke testing;
- update/migration/rollback testing;
- backup/restore testing;
- PWA/mobile interaction checks;
- gallery upload/moderation tests.

Support current and previous major versions of Chrome, Edge, Firefox, and Safari, including corresponding mainstream mobile browsers. Use progressive enhancement rather than extensive legacy polyfills.

## 46. Import/export and portability

Admin portability export includes core structured data, configuration, documents/media, and machine-readable manifest sufficient to migrate/restore on another host.

Guided import framework supports CSV-style imports with field mapping, validation preview, duplicate detection, dry run, and reusable importer definitions. v1 ships only importers actually needed.

Existing NDWG content will be manually rebuilt/cleaned rather than scraped/imported wholesale.

Architecture provides connector/adaptor extension points so future Ramblers membership/booking integrations can support scheduled sync, but no public plugin system exists in v1.

## 47. Deployment lifecycle

NDWG launch strategy:

1. development;
2. independent staging/subdomain;
3. committee testing;
4. manual clean content entry;
5. fixes/final checks;
6. production domain cutover;
7. retain old site temporarily as operational fallback if desired, without running two competing public experiences.

Primary deployment route for normal installations is Shared Hosting Release ZIP + guided setup/update. Git/SSH deployment may be documented for advanced environments but is not required.

## 48. Product identity

The product is branded externally as **Waymark Community** and referred to internally in code, namespaces, repository conventions, and concise developer terminology as **Waymark**. NDWG remains the first/reference installation and is never the product name.

Public installations may show subtle optional **“Powered by Waymark Community”** attribution, while setup, admin, update, release, and documentation surfaces may use the Waymark Community product identity more clearly.

Naming conventions:

- public/product name: `Waymark Community`;
- internal/project name: `Waymark`;
- PHP root namespace: `Waymark` where a project-specific namespace is required;
- suggested repository name: `waymark`;
- shared-hosting release package: `waymark-community-<version>-shared-hosting.zip`;
- default PWA/app name: `Waymark Community`;
- installation-specific group branding remains dominant on the public site.

## 49. Success criteria for v1

v1 is successful when:

- NDWG can run the public site, walks, socials, holidays, galleries, admin, documents, committee information, accounts, and moderation without editing code;
- the public site visually matches the approved design language rather than framework defaults;
- a non-developer-oriented administrator can install from a release ZIP on supported shared hosting using the web setup flow;
- a new walk added in admin automatically appears in all relevant public views/homepage/calendar without hand-editing HTML;
- member photo uploads are event/album-linked, attributed, optimised, moderated, and publicly browsable;
- backups, restore, safe updates, and rollback are operationally credible;
- the repository is clear enough for a new developer to understand how domains, frontend, docs, tests, and release tooling fit together;
- a clean source clone reproduces a developer environment using documented steps;
- a published release ZIP contains everything needed for supported production installation without development tooling;
- accessibility/performance/security requirements are tested in release workflow;
- no out-of-scope management-suite features have crept into v1.
