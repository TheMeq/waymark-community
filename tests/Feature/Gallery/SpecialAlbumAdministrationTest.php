<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Actions\DeleteSpecialAlbum;
use App\Domain\Gallery\Actions\SaveSpecialAlbum;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Filament\Pages\PhotoModeration;
use App\Filament\Resources\SpecialAlbumResource;
use App\Filament\Resources\SpecialAlbumResource\Pages\CreateSpecialAlbum;
use App\Filament\Resources\SpecialAlbumResource\Pages\EditSpecialAlbum;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class SpecialAlbumAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_create_and_edit_an_album_through_the_filament_resource(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        Livewire::actingAs($administrator)->test(CreateSpecialAlbum::class)
            ->fillForm(['title' => 'Summer memories', 'slug' => 'summer-memories', 'description' => 'Photos beyond a single event.'])
            ->call('create')
            ->assertHasNoFormErrors();

        $album = SpecialAlbum::query()->sole();

        Livewire::actingAs($administrator)->test(EditSpecialAlbum::class, ['record' => $album->getRouteKey()])
            ->fillForm(['title' => 'Summer highlights', 'slug' => 'summer-highlights', 'description' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('special_albums', ['id' => $album->id, 'title' => 'Summer highlights', 'slug' => 'summer-highlights', 'description' => null]);
    }

    public function test_album_administration_requires_its_dedicated_capability_at_page_and_action_boundaries(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);

        $this->assertTrue($administrator->hasCapability(ModuleCapability::ManageSpecialAlbums));
        $this->assertFalse($moderator->hasCapability(ModuleCapability::ManageSpecialAlbums));
        $this->actingAs($moderator);
        $this->assertFalse(SpecialAlbumResource::canViewAny());

        $this->expectException(AuthorizationException::class);
        app(SaveSpecialAlbum::class)->handle($moderator, null, 'Forbidden album', 'forbidden-album', null);
    }

    public function test_album_slugs_are_unique_when_created_or_edited(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        app(SaveSpecialAlbum::class)->handle($administrator, null, 'First album', 'shared-slug', null);

        $this->expectException(ValidationException::class);
        app(SaveSpecialAlbum::class)->handle($administrator, null, 'Second album', 'shared-slug', null);
    }

    public function test_an_empty_album_can_be_deleted_but_a_referenced_album_is_retained_with_a_validation_error(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $empty = app(SaveSpecialAlbum::class)->handle($administrator, null, 'Empty album', 'empty-album', null);
        $referenced = app(SaveSpecialAlbum::class)->handle($administrator, null, 'Referenced album', 'referenced-album', null);
        $photo = $this->publicAlbumPhoto($referenced);

        $this->assertTrue(app(DeleteSpecialAlbum::class)->handle($administrator, $empty));
        $this->assertDatabaseMissing('special_albums', ['id' => $empty->id]);

        try {
            app(DeleteSpecialAlbum::class)->handle($administrator, $referenced);
            $this->fail('A referenced Special Album was deleted.');
        } catch (ValidationException $exception) {
            $this->assertSame('This album cannot be deleted while photos reference it.', $exception->errors()['album'][0]);
        }

        $this->assertDatabaseHas('special_albums', ['id' => $referenced->id]);
        $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'special_album_id' => $referenced->id]);
    }

    public function test_a_new_album_is_available_to_upload_moderation_and_public_gallery_boundaries(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $uploader = User::factory()->create();
        $album = app(SaveSpecialAlbum::class)->handle($administrator, null, 'Season highlights', 'season-highlights', 'Selected community memories.');

        $this->actingAs($uploader)->get(route('community-photos.upload.create'))
            ->assertOk()
            ->assertSee('album:'.$album->id, false);

        $moderation = Livewire::actingAs($moderator)->test(PhotoModeration::class);
        $this->assertArrayHasKey('album:'.$album->id, $moderation->instance()->contextOptions());

        $photo = $this->publicAlbumPhoto($album);
        Storage::disk('local')->put($photo->processed_variants['master'], 'safe image bytes');

        $this->get(route('gallery.albums.show', $album))->assertOk()->assertSeeText('Season highlights');
        $this->get(route('gallery.index'))->assertOk()->assertSeeText('Season highlights');
    }

    private function publicAlbumPhoto(SpecialAlbum $album): CommunityPhoto
    {
        $path = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg';

        return CommunityPhoto::query()->create([
            'special_album_id' => $album->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image',
            'processing_status' => 'complete',
            'storage_disk' => 'local',
            'source_path' => $path,
            'processed_variants' => ['master' => $path],
            'caption' => 'Album memory',
            'moderation_status' => 'approved',
            'published_at' => now()->subMinute(),
        ]);
    }
}
