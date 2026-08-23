<?php

namespace App\Domain\Governance\Queries;

use App\Domain\Governance\Models\CommitteeHubLink;
use App\Domain\Governance\Models\CommitteeMeeting;
use App\Domain\Governance\Models\CommitteeRole;
use App\Domain\Governance\Models\Document;

final class CommitteeHubContent
{
    public function get(): array
    {
        return [
            'contacts' => CommitteeRole::query()->with('person')->where('active', true)->orderBy('sort_order')->get(),
            'documents' => Document::query()->with('category')->where('visibility', 'committee')->orderBy('title')->get(),
            'meetings' => CommitteeMeeting::query()->with('minutesVersion')->orderByDesc('meeting_date')->get(),
            'links' => CommitteeHubLink::query()->where('active', true)->orderBy('sort_order')->get(),
        ];
    }
}
