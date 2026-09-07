# Waymark Resilient Walk Creation Design

**Status:** Design for independent review; implementation has not started.

**Baseline:** Post-v1.0.2 development branch containing the approved automatic Walk slug work through `229c263`.

## Purpose

Make the five-step Add Walk flow resilient to interruption and missing supporting data without changing Waymark's public event lifecycle or introducing a second Walk model.

The design has two user-facing outcomes:

- a private Walk draft is saved automatically once Step 1 has a valid title and start date/time; and
- authorised Walk creators can add a Grade or Tag from the Step 2 selectors without leaving the wizard.

This is an admin usability change only. It does not alter public pages, product versioning, release packaging, or the meaning of Draft, Pending Approval, or Published.

## Goals

- Persist a recoverable private draft at the first meaningful checkpoint.
- Save subsequent completed wizard steps to the same draft.
- Keep final submission deliberate and separate from draft persistence.
- Allow the draft to be resumed securely after navigation, refresh, or browser closure.
- Let authorised Walk creators create valid Grades and Tags inline.
- Preserve the approved server-generated Walk slug format and stability rules.
- Provide accessible, concise save and error feedback.
- Verify the feature proportionately with focused domain, Livewire, and policy tests.

## Non-goals

- Autosaving every field change or keystroke.
- A general revision history, collaborative editing, or conflict-resolution system.
- A second draft table or browser-local draft store.
- Changes to public Walk presentation or public event visibility rules.
- Broader access to grading configuration, Walk field settings, direct-publish settings, or existing Grade/Tag editing and deletion.
- Version changes, release qualification, release archives, tags, or publication.

## Existing Behaviour and Constraints

### Add Walk wizard

`App\Filament\Resources\WalkResource\Pages\CreateWalk` is a five-step Filament `CreateRecord` wizard. Its final submit calls `App\Domain\Walks\Actions\CreateWalk`; there is no earlier persistence checkpoint. Leaving the page before final submit loses server-unpersisted form state.

Filament 5 already provides the two framework seams this design needs:

- `Wizard\Step::afterValidation()` runs only after the current step validates and may halt navigation on failure; and
- `Select::createOptionForm()` plus `createOptionUsing()` opens an inline modal and selects the returned record key after successful creation.

No existing Waymark form implements automatic draft persistence or inline Select option creation.

### Current creation and submission coupling

`App\Domain\Walks\Actions\CreateWalk` currently:

1. creates an `Event` with Draft status and private publication fields;
2. creates its related `Walk` through `SaveWalkDetails`; then
3. immediately calls `SubmitWalkForPublication`.

The transaction covers initial Event/Walk persistence, but submission happens afterward. Calling this action at a wizard checkpoint would therefore publish directly or move the record to Pending Approval. It must not be used as the checkpoint operation in its current form.

`SubmitWalkForPublication` already contains the approved final transition: administrators and installations with direct publishing enabled publish immediately; other Walk Leader submissions become Pending Approval and remain private.

`UpdateWalk` updates Event and Walk fields without changing lifecycle status. When no slug is supplied it preserves the existing slug.

### Draft representation and discovery

The existing `events` and `walks` tables already represent an incomplete private draft. No schema change is required. A Walk row requires `primary_leader_id`, even though the current wizard does not collect that field until Step 5.

Drafts already appear in:

- the Filament Walk resource for administrators or the owning organiser; and
- the Leader Hub's Draft walks group.

Both surfaces already provide an authorised edit path. Public Walk queries require public publication fields and supported public statuses, so Draft and Pending Approval records remain unavailable through public list and detail routes.

### Grade and Tag permissions

Grade and Tag records are global installation data. Their current policies require `ManageEventConfiguration` for viewing, creating, updating, and deleting. The default Walk Leader role does not have that capability because it also protects broader configuration concerns.

The full Grading and Tags resources must remain configuration-only. The design grants only the ability to create a valid supporting record to an active, verified user who can both create Walks and manage their own or all Walks. It does not grant `ManageEventConfiguration`, list access, editing, deletion, or access to Walk field/direct-publish settings.

The configurable capability matrix currently permits `CreateWalks` without `ManageOwnWalks` or `ManageAllWalks`. Such a user can enter the existing create flow but cannot pass its final publish authorisation or later edit the resulting record. The resilient flow must not create an unusable orphan draft for that combination. Walk creation eligibility is therefore refined to require `CreateWalks` plus own/all-Walk management. This does not change the default Walk Leader or Administrator roles.

