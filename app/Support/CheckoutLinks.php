<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;

/**
 * Liens de la page de paiement (checkout).
 *
 * Les identifiants de transaction sont des nombres à 10 chiffres, donc devinables :
 * la page n'est accessible que par un lien signé (HMAC de l'URL, expiration incluse).
 */
class CheckoutLinks
{
    /** Délai pour payer : identique à la fenêtre de confirmation côté API (10 minutes). */
    public const PAYMENT_WINDOW_MINUTES = 10;

    /** Le lien survit un peu au paiement pour pouvoir afficher l'état « expiré ». */
    private const LINK_LIFETIME_MINUTES = 30;

    public static function url(string $env, string $transactionId, CarbonInterface $createdAt): string
    {
        return URL::temporarySignedRoute('checkout.show', self::expiry($createdAt), self::params($env, $transactionId));
    }

    public static function statusUrl(string $env, string $transactionId, CarbonInterface $createdAt): string
    {
        return URL::temporarySignedRoute('checkout.status', self::expiry($createdAt), self::params($env, $transactionId));
    }

    private static function expiry(CarbonInterface $createdAt): CarbonInterface
    {
        return $createdAt->copy()->addMinutes(self::LINK_LIFETIME_MINUTES);
    }

    private static function params(string $env, string $transactionId): array
    {
        return ['env' => strtolower($env), 'transactionId' => $transactionId];
    }
}
