<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $type;
    public int $expiresInMinutes;

    /**
     * Create a new message instance.
     *
     * @param string $code
     * @param string $type 'registration' or 'login'
     */
    public function __construct(string $code, string $type = 'registration')
    {
        $this->code = $code;
        $this->type = $type;
        $this->expiresInMinutes = config('auth.email_verification.expires_minutes', 5);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->type === 'registration'
            ? 'Your ReWear Verification Code'
            : 'Your ReWear Login Code';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            text: 'emails.verification-code-text',
            with: [
                'code' => $this->code,
                'type' => $this->type,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
