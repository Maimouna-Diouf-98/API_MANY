<?php

namespace App\Mail;

use App\Domain\Auth\Models\Aggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AggregatorEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Aggregator $aggregator) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vérifiez votre adresse email — Many Portail',
        );
    }

    public function content(): Content
    {
        // Nom réel de la connexion utilisée (getConnectionName() peut renvoyer null
        // quand la connexion par défaut est définie par un middleware)
        $connection = $this->aggregator->getConnection()->getName();

        $verificationUrl = URL::temporarySignedRoute(
            'portal.verify.email',
            now()->addHours(24),
            [
                'id'   => $this->aggregator->id,
                'conn' => $connection,
                'hash' => sha1($this->aggregator->email),
            ]
        );

        return new Content(
            view: 'emails.aggregator-verify',
            with: [
                'aggregator'      => $this->aggregator,
                'verificationUrl' => $verificationUrl,
            ]
        );
    }
}