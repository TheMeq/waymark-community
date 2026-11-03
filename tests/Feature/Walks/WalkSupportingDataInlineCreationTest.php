<?php

namespace Tests\Feature\Walks;

use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Filament\Resources\WalkResource;
use App\Filament\Resources\WalkResource\Pages\CreateWalk as CreateWalkPage;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkSupportingDataInlineCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_leader_creates_and_selects_a_grade_from_the_add_walk_form(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = str_replace(['"', '`', '[', ']'], '', strtolower($query->sql));
        });

        $component = Livewire::actingAs($leader)->test(CreateWalkPage::class)
            ->callFormComponentAction('grade_id', 'createOption', [
                'name' => 'Steady',
                'description' => 'Mixed paths with a sustained climb.',
                'colour' => '#2563EB',
            ])
            ->assertHasNoFormComponentActionErrors();
        $grade = Grade::query()->where('name', 'Steady')->sole();

        $component->assertFormSet(['grade_id' => $grade->id]);
        $this->assertSame(1, $grade->display_order);
        $this->assertTrue(collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, 'from grades') && str_contains($sql, 'where name'),
        ));
        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, 'from walks') && str_contains($sql, 'where name'),
        ));
    }

    public function test_inline_tag_creation_appends_to_existing_selections(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $existing = Tag::query()->create(['name' => 'Woodland']);

        $component = Livewire::actingAs($leader)->test(CreateWalkPage::class)
            ->fillForm(['tag_ids' => [$existing->id]])
            ->callFormComponentAction('tag_ids', 'createOption', [
                'name' => 'Riverside',
            ])
            ->assertHasNoFormComponentActionErrors();
        $created = Tag::query()->where('name', 'Riverside')->sole();

        $component->assertFormSet(['tag_ids' => [$existing->id, $created->id]]);
    }

    public function test_duplicate_inline_grade_is_rejected_against_the_grades_table(): void
    {
        $leader = User::factory()->walkLeader()->create();
        Grade::query()->create([
            'display_order' => 1,
            'name' => 'Steady',
            'description' => 'Existing grade.',
        ]);

        Livewire::actingAs($leader)->test(CreateWalkPage::class)
            ->callFormComponentAction('grade_id', 'createOption', [
                'name' => 'Steady',
                'description' => 'Duplicate grade.',
            ])
            ->assertHasFormComponentActionErrors(['name' => 'unique']);

        $this->assertDatabaseCount('grades', 1);
    }

    public function test_invalid_or_duplicate_inline_options_remain_uncreated(): void
    {
        $leader = User::factory()->walkLeader()->create();
        Tag::query()->create(['name' => 'Riverside']);

        Livewire::actingAs($leader)->test(CreateWalkPage::class)
            ->callFormComponentAction('grade_id', 'createOption', [
                'name' => 'Incomplete',
                'description' => '',
            ])
            ->assertHasFormComponentActionErrors(['description' => 'required']);

        Livewire::actingAs($leader)->test(CreateWalkPage::class)
            ->callFormComponentAction('tag_ids', 'createOption', [
                'name' => 'Riverside',
            ])
            ->assertHasFormComponentActionErrors(['name' => 'unique']);

        $this->assertDatabaseMissing('grades', ['name' => 'Incomplete']);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_inline_selects_use_approved_empty_messages_and_policy_visibility(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $this->actingAs($leader);

        $grade = $this->select('grade_id', allowInlineCreation: true);
        $tag = $this->select('tag_ids', allowInlineCreation: true);

        $this->assertSame('No grades yet.', (string) $grade->getNoOptionsMessage());
        $this->assertSame('No tags yet.', (string) $tag->getNoOptionsMessage());

        Livewire::actingAs($leader)->test(CreateWalkPage::class)
            ->assertFormComponentActionVisible('grade_id', 'createOption')
            ->assertFormComponentActionVisible('tag_ids', 'createOption')
            ->assertFormComponentActionHasLabel('grade_id', 'createOption', 'Create grade')
            ->assertFormComponentActionHasLabel('tag_ids', 'createOption', 'Create tag');

        Livewire::actingAs(User::factory()->create())
            ->test(CreateWalkPage::class)
            ->assertForbidden();
    }

    public function test_edit_walk_form_has_no_inline_supporting_data_actions(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $draft = app(SaveWalkDraft::class)->create($leader, [
            'title' => 'Reservoir circuit',
            'starts_at' => '2026-09-12 09:30:00',
        ]);

        Livewire::actingAs($leader)->test(EditWalk::class, ['record' => $draft->id])
            ->assertFormComponentActionDoesNotExist('grade_id', 'createOption')
            ->assertFormComponentActionDoesNotExist('tag_ids', 'createOption');
    }

    private function select(string $name, bool $allowInlineCreation): Select
    {
        $component = collect(WalkResource::formComponents($allowInlineCreation))
            ->first(fn ($component): bool => $component->getName() === $name);

        $this->assertInstanceOf(Select::class, $component);

        return $component;
    }
}
