<?php

namespace App\Domain\Operations\Analytics;

final class CampaignParameterFilter
{
    private const ALLOWED = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, string>
     */
    public function from(array $parameters): array
    {
        $campaign = [];

        foreach (self::ALLOWED as $key) {
            $value = $parameters[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value !== '' && mb_strlen($value) <= 100 && preg_match('/\A[\pL\pN _.\-~:@+\/]+\z/u', $value) === 1) {
                $campaign[$key] = $value;
            }
        }

        return $campaign;
    }
}
