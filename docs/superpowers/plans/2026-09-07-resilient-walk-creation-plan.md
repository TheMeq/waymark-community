# Resilient Walk Creation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the five-step Add Walk wizard save and resume one private Walk draft while allowing narrowly authorised inline Grade and Tag creation, without changing public lifecycle semantics.

**Architecture:** `SaveWalkDraft` owns transactional private-draft creation and checkpoint updates in the existing Event/Walk aggregate. `CreateWalk` composes that draft persistence with the existing deliberate submission action, while the Filament create page carries a server-issued draft ID and wizard step in its authenticated URL and re-authorises the draft on every request.

**Tech Stack:** PHP 8.3+, Laravel 13, Eloquent, Livewire 4, Filament 5.7, Blade/Alpine, PHPUnit 12, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-07-resilient-walk-creation-design.md`

## Global Constraints

- Work on `codex/add-walk-auto-slug` after specification commit `38aa7c2a934d829a1f0327362447af381a39bfcc`.
- Do not add a draft table, browser-local draft store, migration, public-page change, version change, release artifact, or release tag.
- Stop and report if implementation discovery shows the existing Event/Walk aggregate cannot represent the approved draft safely.
- A checkpoint may leave the Event only in `EventStatus::Draft`, with `is_public = false` and `published_at = null`; it must never call submission or publication actions.
- Initial persistence assigns the actor as organiser and temporary primary leader. Later validated form data may replace the primary leader through existing eligibility rules.
- Once generated, automatic checkpoint and final-submit updates omit `slug`; title/date edits do not regenerate it. Existing authorised manual slug editing remains on Edit Walk.
- Resume always loads through `WalkResource::getEloquentQuery()`, re-authorises `update`, and accepts only Draft records.
- Inline Grade/Tag creation does not grant resource listing, editing, deletion, field-setting, or direct-publish configuration access.
- Each behaviour change follows red-green-refactor: write the smallest real-behaviour test, observe the expected failure, implement only enough to pass, rerun the focused tests, then commit.
- Ordinary implementation verification is limited to affected PHP/Livewire tests, one justified desktop browser test, Pint and `php -l` for changed PHP, and `git diff --check`. Release qualification is deferred.

## File and Interface Map

- `app/Domain/Walks/Actions/GenerateWalkSlug.php` — generates the approved unique slug for first persistence only.
- `app/Domain/Walks/Actions/SaveWalkDraft.php` — creates or updates one authorised private Draft without lifecycle submission.
- `app/Domain/Walks/Actions/CreateWalk.php` — retains one-shot creation and accepts an optional existing Draft for final submission.
- `app/Domain/Walks/Actions/CreateGrade.php` and `CreateTag.php` — validate, authorise, and persist supporting records for both full resources and inline selectors.
- `app/Filament/Resources/WalkResource/Support/WalkFormData.php` — maps a stored Event/Walk aggregate into shared Filament form state.
- `app/Filament/Resources/WalkResource/Pages/CreateWalk.php` — owns draft URL state, checkpoint callbacks, resume hydration, feedback, and final-submit presentation.
- `resources/views/filament/walks/draft-save-status.blade.php` — persistent live status and focused error summary.
- Existing policies/resources/pages are modified narrowly; no new route or migration is needed.

---

### Task 1: Extract the reusable Walk slug generator

**Files:**
- Create: `app/Domain/Walks/Actions/GenerateWalkSlug.php`
- Create: `tests/Feature/Walks/WalkSlugGeneratorTest.php`
- Modify: `app/Domain/Walks/Actions/CreateWalk.php`
- Verify unchanged integration coverage: `tests/Feature/Walks/WalkCreationSlugTest.php`

**Interfaces:**
- Consumes: `Event::query()` and a title plus Carbon-compatible start value.
- Produces: `GenerateWalkSlug::handle(string $title, mixed $startsAt): string`.
- Preserves: `{slugified-title}-{YYYY-MM-DD}{optional-numeric-suffix}`, the complete date/suffix, fallback title `walk`, uniqueness, and a 255-character maximum.

- [ ] **Step 1: Write the failing generator tests**

Create `WalkSlugGeneratorTest` with real Event rows and these assertions:

```php
public function test_it_generates_the_approved_walk_slug_and_increments_collisions(): void
{
    $actor = User::factory()->create();
    $generator = app(GenerateWalkSlug::class);

    $first = $generator->handle('Reservoir Circuit', '2026-09-12 09:30:00');
    Event::factory()->for($actor, 'organiser')->create(['slug' => $first]);
    $second = $generator->handle('Reservoir Circuit', '2026-09-12 09:30:00');

    $this->assertSame('reservoir-circuit-2026-09-12', $first);
    $this->assertSame('reservoir-circuit-2026-09-12-2', $second);
}

public function test_long_collision_slug_preserves_the_complete_date_and_suffix(): void
{
    $actor = User::factory()->create();
    $generator = app(GenerateWalkSlug::class);
    $title = str_repeat('Boundary ', 40);
    $first = $generator->handle($title, '2026-09-12 09:30:00');
    Event::factory()->for($actor, 'organiser')->create(['slug' => $first]);
    $second = $generator->handle($title, '2026-09-12 09:30:00');

    $this->assertSame(255, strlen($first));
    $this->assertStringEndsWith('-2026-09-12-2', $second);
    $this->assertLessThanOrEqual(255, strlen($second));
}
```

- [ ] **Step 2: Run the new test and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkSlugGeneratorTest.php`

Expected: FAIL because `App\Domain\Walks\Actions\GenerateWalkSlug` does not exist.

- [ ] **Step 3: Move the existing algorithm without changing it**

Implement the extracted action with the exact collision loop already accepted in `CreateWalk`:

