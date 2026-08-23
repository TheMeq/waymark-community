<?php

namespace App\Domain\Governance\Mail;

use App\Domain\Governance\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class DocumentReviewReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Document review due: '.$this->document->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.document-review-reminder');
    }
}
