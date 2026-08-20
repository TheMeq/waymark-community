<?php

namespace Tests\Feature\Walks;

use App\Domain\Walks\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_tag_can_be_reused_by_looking_up_its_stable_name(): void
    {
        $tag = Tag::query()->create([
            'name' => 'Riverside',
        ]);

        $reusedTag = Tag::query()->where('name', 'Riverside')->sole();

        $this->assertTrue($reusedTag->is($tag));
    }
}
