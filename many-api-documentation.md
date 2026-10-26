# Many API — Documentation d'intégration
> Version 1.0 · Devise : XOF · Base URL sandbox : `http://localhost:8000/api/v1` · Base URL production : `https://api.many.sn/api/v1`

---

## Sommaire

1. [Authentification](#1-authentification)
2. [Headers requis](#2-headers-requis)
3. [Transactions pendantes (app mobile)](#3-transactions-pendantes-app-mobile)
4. [Confirmer un paiement (app mobile)](#4-confirmer-un-paiement-app-mobile)
5. [Collections](#5-collections)
6. [Disbursements](#6-disbursements)
7. [Bulk Payments](#7-bulk-payments)
8. [Sous-marchands](#8-sous-marchands)
9. [Balance](#9-balance)
10. [Enregistrer FCM Token](#10-enregistrer-fcm-token)
11. [Notifications push FCM](#11-notifications-push-fcm)
12. [Webhooks](#12-webhooks)
13. [Codes d'erreur](#13-codes-derreur)
14. [Flux complets](#14-flux-complets)

---

## 1. Authentification

Générez un token Bearer à partir de vos credentials d'application.

```
POST /auth/token
```

**Headers :**
```
X-Many-Client-Id: agg_sandbox_xxxxxxxxxxxx  (sandbox)
X-Many-Client-Id: agg_live_xxxxxxxxxxxx     (production)
Content-Type: application/json
```

**Body :**
```json
{
    "client_id":     "agg_sandbox_xxxxxxxxxxxx",
    "client_secret": "votre_client_secret_48_caracteres",
    "grant_type":    "client_credentials"
}
```

**Réponse 200 :**
```json
{
    "access_token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type":   "Bearer",
    "expires_in":   3600,
    "environment":  "SANDBOX"
}
```

> ⚠️ Le token expire après **1 heure**. Régénérez-le avant expiration.

---

## 2. Headers requis

Toutes les requêtes authentifiées nécessitent ces headers :

```
Authorization:   Bearer votre_access_token
X-Many-Client-Id: agg_sandbox_xxx  (ou agg_live_xxx)
Content-Type:    application/json
Accept:          application/json
```

Le préfixe du `client_id` détermine l'environnement :
- `agg_sandbox_` → Base de données sandbox (tests)
- `agg_live_`    → Base de données production (vrais mouvements)

---

## 3. Transactions pendantes (app mobile)

> Endpoint **public** — Appelé par l'app mobile Many pour récupérer les demandes de paiement en attente pour un client.

```
GET /transactions/pending/{phone}
```

**Paramètre :** `phone` = numéro du client (ex: `+221774825025`)

**Réponse 200 :**
```json
{
    "pending_count": 1,
    "transactions": [
        {
            "transaction_id":  "2831692691",
            "amount":          "5000.00",
            "currency":        "XOF",
            "merchant_name":   "Maya Store SARL",
            "order_reference": "CMD-001",
            "created_at":      "2026-09-18T10:00:00.000000Z",
            "expires_at":      "2026-09-18T10:10:00.000000Z"
        }
    ]
}
```

> ℹ️ L'app mobile doit **poller cet endpoint** toutes les 10-30 secondes OU écouter la notification push FCM pour déclencher l'appel.

---

## 4. Confirmer un paiement (app mobile)

> Endpoint **public** — Appelé par l'app mobile Many après que le client a scanné le QR code et saisi son mpin.

```
POST /transactions/{transaction_id}/confirm
```

**Body :**
```json
{
    "customer_phone": "+221774825025",
    "mpin":           "1234"
}
```

**Réponse 200 :**
```json
{
    "transaction_id": "2831692691",
    "status":         "PROCESSING",
    "message":        "Paiement en cours de traitement."
}
```

**Erreurs possibles :**

| Code | HTTP | Description |
|------|------|-------------|
| `TRANSACTION_NOT_FOUND` | 404 | Transaction introuvable |
| `TRANSACTION_ALREADY_PROCESSED` | 409 | Déjà traitée |
| `TRANSACTION_EXPIRED` | 410 | Expirée (> 10 min) |
| `INVALID_MPIN` | 401 | PIN incorrect |
| `CUSTOMER_MPIN_NOT_SET` | 422 | PIN non configuré |

---

## 5. Collections

### 5.1 Créer une collection

```
POST /transactions
```

**Body :**
```json
{
    "sub_merchant_id": "uuid-du-sous-marchand",
    "amount":          5000,
    "currency":        "XOF",
    "customer_phone":  "+221774825025",
    "order_reference": "CMD-2026-001",
    "idempotency_key": "CMD-2026-001-v1"
}
```

**Règles :**
- `amount` : entier, minimum 100 XOF
- `customer_phone` : doit avoir un compte Many actif (production)
- `customer_phone` sandbox : `+221770000001` à `+221770000099`
- `idempotency_key` : optionnel, évite les doublons

**Réponse 201 :**
```json
{
    "transaction_id":    "2831692691",
    "sub_merchant_id":   "uuid-du-sous-marchand",
    "aggregator_id":     "uuid-de-l-agregateur",
    "type":              "COLLECTION",
    "status":            "PENDING",
    "amount":            "5000.00",
    "fees": {
        "many_fee":      "50.00"
    },
    "net_to_aggregator": "4950.00",
    "currency":          "XOF",
    "environment":       "PRODUCTION",
    "qr_code":           "base64_svg_ici...",
    "qr_code_format":    "base64/svg+xml",
    "qr_expires_in":     600,
    "created_at":        "2026-09-18T10:00:00.000000Z"
}
```

> Le `qr_code` est un SVG encodé en base64.
> Affichez-le ainsi côté front-end :
> ```html
> <img src="data:image/svg+xml;base64,PD94..." />
> ```

### 5.2 Récupérer une collection

```
GET /transactions/{transaction_id}
```

**Réponse 200 :**
```json
{
    "transaction_id":    "2831692691",
    "type":              "COLLECTION",
    "status":            "SUCCESS",
    "amount":            "5000.00",
    "fees": {
        "many_fee":      "50.00"
    },
    "net_to_aggregator": "4950.00",
    "currency":          "XOF",
    "customer_phone":    "+221774825025",
    "order_reference":   "CMD-2026-001",
    "environment":       "PRODUCTION",
    "created_at":        "2026-09-18T10:00:00.000000Z",
    "completed_at":      "2026-09-18T10:02:30.000000Z"
}
```

**Statuts possibles :**

| Statut | Description |
|--------|-------------|
| `PENDING` | En attente de confirmation client |
| `SUCCESS` | Paiement réussi, wallet crédité |
| `FAILED` | Échoué (solde insuffisant, PIN erroné, expiré) |
| `REFUNDED` | Remboursé |

### 5.3 Lister les collections

```
GET /transactions?status=SUCCESS&limit=20
```

**Paramètres :**
- `status` : PENDING, SUCCESS, FAILED, REFUNDED (optionnel)
- `sub_merchant_id` : filtrer par marchand (optionnel)
- `limit` : nombre par page (défaut 20)

### 5.4 Rembourser une collection

```
POST /transactions/{transaction_id}/refund
```

**Body :**
```json
{
    "amount": 5000,
    "reason": "Annulation commande client"
}
```

---

## 6. Disbursements

### 6.1 Créer un disbursement

```
POST /disbursements
```

**Body :**
```json
{
    "sub_merchant_id": "uuid-du-sous-marchand",
    "recipient_phone": "+221771234567",
    "amount":          10000,
    "currency":        "XOF",
    "reference":       "REMB-2026-001",
    "idempotency_key": "REMB-2026-001-v1"
}
```

**Réponse 201 :**
```json
{
    "disbursement_id": "4307442865",
    "sub_merchant_id": "uuid-du-sous-marchand",
    "aggregator_id":   "uuid-de-l-agregateur",
    "sender_id":       61,
    "receiver_id":     42,
    "recipient_phone": "+221771234567",
    "amount":          "10000.00",
    "fees": {
        "many_fee":    "100.00"
    },
    "net_amount":      "9900.00",
    "currency":        "XOF",
    "status":          "PENDING",
    "reference":       "REMB-2026-001",
    "environment":     "PRODUCTION",
    "message":         "Disbursement en attente de confirmation. Confirmez avec votre mpin.",
    "created_at":      "2026-09-18T10:00:00.000000Z"
}
```

> ⚠️ En production, le disbursement reste `PENDING` jusqu'à confirmation par mpin.
> Le total débité = `amount + many_fee` (ex: 10 000 + 100 = 10 100 XOF).

### 6.2 Confirmer un disbursement (production)

> Appelé par l'agrégateur après réception de la notification push.

```
POST /disbursements/{disbursement_id}/confirm
```

**Body :**
```json
{
    "mpin": "votre_mot_de_passe"
}
```

**Réponse 200 :**
```json
{
    "disbursement_id": "4307442865",
    "status":          "PROCESSING",
    "message":         "Disbursement confirmé. Traitement en cours."
}
```

### 6.3 Récupérer un disbursement

```
GET /disbursements/{disbursement_id}
```

**Statuts possibles :**

| Statut | Description |
|--------|-------------|
| `PENDING` | En attente de confirmation mpin |
| `SUCCESS` | Exécuté, destinataire crédité |
| `FAILED` | Échoué |

### 6.4 Lister les disbursements

```
GET /disbursements?status=SUCCESS&limit=20
```

---

## 7. Bulk Payments

### 7.1 Créer un bulk payment

```
POST /bulk-payments
```

**Body :**
```json
{
    "sub_merchant_id": "uuid-du-sous-marchand",
    "label":           "Salaires Septembre 2026",
    "currency":        "XOF",
    "webhook_url":     "https://votre-site.sn/webhooks/many",
    "recipients": [
        {
            "phone":     "+221771111111",
            "amount":    150000,
            "reference": "SAL-EMP-001"
        },
        {
            "phone":     "+221772222222",
            "amount":    120000,
            "reference": "SAL-EMP-002"
        },
        {
            "phone":     "+221773333333",
            "amount":    95000,
            "reference": "SAL-EMP-003"
        }
    ]
}
```

**Règles :**
- Maximum **1000 bénéficiaires** par bulk
- Chaque `amount` minimum 100 XOF
- Solde agrégateur doit couvrir `total_amount + total_fee`

**Réponse 202 :**
```json
{
    "bulk_id":          "BLK-GCXE9GGLWV",
    "sub_merchant_id":  "uuid-du-sous-marchand",
    "label":            "Salaires Septembre 2026",
    "status":           "PENDING",
    "total_recipients": 3,
    "total_amount":     "365000.00",
    "fees": {
        "many_fee":     "3650.00"
    },
    "currency":         "XOF",
    "environment":      "PRODUCTION",
    "message":          "Paiement de masse créé. Confirmez avec votre mpin.",
    "created_at":       "2026-09-18T10:00:00.000000Z"
}
```

### 7.2 Confirmer un bulk payment (production)

```
POST /bulk-payments/{bulk_id}/confirm
```

**Body :**
```json
{
    "mpin": "votre_mot_de_passe"
}
```

**Réponse 200 :**
```json
{
    "bulk_id":          "BLK-GCXE9GGLWV",
    "status":           "PROCESSING",
    "total_recipients": 3,
    "message":          "Paiement de masse confirmé. Traitement en cours."
}
```

### 7.3 Statut d'un bulk payment

```
GET /bulk-payments/{bulk_id}
```

**Réponse 200 :**
```json
{
    "bulk_id":          "BLK-GCXE9GGLWV",
    "label":            "Salaires Septembre 2026",
    "status":           "COMPLETED",
    "total_recipients": 3,
    "success_count":    3,
    "failure_count":    0,
    "total_amount":     "365000.00",
    "fees": {
        "many_fee":     "3650.00"
    },
    "currency":         "XOF",
    "environment":      "PRODUCTION",
    "confirmed_at":     "2026-09-18T10:01:00.000000Z",
    "completed_at":     "2026-09-18T10:02:15.000000Z",
    "recipients": [
        {
            "phone":          "+221771111111",
            "sender_id":      61,
            "receiver_id":    10,
            "amount":         "150000.00",
            "many_fee":       "1500.00",
            "net_amount":     "148500.00",
            "reference":      "SAL-EMP-001",
            "status":         "SUCCESS",
            "failure_reason": null
        }
    ]
}
```

**Statuts bulk :**

| Statut | Description |
|--------|-------------|
| `PENDING` | Créé, en attente de confirmation mpin |
| `PROCESSING` | Confirmé, traitement en cours |
| `COMPLETED` | Tous les paiements traités |

---

## 8. Sous-marchands

### 8.1 Créer un sous-marchand

```
POST /sub-merchants
```

**Body :**
```json
{
    "application_id": "uuid-de-l-application",
    "legal_name":     "Maya Store SARL",
    "business_type":  "Commerce",
    "address":        "Dakar, Plateau, Rue 10",
    "webhook_url":    "https://mayastore.sn/webhooks/many"
}
```

**Réponse 201 :**
```json
{
    "sub_merchant_id":    "uuid-du-sous-marchand",
    "application_id":     "uuid-de-l-application",
    "legal_name":         "Maya Store SARL",
    "business_type":      "Commerce",
    "address":            "Dakar, Plateau, Rue 10",
    "external_reference": "SM-MAYASTORE-XXXX",
    "status":             "PENDING",
    "created_at":         "2026-09-18T10:00:00.000000Z"
}
```

### 8.2 Activer un sous-marchand

```
POST /sub-merchants/{sub_merchant_id}/activate
```

### 8.3 Suspendre un sous-marchand

```
POST /sub-merchants/{sub_merchant_id}/suspend
```

### 8.4 Clôturer un sous-marchand

```
POST /sub-merchants/{sub_merchant_id}/close
```

### 8.5 Lister les sous-marchands

```
GET /sub-merchants?status=ACTIVE
```

---

## 9. Balance

```
GET /balance
```

**Réponse 200 :**
```json
{
    "aggregator_id":   "uuid-de-l-agregateur",
    "balance":         1024750.00,
    "pending_balance": 5000.00,
    "currency":        "XOF",
    "environment":     "PRODUCTION",
    "last_updated_at": "2026-09-18T10:02:30.000000Z"
}
```

---

## 10. Enregistrer FCM Token

> Appelé par l'app mobile Many quand un agrégateur se connecte pour recevoir les notifications push.

```
POST /device/register
```

**Body :**
```json
{
    "fcm_token":   "eIP8dRvERp6SlCIIDLv...",
    "device_type": "android"
}
```

**Réponse 200 :**
```json
{
    "success": true,
    "message": "Token FCM enregistré avec succès."
}
```

---

## 11. Notifications push FCM

L'app mobile Many doit écouter ces types de notifications :

### 11.1 Demande de paiement (collection)

Reçue par le **client** quand un agrégateur initie une collection.

```json
{
    "notification": {
        "title": "Demande de paiement de Maya Store",
        "body":  "Maya Store vous demande de régler 5 000 XOF. Ouvrez Many pour confirmer."
    },
    "data": {
        "type":           "PAYMENT_REQUEST",
        "transaction_id": "2831692691",
        "amount":         "5000",
        "currency":       "XOF",
        "merchant_name":  "Maya Store"
    }
}
```

**Action attendue de l'app :**
1. Ouvrir l'écran de confirmation de paiement
2. Afficher le montant et le marchand
3. Demander le mpin au client
4. Appeler `POST /transactions/{transaction_id}/confirm`

### 11.2 Confirmation de disbursement

Reçue par l'**agrégateur** quand un disbursement est créé.

```json
{
    "notification": {
        "title": "💸 Confirmation décaissement",
        "body":  "Confirmez l'envoi de 10 000 XOF à +221771234567"
    },
    "data": {
        "type":            "DISBURSEMENT_CONFIRMATION",
        "disbursement_id": "4307442865",
        "amount":          "10 000",
        "recipient_phone": "+221771234567"
    }
}
```

**Action attendue de l'app :**
1. Afficher une alerte de confirmation
2. Demander le mpin à l'agrégateur
3. Appeler `POST /disbursements/{disbursement_id}/confirm`

### 11.3 Confirmation de bulk payment

Reçue par l'**agrégateur** quand un bulk payment est créé.

```json
{
    "notification": {
        "title": "Paiement de masse à confirmer",
        "body":  "Salaires Septembre 2026 : 3 bénéficiaires, 365 000 XOF. Ouvrez Many pour confirmer."
    },
    "data": {
        "type":             "BULK_PAYMENT_CONFIRMATION",
        "bulk_id":          "BLK-GCXE9GGLWV",
        "total_recipients": "3",
        "amount":           "365000",
        "fee":              "3650"
    }
}
```

**Action attendue de l'app :**
1. Afficher un résumé du bulk
2. Demander le mpin à l'agrégateur
3. Appeler `POST /bulk-payments/{bulk_id}/confirm`

### 11.4 Argent reçu (disbursement/bulk)

Reçue par le **destinataire** après réception d'un virement.

```json
{
    "notification": {
        "title": "💰 Argent reçu !",
        "body":  "Vous avez reçu 9 900 XOF sur votre compte Many."
    },
    "data": {
        "type":            "MONEY_RECEIVED",
        "disbursement_id": "4307442865",
        "amount":          "9 900"
    }
}
```

---

## 12. Webhooks

Many envoie des webhooks à votre `webhook_url` après chaque événement.

**Headers du webhook :**
```
Content-Type:             application/json
X-Many-Webhook-Signature: hmac_sha256_signature
X-Many-Webhook-Timestamp: 1726650150
X-Many-Event-Id:          uuid-de-l-evenement
```

**Vérification de la signature :**
```php
$signature = hash_hmac('sha256', json_encode($payload), $votre_client_secret);
if (!hash_equals($signature, $request->header('X-Many-Webhook-Signature'))) {
    // Signature invalide — rejeter
    abort(401);
}
```

### 12.1 transaction.succeeded

```json
{
    "event_id":   "uuid",
    "event_type": "transaction.succeeded",
    "created_at": "2026-09-18T10:02:30+00:00",
    "data": {
        "transaction_id":    "2831692691",
        "sub_merchant_id":   "uuid",
        "aggregator_id":     "uuid",
        "type":              "COLLECTION",
        "amount":            "5000.00",
        "currency":          "XOF",
        "status":            "SUCCESS",
        "fees": {
            "many_fee":      "50.00"
        },
        "net_to_aggregator": "4950.00"
    }
}
```

### 12.2 transaction.failed

```json
{
    "event_id":   "uuid",
    "event_type": "transaction.failed",
    "created_at": "2026-09-18T10:12:00+00:00",
    "data": {
        "transaction_id": "2831692691",
        "status":         "FAILED",
        "type":           "COLLECTION",
        "amount":         "5000.00",
        "currency":       "XOF"
    }
}
```

### 12.3 disbursement.succeeded

```json
{
    "event_id":   "uuid",
    "event_type": "disbursement.succeeded",
    "created_at": "2026-09-18T10:02:30+00:00",
    "data": {
        "disbursement_id": "4307442865",
        "sub_merchant_id": "uuid",
        "aggregator_id":   "uuid",
        "type":            "DISBURSEMENT",
        "amount":          "10000.00",
        "currency":        "XOF",
        "status":          "SUCCESS",
        "fees": {
            "many_fee":    "100.00"
        },
        "net_amount":      "9900.00",
        "recipient_phone": "+221771234567",
        "reference":       "REMB-2026-001"
    }
}
```

### 12.4 bulk_payment.completed

```json
{
    "event_id":   "uuid",
    "event_type": "bulk_payment.completed",
    "created_at": "2026-09-18T10:05:00+00:00",
    "data": {
        "bulk_id":          "BLK-GCXE9GGLWV",
        "sub_merchant_id":  "uuid",
        "aggregator_id":    "uuid",
        "type":             "BULK_PAYMENT",
        "label":            "Salaires Septembre 2026",
        "status":           "COMPLETED",
        "total_recipients": 3,
        "success_count":    3,
        "failure_count":    0,
        "total_amount":     "365000.00",
        "fees": {
            "many_fee":     "3650.00"
        },
        "currency":         "XOF"
    }
}
```

### 12.5 bulk_payment.partially_completed

M�me structure que `bulk_payment.completed` mais avec `failure_count > 0`.

---

## 13. Codes d'erreur

| Code | HTTP | Description |
|------|------|-------------|
| `AUTH_INVALID_CLIENT` | 401 | client_id ou client_secret invalide |
| `AUTH_INVALID_TOKEN` | 401 | Token Bearer manquant ou expiré |
| `AUTH_INVALID_SIGNATURE` | 401 | Signature HMAC invalide |
| `ACCOUNT_SUSPENDED` | 403 | Compte suspendu ou clôturé |
| `PRODUCTION_NOT_ENABLED` | 403 | Compte non activé en production |
| `SUB_MERCHANT_NOT_FOUND` | 404 | Sous-marchand introuvable |
| `SUB_MERCHANT_NOT_ACTIVE` | 422 | Sous-marchand inactif |
| `CUSTOMER_NOT_FOUND` | 404 | Client sans compte Many |
| `CUSTOMER_WALLET_NOT_FOUND` | 422 | Portefeuille client introuvable |
| `CUSTOMER_MPIN_NOT_SET` | 422 | Client sans PIN configuré |
| `INSUFFICIENT_FUNDS` | 422 | Solde insuffisant |
| `INVALID_MPIN` | 401 | PIN incorrect |
| `RECIPIENT_NOT_FOUND` | 404 | Destinataire sans compte Many |
| `RECIPIENT_WALLET_NOT_FOUND` | 422 | Portefeuille destinataire introuvable |
| `RECIPIENTS_NOT_FOUND` | 422 | Certains destinataires sans compte |
| `TRANSACTION_NOT_FOUND` | 404 | Transaction introuvable |
| `TRANSACTION_ALREADY_PROCESSED` | 409 | Transaction déjà traitée |
| `TRANSACTION_EXPIRED` | 410 | Transaction expirée (> 10 min) |
| `DISBURSEMENT_NOT_FOUND` | 404 | Disbursement introuvable |
| `DISBURSEMENT_ALREADY_PROCESSED` | 409 | Disbursement déjà traité |
| `BULK_PAYMENT_NOT_FOUND` | 404 | Bulk payment introuvable |
| `BULK_PAYMENT_ALREADY_PROCESSED` | 409 | Bulk déjà traité |
| `BULK_PAYMENT_EXPIRED` | 410 | Bulk expiré (> 10 min) |
| `SANDBOX_INVALID_RECIPIENT` | 422 | Numéro de test invalide en sandbox |
| `INTERNAL_ERROR` | 500 | Erreur interne |

**Format d'erreur :**
```json
{
    "error":   "CODE_ERREUR",
    "message": "Description lisible de l'erreur."
}
```

---

## 14. Flux complets

### 14.1 Flux collection production

```
Agrégateur                    Many API                 App Mobile Many
    |                             |                           |
    |-- POST /transactions -----> |                           |
    |                             |-- Crée transaction        |
    |                             |   status: PENDING         |
    |                             |-- QR Code généré          |
    |<-- 201 { qr_code } ---------|                           |
    |                             |                           |
    | (Affiche QR sur caisse)     |-- Notification push ----> |
    |                             |   type: PAYMENT_REQUEST   |
    |                             |                           |
    | (Client scanne le QR)       |                           |
    |                             |<-- GET /pending/{phone} --|
    |                             |-- Retourne transaction --->|
    |                             |                           |
    |                             |  (Client entre son mpin)  |
    |                             |<-- POST /confirm ---------|
    |                             |-- Vérifie mpin            |
    |                             |-- Débite client           |
    |                             |-- Crédite agrégateur      |
    |                             |-- Crédite Many (1%)       |
    |                             |   status: SUCCESS         |
    |<-- Webhook: succeeded ------|                           |
```

### 14.2 Flux disbursement production

```
Agrégateur                    Many API                 App Mobile Many
    |                             |                           |
    |-- POST /disbursements -----> |                           |
    |                             |-- Vérifie solde           |
    |                             |-- Vérifie destinataire    |
    |                             |   status: PENDING         |
    |<-- 201 { PENDING } ---------|                           |
    |                             |-- Notification push ----> | (agrégateur)
    |                             |   DISBURSEMENT_CONFIRM    |
    |                             |                           |
    |  (Agrégateur entre mpin)    |                           |
    |-- POST /confirm ----------> |                           |
    |                             |-- Débite agrégateur       |
    |                             |-- Crédite destinataire    |
    |                             |-- Crédite Many (1%)       |
    |                             |   status: SUCCESS         |
    |                             |-- Notif destinataire ---> | (destinataire)
    |<-- Webhook: succeeded ------|   MONEY_RECEIVED          |
```

### 14.3 Flux bulk payment production

```
Agrégateur                    Many API
    |                             |
    |-- POST /bulk-payments -----> |
    |                             |-- Vérifie solde total
    |                             |-- Vérifie tous destinataires
    |                             |   status: PENDING
    |<-- 202 { PENDING } ---------|
    |                             |-- Notification push → agrégateur
    |                             |   BULK_PAYMENT_CONFIRMATION
    |                             |
    |-- POST /{id}/confirm ------> |
    |                             |-- Pour chaque recipient :
    |                             |   ├── Vérifie solde
    |                             |   ├── Débite agrégateur
    |                             |   ├── Crédite destinataire
    |                             |   ├── Crédite Many (1%)
    |                             |   └── Notif → destinataire
    |                             |   status: COMPLETED
    |<-- Webhook: completed ------|
```

---

## Calcul des frais

```
Frais Many = 1% du montant (arrondi à l'entier inférieur)

COLLECTION 5 000 XOF :
→ many_fee          = 50 XOF
→ net_to_aggregator = 4 950 XOF
→ Client débité     : 5 000 XOF

DISBURSEMENT 10 000 XOF :
→ many_fee              = 100 XOF
→ net_amount            = 9 900 XOF
→ Agrégateur débité     : 10 100 XOF (amount + many_fee)
→ Destinataire crédité  : 9 900 XOF

BULK 365 000 XOF (3 bénéficiaires) :
→ many_fee (total)     = 3 650 XOF
→ Agrégateur débité    : 368 650 XOF
→ Chaque bénéficiaire reçoit amount - (amount × 1%)
```

---

## Numéros de test sandbox

| Numéro | Solde |
|--------|-------|
| `+221770000001` | 500 000 XOF |
| `+221770000002` | 500 000 XOF |
| `+221770000003` | 500 000 XOF |
| ... | ... |
| `+221770000099` | 500 000 XOF |

> Les soldes sandbox se rechargent automatiquement à 500 000 XOF quand ils descendent sous 50 000 XOF.

---

*Documentation Many API v1.0 — Dernière mise à jour : Octobre 2026*