```php
final class GenerateWalkSlug
{
    public function handle(string $title, mixed $startsAt): string
    {
        $date = $startsAt instanceof CarbonInterface
            ? $startsAt
            : CarbonImmutable::parse((string) $startsAt);
        $dateSuffix = '-'.$date->format('Y-m-d');
        $titleSlug = Str::slug($title) ?: 'walk';
        $slug = Str::limit($titleSlug, 255 - strlen($dateSuffix), '').$dateSuffix;

        for ($suffix = 2; Event::query()->where('slug', $slug)->exists(); $suffix++) {
            $suffixText = '-'.$suffix;
            $slug = Str::limit($titleSlug, 255 - strlen($dateSuffix) - strlen($suffixText), '').$dateSuffix.$suffixText;
        }

        return $slug;
    }
}
```

Inject it into `CreateWalk`, replace `$this->uniqueSlug(...)` with `$this->generateWalkSlug->handle(...)`, and remove only the former private method and its now-unused imports.

- [ ] **Step 4: Run focused slug coverage and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkSlugGeneratorTest.php tests/Feature/Walks/WalkCreationSlugTest.php`

Expected: both files pass, including the collision boundary and edit-stability tests.

- [ ] **Step 5: Commit the extraction**

```bash
git add app/Domain/Walks/Actions/GenerateWalkSlug.php app/Domain/Walks/Actions/CreateWalk.php tests/Feature/Walks/WalkSlugGeneratorTest.php
git commit -m "refactor(walks): extract walk slug generation"
```

### Task 2: Create the first private Draft through `SaveWalkDraft`

**Files:**
- Create: `app/Domain/Walks/Actions/SaveWalkDraft.php`
- Create: `tests/Feature/Walks/SaveWalkDraftTest.php`
- Modify: `app/Policies/WalkPolicy.php`

**Interfaces:**
- Consumes: `GenerateWalkSlug::handle()`, `SaveWalkDetails::handle()`, and `Gate::forUser($actor)->authorize('create', Walk::class)`.
- Produces: `SaveWalkDraft::create(User $actor, array $attributes): Walk`.
- Initial input: required `title` and `starts_at`; optional Step 1 Event/location fields; any caller-supplied organiser, publication state, slug, or primary leader is ignored.
- Activity invariant: `User::hasCapability()` already returns `false` before role or legacy-capability lookup whenever `User::isActive()` is false. The policy must reuse that fail-closed capability seam rather than duplicate account-status logic.
- Verification invariant: every draft creator, including an Administrator with `ManageAllWalks`, must have a verified email. Remove the current `ManageAllWalks` verification bypass; there is no repository-wide administrator convention requiring it, and `User::canAccessPanel()` already requires verified email for all admin-panel users.

- [ ] **Step 1: Write failing tests for initial persistence and coherent authority**

In `SaveWalkDraftTest`, cover:

```php
public function test_valid_step_one_creates_one_private_actor_owned_draft(): void
{
    $leader = User::factory()->walkLeader()->create();

    $walk = app(SaveWalkDraft::class)->create($leader, [
        'title' => 'Reservoir circuit',
        'starts_at' => '2026-09-12 09:30:00',
        'meeting_location_name' => 'North gate',
        'primary_leader_id' => User::factory()->walkLeader()->create()->id,
        'is_public' => true,
    ]);

    $this->assertSame(EventStatus::Draft, $walk->event->status);
    $this->assertFalse($walk->event->is_public);
    $this->assertNull($walk->event->published_at);
    $this->assertSame($leader->id, $walk->event->organiser_id);
    $this->assertSame($leader->id, $walk->primary_leader_id);
    $this->assertSame('reservoir-circuit-2026-09-12', $walk->event->slug);
    $this->assertSame('North gate', $walk->meeting_location_name);
    $this->assertDatabaseCount('events', 1);
    $this->assertDatabaseCount('walks', 1);
}
```

Add data-driven cases proving blank/overlong title and missing/invalid start time throw `ValidationException` and leave both tables empty. Configure a role with `CreateWalks` but neither `ManageOwnWalks` nor `ManageAllWalks`, assert `WalkPolicy::create()` is false, and assert `SaveWalkDraft::create()` throws `AuthorizationException`. Add distinct regressions proving: a suspended/disabled user receives no effective Walk capabilities through `hasCapability()` and cannot create a draft; an unverified Walk Leader cannot create a draft; and an unverified Administrator with `ManageAllWalks` cannot create a draft.

- [ ] **Step 2: Run the draft test and verify RED**

Run: `php artisan test tests/Feature/Walks/SaveWalkDraftTest.php`

Expected: FAIL because `SaveWalkDraft` is absent and the current Walk create policy accepts the create-only capability combination.

- [ ] **Step 3: Refine creation authority and implement atomic initial persistence**

Change `WalkPolicy::create()` to require verified email plus coherent effective capabilities. Activity is enforced by the existing `User::hasCapability()` fail-closed check, so do not add another account-status comparison in the policy:

```php
// User::hasCapability() returns false for every inactive account.
return $user->hasVerifiedEmail()
    && $user->hasCapability(ModuleCapability::CreateWalks)
    && (
        $user->hasCapability(ModuleCapability::ManageOwnWalks)
        || $user->hasCapability(ModuleCapability::ManageAllWalks)
    );
