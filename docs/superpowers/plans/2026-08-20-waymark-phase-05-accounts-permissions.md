# Waymark Phase 5 — Accounts, Permissions and Leaders Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Deliver lightweight community accounts and volunteer/admin permissions without turning Waymark into a membership-management system.

**Architecture:** Account identity, membership verification metadata, roles and module capabilities remain separate concepts. Public profile exposure is intentionally minimal.

**Spec:** `docs/superpowers/specs/2026-08-20-waymark-community-design.md`

---

### Task 1: Public registration, email verification and lightweight onboarding
- Implement custom Blade registration/login/reset/verify screens using first-party auth backend.
- Allow account login before verification but gate meaningful identity actions per spec.
- Add concise New Here onboarding with three-walk policy as information only.
- Test no attendance records/counters exist.
- Commit.

### Task 2: Profile and communication preferences
- Add internal real name, public display name, optional phone/photo and small communication-preference set.
- Default public attribution format to first name + surname initial when no explicit display name exists.
- No public member directory route/query.
- Commit.

### Task 3: Roles and configurable module permissions
- Implement fixed role vocabulary with configurable capabilities per module.
- Evaluate and use a mature permission package only if it reduces complexity without violating repository rules; otherwise implement focused tables/policies.
- Test Registered User, Verified Member, Walk Leader, Moderator and Administrator defaults.
- Test module-scoped moderator access.
- Commit.

### Task 4: Ramblers verification metadata
- Add admin-only verification action recording verifier, timestamp, source/method and optional review date.
- No user “request verification” UI.
- Add review-date dashboard flag without automatic expiry.
- Commit.

### Task 5: Walk leader public profile and Leader Hub
- Minimal optional public leader profile with display name/photo/intro/upcoming walks.
- Private Leader Hub surfaces own drafts/upcoming/past walks, duplication shortcut, private notes and later photo moderation seam.
- Do not create member directory.
- Commit.

### Task 6: Favourites
- Add polymorphic or intentionally scoped favourite records for approved public content types.
- No reminder notifications in v1.
- Test private ownership and deletion behaviour.
- Commit.

### Task 7: Optional 2FA and sensitive re-authentication
- Enable optional 2FA using the selected first-party auth foundation.
- Sensitive admin actions require recent password confirmation; require fresh 2FA when enabled for that user.
- Keep password recovery otherwise simple.
- Commit.

### Task 8: Installation owner and admin promotion
- Mark initial admin as installation owner.
- Add explicit ownership transfer with re-auth, audit hook and email confirmation.
- Admin promotion/creation is high-risk: re-auth, audit hook, notify existing admins.
- No immortal super-admin.
- Commit.

### Task 9: User data export/deletion/inactivity
- Implement self-service data export request producing a temporary authenticated download asynchronously/cron-fallback friendly.
- Implement deletion request reviewed by admin, with deactivation/anonymisation preserving historical integrity.
- Flag stale accounts for review; no automatic deletion.
- Commit.

## Phase 5 Gate
Community accounts are useful but lightweight; no directory/attendance/payment/member-management creep; privileged access is policy-tested; leaders have exactly the agreed ownership surface.
