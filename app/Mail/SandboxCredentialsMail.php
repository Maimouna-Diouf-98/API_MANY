<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SandboxCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $legalName,
        public string $email,
        public string $password,
        public string $portalUrl,
        public string $verificationUrl,
        public int    $validHours = 72,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre accès de test Many est prêt');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sandbox-credentials');
    }
}
