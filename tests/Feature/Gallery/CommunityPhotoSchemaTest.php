<?php

namespace Tests\Feature\Gallery;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CommunityPhotoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_storage_references_are_limited_to_generated_paths_on_the_configured_disk(): void
    {
        $safe = PhotoStorageReference::from('local', 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/source.jpg');

        $this->assertSame('local', $safe->disk);
        $this->assertSame('community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/source.jpg', $safe->path);
        $this->assertFalse(PhotoStorageReference::isSafe('local', '../private/photo.jpg'));
        $this->assertFalse(PhotoStorageReference::isSafe('local', 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/../../photo.jpg'));
        $this->assertFalse(PhotoStorageReference::isSafe('public', 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/source.jpg'));
    }

    public function test_photo_requires_exactly_one_event_or_special_album_at_the_application_boundary(): void
    {
        $uploader = User::factory()->create();
        $event = Event::factory()->create();
        $album = SpecialAlbum::query()->create([
            'title' => 'Summer weekend',
            'slug' => 'summer-weekend',
        ]);

        $this->expectException(\LogicException::class);

        CommunityPhoto::query()->create($this->photoAttributes($uploader, [
            'event_id' => $event->id,
            'special_album_id' => $album->id,
        ]));
    }

    public function test_database_rejects_photos_without_a_source_context_or_with_two_contexts(): void
    {
        $uploader = User::factory()->create();
        $event = Event::factory()->create();
        $album = SpecialAlbum::query()->create([
            'title' => 'Summer weekend',
            'slug' => 'summer-weekend',
        ]);

        foreach ([
            ['event_id' => null, 'special_album_id' => null],
            ['event_id' => $event->id, 'special_album_id' => $album->id],
        ] as $association) {
            try {
                DB::table('community_photos')->insert([
                    ...$this->photoAttributes($uploader, $association),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->fail('The database allowed an orphaned or multiply-associated community photo.');
            } catch (QueryException) {
                $this->assertDatabaseCount('community_photos', 0);
            }
        }
    }

    /** @param array<string, int|null> $association
     *  @return array<string, mixed>
     */
    private function photoAttributes(User $uploader, array $association): array
    {
        return [
            'event_id' => $association['event_id'] ?? null,
            'special_album_id' => $association['special_album_id'] ?? null,
            'uploader_id' => $uploader->id,
            'media_type' => 'image',
            'processing_status' => 'pending',
            'storage_disk' => 'local',
            'source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/source.jpg',
            'moderation_status' => 'pending',
        ];
    }
}
