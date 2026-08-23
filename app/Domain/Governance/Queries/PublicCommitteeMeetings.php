<?php

namespace App\Domain\Governance\Queries;

use App\Domain\Governance\Models\CommitteeMeeting;
use Illuminate\Support\Collection;

final class PublicCommitteeMeetings
{
    public function get(): Collection
    {
        return CommitteeMeeting::query()->with('minutesVersion.document')->where('visibility', 'public')->orderByDesc('meeting_date')->get();
    }
}
