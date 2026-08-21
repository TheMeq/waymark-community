<?php

namespace App\Domain\Accounts\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdministratorAccessChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $account,
        private readonly string $change,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $description = $this->change === 'created' ? 'created as' : 'promoted to';

        return (new MailMessage)
            ->subject('Administrator access changed')
            ->line($this->account->name.' was '.$description.' an Administrator.');
    }
}
