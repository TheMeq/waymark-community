<?php

namespace App\Domain\Operations\Analytics;

final readonly class AnalyticsViewModel
{
    public function __construct(public string $provider, public string $trackingId) {}
}