```

The leading email-verification condition intentionally applies to Administrators as well as Walk Leaders. `ManageAllWalks` grants management breadth only; it does not bypass identity verification.

Implement `SaveWalkDraft::create()` as one `DB::transaction()`. Apply these exact rules: `title => required|string|max:255`; `starts_at => required|date`; `ends_at => nullable|date|after:starts_at`; `meeting_location_name => nullable|string|max:255`; `meeting_address => nullable|string|max:5000`; `meeting_postcode => nullable|string|max:32`; paired latitude/longitude numeric range rules matching `WalkDetailsData`; and nullable `what3words`/`os_grid_reference` strings capped at 255. Create the Event with fixed Walk/Draft/private fields and a generated slug, then call `SaveWalkDetails` with only validated Step 1 details plus `primary_leader_id => $actor->id`.

The action must not inject or call `SubmitWalkForPublication`, `PublishEvent`, or `ChangeEventStatus`.

- [ ] **Step 4: Run focused policy/draft tests and verify GREEN**

Run: `php artisan test tests/Feature/Walks/SaveWalkDraftTest.php tests/Feature/Walks/WalkAdministrationTest.php --filter='create|access'`

Expected: initial draft and access-policy cases pass; active verified default Walk Leaders and Administrators retain create access; inactive users and unverified users, including unverified Administrators, are denied.

- [ ] **Step 5: Commit initial draft persistence**

```bash
git add app/Domain/Walks/Actions/SaveWalkDraft.php app/Policies/WalkPolicy.php tests/Feature/Walks/SaveWalkDraftTest.php
git commit -m "feat(walks): save initial private drafts"
```

### Task 3: Update only the same authorised Draft at later checkpoints

**Files:**
- Modify: `app/Domain/Walks/Actions/SaveWalkDraft.php`
- Modify: `tests/Feature/Walks/SaveWalkDraftTest.php`
- Reuse unchanged: `app/Domain/Walks/Actions/UpdateWalk.php`

**Interfaces:**
- Consumes: `UpdateWalk::handle(Walk $walk, User $actor, array $attributes): Walk`.
- Produces: `SaveWalkDraft::update(Walk $draft, User $actor, array $attributes): Walk`.
- Guarantees: record lock, update authorisation, Draft-only status, supplied-field merge, stable slug, and one Event/Walk aggregate.

- [ ] **Step 1: Write failing update/lifecycle tests**

Extend `SaveWalkDraftTest` with tests that:

- create a draft, save Step 2 grade/tag/distance values, then save Step 3 travel values;
- assert the same Event and Walk IDs remain, table counts stay at one, Step 2 data survives Step 3, and tag/co-leader relationships are not erased when omitted;
- edit title/start date and assert the original slug remains unchanged;
- attempt to update another organiser's draft and expect `AuthorizationException` with no changes;
- change each tested Event to `PendingApproval` and `Published`, call `update()`, expect `ValidationException` on `draft`, and assert no field changed.

The central identity assertion should be literal:

```php
$updated = app(SaveWalkDraft::class)->update($draft, $leader, ['distance' => 8.5]);

$this->assertSame($draft->id, $updated->id);
$this->assertSame($draft->event_id, $updated->event_id);
$this->assertDatabaseCount('events', 1);
$this->assertDatabaseCount('walks', 1);
```

- [ ] **Step 2: Run the update cases and verify RED**

Run: `php artisan test tests/Feature/Walks/SaveWalkDraftTest.php --filter='update|same|slug|non_draft|another'`

Expected: FAIL because `SaveWalkDraft::update()` does not exist.

- [ ] **Step 3: Implement locked, Draft-only partial updates**

Within a transaction, reload the Walk by key with its Event/relations and `lockForUpdate()`, lock/load its Event, authorise `update`, then reject any status other than `EventStatus::Draft`:

```php
if ($lockedWalk->event->status !== EventStatus::Draft) {
    throw ValidationException::withMessages([
        'draft' => 'Only a draft walk can be saved from the Add Walk wizard.',
    ]);
}

return $this->updateWalk->handle(
    $lockedWalk,
    $actor,
    Arr::except($attributes, ['slug', 'status', 'is_public', 'published_at', 'organiser_id']),
);
```

Do not infer identity from title/date. Do not create a record on this path. Before saving supplied fields, force the Draft Event back to `is_public = false` and `published_at = null`; status remains Draft.

- [ ] **Step 4: Run draft/update regression coverage and verify GREEN**

Run: `php artisan test tests/Feature/Walks/SaveWalkDraftTest.php tests/Feature/Walks/WalkCreationSlugTest.php tests/Feature/Walks/WalkDetailsTest.php`

Expected: partial updates pass, stored details survive omitted fields, and slug behaviour remains accepted.

- [ ] **Step 5: Commit checkpoint update behaviour**

```bash
git add app/Domain/Walks/Actions/SaveWalkDraft.php tests/Feature/Walks/SaveWalkDraftTest.php
git commit -m "feat(walks): update existing drafts safely"
```

### Task 4: Refactor `CreateWalk` into deliberate final-submit orchestration

**Files:**
- Modify: `app/Domain/Walks/Actions/CreateWalk.php`
- Modify: `tests/Feature/Walks/WalkAdministrationTest.php`
- Modify: `tests/Feature/Walks/SaveWalkDraftTest.php`
- Verify unchanged: `tests/Feature/Walks/WalkCreationSlugTest.php`

**Interfaces:**
- Consumes: `SaveWalkDraft::create()`, `SaveWalkDraft::update()`, and `SubmitWalkForPublication::handle()`.
- Produces: `CreateWalk::handle(User $organiser, array $attributes, ?Walk $draft = null): Walk`.
- Compatibility: all existing two-argument callers remain final one-shot submissions.

- [ ] **Step 1: Write failing orchestration tests**

Write these failing orchestration tests:

1. A two-argument call still creates one aggregate and ends Published or Pending Approval under existing rules.
2. Passing an already persisted Draft updates and submits that exact Walk without adding Event/Walk rows.
3. An Event status-transition failure rolls back the final form changes and leaves the prior Draft checkpoint intact.

For rollback, register an `Event::updating` listener that throws only when `status` becomes non-Draft, call `CreateWalk` with an existing draft and changed summary, then assert the exception and the previously stored summary/status remain.

- [ ] **Step 2: Run the orchestration cases and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Walks/SaveWalkDraftTest.php --filter='one_shot|existing_draft|rolls_back|pending|publish'`

