# PRD

## Payment Orchestrator / Payment Hub

### Current Implementation Baseline

Dokumen ini adalah PRD yang diperbarui berdasarkan fakta implementasi code saat ini. Isi dokumen ini merepresentasikan scope yang benar-benar tersedia, batasan yang nyata, dan area lanjutan yang masih terbuka.

## 1. Product Overview

Payment Orchestrator adalah backend terpusat yang menjadi perantara antara banyak aplikasi client dengan payment provider eksternal seperti Tripay, Midtrans, dan Xendit.

Tujuan utamanya:

- memusatkan integrasi payment gateway
- menghindari penyebaran credential provider ke banyak aplikasi
- menyediakan API payment yang seragam untuk client app
- menangani callback provider dan webhook ke aplikasi asal
- menyediakan audit trail transaksi dan panel operasional admin

Stack implementasi saat ini:

- Laravel 10
- PHP 8.1+
- MySQL
- Redis opsional
- REST API
- Admin panel web berbasis Blade dan session auth

## 2. Product Goals

### 2.1 Business Goals

- satu backend payment terpusat untuk banyak aplikasi
- credential provider dikelola di satu tempat oleh admin
- aplikasi client menggunakan kontrak API yang seragam
- transaksi dapat diaudit secara terpusat
- satu aplikasi dapat diarahkan ke provider default tertentu
- banyak aplikasi dapat berbagi satu konfigurasi provider yang sama

### 2.2 Technical Goals

- multi-provider abstraction melalui adapter
- ledger transaksi terpusat
- callback verification per provider
- outbound webhook ke aplikasi asal
- id transaksi publik yang aman untuk API
- admin panel untuk manajemen user, aplikasi, provider, dan monitoring

## 3. User Roles

### 3.1 Admin

Admin saat ini dapat:

- login ke panel admin
- mengelola user admin
- mengelola aplikasi client
- mengelola credential dan status provider
- melihat dashboard dan statistik transaksi
- melihat daftar transaksi, callback log, webhook log, dan audit trail

### 3.2 Client Application

Client application dapat:

- autentikasi dengan `X-API-Key`
- membuat transaksi payment
- melihat detail dan daftar transaksi sendiri
- melakukan lookup by `external_order_id`
- melakukan manual sync status ke provider
- melakukan cancel internal payment
- meminta refund untuk payment yang sudah paid
- melihat delivery webhook miliknya sendiri

## 4. Current Architecture

Arsitektur aktual:

```text
Client App
   |
   v
Payment Hub API
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
Provider Callback Processing
   |
   v
WebhookService
   |
   v
Client App Webhook Endpoint
```

Komponen penting:

- `PaymentService` sebagai orchestration layer utama
- `PaymentProviderInterface` sebagai kontrak adapter
- `ProviderResolver` untuk memilih adapter berdasarkan `provider_code`
- `WebhookService` untuk pengiriman webhook outbound
- admin panel web untuk konfigurasi dan observability

## 5. Implemented Features

### 5.1 Admin Authentication and User Management

Sudah tersedia:

- login admin via `/admin/login`
- logout admin
- user management CRUD
- status aktif user admin
- seeder awal `superadmin / superadmin`

Belum tersedia:

- role-based permission matrix
- 2FA
- password reset flow

### 5.2 Application Management

Sudah tersedia melalui admin panel:

- create application
- update application
- delete application jika belum punya transaksi atau webhook delivery
- rotate API key
- rotate webhook secret
- pilih default provider aktif saat create atau update

Perilaku penting:

- API key aplikasi disimpan hashed SHA-256
- webhook secret aplikasi disimpan terenkripsi
- satu aplikasi hanya menunjuk satu `default_provider`

### 5.3 Provider Management

Sudah tersedia melalui admin panel:

- list provider
- view detail provider
- update credential provider
- aktifkan atau nonaktifkan provider
- toggle sandbox mode
- atur `supports_refund_api`

