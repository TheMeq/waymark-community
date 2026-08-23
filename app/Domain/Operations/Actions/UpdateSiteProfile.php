<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Content\Models\NavigationItem;
use App\Domain\Operations\Models\BrandingConfigurationSnapshot;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateSiteProfile
{
    private const UPDATABLE_ATTRIBUTES = [
        'group_name',
        'short_name',
        'contact_email',
        'timezone',
        'locale',
        'distance_unit',
        'ascent_unit',
        'start_year',
        'logo_path',
        'favicon_path',
        'hero_default_path',
        'primary_colour',
        'accent_colour',
        'typography_option',
        'social_links',
        'terminology',
        'affiliation_name',
        'affiliation_url',
        'module_configuration',
    ];

    /** @param array<string, mixed> $validated */
    public function handle(array $validated, ?User $actor = null): SiteProfile
    {
        $this->validatePublicBranding($validated);

        return DB::transaction(function () use ($validated, $actor): SiteProfile {
            $profile = SiteProfile::query()
                ->lockForUpdate()
                ->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

            $previous = $profile->getAttributes();
            $profile->fill(Arr::only($validated, self::UPDATABLE_ATTRIBUTES));
            $profile->save();

            if ($actor !== null) {
                BrandingConfigurationSnapshot::query()->create(['actor_id' => $actor->id, 'previous_values' => $previous, 'new_values' => $profile->getAttributes()]);
            }

            return $profile->refresh();
        });
    }

    /** @param array<string, mixed> $values */
    private function validatePublicBranding(array $values): void
    {
        $allowedTerms = ['walks', 'members', 'join', 'holidays', 'gallery'];
        $terms = (array) ($values['terminology'] ?? []);
        if (array_diff(array_keys($terms), $allowedTerms) !== []) {
            throw ValidationException::withMessages(['terminology' => 'Use only the approved public terminology keys.']);
        }

        $links = (array) ($values['social_links'] ?? []);
        if (count($links) > 10) {
            throw ValidationException::withMessages(['social_links' => 'Add no more than ten social links.']);
        }
        foreach ($links as $label => $url) {
            if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 60 || ! is_string($url) || ! NavigationItem::isAllowedUrl($url)) {
                throw ValidationException::withMessages(['social_links' => 'Each social link needs a short label and safe URL.']);
            }
        }

        foreach (['logo_path', 'favicon_path', 'hero_default_path', 'affiliation_url'] as $attribute) {
            $url = $values[$attribute] ?? null;
            if (filled($url) && (! is_string($url) || ! NavigationItem::isAllowedUrl($url))) {
                throw ValidationException::withMessages([$attribute => 'Use a site path or secure external URL.']);
            }
        }
        if (isset($values['typography_option']) && ! in_array($values['typography_option'], ['instrument', 'system'], true)) {
            throw ValidationException::withMessages(['typography_option' => 'Choose an approved typography option.']);
        }
    }
}
