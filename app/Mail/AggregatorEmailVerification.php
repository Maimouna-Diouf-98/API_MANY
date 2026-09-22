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
        $verificationUrl = URL::temporarySignedRoute(
            'portal.verify.email',
            now()->addHours(24),
            ['id' => $this->aggregator->id]
        );

        return new Content(
            view: 'emails.aggregator-verify',
            with: [
                'aggregator'       => $this->aggregator,
                'verificationUrl'  => $verificationUrl,
            ]
        );
    }
}