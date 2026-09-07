<?php

namespace Tests\Feature\Walks;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Membership\Enums\AccountStatus;
use App\Domain\Walks\Actions\CreateGrade;
use App\Domain\Walks\Actions\CreateTag;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WalkSupportingDataCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_creation_validates_supported_fields(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Grade::query()->create([
            'display_order' => 1,
            'name' => 'Gentle',
            'description' => 'Relaxed paths.',
        ]);

        try {
            app(CreateGrade::class)->handle($administrator, [
                'display_order' => -1,
                'name' => 'Gentle',
                'description' => str_repeat('x', 1001),
                'colour' => 'blue',
            ]);
            $this->fail('Invalid grade details were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('display_order', $exception->errors());
            $this->assertArrayHasKey('name', $exception->errors());
            $this->assertArrayHasKey('description', $exception->errors());
            $this->assertArrayHasKey('colour', $exception->errors());
        }

        try {
            app(CreateGrade::class)->handle($administrator, [
                'name' => str_repeat('x', 256),
                'description' => '',
            ]);
            $this->fail('Missing and overlong grade details were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
            $this->assertArrayHasKey('description', $exception->errors());
        }

        $this->assertDatabaseCount('grades', 1);
    }

    public function test_grade_creation_honours_explicit_order_and_assigns_the_next_order_when_omitted(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();

        $first = app(CreateGrade::class)->handle($administrator, [
            'name' => 'Gentle',
            'description' => 'Relaxed paths.',
        ]);
        $explicit = app(CreateGrade::class)->handle($administrator, [
            'display_order' => 10,
            'name' => 'Challenging',
            'description' => 'Sustained climbs.',
            'colour' => '#2563EB',
        ]);
        $next = app(CreateGrade::class)->handle($administrator, [
            'name' => 'Severe',
            'description' => 'Long and demanding.',
            'colour' => '#2563EBCC',
        ]);

        $this->assertSame(1, $first->display_order);
        $this->assertSame(10, $explicit->display_order);
        $this->assertSame(11, $next->display_order);
    }

    public function test_tag_creation_validates_required_unique_and_maximum_length_name(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Tag::query()->create(['name' => 'Riverside']);

        foreach (['', 'Riverside', str_repeat('x', 256)] as $name) {
            try {
                app(CreateTag::class)->handle($administrator, ['name' => $name]);
                $this->fail('Invalid tag details were accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('name', $exception->errors());
            }
        }

        $this->assertDatabaseCount('tags', 1);
    }

    public function test_default_walk_leader_and_administrator_can_create_supporting_data(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $administrator = User::factory()->initialAdministrator()->create();

        $grade = app(CreateGrade::class)->handle($leader, [
            'name' => 'Steady',
            'description' => 'Mixed paths.',
        ]);
        $tag = app(CreateTag::class)->handle($administrator, ['name' => 'Woodland']);

        $this->assertSame('Steady', $grade->name);
        $this->assertSame('Woodland', $tag->name);
    }

    public function test_unverified_inactive_create_only_and_unrelated_users_cannot_create_supporting_data(): void
    {
        $unverified = User::factory()->walkLeader()->unverified()->create();
        $inactive = User::factory()->walkLeader()->create(['account_status' => AccountStatus::Suspended]);
        $unrelated = User::factory()->create();
        RoleCapability::query()
            ->where('role', AccountRole::WalkLeader->value)
            ->whereIn('capability', [
                ModuleCapability::ManageOwnWalks->value,
                ModuleCapability::ManageAllWalks->value,
            ])
            ->delete();
        $createOnly = User::factory()->walkLeader()->create();

        foreach ([$unverified, $inactive, $createOnly, $unrelated] as $actor) {
            try {
                app(CreateTag::class)->handle($actor, ['name' => 'Tag '.$actor->id]);
                $this->fail('An unauthorised account created supporting data.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('tags', 0);
    }
}
