<?php

namespace App\Domain\Governance\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Governance\Mail\DocumentReviewReminderMail;
use App\Domain\Governance\Queries\DocumentsDueForReview;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final readonly class SendDocumentReviewReminders
{
    public function __construct(private DocumentsDueForReview $documents) {}

    public function handle(): int
    {
        $recipients = User::query()
            ->where('role', AccountRole::Administrator->value)
            ->where('account_status', 'active')
            ->whereNotNull('email_verified_at')
            ->get();
        if ($recipients->isEmpty()) {
            return 0;
        }

        $documents = $this->documents->get()
            ->filter(fn ($document): bool => $document->review_email_reminder
                && $document->category?->review_reminders_enabled
                && ($document->last_review_reminder_sent_at === null || $document->last_review_reminder_sent_at->lt(today())));
        foreach ($documents as $document) {
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(new DocumentReviewReminderMail($document));
            }
            $document->update(['last_review_reminder_sent_at' => now()]);
        }

        return $documents->count();
    }
}
