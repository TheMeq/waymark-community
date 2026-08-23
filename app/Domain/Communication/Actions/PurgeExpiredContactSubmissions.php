<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\ContactSubmission;

final class PurgeExpiredContactSubmissions
{
    public function handle(): int
    {
        return ContactSubmission::query()->where('retention_expires_at', '<=', now())->delete();
    }
}
