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