Expected: FAIL because `CreateWalk` cannot accept an existing Draft and does not wrap final draft persistence plus submission in one transaction.

- [ ] **Step 3: Compose draft persistence and submission**

Replace direct Event/Walk creation with:

```php
public function handle(User $organiser, array $attributes, ?Walk $draft = null): Walk
{
    return DB::transaction(function () use ($organiser, $attributes, $draft): Walk {
        $draft ??= $this->saveWalkDraft->create($organiser, $attributes);
        $draft = $this->saveWalkDraft->update($draft, $organiser, $attributes);

        return $this->submitWalkForPublication->handle($draft, $organiser);
    });
}
```

Remove now-obsolete persistence imports/dependencies. `SaveWalkDraft::create()` supplies the temporary leader; `update()` applies the final validated leader and all other submitted fields before the deliberate transition.

- [ ] **Step 4: Run affected creation/lifecycle tests and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Walks/SaveWalkDraftTest.php tests/Feature/Walks/WalkCreationSlugTest.php`

Expected: existing one-shot behaviour, existing Draft submission, atomic rollback, direct publish, approval-required status, and slug coverage all pass.

- [ ] **Step 5: Commit final-submit composition**

```bash
git add app/Domain/Walks/Actions/CreateWalk.php tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Walks/SaveWalkDraftTest.php
git commit -m "refactor(walks): submit persisted drafts deliberately"
```

### Task 5: Add narrow Grade and Tag creation actions and permissions

**Files:**
- Create: `app/Domain/Walks/Actions/CreateGrade.php`
- Create: `app/Domain/Walks/Actions/CreateTag.php`
- Create: `tests/Feature/Walks/WalkSupportingDataCreationTest.php`
- Modify: `app/Policies/GradePolicy.php`
- Modify: `app/Policies/TagPolicy.php`
- Modify: `app/Filament/Resources/GradeResource.php`
- Modify: `app/Filament/Resources/TagResource.php`
- Modify: `app/Filament/Resources/GradeResource/Pages/CreateGrade.php`
- Modify: `app/Filament/Resources/TagResource/Pages/CreateTag.php`
- Modify: `tests/Feature/Walks/WalkConfigurationAdminTest.php`
- Modify: `tests/Feature/Walks/WalkConfigurationResourcesTest.php`

**Interfaces:**
- Produces: `CreateGrade::handle(User $actor, array $attributes): Grade` and `CreateTag::handle(User $actor, array $attributes): Tag`.
- Produces: `GradeResource::creationFormComponents(bool $includeDisplayOrder = true): array` and `TagResource::creationFormComponents(): array`.
- Permission: `ManageEventConfiguration`, or the same active/verified coherent authority accepted by `WalkPolicy::create()`.

- [ ] **Step 1: Write failing action and policy tests**

Test the real actions for required/unique/max-length fields, Grade description/accent validation, explicit administrator display order, and automatic inline display order of `max(display_order) + 1` (starting at one when empty). Test that a default Walk Leader and Administrator can create supporting data, while an unverified, inactive, create-only, or unrelated user cannot.

Extend configuration tests to prove a Walk Leader still receives 403 from:

```php
$this->get('/admin/grades')->assertForbidden();
$this->get('/admin/grades/create')->assertForbidden();
$this->get('/admin/tags')->assertForbidden();
$this->get('/admin/tags/create')->assertForbidden();
```

Also assert they cannot update/delete existing Grade/Tag models, while administrators retain all resource operations.

- [ ] **Step 2: Run the new focused tests and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkSupportingDataCreationTest.php tests/Feature/Walks/WalkConfigurationAdminTest.php`

Expected: FAIL because the actions are absent and policy `create` is configuration-only.

- [ ] **Step 3: Implement shared validation/persistence and retain resource boundaries**

Each action must call `Gate::forUser($actor)->authorize('create', Model::class)` and create only validated data. Grade rules are `display_order => integer|min:0|max:65535`, `name => required|string|max:255|unique:grades,name`, `description => required|string|max:1000`, and `colour => nullable|string|regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/`. Tag rules are `name => required|string|max:255|unique:tags,name`. `CreateGrade` assigns display order only when absent:

```php
$attributes['display_order'] ??= ((int) Grade::query()->max('display_order')) + 1;
```

Grade/Tag policy `create()` should allow either `ManageEventConfiguration` or `Gate::forUser($user)->allows('create', Walk::class)`. All other policy methods remain unchanged.

Extract fresh component instances into the resource methods named above. Override each resource's `canCreate()` to require `ManageEventConfiguration`, so the standalone Filament pages remain configuration-only even though the domain policy permits inline creation. Override the two create pages' `handleRecordCreation()` methods to call the new domain actions for administrators.