## Design Decision

Persist the initial and checkpoint data in the existing Event/Walk aggregate through a dedicated `SaveWalkDraft` action. Keep the Add Walk page in a draft-aware wizard mode for the active flow, with the draft identifier and current step represented in the authenticated admin URL. Use the same draft record when the user advances, saves again, refreshes, or resumes from the Walks area.

The alternatives were rejected for these reasons:

- Redirecting to the existing flat Edit Walk page immediately after Step 1 would persist safely but abandon the approved five-step creation experience at the first checkpoint.
- Browser local storage would not create the required Waymark Draft, would be tied to one browser, and would introduce a second persistence model.
- Calling `CreateWalk` at every checkpoint would trigger Pending Approval or Published transitions and could create duplicate records.

## User Flow

### 1. Start and Step 1

The user opens Add Walk and completes When and where. Title and start date/time remain required. Other Step 1 fields retain their existing optionality and validation.

When the user activates Next:

1. Filament validates Step 1.
2. `SaveWalkDraft` creates the Event and Walk in one transaction.
3. The Event receives Draft status, `is_public = false`, and `published_at = null`.
4. The organiser and initial primary leader are the current user. The later Leader and publishing step may replace the primary leader using the existing eligibility rules.
5. The approved unique slug is generated from the submitted Step 1 title and Walk date.
6. The page stores the returned Walk identifier in its authenticated resume URL and advances to Step 2.
7. A quiet `Draft saved` status is announced and displayed.

No record is created while title or start date/time is absent or invalid.

### 2. Step 2 and supporting data

The Grade and Tags selectors retain their current sorting, search, nullable status, and multi-select behaviour for Tags.

If no Grade exists, the selector presents `No grades yet — create one here.` Its create action opens an inline Filament modal containing:

- Grade name, required and unique;
- practical description, required;
- optional accent colour.

Display order is assigned automatically after the current highest order in the same creation action, avoiding a technical ordering field in the volunteer-facing modal. Administrators retain the existing full resource form when they need explicit ordering control.

If no Tag exists, the selector presents `No tags yet — create one here.` The inline modal contains the existing required, unique, maximum-length Name field.

After successful creation Filament receives the new model key and selects it immediately. A newly created Tag is added to, rather than replacing, the existing multi-selection.

Advancing from Step 2 validates its fields and updates the existing draft. It never creates another Event or Walk.

### 3. Later checkpoints

Advancing from Steps 3 and 4 saves the validated fields belonging to that step to the same draft. Returning to an earlier step and advancing again updates that draft.

Checkpoint calls pass only fields from steps reached and validated so far. Unvisited fields are not submitted as null and cannot erase an existing value. `UpdateWalk`'s existing merge behaviour remains the basis for preserving stored Walk details.

There is no per-keystroke save. The status indicates the last successful checkpoint, so a user leaving midway through a step understands that only the previous checkpoint is durable. An additional Save draft button is not part of this first implementation; it can be considered separately if real use shows that step-boundary saves are insufficient.

### 4. Leave, refresh, and resume

After initial persistence, the authenticated URL carries the draft identifier and Filament's current-step query value. Refreshing the page reloads that draft, authorises access, fills the shared Walk form state, and restores the accessible wizard step.

The Walk resource and Leader Hub continue to show the record as Draft. Their action for an incomplete Draft is labelled `Continue draft` and opens the draft-aware Add Walk wizard. Non-Draft records continue to use the existing Edit Walk page.

The resume endpoint must:

- load through the Walk resource's ownership-scoped query;
- authorise update access again on every request;
- accept only records whose Event status is Draft;
- return not found or forbidden without revealing another organiser's draft; and
- never infer a draft from title/date, because two legitimate Walks may share those values.

The page keeps one server-issued draft identifier after creation. Next is disabled while its request is active, and every later checkpoint resolves that identifier before saving. Repeated navigation requests therefore update the same aggregate rather than creating duplicates.

### 5. Final submission

The final action is labelled `Submit walk`, not Save draft. It validates the complete form, updates the existing draft once, and then deliberately invokes the existing submission rules.

- With direct publishing enabled, or for an administrator with `ManageAllWalks`, the Event becomes Published and public through `PublishEvent`.
- Otherwise it becomes Pending Approval and remains private.