Provider seeded bawaan:

- `tripay`
- `midtrans`
- `xendit`

Credential minimum sebelum aktivasi:

- Tripay: `merchant_code`, `api_key`, `private_key`
- Midtrans: `server_key`
- Xendit: `secret_key`, `callback_token`

### 5.4 Client Payment API

Sudah tersedia:

- `GET /api/v1/providers`
- `GET /api/v1/payment-methods`
- `GET /api/v1/payments`
- `POST /api/v1/payments`
- `GET /api/v1/payments/lookup`
- `GET /api/v1/payments/{payment_id}`
- `GET /api/v1/payments/{payment_id}/events`
- `POST /api/v1/payments/{payment_id}/sync`
- `POST /api/v1/payments/{payment_id}/cancel`
- `POST /api/v1/payments/{payment_id}/refund`
- `GET /api/v1/webhook-deliveries`
- `POST /api/v1/webhook-deliveries/{delivery_id}/retry`
- `GET /api/v1/health`

Auth model:

- `X-API-Key`
- scope data selalu dibatasi ke aplikasi yang terautentikasi
- rate limit `60 request / menit`

### 5.5 Multi-provider Payment Creation

Flow create payment aktual:

1. client app mengirim request create payment
2. sistem validasi payload dan aplikasi terautentikasi
3. provider ditentukan dari `provider_code` request atau `default_provider` aplikasi
4. payment method internal di-map ke `payment_method_mappings`
5. payment order dibuat dengan status `CREATED`
6. sistem mencatat event `provider.request`
7. adapter provider dipanggil untuk create transaction
8. snapshot request dan response provider disimpan di `provider_transactions`
9. payment diubah ke `PENDING`
10. event `payment.created` dicatat
11. webhook `payment.created` dibuat dan langsung dicoba kirim

### 5.6 Provider Callback Handling

Sudah tersedia di:

```text
POST /api/v1/callback/{provider_code}
```

Supported callback verification:

- Tripay: HMAC SHA256 raw body dengan `private_key`
- Midtrans: signature SHA512
- Xendit: `x-callback-token`

Flow callback aktual:

1. resolve provider dari path
2. verifikasi signature atau token via adapter
3. lookup payment berdasarkan `provider_code + merchant_ref`
4. cek amount match
5. jika payment sudah final maka callback menjadi no-op
6. map status provider ke status internal
7. update payment dan latest provider transaction
8. catat payment event
9. buat outbound webhook untuk aplikasi asal
10. langsung kirim webhook secara sinkron

### 5.7 Manual Payment Status Sync

Sudah tersedia endpoint manual:

```text
POST /api/v1/payments/{payment_id}/sync
```

Fungsi:

- query status terbaru langsung ke provider
- sinkronkan status internal jika berubah
- catat event `provider.status_synced`
- kirim webhook outbound jika perubahan status menghasilkan event payment

Ini adalah mekanisme utama untuk polling manual saat callback provider gagal diterima client app.

### 5.8 Refund

Sudah tersedia secara API untuk payment yang sudah `PAID`.

Perilaku aktual:

- refund hanya bisa dilakukan jika provider mengizinkan `supports_refund_api`
- Midtrans dan Xendit punya jalur refund API nyata bila credential lengkap
- Tripay tetap tergantung flag config dan pada implementasi saat ini belum punya call refund nyata ke API eksternal
- jika refund berhasil, status payment berubah menjadi `REFUNDED`
- webhook `payment.refunded` akan dibuat dan dicoba kirim

### 5.9 Outbound Webhook

Sudah tersedia:

- payload webhook standar ke `applications.webhook_url`
- HMAC SHA256 signature dengan `webhook_secret`
- header `X-Webhook-Signature`, `X-Webhook-Event`, `X-Webhook-Delivery-Id`, `X-Webhook-Timestamp`
- log delivery ke `webhook_deliveries`
- event `webhook.success` dan `webhook.failed`

