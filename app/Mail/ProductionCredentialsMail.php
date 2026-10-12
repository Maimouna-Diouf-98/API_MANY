<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductionCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $legalName,
        public string $email,
        public string $clientId,
        public string $clientSecret,
        public string $password,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Vos accès production Many sont prêts !',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.production-credentials');
    }
}