If final validation fails, the record remains Draft. If the final persistence or transition fails, the operation rolls back to the last successfully saved checkpoint and shows a concise error without claiming submission succeeded.

After success, the draft identifier is cleared and the user is redirected through the existing resource flow.

## Domain Lifecycle

| Operation | Status after operation | Public | Submission invoked |
|---|---|---:|---:|
| Valid Step 1 checkpoint | Draft | No | No |
| Step 2-4 checkpoint | Draft | No | No |
| Resume or ordinary draft edit | Draft | No | No |
| Final submit, approval required | Pending Approval | No | Yes |
| Final submit, direct publish allowed | Published | Yes | Yes |

`SaveWalkDraft` refuses to checkpoint a Pending Approval, Published, Changed, Postponed, Cancelled, Completed, or Archived Event. Those records remain under the existing Edit Walk and status workflows.

## Component Boundaries

### `SaveWalkDraft`

A new focused domain action owns create-or-update draft persistence.

- Initial save authorises coherent Walk creation (`CreateWalks` plus own/all-Walk management), validates the minimum identity, generates the slug, creates the private Draft Event, and creates the Walk with the actor as the initial primary leader.
- Subsequent saves require the existing update permission, verify the record is still Draft, preserve its slug, and update only supplied fields.
- The action is transactional and never calls `SubmitWalkForPublication`, `PublishEvent`, or `ChangeEventStatus` toward a submitted state.
- Existing `SaveWalkDetails` and leader/field validation remain the persistence boundary for Walk-specific data.

### `CreateWalk`

`CreateWalk` remains the deliberate completed-creation orchestration boundary for existing callers and tests. It is refactored to compose `SaveWalkDraft` with `SubmitWalkForPublication`, optionally receiving the wizard's already-persisted Draft. This preserves one-shot creation while preventing the wizard from invoking final submission at checkpoints.

The final draft update and lifecycle transition execute in one outer transaction. A failure does not leave a half-submitted record.

### Slug generation

The approved slug algorithm moves from the private method on `CreateWalk` to a focused generator used when `SaveWalkDraft` first persists the Event. Its behaviour remains:

`{slugified-title}-{YYYY-MM-DD}{optional-numeric-suffix}`

The generator preserves the complete date and collision suffix within 255 characters. Once the draft exists, automatic checkpoint and final-submit updates omit `slug`, so title/date changes do not silently change it. Existing deliberate slug editing on the authorised Edit Walk form remains unchanged.

### Wizard page and form mapping

The existing `CreateWalk` Filament page gains:

- draft-aware mount/hydration;
- step `afterValidation` checkpoint callbacks;
- an authorised resume identifier in the URL;
- persisted current-step query state;
- a shared method that maps stored Event/Walk values back into form state; and
- accessible saved/error status presentation.

Form component definitions remain shared through `WalkResource::formComponents()`. The Edit Walk page continues to use the same fields and `UpdateWalk` action.

### Grade and Tag creation

Introduce focused Grade and Tag creation actions so standalone resource creation and inline creation use the same validation and model persistence. The Grade action accepts an explicit administrator-provided display order or assigns the next order for inline creation. Database uniqueness remains the final concurrency guard.

`GradeResource` and `TagResource` expose reusable creation field definitions for their full pages and inline modals rather than duplicating rules in the Walk form.

The Grade and Tag policies broaden only their create decision to the narrowly defined Walk-creator case. View/list, update, delete, and global configuration permissions remain unchanged.

## Validation

### Initial draft

The initial checkpoint requires:

- an active, verified, authorised Walk creator;
- a non-empty title no longer than 255 characters; and
- a valid start date/time.

Optional Step 1 values are validated when supplied. The action supplies the actor as organiser and initial primary leader to satisfy the existing aggregate and database invariant.

### Later checkpoints

Each Next action validates its current step before saving. Existing `WalkDetailsData` rules continue to govern leaders, Grade/Tag keys, measurements, coordinates, travel details, attachments, and other Walk fields. Validation for unvisited steps remains deferred.

### Final submission

The final action validates all wizard fields, including the required primary leader and all existing cross-field rules, before it requests a status transition. Grade and Tags remain optional because the current product model and approved Walk specification define them as configurable metadata rather than mandatory publication fields.

## Permissions

