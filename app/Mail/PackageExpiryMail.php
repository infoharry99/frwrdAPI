<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PackageExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public int $daysLeft;

    public function __construct(int $daysLeft)
    {
        $this->daysLeft = $daysLeft;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "📅 Class Ending Soon – {$this->daysLeft} Day" . ($this->daysLeft > 1 ? 's' : '') . " Left",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.package_expiry',
        );
    }
}
