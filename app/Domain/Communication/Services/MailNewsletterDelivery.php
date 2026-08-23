<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Contracts\NewsletterDelivery;
use App\Domain\Communication\Mail\NewsletterMail;
use App\Domain\Communication\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class MailNewsletterDelivery implements NewsletterDelivery
{
    public function send(Newsletter $newsletter, User $recipient): void
    {
        Mail::to($recipient->email)->send(new NewsletterMail($newsletter, $recipient));
    }
}
