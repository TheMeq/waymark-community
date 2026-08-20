<?php

namespace Tests\Feature\Walks;

use App\Domain\Walks\Actions\UpdateWalkFieldSettings;
use App\Domain\Walks\Models\WalkFieldSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WalkFieldSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabling_an_optional_field_preserves_the_other_optional_field_settings(): void
    {
        $settings = app(UpdateWalkFieldSettings::class)->handle([
            'what3words' => true,
            'gpx' => true,
        ]);

        $updatedSettings = app(UpdateWalkFieldSettings::class)->handle([
            'what3words' => false,
        ]);

        $this->assertTrue($settings->is($updatedSettings));
        $this->assertFalse($updatedSettings->isEnabled('what3words'));
        $this->assertTrue($updatedSettings->isEnabled('gpx'));
        $this->assertSame(1, WalkFieldSettings::query()->count());
    }

    public function test_database_rejects_a_second_walk_field_settings_record(): void
    {
        $settings = app(UpdateWalkFieldSettings::class)->handle([]);

        try {
            DB::table('walk_field_settings')->insert([
                'id' => 2,
                'field_configuration' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('The database accepted a second walk field settings record.');
        } catch (QueryException) {
            $this->assertSame(1, WalkFieldSettings::query()->count());
            $this->assertTrue($settings->is(WalkFieldSettings::query()->sole()));
        }
    }
}
