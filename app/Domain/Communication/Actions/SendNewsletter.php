<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Contracts\NewsletterDelivery;
use App\Domain\Communication\Models\Newsletter;
use App\Domain\Communication\Queries\NewsletterAudience;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class SendNewsletter
{
    public function __construct(private NewsletterAudience $audience, private NewsletterDelivery $delivery) {}

    public function handle(User $actor, Newsletter $newsletter): int
    {
        if (! $actor->hasCapability(ModuleCapability::ManageCommunications) || ! in_array($newsletter->status, ['draft', 'scheduled'], true) || ($newsletter->status === 'scheduled' && $newsletter->scheduled_for?->isFuture())) {
            throw ValidationException::withMessages(['newsletter' => 'This newsletter cannot be sent now.']);
        }
        $recipients = $this->audience->for($newsletter);
        foreach ($recipients as $recipient) {
            $this->delivery->send($newsletter, $recipient);
        }
        $newsletter->update(['status' => 'sent', 'sent_at' => now()]);

        return $recipients->count();
    }
}
