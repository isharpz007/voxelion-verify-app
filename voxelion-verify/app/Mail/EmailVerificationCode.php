<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable delivering the 6-digit OTP. Synchronous (not queued) so delivery failures surface immediately.
 */
class EmailVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes = 5,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Voxelion verification code');
    }

    public function content(): Content
    {
        return new Content(html: 'emails.verification-code');
    }
}
