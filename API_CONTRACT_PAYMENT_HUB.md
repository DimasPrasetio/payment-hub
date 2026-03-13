# API Contract Payment Hub

## Payment Orchestrator / Payment Hub

### Version

`1.0.0`

### Base URL

```text
https://{domain}/api/v1
```

Dokumen ini adalah kontrak sistem Payment Hub berdasarkan implementasi code saat ini, bukan target ideal atau future scope.

## 1. System Scope

Payment Hub saat ini menyediakan:

- client API berbasis `X-API-Key`
- adapter provider untuk `tripay`, `midtrans`, dan `xendit`
- callback endpoint provider pada `/api/v1/callback/{provider_code}`
- outbound webhook ke client app
- admin panel web untuk login, user management, application management, provider management, monitoring, dan statistics

Payment Hub saat ini belum menyediakan:

- admin JSON API
- JWT auth untuk client API
- queue worker otomatis untuk retry webhook
- multi profile merchant untuk provider code yang sama
- provider fallback routing

## 2. Actual Architecture

High level flow:

```text
Client App
   |
   v
Payment Hub Client API
   |
   v
PaymentService
   |
   v
ProviderResolver
   |
   +--> TripayProvider
   +--> MidtransProvider
   +--> XenditProvider
   |
   v
Provider Callback -> Payment Hub -> Client Webhook
```

Komponen inti:

- `PaymentService` sebagai orchestration layer utama
- `PaymentProviderInterface` sebagai kontrak adapter
- `ProviderResolver` untuk memilih adapter dari `provider_code`
- `WebhookService` untuk pengiriman webhook outbound
- admin panel web berbasis session auth Laravel

## 3. Authentication Model

### 3.1 Client API

Semua endpoint client API, kecuali health dan provider callback, memakai:

```text
X-API-Key: {plain_application_api_key}
```

Karakteristik:

- API key disimpan hashed SHA-256 di tabel `applications`
- request hanya boleh mengakses data milik aplikasi yang terautentikasi
- rate limit `60 request / menit`

### 3.2 Provider Callback

Endpoint callback provider tidak memakai API key.

Autentikasi callback dilakukan per provider:

- Tripay: `X-Callback-Signature` HMAC SHA256 raw body dengan `private_key`
- Midtrans: SHA512(`order_id + status_code + gross_amount + server_key`)
- Xendit: header `x-callback-token`

### 3.3 Admin Panel

Admin panel memakai auth session Laravel.

Flow login:

- login page di `/admin/login`
- login memakai `username` dan `password`
- hanya user aktif yang boleh masuk

Tidak ada role matrix selain status aktif user.

## 4. Response Conventions

### 4.1 Standard API Envelope

Mayoritas endpoint API memakai:

```json
{
  "success": true,
  "data": {},
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Error format:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {}
  },
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

### 4.2 Endpoint Exceptions

Endpoint berikut tidak memakai envelope standar:

- `GET /api/v1/health`
- `POST /api/v1/callback/{provider_code}`

## 5. Resource Identity Strategy

### 5.1 Public IDs

| Resource | Prefix | Exposed Field |
|---|---|---|
| Payment | `pay_` | `payment_id` |
| Payment Event | `evt_` | `id` |
| Webhook Delivery | `wh_` | `id` |

Semua public ID dihasilkan dari ULID lower-case dengan prefix.

### 5.2 Code-based Resources

| Resource | Identifier |
|---|---|
| Application | `code` |
| Provider | `code` |
| Payment Method Mapping | `internal_code + provider_code` |

## 6. Provider Contract

Provider interface aktual:

```text
createTransaction(PaymentOrder, PaymentMethodMapping, PaymentProvider): array
queryTransaction(PaymentOrder, PaymentProvider): array
verifyCallback(Request, PaymentProvider): array
getAvailablePaymentMethods(PaymentProvider): array
refund(PaymentOrder, int amount, string reason, PaymentProvider): array
```

Resolver yang tersedia:

- `tripay`
- `midtrans`
- `xendit`

## 7. Provider Capability Summary

| Provider | Create | Query / Sync | Callback Verify | Refund API | Stub Fallback |
|---|---|---|---|---|---|
| Tripay | yes | yes | yes | no | no |
| Midtrans | yes | yes | yes | conditional by config | no |
| Xendit | yes | yes | yes | conditional by config | no |

Catatan:

- runtime akan mengembalikan `PROVIDER_CONFIG_INCOMPLETE` jika credential minimum belum lengkap
- provider aktif wajib untuk create payment baru
- callback, sync, dan refund untuk payment existing tetap bisa memakai konfigurasi provider walaupun provider sudah dinonaktifkan untuk traffic baru

## 8. Provider Activation Requirements

Sebelum provider dapat diaktifkan dari admin panel, field minimum berikut harus tersedia:

| Provider | Required Config |
|---|---|
| `tripay` | `merchant_code`, `api_key`, `private_key` |
| `midtrans` | `server_key` |
| `xendit` | `secret_key`, `callback_token` |

Field config umum yang saat ini bisa dikelola admin:

- `merchant_code`
- `api_key`
- `private_key`
- `client_key`
- `server_key`
- `secret_key`
- `callback_token`
- `api_base_url`
- `public_base_url`
- `return_url`
- `supports_refund_api`
- extra config JSON

Credential provider disimpan terenkripsi di tabel `payment_providers.config`.

## 9. Client API Surface

Client API detail lengkap ada di `API_CONTRACT_CLIENT.md`.

Ringkasan endpoint yang benar-benar tersedia:

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `GET` | `/api/v1/health` | none | health check |
| `POST` | `/api/v1/callback/{provider_code}` | provider signature | inbound provider callback |
| `GET` | `/api/v1/providers` | `X-API-Key` | list active providers |
| `GET` | `/api/v1/payment-methods` | `X-API-Key` | list payment method mappings |
| `GET` | `/api/v1/payments` | `X-API-Key` | list payments |
| `POST` | `/api/v1/payments` | `X-API-Key` | create payment |
| `GET` | `/api/v1/payments/lookup` | `X-API-Key` | lookup by external order id |
| `GET` | `/api/v1/payments/{payment_id}` | `X-API-Key` | payment detail |
| `GET` | `/api/v1/payments/{payment_id}/events` | `X-API-Key` | payment audit trail |
| `POST` | `/api/v1/payments/{payment_id}/sync` | `X-API-Key` | refresh latest status from provider |
| `POST` | `/api/v1/payments/{payment_id}/cancel` | `X-API-Key` | cancel payment internal pre-provider |
| `POST` | `/api/v1/payments/{payment_id}/refund` | `X-API-Key` | refund paid payment |
| `GET` | `/api/v1/webhook-deliveries` | `X-API-Key` | list outbound delivery records |
| `POST` | `/api/v1/webhook-deliveries/{delivery_id}/retry` | `X-API-Key` | queue resend untuk manual retry |

## 10. Provider Callback Contract

### 10.1 Endpoint

```text
POST /api/v1/callback/{provider_code}
```

Supported values:

- `tripay`
- `midtrans`
- `xendit`

### 10.2 Processing Rules

Actual callback processing flow:

1. resolve provider by `{provider_code}`
2. reject jika provider tidak ditemukan
3. delegate signature atau token verification ke adapter
4. lookup payment by `provider_code + merchant_ref`
5. log `callback.received` jika verification valid
6. jika amount mismatch, log `callback.rejected` dan return success tanpa state update
7. jika transisi status dari provider tidak valid, callback menjadi no-op terkontrol tanpa menurunkan state internal
8. map status provider ke status internal
9. update payment dan latest provider transaction
10. create outbound webhook delivery untuk eventable state
11. queue webhook delivery

### 10.3 Success and Error Behavior

Success:

```json
{
  "success": true
}
```

Error bodies dikembalikan untuk semua error callback, termasuk internal error.

Karakteristik:

- signature invalid tetap `403`
- payment/provider tidak ditemukan tetap `404`/`422`
- internal error merespons `500` agar provider dapat retry bila mendukung

### 10.4 Provider-specific Expectations

Tripay:

- signature header `X-Callback-Signature`
- merchant reference dari `merchant_ref`
- amount dari `total_amount`
- provider reference dari `reference`
- status dari `status`

Midtrans:

- signature dari payload `signature_key` atau header `X-Callback-Signature`
- merchant reference dari `order_id`
- amount dari `gross_amount`
- provider reference dari `transaction_id`
- status dari `transaction_status`

Xendit:

- callback token dari header `x-callback-token`
- merchant reference dari `external_id`
- amount dari `amount`
- provider reference dari `payment_id` atau `id`
- status dari `status`

## 11. Outbound Webhook Contract

Payment Hub mengirim webhook ke `applications.webhook_url`.

Headers:

```text
Content-Type: application/json
X-Webhook-Signature: {hmac_sha256}
X-Webhook-Event: payment.paid
X-Webhook-Delivery-Id: wh_01hrxyz123abc456defghi789
X-Webhook-Timestamp: 1741770000
User-Agent: PaymentHub/1.0
```

Signature:

```text
hash_hmac('sha256', raw_body, webhook_secret)
```

Event yang saat ini dikirim:

- `payment.created`
- `payment.paid`
- `payment.failed`
- `payment.expired`
- `payment.refunded`

Karakteristik delivery saat ini:

- webhook dikirim melalui queue job
- timeout `15` detik
- delivery status: `pending`, `success`, `failed`
- retry schedule dihitung menjadi `next_retry_at`
- command scheduler `webhook-deliveries:retry-due` akan mengantrekan retry yang sudah jatuh tempo
- endpoint manual retry mereset state delivery dan langsung mengantrekan resend

## 12. Data Model Summary

### 12.1 `users`

- admin login account
- field utama: `username`, `name`, `email`, `password`, `is_active`

### 12.2 `payment_providers`

- provider config terpusat
- `code` unique
- `config` encrypted
- `is_active`
- `sandbox_mode`

### 12.3 `payment_method_mappings`

- mapping internal method ke method provider
- unique pada `internal_code + provider_code`
- menyimpan fee dan amount range

### 12.4 `applications`

- aplikasi client yang memakai Payment Hub
- `api_key` hashed
- `default_provider` wajib refer ke provider aktif saat create atau update via admin
- `webhook_secret` disimpan terenkripsi

### 12.5 `payment_orders`

- ledger transaksi utama
- `public_id`
- `application_id`
- `external_order_id`
- `idempotency_key`
- `merchant_ref`
- `provider_code`
- `payment_method`
- `amount`
- `currency`
- `status`
- `metadata`
- `paid_at`
- `expires_at`

### 12.6 `provider_transactions`

- snapshot request dan response provider
- menyimpan instruction seperti URL checkout, pay code, QR string, dan QR URL

### 12.7 `payment_events`

- audit trail transaksi
- event type aktual termasuk provider request, callback, sync, dan webhook result

### 12.8 `webhook_deliveries`

- log pengiriman webhook outbound
- menyimpan payload request, response code, response body, attempt, status, dan `next_retry_at`

## 13. Admin Web Surface

Admin capabilities yang benar-benar tersedia melalui web:

- login dan logout admin
- user management
- application management
- provider management
- dashboard
- statistics
- transactions
- payment method mapping list
- callback log list
- webhook log list
- audit trail list
- reconciliation page
- refunds page

Admin panel route summary:

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/admin/login` | login form |
| `POST` | `/admin/login` | login submit |
| `POST` | `/admin/logout` | logout |
| `GET` | `/admin/dashboard` | dashboard |
| `GET` | `/admin/statistics` | statistics |
| `GET` | `/admin/transactions` | transaction list |
| `GET` | `/admin/transactions/{paymentOrder}` | transaction detail |
| `GET` | `/admin/applications` | application list |
| `GET` | `/admin/applications/create` | create application form |
| `POST` | `/admin/applications` | create application |
| `GET` | `/admin/applications/{application}` | application detail |
| `PUT` | `/admin/applications/{application}` | update application |
| `POST` | `/admin/applications/{application}/rotate-api-key` | rotate api key |
| `POST` | `/admin/applications/{application}/rotate-webhook-secret` | rotate webhook secret |
| `DELETE` | `/admin/applications/{application}` | delete application if unused |
| `GET` | `/admin/users` | admin user list |
| `GET` | `/admin/users/create` | create user form |
| `POST` | `/admin/users` | create admin user |
| `GET` | `/admin/users/{user}/edit` | edit user form |
| `PUT` | `/admin/users/{user}` | update admin user |
| `DELETE` | `/admin/users/{user}` | delete admin user |
| `GET` | `/admin/providers` | provider list |
| `GET` | `/admin/providers/{provider}` | provider detail |
| `PUT` | `/admin/providers/{provider}` | update provider config |

