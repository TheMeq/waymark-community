<?php

namespace App\Domain\Governance\Queries;

use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Governance\Models\CommitteeHubLink;
use App\Domain\Governance\Models\CommitteeMeeting;
use App\Domain\Governance\Models\CommitteeRole;
use App\Models\User;

final readonly class CommitteeHubContent
{
    public function __construct(private AvailableDocuments $available) {}

    public function get(User $actor): array
    {
        return [
            'contacts' => CommitteeRole::query()->with('person')->where('active', true)->orderBy('sort_order')->get(),
            'documents' => $this->available->query($actor)->with(['category', 'currentVersion'])->where('visibility', 'committee')->orderBy('title')->get(),
            'meetings' => CommitteeMeeting::query()->with('minutesVersion')->orderByDesc('meeting_date')->get(),
            'links' => CommitteeHubLink::query()->where('active', true)->orderBy('sort_order')->get()->each(
                fn (CommitteeHubLink $link): CommitteeHubLink => $link->setAttribute('url', PublicUrl::resolve($link->url)),
            ),
        ];
    }
}
