<?php

namespace App\Domain\Communication\Mail;

use App\Domain\Communication\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ContactSubmissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(replyTo: [$this->submission->email], subject: 'Website contact: '.$this->submission->department->public_label);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-submission');
    }
}