Retry behavior aktual:

- `next_retry_at` dihitung dengan delay `1m`, `5m`, `30m`, `2h`, `12h`
- belum ada worker otomatis yang mengeksekusi retry
- endpoint retry manual saat ini hanya mereset state delivery menjadi `pending`

### 5.10 Admin Monitoring

Sudah tersedia halaman admin untuk:

- dashboard
- statistics
- transactions
- applications
- providers
- payment methods
- callbacks
- webhooks
- audit trail
- reconciliation
- refunds

Sebagian halaman bersifat observability dan read-only.

## 6. Provider Support Matrix

| Provider | Create | Query / Sync | Callback Verify | Refund | Notes |
|---|---|---|---|---|---|
| Tripay | yes | yes | yes | partial | refund API belum benar-benar terintegrasi ke API eksternal |
| Midtrans | yes | yes | yes | yes | memakai SDK resmi |
| Xendit | yes | yes | yes | yes | memakai SDK resmi |

Catatan:

- semua adapter memiliki fallback stub bila credential minimum belum lengkap
- runtime tetap membutuhkan provider aktif agar request client bisa diproses

## 7. Status Lifecycle

Status internal:

- `CREATED`
- `PENDING`
- `PAID`
- `FAILED`
- `EXPIRED`
- `REFUNDED`

Mapping status utama:

Tripay:

- `UNPAID` -> `PENDING`
- `PAID` -> `PAID`
- `FAILED` -> `FAILED`
- `EXPIRED` -> `EXPIRED`
- `REFUND` atau `REFUNDED` -> `REFUNDED`

Midtrans:

- `pending` atau `authorize` -> `PENDING`
- `settlement` -> `PAID`
- `capture` -> `PAID` atau `PENDING` jika fraud `challenge`
- `expire` -> `EXPIRED`
- `deny`, `cancel`, `failure` -> `FAILED`
- `refund`, `partial_refund`, `chargeback`, `partial_chargeback` -> `REFUNDED`

Xendit:

- `PAID` atau `SETTLED` -> `PAID`
- `EXPIRED` -> `EXPIRED`
- status lain -> `PENDING`

## 8. Data Model

### 8.1 `users`

Field utama:

- `username`
- `name`
- `email`
- `password`
- `is_active`

### 8.2 `payment_providers`

Field utama:

- `code`
- `name`
- `config`
- `is_active`
- `sandbox_mode`

Catatan:

- `code` unique
- satu row provider merepresentasikan satu konfigurasi provider

### 8.3 `payment_method_mappings`

Field utama:

- `internal_code`
- `provider_code`
- `provider_method_code`
- `display_name`
- `group`
- `icon_url`
- `fee_flat`
- `fee_percent`
- `min_amount`
- `max_amount`
- `is_active`

### 8.4 `applications`

Field utama:

- `code`
- `name`
- `api_key`
- `default_provider`
- `webhook_url`
- `webhook_secret`
- `status`

### 8.5 `payment_orders`

Field utama:

- `public_id`
- `application_id`
- `tenant_id`
- `external_order_id`
- `idempotency_key`
- `merchant_ref`
- `provider_code`
- `payment_method`
- `customer_name`
- `customer_email`
- `customer_phone`
- `amount`
- `currency`
- `status`
- `metadata`
- `paid_at`
- `expires_at`

### 8.6 `provider_transactions`

Field utama:

- `payment_order_id`
- `provider`
- `merchant_ref`
- `provider_reference`
- `payment_method`
- `payment_url`
- `pay_code`
- `qr_string`
- `qr_url`
- `raw_request`
- `raw_response`
- `paid_at`

### 8.7 `payment_events`

Field utama:

- `public_id`
- `payment_order_id`
- `event_type`
- `payload`
- `created_at`

### 8.8 `webhook_deliveries`

Field utama:

