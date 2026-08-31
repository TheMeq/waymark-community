<?php

namespace App\Domain\Operations\Health;

use Illuminate\Support\Facades\Cache;

final readonly class AdminHealthAlert
{
    public function __construct(private SystemHealth $health) {}

    public function serious(bool $https): bool
    {
        return (bool) Cache::remember(
            'waymark.admin.health-alert.'.($https ? 'https' : 'http').'.v1',
            now()->addSeconds(max(1, (int) config('waymark.health_alert_cache_seconds', 60))),
            fn (): bool => $this->health->localReport($https)->serious(),
        );
    }
}
