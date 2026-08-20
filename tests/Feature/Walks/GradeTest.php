<?php

namespace Tests\Feature\Walks;

use App\Domain\Walks\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_preserves_ordered_textual_meaning_when_its_optional_colour_is_absent(): void
    {
        $grade = Grade::query()->create([
            'display_order' => 10,
            'name' => 'Gentle',
            'description' => 'Mostly level paths at an easy pace.',
            'colour' => null,
        ]);

        $grade->refresh();

        $this->assertSame(10, $grade->display_order);
        $this->assertSame('Gentle', $grade->name);
        $this->assertSame('Mostly level paths at an easy pace.', $grade->description);
        $this->assertNull($grade->colour);
    }
}
