<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Actions\PublishEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\DownloadWalkGpx;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Actions\StoreWalkGpx;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class GpxUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_gpx_is_saved_to_private_generated_storage_with_bounded_route_data(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');

        $walk = $this->walk();
        $upload = UploadedFile::fake()->createWithContent('my-scenic-route.gpx', $this->validGpx());

        $stored = app(StoreWalkGpx::class)->handle($walk, $upload);

        $this->assertMatchesRegularExpression('#^walks/gpx/[0-9a-f-]+\.gpx$#', $stored->gpx_path);
        $this->assertNotSame('my-scenic-route.gpx', $stored->gpx_path);
        Storage::disk('local')->assertExists($stored->gpx_path);
        $this->assertSame(2, $stored->gpx_derived_metadata['point_count']);
        $this->assertSame([[52.95, -1.16], [52.96, -1.15]], $stored->gpx_derived_metadata['route_points']);
        $this->assertSame([[52.95, -1.16], [52.96, -1.15]], [
            $stored->gpx_derived_metadata['bounds']['south_west'],
            $stored->gpx_derived_metadata['bounds']['north_east'],
        ]);
        $this->assertEqualsWithDelta(1298, $stored->gpx_derived_metadata['distance_metres'], 2);
    }

    public function test_invalid_gpx_leaves_the_existing_walk_and_private_storage_unchanged(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        $walk = $this->walk();
        $original = $walk->only(['gpx_path', 'gpx_derived_metadata']);
        $upload = UploadedFile::fake()->createWithContent('route.gpx', '<!DOCTYPE gpx SYSTEM "https://attacker.test/route.dtd"><gpx/>');

        try {
            app(StoreWalkGpx::class)->handle($walk, $upload);
            $this->fail('A GPX document with a doctype was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('gpx', $exception->errors());
            $this->assertStringContainsString('DOCTYPE', $exception->errors()['gpx'][0]);
        }

        $this->assertSame($original, $walk->fresh()->only(['gpx_path', 'gpx_derived_metadata']));
        $this->assertSame([], Storage::disk('local')->allFiles('walks/gpx'));
    }

    public function test_gpx_upload_rejects_an_unexpected_extension_before_storage(): void
    {
        Storage::fake('local');
        $walk = $this->walk();

        try {
            app(StoreWalkGpx::class)->handle($walk, UploadedFile::fake()->createWithContent('route.xml', $this->validGpx()));
            $this->fail('A non-GPX extension was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('.gpx', $exception->errors()['gpx'][0]);
            $this->assertSame([], Storage::disk('local')->allFiles('walks/gpx'));
        }
    }

    public function test_gpx_upload_rejects_empty_and_out_of_range_routes_before_storage(): void
    {
        Storage::fake('local');
        $walk = $this->walk();

        foreach ([
            '<gpx version="1.1"/>',
            '<gpx version="1.1"><trkpt lat="91" lon="0"/></gpx>',
        ] as $document) {
            try {
                app(StoreWalkGpx::class)->handle($walk, UploadedFile::fake()->createWithContent('route.gpx', $document));
                $this->fail('An invalid GPX route was accepted.');
            } catch (ValidationException) {
                $this->assertNull($walk->fresh()->gpx_path);
                $this->assertSame([], Storage::disk('local')->allFiles('walks/gpx'));
            }
        }
    }

    public function test_gpx_parser_bounds_the_map_route_payload_while_retaining_the_route_endpoints(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        config()->set('walks.gpx.map_route_points', 2);

        $stored = app(StoreWalkGpx::class)->handle($this->walk(), UploadedFile::fake()->createWithContent('route.gpx', <<<'GPX'
<gpx version="1.1"><trk><trkseg><trkpt lat="52.95" lon="-1.16"/><trkpt lat="52.955" lon="-1.155"/><trkpt lat="52.96" lon="-1.15"/></trkseg></trk></gpx>
GPX));

        $this->assertSame(3, $stored->gpx_derived_metadata['point_count']);
        $this->assertSame([[52.95, -1.16], [52.96, -1.15]], $stored->gpx_derived_metadata['route_points']);
    }

    public function test_gpx_parser_keeps_both_route_endpoints_when_a_too_low_map_point_limit_is_configured(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        config()->set('walks.gpx.map_route_points', 1);

        $stored = app(StoreWalkGpx::class)->handle($this->walk(), UploadedFile::fake()->createWithContent('route.gpx', $this->validGpx()));

        $this->assertSame([[52.95, -1.16], [52.96, -1.15]], $stored->gpx_derived_metadata['route_points']);
    }

    public function test_download_seam_serves_the_private_generated_gpx_without_using_the_client_filename(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        $stored = app(StoreWalkGpx::class)->handle($this->walk(), UploadedFile::fake()->createWithContent('untrusted-name.gpx', $this->validGpx()));

        $response = app(DownloadWalkGpx::class)->handle($stored);

        $this->assertSame('application/gpx+xml', $response->headers->get('content-type'));
        $this->assertStringContainsString('walk-route.gpx', (string) $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('untrusted-name', (string) $response->headers->get('content-disposition'));
    }

    public function test_walk_can_publish_without_meeting_coordinates_or_a_gpx_file(): void
    {
        $walk = $this->walk();

        app(PublishEvent::class)->handle($walk->event);

        $this->assertTrue($walk->event->fresh()->is_public);
        $this->assertNull($walk->fresh()->gpx_path);
        $this->assertNull($walk->fresh()->latitude);
        $this->assertNull($walk->fresh()->longitude);
    }

    public function test_gpx_storage_is_cleaned_up_when_persisting_derived_data_fails(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        $walk = $this->walk();
        Walk::saving(static function (): void {
            throw new \RuntimeException('Database write failed.');
        });

        try {
            app(StoreWalkGpx::class)->handle($walk, UploadedFile::fake()->createWithContent('route.gpx', $this->validGpx()));
            $this->fail('A failed persistence operation retained the generated GPX file.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Database write failed.', $exception->getMessage());
            $this->assertSame([], Storage::disk('local')->allFiles('walks/gpx'));
        } finally {
            Walk::flushEventListeners();
        }
    }

    private function walk(): Walk
    {
        return app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_id' => User::factory()->create()->id,
        ]);
    }

    private function validGpx(): string
    {
        return <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Waymark test" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><trkseg><trkpt lat="52.9500" lon="-1.1600"/><trkpt lat="52.9600" lon="-1.1500"/></trkseg></trk>
</gpx>
GPX;
    }
}