- [ ] **Step 4: Run supporting-data and resource coverage and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkSupportingDataCreationTest.php tests/Feature/Walks/WalkConfigurationAdminTest.php tests/Feature/Walks/WalkConfigurationResourcesTest.php`

Expected: actions and validation pass; narrow creators can create through the domain boundary; full resources remain administrator/configuration-only.

- [ ] **Step 5: Commit supporting-data actions and permissions**

```bash
git add app/Domain/Walks/Actions/CreateGrade.php app/Domain/Walks/Actions/CreateTag.php app/Policies/GradePolicy.php app/Policies/TagPolicy.php app/Filament/Resources/GradeResource.php app/Filament/Resources/TagResource.php app/Filament/Resources/GradeResource/Pages/CreateGrade.php app/Filament/Resources/TagResource/Pages/CreateTag.php tests/Feature/Walks/WalkSupportingDataCreationTest.php tests/Feature/Walks/WalkConfigurationAdminTest.php tests/Feature/Walks/WalkConfigurationResourcesTest.php
git commit -m "feat(walks): allow narrow supporting data creation"
```

### Task 6: Add inline Grade and Tag creation to Step 2

**Files:**
- Modify: `app/Filament/Resources/WalkResource.php`
- Create: `tests/Feature/Walks/WalkSupportingDataInlineCreationTest.php`

**Interfaces:**
- Consumes: resource creation field arrays and the new `CreateGrade`/`CreateTag` actions.
- Produces: `WalkResource::formComponents(bool $allowInlineSupportingDataCreation = false): array`; Filament Select `createOptionForm()` and `createOptionUsing()` flows returning the new model key only in the Add Walk wizard.

- [ ] **Step 1: Write failing Livewire/component-action tests**

Using `Livewire::actingAs($leader)->test(CreateWalkPage::class)` and Filament's real form component actions, perform the action first, fetch the persisted Grade, then assert its selected key:

```php
$component = Livewire::actingAs($leader)->test(CreateWalkPage::class)
    ->callFormComponentAction('grade_id', 'createOption', [
    'name' => 'Steady',
    'description' => 'Mixed paths with a sustained climb.',
    'colour' => '#2563EB',
    ])
    ->assertHasNoFormComponentActionErrors();
$grade = Grade::query()->where('name', 'Steady')->sole();
$component->assertFormSet(['grade_id' => $grade->id]);
```

For Tags, pre-fill `tag_ids` with an existing ID, create `Riverside`, and assert the final state contains both IDs. Add invalid/duplicate cases that remain in the modal and add no row. Inspect the configured Select components to assert the exact no-options messages and that an authorised actor sees the `createOption` action while an unrelated actor cannot invoke a successful create.

- [ ] **Step 2: Run inline tests and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkSupportingDataInlineCreationTest.php`

Expected: FAIL because Grade and Tag Selects have no create-option action or approved empty-state message.

- [ ] **Step 3: Configure both selectors without duplicating rules**

Add the optional boolean argument to `WalkResource::formComponents()`. `WalkResource::form()` keeps its default `false`, while `CreateWalk::fields()` requests `true`; this prevents inline supporting-data actions from leaking onto ordinary Edit Walk forms. For Grade, when the boolean is true, add:

```php
->noOptionsMessage('No grades yet — create one here.')
->createOptionForm(fn (): array => GradeResource::creationFormComponents(includeDisplayOrder: false))
->createOptionUsing(function (array $data): int {
    /** @var User $actor */
    $actor = auth()->user();

    return app(CreateGradeAction::class)->handle($actor, $data)->getKey();
})
```

Configure its create-option action with the heading `Create grade` and visibility based on the Grade create policy. Apply the equivalent Tag setup with `No tags yet — create one here.` and `Create tag`. Rely on Filament's accepted multiple-Select behaviour to append the returned Tag key; do not replace `tag_ids` manually. Assert `EditWalk` has neither create-option action so the feature remains specific to Step 2.

- [ ] **Step 4: Run inline plus permission tests and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkSupportingDataInlineCreationTest.php tests/Feature/Walks/WalkSupportingDataCreationTest.php`

Expected: new options validate, persist, and become selected; existing Tag selections survive; unauthorised creation fails at the action boundary.

- [ ] **Step 5: Commit inline supporting-data creation**

```bash
git add app/Filament/Resources/WalkResource.php tests/Feature/Walks/WalkSupportingDataInlineCreationTest.php
git commit -m "feat(walks): create grades and tags inline"
```

### Task 7: Save the first Draft after valid Step 1

**Files:**
- Modify: `app/Filament/Resources/WalkResource/Pages/CreateWalk.php`
- Create: `resources/views/filament/walks/draft-save-status.blade.php`
- Create: `tests/Feature/Walks/WalkDraftWizardTest.php`

**Interfaces:**
- Consumes: `Wizard\Step::afterValidation()`, `SaveWalkDraft::create()`, Livewire `#[Url(as: 'draft')]`, and `Schema` View components.
- Produces: URL-backed `public ?int $draftId`, `public ?string $draftSaveStatus`, and a Step 1 checkpoint.

- [ ] **Step 1: Write failing Step 1 wizard tests**

Use Filament's `goToNextWizardStep()` helper. Assert an empty or invalid Step 1 remains on step 1 with form errors and zero rows. Then fill title/start/location, advance once, and assert:

```php
->assertWizardCurrentStep(2)
->assertSet('draftId', fn (?int $id): bool => filled($id))
->assertSee('Draft saved');
```

Fetch the Walk by that component property and assert the private Draft invariants. Advance Back/Next again and assert table counts remain one and `primary_leader_id` in form state is the actor ID.

- [ ] **Step 2: Run the wizard test and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php --filter='step_one|first_draft|invalid'`

Expected: FAIL because advancing Step 1 does not persist a record or expose draft state/status.

- [ ] **Step 3: Add draft URL state, Step 1 callback, and persistent status region**

Add:

```php
#[Url(as: 'draft')]
public ?int $draftId = null;

public ?string $draftSaveStatus = null;
public ?string $draftSaveError = null;
```

Attach `->afterValidation(fn () => $this->checkpointStep(1))` to Step 1. In `checkpointStep()`, select only Step 1 keys from `$this->data`; call `SaveWalkDraft::create()` when `draftId` is null, otherwise resolve and call `SaveWalkDraft::update()` so Back/Next never duplicates the aggregate. Assign a newly returned ID only after success, set `data.primary_leader_id` to the actor when absent, clear errors, and set `Draft saved`.

Override `content(Schema $schema)` to render the normal form component followed by `ViewComponent::make('filament.walks.draft-save-status')`, importing `Filament\Schemas\Components\View as ViewComponent`. Keep an always-present empty `role="status" aria-live="polite" aria-atomic="true"` element in the Blade view so Livewire text updates are announced.

- [ ] **Step 4: Run Step 1 and existing wizard tests and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php --filter='step_one|first_draft|invalid|guided_five_step'`

