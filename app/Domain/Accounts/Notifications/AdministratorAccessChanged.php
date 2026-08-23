<?php

namespace App\Domain\Accounts\Notifications;

use App\Domain\Communication\Support\TransactionalEmailCopy;
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
        $copy = app(TransactionalEmailCopy::class)->for('administrator_access_changed', [
            'account_name' => $this->account->name,
            'change_description' => $description,
        ]);

        return (new MailMessage)
            ->subject($copy['subject'])
            ->view('mail.transactional', ['copy' => $copy]);
    }
}
