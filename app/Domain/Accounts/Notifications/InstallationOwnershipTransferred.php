<?php

namespace App\Domain\Accounts\Notifications;

use App\Domain\Communication\Support\TransactionalEmailCopy;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InstallationOwnershipTransferred extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $otherOwner,
        private readonly bool $recipientIsNewOwner,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->recipientIsNewOwner
            ? 'You are now the Installation Owner for this Waymark Community installation.'
            : 'You are no longer the Installation Owner for this Waymark Community installation.';
        $copy = app(TransactionalEmailCopy::class)->for('installation_ownership_transferred', [
            'ownership_message' => $message,
            'other_account_name' => $this->otherOwner->name,
        ]);

        return (new MailMessage)
            ->subject($copy['subject'])
            ->view('mail.transactional', ['copy' => $copy]);
    }
}