Expected: only valid Step 1 creates one private Draft, exposes status, and advances; the five approved step labels remain unchanged.

- [ ] **Step 5: Commit the initial wizard checkpoint**

```bash
git add app/Filament/Resources/WalkResource/Pages/CreateWalk.php resources/views/filament/walks/draft-save-status.blade.php tests/Feature/Walks/WalkDraftWizardTest.php
git commit -m "feat(walks): checkpoint the first wizard step"
```

### Task 8: Save Steps 2–4 to the same Draft without erasing unvisited data

**Files:**
- Modify: `app/Filament/Resources/WalkResource/Pages/CreateWalk.php`
- Modify: `tests/Feature/Walks/WalkDraftWizardTest.php`

**Interfaces:**
- Consumes: `SaveWalkDraft::update()` and the page's server-issued `draftId`.
- Produces: explicit per-step field maps and Step 2, 3, and 4 `afterValidation()` callbacks.

- [ ] **Step 1: Write failing checkpoint identity/merge tests**

Drive the Livewire wizard through Steps 1–4. At each boundary, fill a distinctive value, advance, and reload the same Walk. Assert:

- Step 2 saves grade, existing plus inline-created Tags, distance, ascent, duration, capacity, availability, and terrain.
- Step 3 saves only travel/practical fields while Step 2 data remains.
- Step 4 saves summary/description/image/attachments while earlier data remains.
- Back/Next updates the same IDs and counts remain one.
- A stored optional value for an unvisited later step is not replaced with null by an earlier checkpoint.

- [ ] **Step 2: Run later checkpoint tests and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php --filter='later_steps|same_draft|unvisited|repeated'`

Expected: FAIL because Steps 2–4 have no persistence callbacks.

- [ ] **Step 3: Add exact step field maps and update callbacks**

Define one class constant keyed 1–4 containing exactly the fields already assigned to each wizard step. Attach `afterValidation()` to Steps 2–4 and pass only `Arr::only($this->data, self::CHECKPOINT_FIELDS[$step])` to `SaveWalkDraft::update()`.

Every update must first resolve the server-issued ID. Never pass all `$this->data`, and never synthesize a new Draft when an expected ID is absent; halt with the save error instead.

- [ ] **Step 4: Run wizard and draft-action tests and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/SaveWalkDraftTest.php`

Expected: all checkpoints update one Draft and omitted/unvisited data remains intact.

- [ ] **Step 5: Commit later-step checkpoints**

```bash
git add app/Filament/Resources/WalkResource/Pages/CreateWalk.php tests/Feature/Walks/WalkDraftWizardTest.php
git commit -m "feat(walks): checkpoint later wizard steps"
```

### Task 9: Resume authorised Drafts and expose `Continue draft`

**Files:**
- Create: `app/Filament/Resources/WalkResource/Support/WalkFormData.php`
- Modify: `app/Filament/Resources/WalkResource/Pages/CreateWalk.php`
- Modify: `app/Filament/Resources/WalkResource/Pages/EditWalk.php`
- Modify: `app/Filament/Resources/WalkResource.php`
- Modify: `app/ViewModels/LeaderHubPageViewModel.php`
- Modify: `resources/views/leader-hub/index.blade.php`
- Modify: `tests/Feature/Walks/WalkDraftWizardTest.php`
- Modify: `tests/Feature/Walks/WalkAdministrationTest.php`
- Modify: `tests/Feature/Accounts/LeaderProfilesTest.php`

**Interfaces:**
- Produces: `WalkFormData::from(Walk $walk): array` with Event fields, Walk attributes, `co_leader_ids`, and `tag_ids`.
- Produces: create URL `WalkResource::getUrl('create', ['draft' => $walk->getKey()])` for Drafts only.
- Consumes: `WalkResource::getEloquentQuery()` plus `Gate::forUser($actor)->authorize('update', $walk)` on mount, hydration, and checkpoint/final requests.

- [ ] **Step 1: Write failing resume/authorisation/continuation tests**

Write resume tests that use `Livewire::withQueryParams(['draft' => $draft->id, 'step' => 'walk-details'])` to mount `CreateWalk` and assert step 2 plus all persisted form state. Change a saved value, advance, and assert the same record updates.

Add HTTP/Livewire cases for a missing ID, another organiser's Draft, and a Published/Pending Approval ID; expect 404 or 403 without rendering private values. After mounting an owned Draft, change its status or revoke authority before the next Livewire request and assert the request is rejected, proving re-authorisation is not mount-only.

For resource and Leader Hub output, assert Drafts use `Continue draft` with `/admin/walks/create?draft=<id>`, while Pending Approval/Published records retain `Edit` and the existing edit URL.

- [ ] **Step 2: Run resume/surface tests and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Accounts/LeaderProfilesTest.php --filter='resume|continue_draft|inaccessible|missing|non_draft|re_author'`

Expected: FAIL because Create Walk cannot hydrate an existing Draft and both management surfaces link Drafts to Edit.

- [ ] **Step 3: Extract form mapping and implement secure hydration**

Move the Event/relationship merge currently in `EditWalk::mutateFormDataBeforeFill()` into `WalkFormData::from()`, and use it from both pages.

In Create Walk, centralise resolution in a private method that:

```php
$walk = WalkResource::getEloquentQuery()
    ->with(['event', 'coLeaders', 'tags'])
    ->whereKey($this->draftId)
    ->firstOrFail();
