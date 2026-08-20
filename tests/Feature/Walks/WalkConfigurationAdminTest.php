<?php

namespace Tests\Feature\Walks;

use App\Domain\Walks\Actions\UpdateWalkFieldSettings;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\Models\WalkFieldSettings;
use App\Filament\Resources\GradeResource\Pages\CreateGrade;
use App\Filament\Resources\TagResource\Pages\CreateTag;
use App\Filament\Resources\WalkFieldSettingsResource\Pages\EditWalkFieldSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkConfigurationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_create_rejects_an_invalid_order_duplicate_name_and_overlong_description(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Grade::query()->create([
            'display_order' => 1,
            'name' => 'Gentle',
            'description' => 'An easy walk.',
        ]);

        Livewire::test(CreateGrade::class)
            ->fillForm([
                'display_order' => -1,
                'name' => 'Gentle',
                'description' => str_repeat('x', 1001),
            ])
            ->call('create')
            ->assertHasFormErrors([
                'display_order' => 'min',
                'name' => 'unique',
                'description' => 'max',
            ]);

        $this->assertSame(1, Grade::query()->count());
    }

    public function test_grade_create_rejects_an_overlong_name(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(CreateGrade::class)
            ->fillForm([
                'display_order' => 1,
                'name' => str_repeat('x', 256),
                'description' => 'A practical written grade description.',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'max']);

        $this->assertSame(0, Grade::query()->count());
    }

    public function test_tag_create_rejects_a_blank_or_duplicate_name(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Tag::query()->create(['name' => 'Riverside']);

        Livewire::test(CreateTag::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);

        Livewire::test(CreateTag::class)
            ->fillForm(['name' => 'Riverside'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);

        $this->assertSame(1, Tag::query()->count());
    }

    public function test_tag_create_rejects_an_overlong_name(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(CreateTag::class)
            ->fillForm(['name' => str_repeat('x', 256)])
            ->call('create')
            ->assertHasFormErrors(['name' => 'max']);

        $this->assertSame(0, Tag::query()->count());
    }

    public function test_grade_list_keeps_label_and_description_visible_with_or_without_an_accent_colour(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Grade::query()->create([
            'display_order' => 1,
            'name' => 'Gentle',
            'description' => 'Easy paths and a relaxed pace.',
            'colour' => null,
        ]);
        Grade::query()->create([
            'display_order' => 2,
            'name' => 'Challenging',
            'description' => 'Longer distance with sustained climbs.',
            'colour' => '#2563EB',
        ]);

        $this->get('/admin/grades')
            ->assertSuccessful()
            ->assertSeeText('Gentle')
            ->assertSeeText('Easy paths and a relaxed pace.')
            ->assertSeeText('Challenging')
            ->assertSeeText('Longer distance with sustained climbs.');
    }

    public function test_walk_field_settings_edit_saves_enabled_and_disabled_checkbox_state(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $settings = app(UpdateWalkFieldSettings::class)->handle([]);

        Livewire::test(EditWalkFieldSettings::class, ['record' => $settings->getKey()])
            ->fillForm([
                'field_configuration' => ['gpx', 'what3words'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings->refresh();

        $this->assertTrue($settings->isEnabled('gpx'));
        $this->assertTrue($settings->isEnabled('what3words'));
        $this->assertFalse($settings->isEnabled('co_leaders'));
        $this->assertFalse($settings->isEnabled('recap'));
        $this->assertSame(1, WalkFieldSettings::query()->count());
    }

    public function test_walk_field_settings_edit_saves_the_direct_publish_setting(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $settings = app(UpdateWalkFieldSettings::class)->handle([]);

        Livewire::test(EditWalkFieldSettings::class, ['record' => $settings->getKey()])
            ->fillForm([
                'field_configuration' => array_keys(WalkFieldSettings::OPTIONAL_FIELDS),
                'leaders_can_publish_directly' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($settings->fresh()->leaders_can_publish_directly);
    }

    public function test_walk_leader_cannot_access_or_update_global_configuration_resources(): void
    {
        $settings = app(UpdateWalkFieldSettings::class)->handle([]);
        $walkLeader = User::factory()->create(['can_manage_walks' => true]);

        $this->actingAs($walkLeader)
            ->get('/admin/walk-field-settings')
            ->assertForbidden();
        $this->get("/admin/walk-field-settings/{$settings->id}/edit")->assertForbidden();
        $this->get('/admin/grades')->assertForbidden();
        $this->get('/admin/tags')->assertForbidden();

        Livewire::test(EditWalkFieldSettings::class, ['record' => $settings->getKey()])
            ->assertForbidden();
    }

    public function test_forbidden_walk_leader_does_not_initialise_walk_field_settings(): void
    {
        $walkLeader = User::factory()->create(['can_manage_walks' => true]);

        $this->assertSame(0, WalkFieldSettings::query()->count());

        $this->actingAs($walkLeader)
            ->get('/admin/walk-field-settings')
            ->assertForbidden();

        $this->assertSame(0, WalkFieldSettings::query()->count());
    }

    public function test_administrator_can_access_all_global_configuration_resources(): void
    {
        $settings = app(UpdateWalkFieldSettings::class)->handle([]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin/walk-field-settings')->assertSuccessful();
        $this->get("/admin/walk-field-settings/{$settings->id}/edit")->assertSuccessful();
        $this->get('/admin/grades')->assertSuccessful();
        $this->get('/admin/tags')->assertSuccessful();
    }

    public function test_tag_and_walk_field_settings_indexes_render_for_an_admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin/tags')->assertSuccessful();
        $this->get('/admin/walk-field-settings')->assertSuccessful();
    }
}