- `public_id`
- `payment_order_id`
- `application_id`
- `event_type`
- `target_url`
- `request_body`
- `response_code`
- `response_body`
- `attempt`
- `status`
- `next_retry_at`
- `created_at`

## 9. Security Baseline

### 9.1 Client API

- API key via `X-API-Key`
- rate limiting per API key
- data isolation per authenticated application
- public IDs dipakai di API

### 9.2 Provider Credential Security

- credential provider disimpan terenkripsi di database
- credential tidak diekspos ke client API
- credential dikelola lewat admin panel

### 9.3 Application Credential Security

- API key aplikasi disimpan hashed
- webhook secret aplikasi disimpan terenkripsi
- API key plain hanya ditampilkan saat issue atau rotate

### 9.4 Callback Security

- verifikasi signature atau token per adapter
- validasi `merchant_ref`
- validasi amount

## 10. Current Constraints and Gaps

Berikut batasan implementasi saat ini yang perlu diperlakukan sebagai known constraint:

- belum ada admin JSON API
- JWT auth belum diimplementasikan
- `idempotency_key` masih global unique, belum scoped per application dan belum memiliki TTL
- webhook delivery masih sinkron, belum queued
- retry webhook otomatis belum berjalan walau `next_retry_at` sudah dihitung
- endpoint manual retry webhook belum melakukan resend HTTP
- health check melaporkan `queue: running` sebagai nilai statis, bukan inspeksi worker aktual
- `GET /api/v1/payment-methods` memfilter status mapping, bukan status provider
- skema provider saat ini belum mendukung beberapa merchant profile berbeda untuk provider code yang sama
- cancel payment belum mengirim cancel request ke provider

## 11. Routing Strategy

Routing provider yang benar-benar tersedia sekarang:

### 11.1 Default Provider per Application

Setiap aplikasi memiliki `default_provider`.

### 11.2 Explicit Override by Client Request

Client boleh mengirim `provider_code` pada create payment untuk override provider default.

### 11.3 Shared Provider Configuration

Banyak aplikasi dapat menunjuk ke `default_provider` yang sama, sehingga satu konfigurasi merchant provider dapat dipakai bersama oleh beberapa aplikasi.

### 11.4 Not Yet Supported

Belum tersedia:

- payment method-based routing otomatis
- provider fallback otomatis
- multiple merchant profile untuk `tripay`, `midtrans`, atau `xendit` dalam satu jenis provider yang sama

## 12. Operational Defaults

Fakta konfigurasi saat ini:

- timezone aplikasi: `UTC`
- CORS aktif untuk `api/*` dengan `allowed_origins = *`
- rate limit client API: `60 request / menit`
- provider seeded default: `tripay`, `midtrans`, `xendit`
- provider seeded default dalam keadaan `sandbox_mode = true` dan `is_active = false`

## 13. Recommended Next Enhancements

Prioritas lanjutan yang paling relevan terhadap arsitektur saat ini:

- ubah webhook delivery menjadi queue-based dan buat worker retry otomatis
- jadikan `idempotency_key` scoped per application dengan TTL
- sediakan admin JSON API bila diperlukan integrasi eksternal
- dukung multiple merchant profile per provider type
- sinkronkan payment methods langsung dari provider API
- tambahkan cancel provider API untuk provider yang mendukung
- tambahkan role dan permission yang lebih granular di admin panel

## 14. Conclusion

Implementasi saat ini sudah berfungsi sebagai Payment Orchestrator yang usable untuk skenario multi-application dengan provider `tripay`, `midtrans`, dan `xendit`, lengkap dengan admin panel operasional, application management, provider credential management, callback handling, manual status sync, dan outbound webhook.

Namun arsitektur saat ini masih merupakan baseline implementasi, bukan final enterprise scope. Fokus kekuatan ada pada sentralisasi integrasi payment dan keseragaman kontrak client API, sementara area seperti async processing, multi merchant profile, dan admin API masih menjadi ruang pengembangan berikutnya.