Gate::forUser($actor)->authorize('update', $walk);
abort_unless($walk->event->status === EventStatus::Draft, 404);
```

On mount, fill the form from `WalkFormData::from($walk)` without invoking a checkpoint. On every hydration and before every save/submit, call the same resolver for authorisation/status but do not refill, so unsaved current-step entries survive Livewire requests.

Call `persistStepInQueryString('step')` on `AccessibleWizard`; Filament 5.7 resolves the approved step IDs and restores the step from the URL. Configure the Walk table action and Leader Hub view model to choose `Continue draft` plus the create URL only when status is Draft.

- [ ] **Step 4: Run resume, ownership, and shared mapping tests and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Accounts/LeaderProfilesTest.php`

Expected: authorised Drafts hydrate and resume at their URL step; each request rechecks ownership/status; management surfaces distinguish Draft continuation from ordinary editing.

- [ ] **Step 5: Commit resume and continuation surfaces**

```bash
git add app/Filament/Resources/WalkResource/Support/WalkFormData.php app/Filament/Resources/WalkResource/Pages/CreateWalk.php app/Filament/Resources/WalkResource/Pages/EditWalk.php app/Filament/Resources/WalkResource.php app/ViewModels/LeaderHubPageViewModel.php resources/views/leader-hub/index.blade.php tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Accounts/LeaderProfilesTest.php
git commit -m "feat(walks): resume authorised walk drafts"
```

### Task 10: Add accessible checkpoint success and failure feedback

**Files:**
- Modify: `app/Filament/Resources/WalkResource/Pages/CreateWalk.php`
- Modify: `resources/views/filament/walks/draft-save-status.blade.php`
- Modify: `tests/Feature/Walks/WalkDraftWizardTest.php`

**Interfaces:**
- Produces: exact success text `Draft saved` and exact checkpoint error text `We couldn't save your draft. Your entries are still on this page. Try again.`
- Produces: browser event `walk-draft-save-failed` for focus transfer to the persistent error summary.
- Consumes: `Filament\Support\Exceptions\Halt` to prevent wizard advancement.

- [ ] **Step 1: Write failing save-failure and accessibility tests**

In a real persistence-failure test, register a temporary `Walk::creating` or `Walk::updating` listener that throws `RuntimeException`, fill the current step, and call `goToNextWizardStep()`. Assert:

- the current wizard step does not change;
- the typed values remain in form state;
- Event/Walk state remains at the previous successful checkpoint;
- the exact error is rendered;
- `walk-draft-save-failed` is dispatched;
- no Pending Approval or Published Event exists.

Render the component and assert one persistent `role="status"` with `aria-live="polite"`/`aria-atomic="true"`, plus a conditional focusable `role="alert"` for failures.

- [ ] **Step 2: Run feedback tests and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php --filter='failure|feedback|aria|preserves_entries'`

Expected: FAIL because checkpoint exceptions are not converted into accessible page feedback/focus and may escape the Livewire request.

- [ ] **Step 3: Halt safely and focus the error summary**

Wrap only the checkpoint action call in `try/catch (Throwable $exception)`. Report the exception, leave form data and `draftId` unchanged, clear the success string, set the exact error, dispatch `walk-draft-save-failed`, and throw `new Halt` so Filament stays on the current step.

In the Blade view, keep the live status region permanently present. Render the error as `role="alert" tabindex="-1" x-ref="draftSaveError"` and add an Alpine window listener that focuses it in `$nextTick`. Clear stale error text after any successful checkpoint.

Use Filament's existing request-target behaviour for Next-button in-flight disabling; do not introduce another JavaScript submission state.

- [ ] **Step 4: Run focused wizard feedback tests and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php`

Expected: persistence failure is atomic, remains on the step with entries intact, and exposes the approved accessible feedback; successful saves remain quiet and concise.

- [ ] **Step 5: Commit accessible checkpoint feedback**

```bash
git add app/Filament/Resources/WalkResource/Pages/CreateWalk.php resources/views/filament/walks/draft-save-status.blade.php tests/Feature/Walks/WalkDraftWizardTest.php
git commit -m "feat(walks): report draft checkpoint status accessibly"
```

### Task 11: Make final submission deliberate and run the focused end-state gate

**Files:**
- Modify: `app/Filament/Resources/WalkResource/Pages/CreateWalk.php`
- Modify: `tests/Feature/Walks/WalkDraftWizardTest.php`
- Modify: `tests/Feature/Walks/WalkAdministrationTest.php`
- Modify: `tests/browser/admin-usability.spec.ts`

**Interfaces:**
- Consumes: `CreateWalk::handle(User $organiser, array $attributes, ?Walk $draft = null): Walk`.
- Produces: final action label `Submit walk`, no create-another action, Draft-aware final submission, and one targeted desktop browser proof of URL persistence/resume.

- [ ] **Step 1: Write failing final-submit tests**

Add Livewire tests that create/checkpoint a Draft, reach Step 5, and assert the page action is labelled `Submit walk`. Submit with full valid state and verify the same Event/Walk becomes Pending Approval when direct publishing is disabled and Published when enabled/authorised.

Add a failure case where the final status transition throws after changed final data is supplied. Assert the Event/Walk retains the prior checkpoint data, remains private Draft, renders `We couldn't submit your walk. Your draft is still saved. Try again.`, and does not redirect.

