<?php

namespace Tests\Unit\Walks;

use App\Domain\Walks\Data\WalkFeaturedImage;
use Tests\TestCase;

final class WalkFeaturedImageTest extends TestCase
{
    public function test_resolves_an_existing_demo_image_to_safe_public_presentation_data(): void
    {
        $image = WalkFeaturedImage::resolve('/images/demo/hero-walkers.png');

        $this->assertNotNull($image);
        $this->assertSame('/images/demo/hero-walkers.png', $image->url);
        $this->assertSame('A group walking together across open moorland', $image->alt);
        $this->assertSame([
            'url' => '/images/demo/hero-walkers.png',
            'alt' => 'A group walking together across open moorland',
        ], $image->toArray());
    }

    public function test_rejects_demo_path_traversal_references(): void
    {
        $this->assertNull(WalkFeaturedImage::resolve('/images/demo/../private/member-photo.png'));
        $this->assertNull(WalkFeaturedImage::resolve('/images/demo/%2e%2e/private/member-photo.png'));
        $this->assertNull(WalkFeaturedImage::resolve('/images/demo/..\\private\\member-photo.png'));
    }

    public function test_rejects_unsupported_or_unavailable_references(): void
    {
        $this->assertNull(WalkFeaturedImage::resolve('walks/featured/member-photo.jpg'));
        $this->assertNull(WalkFeaturedImage::resolve('/storage/member-photo.jpg'));
        $this->assertNull(WalkFeaturedImage::resolve('C:\\private\\member-photo.jpg'));
        $this->assertNull(WalkFeaturedImage::resolve('https://example.com/member-photo.jpg'));
        $this->assertNull(WalkFeaturedImage::resolve('/images/demo/not-an-image.txt'));
        $this->assertNull(WalkFeaturedImage::resolve('/images/demo/missing-image.png'));
    }

    public function test_absent_image_reference_resolves_to_null(): void
    {
        $this->assertNull(WalkFeaturedImage::resolve(null));
        $this->assertNull(WalkFeaturedImage::resolve(''));
    }
}
