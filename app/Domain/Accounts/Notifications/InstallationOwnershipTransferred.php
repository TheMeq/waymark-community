<?php

namespace App\Domain\Accounts\Notifications;

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

        return (new MailMessage)
            ->subject('Installation ownership changed')
            ->line($message)
            ->line('The other account is '.$this->otherOwner->name.'.');
    }
}
