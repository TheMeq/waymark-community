<?php

namespace App\Domain\Operations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['singleton_key', 'provider', 'tracking_id', 'enabled'])]
final class AnalyticsSetting extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $setting): void {
            $provider = (string) $setting->provider;
            $trackingId = trim((string) $setting->tracking_id);
            $valid = match ($provider) {
                'none' => $trackingId === '',
                'ga4' => preg_match('/\AG-[A-Z0-9]+\z/', $trackingId) === 1,
                'plausible' => filter_var($trackingId, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false,
                default => false,
            };

            if (! $valid) {
                throw ValidationException::withMessages(['tracking_id' => 'Choose an approved provider and enter a valid provider identifier.']);
            }

            $setting->tracking_id = $trackingId !== '' ? $trackingId : null;
        });
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
