<?php

namespace App\Domain\Content\Presentation;

use App\Domain\Content\Data\SeoMetadata;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Queries\PublicBranding;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Support\Str;

final class PublicSeo
{
    public function __construct(private readonly PublicBranding $branding) {}

    public function home(SiteProfile $profile): SeoMetadata
    {
        $name = $this->siteName($profile);
        $logo = $this->absolute($this->branding->forProfile($profile)['logo_url'] ?? null);

        return new SeoMetadata(
            title: $name,
            description: 'Walks, weekends away and a welcoming local community.',
            canonical: route('home'),
            image: $this->absolute($profile->hero_default_path),
            structuredData: [[
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $name,
                'url' => route('home'),
                ...($logo === null ? [] : ['logo' => $logo]),
            ]],
        );
    }

    public function page(CmsPage $page): SeoMetadata
    {
        return new SeoMetadata(
            title: $page->seo_title ?: $page->title,
            description: $page->seo_description ?: $this->blocksExcerpt((array) $page->blocks),
            canonical: route('cms.show', $page->slug),
        );
    }

    public function news(NewsArticle $article, ?string $image = null): SeoMetadata
    {
        $title = $article->share_title ?: $article->title;
        $description = $article->share_description ?: ($article->summary ?: $this->blocksExcerpt((array) $article->blocks));
        $canonical = route('news.show', $article->slug);

        return new SeoMetadata(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: $article->expires_at?->isPast() ? 'noindex,follow' : 'index,follow',
            openGraphType: 'article',
            image: $image,
            structuredData: [[
                '@context' => 'https://schema.org',
                '@type' => 'NewsArticle',
                'headline' => $title,
                'description' => $description,
                'url' => $canonical,
                ...($article->publish_at === null ? [] : ['datePublished' => $article->publish_at->toAtomString()]),
                ...($article->updated_at === null ? [] : ['dateModified' => $article->updated_at->toAtomString()]),
                ...($image === null ? [] : ['image' => [$image]]),
            ]],
        );
    }

    public function event(Event $event, SiteProfile $profile, ?string $resolvedImageUrl = null): SeoMetadata
    {
        $canonical = match ($event->type) {
            EventType::Walk => route('walks.show', $event->slug),
            EventType::Social => route('socials.show', $event->slug),
            EventType::Holiday => route('holidays.show', $event->slug),
        };
        $description = $event->summary ?: Str::limit(trim((string) $event->description), 180);
        $location = $event->walk?->meeting_location_name;

        return new SeoMetadata(
            title: $event->title,
            description: $description,
            canonical: $canonical,
            image: $resolvedImageUrl,
            structuredData: [[
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                'name' => $event->title,
                'description' => $description,
                'url' => $canonical,
                'startDate' => $event->starts_at->toAtomString(),
                ...($event->ends_at === null ? [] : ['endDate' => $event->ends_at->toAtomString()]),
                'eventStatus' => $this->eventStatus($event->status),
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'organizer' => ['@type' => 'Organization', 'name' => $this->siteName($profile), 'url' => route('home')],
                ...($location === null ? [] : ['location' => ['@type' => 'Place', 'name' => $location]]),
                ...($resolvedImageUrl === null ? [] : ['image' => [$resolvedImageUrl]]),
            ]],
        );
    }

    public function album(SpecialAlbum $album): SeoMetadata
    {
        return new SeoMetadata(
            title: $album->title,
            description: $album->description ?: 'Community gallery album.',
            canonical: route('gallery.albums.show', $album),
        );
    }

    private function eventStatus(EventStatus $status): string
    {
        return match ($status) {
            EventStatus::Cancelled => 'https://schema.org/EventCancelled',
            EventStatus::Postponed => 'https://schema.org/EventPostponed',
            default => 'https://schema.org/EventScheduled',
        };
    }

    private function siteName(SiteProfile $profile): string
    {
        return $profile->group_name ?: 'Waymark Community';
    }

    private function blocksExcerpt(array $blocks): string
    {
        foreach ($blocks as $block) {
            foreach (['content', 'body'] as $field) {
                $text = trim(strip_tags((string) ($block[$field] ?? '')));
                if ($text !== '') {
                    return Str::limit($text, 180);
                }
            }
        }

        return 'Walks, weekends away and a welcoming local community.';
    }

    private function absolute(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url('/'.ltrim($url, '/'));
    }
}