| Operation | Required authority |
|---|---|
| Start automatic draft | Walk create policy refined to require `CreateWalks` plus `ManageOwnWalks`/`ManageAllWalks` |
| Update/resume draft | Existing Walk update policy and ownership scope, or `ManageAllWalks` |
| Submit draft | Existing Walk publish policy |
| Create Grade/Tag inline | `ManageEventConfiguration`, or active verified user with `CreateWalks` plus `ManageOwnWalks`/`ManageAllWalks` |
| List/edit/delete Grade/Tag | Existing `ManageEventConfiguration` requirement |
| Change Walk field/direct-publish configuration | Existing `ManageEventConfiguration` requirement |

This intentionally lets default Walk Leaders and administrators create the small global records needed to complete a Walk without granting broader configuration access. A custom role with only `CreateWalks` but no own/all-Walk management does not receive supporting-data creation or draft-resume authority.

## Messaging and Accessibility

The implementation retains Waymark's WCAG 2.2 AA target.

- Successful persistence displays `Draft saved` in a persistent, visually restrained status region near the wizard controls.
- The status uses `aria-live="polite"` and text, not colour alone.
- A failed save keeps the current step and entered values, moves focus to a concise error summary or first invalid field, and announces `We couldn't save your draft. Your entries are still on this page. Try again.`
- Inline modal headings and controls use explicit Grade/Tag labels and existing field-level validation messages.
- Filament's modal focus trap and focus restoration are retained and verified by test; keyboard users return to the originating selector after create or cancel.
- The newly selected option is available to assistive technology through the selector's normal value announcement.
- Empty-state text is concise and paired with an actual create action; it is not expressed by colour or icon alone.

## Error Handling and Consistency

- Draft create/update is atomic across Event, Walk, co-leaders, and Tags.
- The draft identifier is assigned to page state only after a successful transaction.
- Failed checkpoint persistence prevents step advancement and does not invoke submission.
- Every checkpoint reloads and authorises the draft; stale, deleted, non-Draft, or inaccessible identifiers are rejected.
- Inline Grade/Tag validation failures stay in the modal and create no option.
- The new option is selected only after successful persistence.
- Unique Grade/Tag races surface as field-level duplicate errors where possible, with database unique constraints remaining authoritative.
- Final submission failure leaves a private Draft at its last successful checkpoint.

## Focused Testing Strategy

Normal implementation verification is intentionally narrower than release qualification.

### Domain and feature tests

- Valid Step 1 creates one Event/Walk with Draft status, private publication fields, organiser ownership, actor as initial primary leader, and the approved generated slug.
- Missing or invalid title/start creates no record.
- Saving later checkpoints updates the same draft and preserves data from other steps.
- Draft checkpointing never calls or produces Pending Approval/Published behaviour.
- Title/date edits after initial persistence preserve the slug; existing collision and 255-character boundary tests remain green.
- Final deliberate submission produces Pending Approval when direct publishing is disabled and Published when enabled/authorised.
- Failed final submission retains the previous Draft state.
- Resume rejects another organiser's, missing, and non-Draft records.
- The Walk resource and Leader Hub expose the authorised draft continuation route.

### Livewire/Filament tests

- Advancing valid Step 1 creates the draft and exposes the saved status.
- Repeated step advances update one record rather than creating duplicates.
- Refresh/resume hydrates saved values and the persisted step.
- Grade inline creation validates, persists, refreshes options, and selects the record.
- Tag inline creation validates, preserves existing selections, and selects the new record.
- Empty states expose their create affordances.
- A default Walk Leader and administrator can create supporting records; a user without Walk creation/management authority cannot.
- Walk Leaders remain unable to list, edit, delete, or otherwise manage global Grade/Tag configuration.

### Browser coverage

Use one targeted browser test only if Livewire/Filament tests cannot prove focus restoration, live-status announcement, or URL-backed refresh behaviour. Do not require the full Playwright suite for ordinary implementation.

### Lightweight implementation gate

Run only affected PHP test files, any single justified browser test, Pint and PHP syntax checks for changed PHP, and `git diff --check`. The full PHP database matrix, full Playwright, installer, package, PWA, staging, performance, and release suites remain deferred to future release qualification.

## Compatibility and Rollout

- No database migration is required.
- Existing Draft records remain valid and resumable through the existing edit path.
- Existing one-shot `CreateWalk` callers retain their final submission behaviour.
- Existing public visibility queries and event status values do not change.
- Existing manual slug edits and automatic redirect behaviour do not change.
- The work remains unreleased on the post-v1.0.2 development line until separately planned, implemented, reviewed, and qualified for a future patch release.
