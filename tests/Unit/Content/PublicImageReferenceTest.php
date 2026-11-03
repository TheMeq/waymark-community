<?php

namespace Tests\Unit\Content;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Rules\PublicImageReferenceRule;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicImageReferenceTest extends TestCase
{
    #[DataProvider('allowedReferences')]
    public function test_allows_only_secure_external_urls_and_safe_root_relative_site_paths(string $reference): void
    {
        $this->assertTrue(PublicImageReference::isAllowed($reference));
        $this->assertSame($reference, PublicImageReference::resolve($reference));
    }

    /** @return array<string, array{string}> */
    public static function allowedReferences(): array
    {
        return [
            'secure external URL' => ['https://images.example.org/walks/ridge.webp'],
            'secure URL with query' => ['https://cdn.example.org/image?id=42&size=large'],
            'root-relative image' => ['/images/walks/ridge.webp'],
            'approved demo compatibility' => ['/images/demo/hero-walkers.png'],
        ];
    }

    public function test_resolves_root_relative_references_with_the_application_prefix(): void
    {
        URL::forceRootUrl('https://example.org/demo-site/ndwg');
        URL::forceScheme('https');

        $this->assertSame(
            '/demo-site/ndwg/images/demo/hero-walkers.png',
            PublicImageReference::resolve('/images/demo/hero-walkers.png'),
        );
    }

    public function test_validation_rule_uses_the_same_reference_boundary(): void
    {
        $safe = Validator::make(
            ['image' => 'https://images.example.org/walk.jpg'],
            ['image' => [new PublicImageReferenceRule]],
        );
        $unsafe = Validator::make(
            ['image' => '/application/.env'],
            ['image' => [new PublicImageReferenceRule]],
        );

        $this->assertFalse($safe->fails());
        $this->assertTrue($unsafe->fails());
    }

    #[DataProvider('unsafeReferences')]
    public function test_rejects_unsafe_or_non_web_references(mixed $reference): void
    {
        $this->assertFalse(PublicImageReference::isAllowed($reference));
        $this->assertNull(PublicImageReference::resolve($reference));

        if ($reference !== null && $reference !== '') {
            $validator = Validator::make(
                ['featured_image_external_url' => $reference],
                ['featured_image_external_url' => [new PublicImageReferenceRule]],
            );

            $this->assertTrue($validator->fails());
            $this->assertArrayHasKey('featured_image_external_url', $validator->errors()->toArray());
        }
    }

    /** @return array<string, array{mixed}> */
    public static function unsafeReferences(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'insecure HTTP' => ['http://images.example.org/walk.jpg'],
            'protocol relative' => ['//images.example.org/walk.jpg'],
            'plain traversal' => ['/images/../private/walk.jpg'],
            'encoded traversal' => ['/images/%2e%2e/private/walk.jpg'],
            'double encoded traversal' => ['/images/%252e%252e/private/walk.jpg'],
            'encoded backslash traversal' => ['/images/%252e%252e%255cprivate%255cwalk.jpg'],
            'Windows drive path' => ['C:\\private\\member-photo.jpg'],
            'Windows UNC path' => ['\\\\server\\share\\member-photo.jpg'],
            'Unix filesystem path' => ['/var/www/member-photo.jpg'],
            'protected storage path' => ['/storage/site-media/member-photo.jpg'],
            'protected application path' => ['/application/storage/member-photo.jpg'],
            'file scheme' => ['file:///var/www/member-photo.jpg'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:image/png;base64,AAAA'],
            'leading whitespace' => [' https://images.example.org/walk.jpg'],
            'trailing whitespace' => ['https://images.example.org/walk.jpg '],
            'embedded whitespace' => ["https://images.example.org/walk\n.jpg"],
            'invalid percent encoding' => ['/images/walk%2.jpg'],
            'over maximum length' => ['https://images.example.org/'.str_repeat('a', 2025)],
            'non-string' => [['/images/walk.jpg']],
        ];
    }
}
