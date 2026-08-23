<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\Newsletter;
use App\Models\User;

final readonly class SendDueNewsletters
{
    public function __construct(private SendNewsletter $send) {}

    public function handle(User $actor): int
    {
        $sent = 0;
        Newsletter::query()->where('status', 'scheduled')->where('scheduled_for', '<=', now())->orderBy('scheduled_for')->each(function (Newsletter $newsletter) use ($actor, &$sent): void {
            $sent += $this->send->handle($actor, $newsletter);
        });

        return $sent;
    }
}
