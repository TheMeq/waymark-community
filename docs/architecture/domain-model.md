# Domain Model Direction

This document records conceptual entities, not final table names. The implementation plan will lock exact schema and interfaces.

## Identity / installation

- Group/SiteSettings
- User
- Role/Permission configuration
- MembershipVerification
- CommunicationPreference
- Favourite
- ConsentRecord

## Events

- Event (shared base concepts)
- Walk
- Social
- Holiday
- RecurringSeries
- EventUpdate
- EventAttachment
- EventTag
- DifficultyGrade
- Location

Holiday may parent child Walk/Social events.

New walks, holidays and socials generate slugs from their title and start date
using the shared event slug generator. Numeric suffixes avoid collisions across
all event types while keeping the complete date within the 255-character limit.
Holiday and social creation forms do not ask for a slug; existing explicit
slugs remain supported by their domain actions, and editing a title or date
does not automatically change an existing URL.

### Walk publication and removal

Walk owners with Manage Own Walks, and administrators with Manage All Walks,
can unpublish or delete a walk after confirmation. Unpublish clears publication
fields and returns the event to Draft while preserving its details and media.
Delete soft-deletes the Walk and makes its retained event private and Archived
in one transaction. Public pages, calendars, galleries and normal admin lists
exclude the deleted walk. Photos, files and event history are retained; managed
featured images remain referenced for cleanup protection. No permanent purge
or restore interface is exposed by these actions.

## Gallery/media

- Gallery/Album context
- Photo
- PhotoModerationDecision
- PhotoReportRemovalRequest
- MediaAsset (admin-managed site media)
- FeaturedPhoto relationship

Every community Photo has an authenticated uploader and event/album context.

## Content

- CmsPage
- CmsBlock
- NewsPost
- NewsCategory
- Testimonial
- HomepageSectionConfiguration
- AnnouncementBanner
- Redirect

## Governance

- Document
- DocumentVersion
- DocumentCategory
- CommitteeRoleAssignment
- CommitteeMeeting

## Operations

- AuditEntry
- BackupRecord
- UpdateRecord
- ImportRun
- ExportRun
- Health state derived from system checks

## Important modelling constraints

- No attendance entity in v1.
- No booking/payment aggregate in v1.
- No member-directory/public social graph.
- Verification is metadata controlled by authorised admins, not a user-request workflow.
- Gallery photos are never context-free.
- Publication/visibility and lifecycle status are distinct concerns.
