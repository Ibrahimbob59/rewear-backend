<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CharityCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $charity;
    public string $password;

    /**
     * Create a new message instance.
     */
    public function __construct(User $charity, string $password)
    {
        $this->charity = $charity;
        $this->password = $password;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Welcome to ReWear - Your Charity Account Credentials')
            ->view('emails.charity-credentials')
            ->with([
                'organizationName' => $this->charity->organization_name ?? $this->charity->name,
                'email' => $this->charity->email,
                'password' => $this->password,
            ]);
    }
}
