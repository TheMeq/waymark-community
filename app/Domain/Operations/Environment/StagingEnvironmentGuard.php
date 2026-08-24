<?php

namespace App\Domain\Operations\Environment;

final readonly class StagingEnvironmentGuard
{
    public function __construct(private StagingMode $staging) {}

    public function apply(): void
    {
        if (! $this->staging->active()) {
            return;
        }

        if (! in_array(config('mail.default'), ['array', 'log'], true)) {
            config()->set('mail.default', 'log');
        }
    }
}