- [ ] **Step 2: Run final-submit tests and verify RED**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php --filter='submit_walk|final_submission|submission_failure'`

Expected: FAIL because the current submit action says `Create`, does not pass the persisted Draft, and does not present a handled final failure.

- [ ] **Step 3: Wire the final action to the existing Draft**

Set `protected static bool $canCreateAnother = false`. Override the create form action label to `Submit walk`. In `handleRecordCreation()`, resolve the current Draft when `draftId` is present and call the three-argument `CreateWalk::handle()`.

Catch final orchestration exceptions at the page boundary, report them, set the exact submission error, dispatch the same focus event, and throw `(new Halt)->rollBackDatabaseTransaction()`. On success clear the query-backed ID before the existing redirect. Full form validation remains Filament's pre-action validation; failed validation does not call the action and leaves the Draft untouched.

- [ ] **Step 4: Rerun final-submit tests and verify GREEN**

Run: `php artisan test tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php --filter='submit_walk|final_submission|submission_failure'`

Expected: the same Draft transitions only after the deliberate action, direct/approval paths remain correct, and failure leaves the last private checkpoint unchanged.

- [ ] **Step 5: Update and run the one justified browser test**

Update the existing `walk leader completes all five Add Walk steps through the dashboard action` test in `tests/browser/admin-usability.spec.ts`:

- remove its obsolete `Slug` interaction, and remove the same obsolete interaction from the existing responsive Back/Next test so future release qualification is not left with a known stale locator;
- after valid Step 1, assert `Draft saved` and a URL containing the server-issued `draft` plus `step=walk-details`;
- reload, assert Step 2 is restored, go Back, and assert title/start values are hydrated;
- on Step 2, open the Grade create-option modal, cancel it, and assert keyboard focus returns to the Grade selector, preserving Filament's modal focus contract;
- finish the five steps through `Submit walk` and retain the Pending Approval row assertion;
- use the existing login/seed and do not add visual baselines or another browser project.

Run: `npx playwright test tests/browser/admin-usability.spec.ts --project=desktop --grep "walk leader completes all five Add Walk steps"`

Expected: PASS. This single test is justified because Livewire tests cannot prove actual browser URL mutation, reload restoration, or modal focus restoration.

- [ ] **Step 6: Run the complete lightweight implementation gate**

Run only:

```bash
php artisan test tests/Feature/Walks/WalkSlugGeneratorTest.php tests/Feature/Walks/WalkCreationSlugTest.php tests/Feature/Walks/SaveWalkDraftTest.php tests/Feature/Walks/WalkSupportingDataCreationTest.php tests/Feature/Walks/WalkSupportingDataInlineCreationTest.php tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Walks/WalkConfigurationAdminTest.php tests/Feature/Walks/WalkConfigurationResourcesTest.php tests/Feature/Accounts/LeaderProfilesTest.php
vendor/bin/pint app/Domain/Walks/Actions/GenerateWalkSlug.php app/Domain/Walks/Actions/SaveWalkDraft.php app/Domain/Walks/Actions/CreateWalk.php app/Domain/Walks/Actions/CreateGrade.php app/Domain/Walks/Actions/CreateTag.php app/Policies/WalkPolicy.php app/Policies/GradePolicy.php app/Policies/TagPolicy.php app/Filament/Resources/GradeResource.php app/Filament/Resources/TagResource.php app/Filament/Resources/GradeResource/Pages/CreateGrade.php app/Filament/Resources/TagResource/Pages/CreateTag.php app/Filament/Resources/WalkResource.php app/Filament/Resources/WalkResource/Pages/CreateWalk.php app/Filament/Resources/WalkResource/Pages/EditWalk.php app/Filament/Resources/WalkResource/Support/WalkFormData.php app/ViewModels/LeaderHubPageViewModel.php tests/Feature/Walks/WalkSlugGeneratorTest.php tests/Feature/Walks/SaveWalkDraftTest.php tests/Feature/Walks/WalkSupportingDataCreationTest.php tests/Feature/Walks/WalkSupportingDataInlineCreationTest.php tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/Feature/Walks/WalkConfigurationAdminTest.php tests/Feature/Walks/WalkConfigurationResourcesTest.php tests/Feature/Accounts/LeaderProfilesTest.php
php -l app/Domain/Walks/Actions/GenerateWalkSlug.php
php -l app/Domain/Walks/Actions/SaveWalkDraft.php
php -l app/Domain/Walks/Actions/CreateWalk.php
php -l app/Domain/Walks/Actions/CreateGrade.php
php -l app/Domain/Walks/Actions/CreateTag.php
php -l app/Filament/Resources/WalkResource/Pages/CreateWalk.php
php -l app/Filament/Resources/WalkResource/Support/WalkFormData.php
git diff --check
```

Do not run the full PHP suite, database-engine matrix, full Playwright, installer, release-browser, PWA, staging, packaging, performance, or release qualification commands.

- [ ] **Step 7: Commit the final page/browser integration**

```bash
git add app/Filament/Resources/WalkResource/Pages/CreateWalk.php tests/Feature/Walks/WalkDraftWizardTest.php tests/Feature/Walks/WalkAdministrationTest.php tests/browser/admin-usability.spec.ts
git commit -m "feat(walks): submit resilient drafts deliberately"
```

## Implementation Completion Gate

Before requesting review, inspect the final diff and confirm all of the following from fresh focused evidence:

1. Every behaviour test was observed failing for the intended missing behaviour before its production change.
2. One valid Step 1 creates exactly one private Draft and no invalid Step 1 creates a record.
3. Steps 2–4 update that same Draft and never submit it.
4. Refresh/reopen resumes through the draft ID and current step; every request re-authorises ownership and Draft status.
5. Inline Grade/Tag creation reuses domain validation and does not expose full configuration resources.
6. Slug format, collision handling, 255-character boundary, and edit stability remain unchanged.
7. Only `Submit walk` can cause Draft to become Pending Approval or Published.
8. Save/submission failures preserve the last successful private checkpoint and entered form values.
9. The single targeted desktop browser test passes; no broad browser or release suite was run.
10. `git status --short` is clean after the final commit, and no version, package, tag, feed, or publication state changed.

Stop for independent implementation review. Release qualification belongs to a separately approved future v1.0.3 preparation task.
