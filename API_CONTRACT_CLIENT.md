# API Contract Client

## Payment Orchestrator Client API

### Version

`1.0.0`

### Base URL

```text
https://{domain}/api/v1
```

Dokumen ini mendeskripsikan endpoint yang benar-benar tersedia untuk client application pada code saat ini.

## 1. Scope

Kontrak ini mencakup:

- autentikasi client app via `X-API-Key`
- health check publik
- list provider aktif
- list payment method mapping
- create, list, detail, lookup, sync, cancel, refund payment
- list payment events
- list dan retry webhook delivery
- webhook outbound dari Payment Orchestrator ke client app

Kontrak ini tidak mencakup:

- admin panel web
- provider callback contract secara rinci
- admin JSON API karena belum tersedia

## 2. Authentication

Semua endpoint client, kecuali `GET /health`, memerlukan header:

```text
X-API-Key: {plain_application_api_key}
```

Karakteristik autentikasi saat ini:

- hanya `X-API-Key` yang didukung
- JWT bearer token belum diimplementasikan
- API key dicocokkan ke aplikasi aktif
- seluruh operasi payment selalu dibatasi ke aplikasi yang terautentikasi

Jika header tidak ada:

```json
{
  "success": false,
  "error": {
    "code": "AUTHENTICATION_FAILED",
    "message": "API key is required."
  },
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Jika key salah:

```json
{
  "success": false,
  "error": {
    "code": "AUTHENTICATION_FAILED",
    "message": "API key is invalid."
  },
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Jika aplikasi nonaktif:

```json
{
  "success": false,
  "error": {
    "code": "APPLICATION_INACTIVE",
    "message": "Application is inactive."
  },
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

## 3. Common Headers

Header request:

- `X-API-Key`: wajib untuk endpoint client
- `X-Request-Id`: opsional, jika dikirim akan dipantulkan kembali
- `Content-Type: application/json`: untuk request body JSON

Header response:

- `X-Request-Id`
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`

## 4. Rate Limiting

Rate limit saat ini:

- `60 request / menit`
- key limiter: `X-API-Key`, fallback ke IP jika header tidak ada

Response saat limit terlampaui:

```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Please try again later."
  },
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

## 5. Standard Response Format

### 5.1 Success Response

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

### 5.2 Paginated Response

```json
{
  "success": true,
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 25,
    "last_page": 2,
    "has_more": true
  },
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

### 5.3 Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "amount": [
        "The amount must be at least 1000."
      ]
    }
  },
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

### 5.4 Exceptions

Endpoint berikut tidak memakai envelope `success/data/meta`:

- `GET /api/v1/health`

## 6. Domain Rules

### 6.1 Public IDs

- payment: `pay_` + ULID, diekspos sebagai `payment_id`
- payment event: `evt_` + ULID, diekspos sebagai `id`
- webhook delivery: `wh_` + ULID, diekspos sebagai `id`

### 6.2 Internal Payment Status

- `CREATED`
- `PENDING`
- `PAID`
- `FAILED`
- `EXPIRED`
- `REFUNDED`

### 6.3 Currency

Saat ini hanya `IDR` yang diterima oleh create payment.

### 6.4 Timestamps

Semua timestamp memakai ISO 8601.

Timezone aplikasi saat ini adalah `UTC`, sehingga contoh payload menggunakan suffix `Z`.

### 6.5 Idempotency

`idempotency_key` saat ini:

- opsional
- di-scope per application
- disimpan secara internal sebagai scoped key
- belum memakai TTL 24 jam

Jika key sama dan payload identik:

- request kedua mengembalikan `200`
- data payment existing dikembalikan ulang

Jika key sama dan payload berbeda:

- response `409`
- error code `IDEMPOTENCY_CONFLICT`

### 6.6 Provider Support

Provider yang sudah dikenali orchestrator saat ini:

- `tripay`
- `midtrans`
- `xendit`

Provider aktif wajib untuk create payment baru.

Untuk payment yang sudah ada:

- callback provider tetap diproses walaupun provider dinonaktifkan untuk traffic baru
- sync dan refund masih bisa memakai konfigurasi provider existing selama credential operasional tersedia

## 7. Public Endpoint

### 7.1 Health Check

```text
GET /api/v1/health
```

Auth: tidak perlu.

Response `200`:

```json
{
  "status": "healthy",
  "version": "1.0.0",
  "timestamp": "2026-03-12T09:00:00Z",
  "services": {
    "database": "connected",
    "redis": "connected",
    "queue": "sync",
    "payment_orders_table": true
  }
}
```

Response `503` saat database tidak tersedia:

```json
{
  "status": "degraded",
  "version": "1.0.0",
  "timestamp": "2026-03-12T09:00:00Z",
  "services": {
    "database": "disconnected"
  },
  "message": "Database connection is unavailable."
}
```

## 8. Client Endpoints

### 8.1 List Active Providers

```text
GET /api/v1/providers
```

Response `200`:

```json
{
  "success": true,
  "data": [
    {
      "code": "midtrans",
      "name": "Midtrans",
      "sandbox_mode": true
    },
    {
      "code": "tripay",
      "name": "Tripay",
      "sandbox_mode": true
    },
    {
      "code": "xendit",
      "name": "Xendit",
      "sandbox_mode": true
    }
  ],
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Catatan:

- hanya provider dengan `is_active = true` yang ditampilkan
- credential provider tidak pernah diekspos

### 8.2 List Payment Methods

```text
GET /api/v1/payment-methods
```

Query parameters:

| Field | Type | Required | Default | Notes |
|---|---|---:|---|---|
| `provider_code` | string | no | - | filter per provider code |
| `active_only` | boolean | no | `true` | filter `payment_method_mappings.is_active` |
| `group` | string | no | - | contoh: `bank_transfer`, `e-wallet` |
| `amount` | integer | no | - | filter berdasar `min_amount` dan `max_amount` |

Response `200`:

```json
{
  "success": true,
  "data": [
    {
      "code": "QRIS",
      "display_name": "QRIS",
      "group": "e-wallet",
      "provider": "tripay",
      "provider_method_code": "QRIS",
      "icon_url": null,
      "fee_flat": 0,
      "fee_percent": 0,
      "min_amount": 1000,
      "max_amount": 10000000,
      "is_active": true
    }
  ],
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Catatan:

- endpoint ini hanya mengembalikan mapping aktif dari provider yang aktif
- provider inactive tidak akan muncul walaupun mapping masih `is_active = true`

### 8.3 Create Payment

```text
POST /api/v1/payments
```

Request body:

```json
{
  "external_order_id": "INV-2026-001",
  "idempotency_key": "idem-blasku-001",
  "amount": 200000,
  "currency": "IDR",
  "payment_method": "QRIS",
  "provider_code": "tripay",
  "customer": {
    "name": "Dimas Prasetio",
    "email": "dimas@example.com",
    "phone": "6281234567890"
  },
  "metadata": {
    "product_name": "Paket Premium"
  }
}
```

Field rules:

| Field | Type | Required | Rules |
|---|---|---:|---|
| `application_code` | string | no | jika dikirim harus sama dengan aplikasi yang terautentikasi |
| `external_order_id` | string | yes | max 100, unique per application |
| `idempotency_key` | string | no | max 100 |
| `amount` | integer | yes | min 1000 |
| `currency` | string | yes | hanya `IDR` |
| `payment_method` | string | yes | internal code, di-normalisasi ke upper-case |
| `provider_code` | string | no | override provider aktif, jika kosong pakai `default_provider` aplikasi |
| `customer.name` | string | yes | max 100 |
| `customer.email` | string | yes | valid email, max 150 |
| `customer.phone` | string | yes | regex `^628[0-9]{7,15}$` |
| `metadata` | object | no | disimpan apa adanya |

Response `201`:

```json
{
  "success": true,
  "data": {
    "payment_id": "pay_01hrxyz123abc456defghi789",
    "application_code": "BLASKU",
    "external_order_id": "INV-2026-001",
    "merchant_ref": "BLASKU-20260312-A1B2C3",
    "provider": "tripay",
    "payment_method": "QRIS",
    "amount": 200000,
    "currency": "IDR",
    "status": "PENDING",
    "customer": {
      "name": "Dimas Prasetio",
      "email": "dimas@example.com",
      "phone": "6281234567890"
    },
    "payment_instruction": {
      "payment_url": "https://tripay.test/checkout/T1234567890",
      "pay_code": null,
      "qr_string": "000201010212...",
      "qr_url": "https://tripay.test/qr/T1234567890"
    },
    "expires_at": "2026-03-12T10:00:00Z",
    "created_at": "2026-03-12T09:00:00Z"
  },
  "meta": {
    "timestamp": "2026-03-12T09:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Catatan:

- `payment_instruction` bersifat provider- dan method-dependent
- field bisa `null` jika provider tidak mengembalikan data tersebut
- create akan memicu webhook `payment.created` ke client app

Idempotent replay response `200`:

- format payload sama seperti create success
- `data` merujuk ke payment existing

Kemungkinan error:

- `VALIDATION_ERROR`
- `IDEMPOTENCY_CONFLICT`
- `PAYMENT_METHOD_NOT_AVAILABLE`
- `PROVIDER_NOT_FOUND`
- `PROVIDER_INACTIVE`
- `PROVIDER_CONFIG_INCOMPLETE`
- `PROVIDER_ERROR`
- `PROVIDER_TIMEOUT`

### 8.4 Get Payment Detail

```text
GET /api/v1/payments/{payment_id}
```

Response `200`:

```json
{
  "success": true,
  "data": {
    "payment_id": "pay_01hrxyz123abc456defghi789",
    "application_code": "BLASKU",
    "external_order_id": "INV-2026-001",
    "merchant_ref": "BLASKU-20260312-A1B2C3",
    "provider": "tripay",
    "payment_method": "QRIS",
    "amount": 200000,
    "currency": "IDR",
    "status": "PENDING",
    "customer": {
      "name": "Dimas Prasetio",
      "email": "dimas@example.com",
      "phone": "6281234567890"
    },
    "payment_instruction": {
      "payment_url": "https://tripay.test/checkout/T1234567890",
      "pay_code": null,
      "qr_string": "000201010212...",
      "qr_url": "https://tripay.test/qr/T1234567890"
    },
    "metadata": {
      "product_name": "Paket Premium"
    },
    "paid_at": null,
    "expires_at": "2026-03-12T10:00:00Z",
    "created_at": "2026-03-12T09:00:00Z",
    "updated_at": "2026-03-12T09:00:00Z"
  },
  "meta": {
    "timestamp": "2026-03-12T09:05:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Error:

- `PAYMENT_NOT_FOUND`

### 8.5 Lookup Payment by External Order ID

```text
GET /api/v1/payments/lookup?external_order_id=INV-2026-001
```

Rules:

- `application_code` opsional
- jika `application_code` dikirim, nilainya harus sama dengan aplikasi yang terautentikasi
- lookup tetap di-scope ke aplikasi yang terautentikasi

Response `200`:

- format sama dengan `GET /payments/{payment_id}`

Error:

- `VALIDATION_ERROR`
- `PAYMENT_NOT_FOUND`

### 8.6 List Payments

```text
GET /api/v1/payments
```

Query parameters:

| Field | Type | Required | Default | Notes |
|---|---|---:|---|---|
| `application_code` | string | no | - | jika dikirim harus sama dengan aplikasi yang terautentikasi |
| `status` | string | no | - | `CREATED`, `PENDING`, `PAID`, `FAILED`, `EXPIRED`, `REFUNDED` |
| `provider_code` | string | no | - | filter provider |
| `payment_method` | string | no | - | filter internal method |
| `date_from` | string | no | - | format `Y-m-d` |
| `date_to` | string | no | - | format `Y-m-d`, harus `>= date_from` |
| `page` | integer | no | `1` | page number |
| `per_page` | integer | no | `20` | min 1, max 100 |
| `sort_by` | string | no | `created_at` | `created_at`, `amount`, `status` |
| `sort_order` | string | no | `desc` | `asc` atau `desc` |

Response `200`:

```json
{
  "success": true,
  "data": [
    {
      "payment_id": "pay_01hrxyz123abc456defghi789",
      "application_code": "BLASKU",
      "external_order_id": "INV-2026-001",
      "merchant_ref": "BLASKU-20260312-A1B2C3",
      "provider": "tripay",
      "payment_method": "QRIS",
      "amount": 200000,
      "currency": "IDR",
      "status": "PAID",
      "customer": {
        "name": "Dimas Prasetio",
        "email": "dimas@example.com",
        "phone": "6281234567890"
      },
      "paid_at": "2026-03-12T09:15:00Z",
      "expires_at": "2026-03-12T10:00:00Z",
      "created_at": "2026-03-12T09:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1,
    "has_more": false
  },
  "meta": {
    "timestamp": "2026-03-12T09:20:00Z",
    "request_id": "req_abc123def456"
  }
}
```

### 8.7 List Payment Events

```text
GET /api/v1/payments/{payment_id}/events
```

Response `200`:

```json
{
  "success": true,
  "data": [
    {
      "id": "evt_01hrxyz123abc456defghi789",
      "payment_id": "pay_01hrxyz123abc456defghi789",
      "event_type": "payment.created",
      "payload": {
        "status": "PENDING",
        "provider": "tripay",
        "payment_method": "QRIS"
      },
      "created_at": "2026-03-12T09:00:00Z"
    }
  ],
  "meta": {
    "timestamp": "2026-03-12T09:25:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Event types yang saat ini dapat muncul:

- `provider.request`
- `provider.response`
- `payment.created`
- `payment.paid`
- `payment.failed`
- `payment.expired`
- `payment.refunded`
- `provider.status_synced`
- `callback.received`
- `callback.rejected`
- `webhook.dispatched`
- `webhook.success`
- `webhook.failed`

### 8.8 Sync Payment Status from Provider

```text
POST /api/v1/payments/{payment_id}/sync
```

Tujuan:

- memaksa orchestrator query status terbaru langsung ke provider
- memperbarui state internal jika ada perubahan
- memicu webhook ke client app jika status berubah ke eventable state

Response `200`:

```json
{
  "success": true,
  "data": {
    "payment_id": "pay_01hrxyz123abc456defghi789",
    "application_code": "BLASKU",
    "external_order_id": "INV-2026-001",
    "merchant_ref": "BLASKU-20260312-A1B2C3",
    "provider": "tripay",
    "payment_method": "QRIS",
    "amount": 200000,
    "currency": "IDR",
    "status": "PAID",
    "customer": {
      "name": "Dimas Prasetio",
      "email": "dimas@example.com",
      "phone": "6281234567890"
    },
    "payment_instruction": {
      "payment_url": "https://tripay.test/checkout/T1234567890",
      "pay_code": null,
      "qr_string": "000201010212...",
      "qr_url": "https://tripay.test/qr/T1234567890"
    },
    "metadata": {
      "product_name": "Paket Premium"
    },
    "paid_at": "2026-03-12T09:15:00Z",
    "expires_at": "2026-03-12T10:00:00Z",
    "created_at": "2026-03-12T09:00:00Z",
    "updated_at": "2026-03-12T09:15:00Z",
    "sync": {
      "status_changed": true,
      "provider_status": "PAID",
      "event_type": "payment.paid",
      "synced_at": "2026-03-12T09:15:00Z",
      "source": "provider_query"
    }
  },
  "meta": {
    "timestamp": "2026-03-12T09:15:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Kemungkinan error:

- `PAYMENT_NOT_FOUND`
- `PROVIDER_ERROR`
- `PROVIDER_TIMEOUT`
- `PROVIDER_DATA_MISMATCH`
- `PROVIDER_NOT_FOUND`
- `PROVIDER_INACTIVE`

Catatan:

- jika status provider sama dengan status internal, `status_changed` bernilai `false`
- `event_type` bisa `null`
- sync dapat memperbarui status internal dan mengirim webhook baru

### 8.9 Cancel Payment

```text
POST /api/v1/payments/{payment_id}/cancel
```

Perilaku saat ini:

- hanya didukung untuk payment internal yang masih `CREATED` dan belum punya transaksi provider
- payment yang sudah provider-managed akan ditolak karena belum ada provider-side cancel API

Response `200` hanya untuk kasus internal pre-provider:

```json
{
  "success": true,
  "data": {
    "payment_id": "pay_01hrxyz123abc456defghi789",
    "status": "FAILED",
    "cancelled_at": "2026-03-12T09:30:00Z"
  },
  "meta": {
    "timestamp": "2026-03-12T09:30:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Kemungkinan error:

- `PAYMENT_NOT_FOUND`
- `PAYMENT_ALREADY_FINAL`
- `PAYMENT_CANCELLATION_NOT_SUPPORTED`

### 8.10 Refund Payment

```text
POST /api/v1/payments/{payment_id}/refund
```

Request body:

```json
{
  "amount": 200000,
  "reason": "Customer request"
}
```

Rules:

- payment harus `PAID`
- `amount` harus sama dengan amount payment
- partial refund belum didukung
- provider harus mendukung refund API sesuai konfigurasi provider

Response `200`:

```json
{
  "success": true,
  "data": {
    "payment_id": "pay_01hrxyz123abc456defghi789",
    "refund_amount": 200000,
    "status": "REFUNDED",
    "refund_method": "api",
    "refunded_at": "2026-03-12T10:00:00Z"
  },
  "meta": {
    "timestamp": "2026-03-12T10:00:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Kemungkinan error:

- `PAYMENT_NOT_FOUND`
- `PAYMENT_ALREADY_FINAL`
- `VALIDATION_ERROR`
- `REFUND_NOT_SUPPORTED`
- `PROVIDER_CONFIG_INCOMPLETE`
- `PROVIDER_REFERENCE_MISSING`
- `PROVIDER_ERROR`
- `PROVIDER_TIMEOUT`

### 8.11 List Webhook Deliveries

```text
GET /api/v1/webhook-deliveries
```

Query parameters:

| Field | Type | Required | Default | Notes |
|---|---|---:|---|---|
| `payment_id` | string | no | - | filter payment public id |
| `event_type` | string | no | - | contoh: `payment.paid` |
| `status` | string | no | - | `pending`, `success`, `failed` |
| `page` | integer | no | `1` | page number |
| `per_page` | integer | no | `20` | min 1, max 100 |

Response `200`:

```json
{
  "success": true,
  "data": [
    {
      "id": "wh_01hrxyz123abc456defghi789",
      "payment_id": "pay_01hrxyz123abc456defghi789",
      "application_code": "BLASKU",
      "event_type": "payment.paid",
      "target_url": "https://client-app.test/api/webhook/payment",
      "status": "success",
      "attempt": 1,
      "response_code": 200,
      "created_at": "2026-03-12T09:15:01Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1,
    "has_more": false
  },
  "meta": {
    "timestamp": "2026-03-12T09:20:00Z",
    "request_id": "req_abc123def456"
  }
}
```

### 8.12 Retry Webhook Delivery

```text
POST /api/v1/webhook-deliveries/{delivery_id}/retry
```

Response `200`:

```json
{
  "success": true,
  "data": {
    "id": "wh_01hrxyz123abc456defghi789",
    "status": "pending",
    "attempt": 2,
    "queued_at": "2026-03-12T09:40:00Z"
  },
  "meta": {
    "timestamp": "2026-03-12T09:40:00Z",
    "request_id": "req_abc123def456"
  }
}
```

Penting:

- endpoint ini mereset state delivery menjadi `pending` dan langsung mengantrekan resend webhook
- `attempt` dinaikkan
- response menggunakan `202 Accepted`

Error:

- `NOT_FOUND`

## 9. Outbound Webhook Contract to Client App

Payment Orchestrator mengirim webhook HTTP POST ke `applications.webhook_url`.

### 9.1 Trigger Events

- `payment.created`
- `payment.paid`
- `payment.failed`
- `payment.expired`
- `payment.refunded`

### 9.2 Headers

```text
Content-Type: application/json
X-Webhook-Signature: {hmac_sha256}
X-Webhook-Event: payment.paid
X-Webhook-Delivery-Id: wh_01hrxyz123abc456defghi789
X-Webhook-Timestamp: 1741770000
User-Agent: PaymentHub/1.0
```

### 9.3 Signature Verification

Hitung signature:

```text
hash_hmac('sha256', raw_body, webhook_secret)
```

Bandingkan hasilnya dengan `X-Webhook-Signature`.

### 9.4 Body

```json
{
  "event": "payment.paid",
  "payment_id": "pay_01hrxyz123abc456defghi789",
  "application_code": "BLASKU",
  "external_order_id": "INV-2026-001",
  "merchant_ref": "BLASKU-20260312-A1B2C3",
  "provider": "tripay",
  "payment_method": "QRIS",
  "amount": 200000,
  "currency": "IDR",
  "status": "PAID",
  "customer": {
    "name": "Dimas Prasetio",
    "email": "dimas@example.com",
    "phone": "6281234567890"
  },
  "paid_at": "2026-03-12T09:15:00Z",
  "metadata": {
    "product_name": "Paket Premium"
  },
  "timestamp": "2026-03-12T09:15:01Z"
}
```

### 9.5 Expected Response from Client App

Client app sebaiknya merespons `2xx`, misalnya:

```json
{
  "received": true
}
```

Karakteristik pengiriman saat ini:

- webhook dikirim melalui queue job
- timeout HTTP = 15 detik
- response `2xx` dianggap berhasil
- jika gagal, status delivery menjadi `failed`
- `next_retry_at` dihitung dengan backoff `1m`, `5m`, `30m`, `2h`, `12h`
- command scheduler `webhook-deliveries:retry-due` akan mengantrekan retry yang sudah jatuh tempo

## 10. Error Codes

| Code | HTTP | Meaning |
|---|---:|---|
| `AUTHENTICATION_FAILED` | 401 | API key tidak ada atau tidak valid |
| `APPLICATION_INACTIVE` | 403 | aplikasi nonaktif |
| `VALIDATION_ERROR` | 422 | request body atau query invalid |
| `PAYMENT_NOT_FOUND` | 404 | payment tidak ditemukan dalam scope aplikasi |
| `PAYMENT_METHOD_NOT_AVAILABLE` | 422 | mapping method tidak tersedia untuk provider |
| `PAYMENT_ALREADY_FINAL` | 409 | payment sudah final atau tidak eligible untuk aksi ini |
| `PAYMENT_CANCELLATION_NOT_SUPPORTED` | 409 | payment sudah provider-managed dan belum bisa dibatalkan di provider |
| `PROVIDER_NOT_FOUND` | 422 | provider tidak dikenali |
| `PROVIDER_INACTIVE` | 422 | provider nonaktif |
| `PROVIDER_ERROR` | 502 | provider mengembalikan error |
| `PROVIDER_TIMEOUT` | 504 | provider timeout |
| `PROVIDER_DATA_MISMATCH` | 409 | hasil sync provider tidak cocok dengan amount payment |
| `PROVIDER_CONFIG_INCOMPLETE` | 422 | config provider belum lengkap untuk operasi tertentu |
| `PROVIDER_REFERENCE_MISSING` | 422 | referensi provider belum tersedia untuk refund |
| `IDEMPOTENCY_CONFLICT` | 409 | idempotency key sama, payload berbeda |
| `REFUND_NOT_SUPPORTED` | 422 | refund API tidak didukung provider |
| `NOT_FOUND` | 404 | resource umum tidak ditemukan, misalnya webhook delivery |
| `RATE_LIMIT_EXCEEDED` | 429 | rate limit exceeded |
| `INTERNAL_ERROR` | 500 | unexpected internal error |

## 11. Endpoint Summary

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `GET` | `/api/v1/health` | none | public health check |
| `GET` | `/api/v1/providers` | `X-API-Key` | list active providers |
| `GET` | `/api/v1/payment-methods` | `X-API-Key` | list payment method mappings |
| `GET` | `/api/v1/payments` | `X-API-Key` | list payments |
| `POST` | `/api/v1/payments` | `X-API-Key` | create payment |
| `GET` | `/api/v1/payments/lookup` | `X-API-Key` | lookup by external order id |
| `GET` | `/api/v1/payments/{payment_id}` | `X-API-Key` | payment detail |
| `GET` | `/api/v1/payments/{payment_id}/events` | `X-API-Key` | audit trail |
| `POST` | `/api/v1/payments/{payment_id}/sync` | `X-API-Key` | refresh status from provider |
| `POST` | `/api/v1/payments/{payment_id}/cancel` | `X-API-Key` | mark payment failed |
| `POST` | `/api/v1/payments/{payment_id}/refund` | `X-API-Key` | refund paid payment |
| `GET` | `/api/v1/webhook-deliveries` | `X-API-Key` | list webhook deliveries |
| `POST` | `/api/v1/webhook-deliveries/{delivery_id}/retry` | `X-API-Key` | queue resend untuk manual retry |

## 12. Implementation Notes

- `application_code` pada create dan lookup bersifat opsional; jika dikirim nilainya harus sama dengan aplikasi yang terautentikasi.
- list payment selalu di-scope ke aplikasi yang terautentikasi, meskipun `application_code` dikirim di query.
- create payment memicu event `payment.created` dan mengantrekan webhook setelah commit.
- `cancel` tidak tersedia untuk payment yang sudah masuk ke lifecycle provider sampai provider-side cancel diimplementasikan.
- `sync` adalah endpoint manual polling yang paling akurat untuk kasus callback provider gagal diterima client app.
- provider yang saat ini didukung di code adalah `tripay`, `midtrans`, dan `xendit`.
- provider config dikelola via admin web panel, bukan JSON admin API.
- CORS saat ini terbuka untuk `api/*` dengan `allowed_origins = *`.