## 14. Operational Notes

- `GET /api/v1/providers` hanya mengembalikan provider aktif.
- `GET /api/v1/payment-methods` hanya mengembalikan mapping aktif dari provider yang aktif.
- create payment baru akan gagal jika provider nonaktif, tetapi callback/sync/refund untuk payment existing tetap dapat memakai provider config yang sama.
- satu `payment_providers.code` hanya mewakili satu config provider. Banyak aplikasi dapat berbagi config yang sama.
- skema saat ini belum mendukung beberapa merchant profile berbeda untuk provider code yang sama.
- `merchant_ref` format aktual adalah `{APP_CODE}-{YYYYMMDD}-{RANDOM6}`.
- `cancel` hanya valid untuk payment internal yang belum masuk lifecycle provider. Payment provider-managed akan ditolak dengan `PAYMENT_CANCELLATION_NOT_SUPPORTED`.
- timezone aplikasi saat ini `UTC`.
- CORS saat ini terbuka untuk `api/*` dengan `allowed_origins = *`.

## 15. Error Codes

| Code | HTTP | Meaning |
|---|---:|---|
| `VALIDATION_ERROR` | 422 | invalid body atau query |
| `AUTHENTICATION_FAILED` | 401 | API key tidak valid atau tidak ada |
| `APPLICATION_INACTIVE` | 403 | aplikasi nonaktif |
| `PAYMENT_NOT_FOUND` | 404 | payment tidak ditemukan |
| `PAYMENT_METHOD_NOT_AVAILABLE` | 422 | mapping method tidak tersedia |
| `PROVIDER_NOT_FOUND` | 422 | provider code tidak dikenali |
| `PROVIDER_INACTIVE` | 422 | provider nonaktif |
| `PROVIDER_ERROR` | 502 | provider mengembalikan error |
| `PROVIDER_TIMEOUT` | 504 | provider timeout |
| `IDEMPOTENCY_CONFLICT` | 409 | key sama dengan payload berbeda |
| `INVALID_CALLBACK_SIGNATURE` | 403 | signature atau token callback tidak valid |
| `PAYMENT_ALREADY_FINAL` | 409 | payment sudah final atau tidak eligible |
| `PAYMENT_CANCELLATION_NOT_SUPPORTED` | 409 | payment sudah provider-managed dan belum bisa dibatalkan di provider |
| `REFUND_NOT_SUPPORTED` | 422 | refund API tidak didukung provider |
| `PROVIDER_CONFIG_INCOMPLETE` | 422 | credential provider belum cukup |
| `PROVIDER_REFERENCE_MISSING` | 422 | referensi provider belum tersedia |
| `PROVIDER_DATA_MISMATCH` | 409 | hasil sync provider tidak cocok dengan amount payment |
| `NOT_FOUND` | 404 | resource umum tidak ditemukan |
| `RATE_LIMIT_EXCEEDED` | 429 | rate limit exceeded |
| `INTERNAL_ERROR` | 500 | unexpected internal error |
