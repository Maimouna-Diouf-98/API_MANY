<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    private string $projectId;
    private string $accessToken;

    public function __construct()
    {
        $this->projectId   = config('firebase.project_id');
        $this->accessToken = $this->getAccessToken();
    }

 private function getAccessToken(): string
{
    $credentials = json_decode(
        file_get_contents(storage_path('app/firebase-credentials.json')),
        true
    );

    $now = time();
    $payload = [
        'iss'   => $credentials['client_email'],
        'sub'   => $credentials['client_email'],
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    ];

    $jwt      = $this->createJwt($payload, $credentials['private_key']);
    $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt,
    ]);

    $token = $response->json('access_token');

    if (!$token) {
        throw new \RuntimeException('FCM: token OAuth non obtenu : ' . $response->body());
    }

    return $token;
}

  private function createJwt(array $payload, string $privateKey): string
{
    $base64UrlEncode = function ($data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    };

    $header = $base64UrlEncode(json_encode([
        'alg' => 'RS256',
        'typ' => 'JWT',
    ]));

    $payloadEncoded = $base64UrlEncode(json_encode($payload));

    $data = $header . '.' . $payloadEncoded;

    openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    return $data . '.' . $base64UrlEncode($signature);
}

   public function sendToToken(
    string $fcmToken,
    string $title,
    string $body,
    array  $data = []
): bool {
    try {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        // Si data est vide, ne pas l'inclure du tout
      $message = [
    'token' => $fcmToken,
    'notification' => [
        'title' => $title,
        'body' => $body,
    ],
    'android' => [
        'priority' => 'HIGH',
        'notification' => [
            'sound' => 'default',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ],
    ],
    'apns' => [
        'payload' => [
            'aps' => [
                'sound' => 'default',
                'badge' => 1,
            ],
        ],
    ],
];

if (!empty($data)) {
    $message['data'] = [];

    foreach ($data as $key => $value) {
        $message['data'][(string) $key] = (string) $value;
    }
}

Log::info('FCM PAYLOAD', ['message' => $message]);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type'  => 'application/json',
        ])->post($url, ['message' => $message]);

        if ($response->successful()) {
            Log::info("FCM v1 notification envoyée : {$title}");
            return true;
        }

        Log::error("FCM v1 error: " . $response->body());
        return false;

    } catch (\Exception $e) {
        Log::error("FCM v1 exception: " . $e->getMessage());
        return false;
    }
}

 public function sendPaymentRequest(
    string $fcmToken,
    string $transactionId,
    string $amount,              // montant brut, ex: "5000"
    string $merchantName,
    string $currency = 'XOF'
): bool {
    $display = number_format((float) $amount, 0, ',', ' ');

    $title = "Demande de paiement de {$merchantName}";
    $body  = "{$merchantName} vous demande de régler {$display} {$currency}. "
           . "Ouvrez Many pour confirmer avec votre code.";

    return $this->sendToToken($fcmToken, $title, $body, [
        'type'           => 'PAYMENT_REQUEST',
        'transaction_id' => $transactionId,
        'amount'         => $amount,
        'currency'       => $currency,
        'merchant_name'  => $merchantName,
    ]);
}

    public function sendDisbursementConfirmation(
        string $fcmToken,
        string $disbursementId,
        string $amount,
        string $recipientPhone
    ): bool {
        return $this->sendToToken(
            $fcmToken,
            '💸 Confirmation décaissement',
            "Confirmez l'envoi de {$amount} XOF à {$recipientPhone}",
            [
                'type'            => 'DISBURSEMENT_CONFIRMATION',
                'disbursement_id' => $disbursementId,
                'amount'          => $amount,
                'recipient_phone' => $recipientPhone,
            ]
        );
    }

    public function sendMoneyReceived(
        string $fcmToken,
        string $amount,
        string $disbursementId
    ): bool {
        return $this->sendToToken(
            $fcmToken,
            '💰 Argent reçu !',
            "Vous avez reçu {$amount} XOF sur votre compte Many.",
            [
                'type'            => 'MONEY_RECEIVED',
                'disbursement_id' => $disbursementId,
                'amount'          => $amount,
            ]
        );
    }
    public function sendBulkConfirmation(
    string $fcmToken,
    string $bulkId,
    string $label,
    int    $recipients,
    string $amount,
    string $fee
): bool {
    $total   = number_format((float) $amount + (float) $fee, 0, ',', ' ');
    $display = number_format((float) $amount, 0, ',', ' ');

    return $this->sendToToken(
        $fcmToken,
        'Paiement de masse à confirmer',
        "{$label} : {$recipients} bénéficiaires, {$display} XOF (total débité {$total} XOF). "
        . "Ouvrez Many pour confirmer avec votre code PIN.",
        [
            'type'             => 'BULK_PAYMENT_CONFIRMATION',
            'bulk_id'          => $bulkId,
            'total_recipients' => (string) $recipients,
            'amount'           => $amount,
            'fee'              => $fee,
        ]
    );
}

public function sendBulkMoneyReceived(
    string  $fcmToken,
    string  $bulkId,
    string  $amount,
    string  $senderName,
    ?string $reference = null
): bool {
    $display = number_format((float) $amount, 0, ',', ' ');

    return $this->sendToToken(
        $fcmToken,
        'Argent reçu',
        "Vous avez reçu {$display} XOF de {$senderName} sur votre compte Many.",
        [
            'type'      => 'MONEY_RECEIVED',
            'bulk_id'   => $bulkId,
            'amount'    => $amount,
            'reference' => $reference ?? '',
        ]
    );
}
}