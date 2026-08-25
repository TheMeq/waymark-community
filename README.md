# Waymark Community

Walking groups run on people giving their time: finding good routes, leading walks, organising weekends away, keeping everyone informed and collecting the photographs afterwards. The website should make that work easier, not become another job in its own right.

Waymark Community gives a walking group one place for its walks, socials, trips, news, photographs and useful information. It is open source and self-hosted, so the group keeps control of its website and data. Each installation belongs to one group and can carry that group's own name, colours, images and personality.

Day-to-day administration is for ordinary group volunteers. Committee members and walk leaders can keep the site current without writing code or understanding the technology behind it.

## Why Waymark?

Group information has a habit of spreading. The website holds some of it, while the rest lives in spreadsheets, email chains, social media posts, photo folders and documents that only one committee member can find.

Waymark brings the useful parts together. It gives the public a clear place to discover the group and see what is coming up, while giving authorised volunteers the tools they need behind the scenes. It does not try to become a social network, booking service or corporate membership system.

## What can your group do with it?

### Share walks and events

Publish walks with distance, ascent, difficulty, leaders, meeting details and updates. Locations can include mapping information and a GPX route where one is available. Social events and multi-day holidays sit alongside walks in a combined **What's On** list and calendar, so visitors do not need to hunt through several parts of the site.

Walk leaders have their own focused area for creating and looking after their walks. Groups can choose whether leaders publish directly or submit work through the permissions available to their role.

### Keep photographs and memories together

Members can contribute photographs to the walk, social or holiday they belong to. Moderation, captions, attribution, albums and featured photographs help turn a folder of uploads into a useful record of the group's time together. Holiday pages can bring their child events and photographs into one shared story.

### Keep the website useful

Volunteers can publish news, maintain ordinary website pages, organise public and restricted documents, update committee resources and change the homepage, navigation and branding. Contact forms, outbound newsletters and site-wide search help people find information and keep in touch without turning Waymark into a mailbox or marketing platform.

Member accounts support favourites, community photographs and access to the right leader or committee resources. They are intentionally lighter than a full membership-management system.

### Look after the site

Accessibility, privacy controls, search metadata and an installable progressive web app are part of the public experience. For administrators, Waymark includes System Health checks, backups, recovery tools and signed updates with staging and rollback safeguards.

## Built for volunteers

The public website and the administration area have different jobs. Visitors get a clear, welcoming site for the group; authorised volunteers get practical forms and workflows for keeping it up to date.

A committee member should not need to know PHP, Blade, Filament, Composer or Node to publish a page, add a walk or moderate photographs. Developing Waymark itself does require technical knowledge, but running the website from day to day should not.

## Self-hosting and Shared Hosting Release ZIPs

Waymark deliberately supports ordinary PHP shared hosting. The preferred package keeps the application outside the public web root and points the domain at `application/public/`. A second package can be extracted directly into `public_html`, `htdocs` or `www` when the host does not allow the document root to be changed.

Runtime requirements are PHP 8.3 or newer with the documented extensions, MySQL 8.4 or MariaDB 11.4, writable storage and a web server. Production packages already contain the PHP dependencies and compiled frontend assets, so the hosting account does not need Git, Composer, Node.js, npm, Docker, Redis or a permanent queue worker.

Start with the [shared-hosting installation guide](docs/deployment/shared-hosting.md). The [release packaging guide](docs/deployment/release-packaging.md) explains the two layouts and how their archives are produced and checked.

## Project status

Waymark Community `v1.0.0` remains the current stable public release. The `v1.0.1` shared-host installer maintenance candidate is being prepared for independent acceptance and has not yet been published. Existing `v1.0.0` tags, packages and release history remain immutable.

## Source/developer checkout

Build requirements are Git, PHP 8.3 or 8.4 with Composer 2, Node.js 24 and npm 11. SQLite supports the fast local test suite; release-affecting work is also checked against MySQL 8.4 and MariaDB 11.4.

```shell
composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate
npm ci
npm run build
composer test
composer verify:repository
npm run test:tooling
```

PowerShell users can replace `cp` with `Copy-Item`. See [developer setup](docs/development/setup.md) and [developer and release testing](docs/development/testing.md) for the full environment and test matrix.

The locked baseline currently resolves Laravel 13.26.1, Filament 5.7.6 and Fortify 1.38.0 against the minimum PHP 8.3.0 platform.

## Repository map

- `app/Domain/` contains the application's account, content, event, gallery, governance and operational behaviour.
- `app/Filament/` contains administration resources and pages; it does not define the public visual style.
- `app/Http/` contains requests, middleware and thin controllers.
- `config/`, `database/` and `routes/` hold runtime configuration, database schema and delivery registration.
- `resources/` contains Blade views and the source CSS and JavaScript for the public site.
- `tests/` contains PHP, browser, accessibility, visual, integration and release-tooling tests.
- `scripts/` contains repository, performance and release-package tooling.
- `docs/` contains architecture, design, development, deployment and project-planning material.

## Development documentation

Contributors changing behaviour should read [`AGENTS.md`](AGENTS.md), the [product specification](docs/superpowers/specs/2026-08-20-waymark-community-design.md), the [architecture notes](docs/architecture/overview.md) and the relevant development documentation. These files preserve the product boundaries, shared-hosting commitments, accessibility target and approved public visual direction.

## Getting involved

Useful contributions come in many sizes. Bug reports, accessibility findings, clearer documentation, usability improvements and ideas grounded in real walking-group experience are all welcome. If you would like to help, read [CONTRIBUTING.md](CONTRIBUTING.md) for a straightforward guide to getting started.
