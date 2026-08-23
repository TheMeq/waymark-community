<?php

namespace App\Domain\Communication\Mail;

use App\Domain\Communication\Models\Newsletter;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Newsletter $newsletter, public User $recipient) {}
    public function envelope(): Envelope { return new Envelope(subject: $this->newsletter->subject); }
    public function content(): Content { return new Content(view: 'mail.newsletter'); }
}
