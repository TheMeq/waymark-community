<?php

namespace App\Domain\Communication\Support;

final readonly class OutboundEmailStatus
{
    public function configured(): bool
    {
        return config('waymark.email.configured') === true;
    }

    public function unavailableMessage(): string
    {
        return 'Email delivery is not configured. This feature will be available after an administrator configures and tests outbound email.';
    }
}
