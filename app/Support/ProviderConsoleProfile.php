<?php

namespace App\Support;

class ProviderConsoleProfile
{
    public static function for(string $providerCode): array
    {
        $code = strtolower($providerCode);
        $definition = self::definitions()[$code] ?? self::fallbackDefinition($code);
        $fields = array_values(array_map(
            fn (array|string $field) => self::resolveField($field),
            $definition['fields'] ?? [],
        ));
        $editableKeys = array_column($fields, 'key');
        $ignoredFields = array_values(array_map(
            fn (string $key) => self::resolveField([
                'key' => $key,
                'is_ignored' => true,
            ]),
            array_values(array_diff(self::knownConfigKeys(), $editableKeys)),
        ));
        $requirements = array_values(array_map(
            fn (string $key) => self::resolveField(['key' => $key]),
            $definition['activation_requirements'] ?? [],
        ));

        return [
            'code' => $code,
            'label' => $definition['label'] ?? strtoupper($code),
            'integration_summary' => $definition['integration_summary'] ?? null,
            'highlights' => array_values($definition['highlights'] ?? []),
            'docs' => array_values($definition['docs'] ?? []),
            'fields' => $fields,
            'activation_requirements' => $requirements,
            'ignored_fields' => $ignoredFields,
            'supports_refund_toggle' => (bool) ($definition['supports_refund_toggle'] ?? false),
        ];
    }

    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::fieldCatalog() as $key => $field) {
            $defaults[$key] = $field['default'] ?? null;
        }

        return $defaults + [
            'supports_refund_api' => false,
        ];
    }

    public static function knownConfigKeys(): array
    {
        return array_keys(self::fieldCatalog());
    }

    public static function editableFieldKeys(string $providerCode): array
    {
        return array_column(self::for($providerCode)['fields'], 'key');
    }

    public static function activationRequirementMap(string $providerCode): array
    {
        $requirements = [];

        foreach (self::for($providerCode)['activation_requirements'] as $field) {
            $requirements[$field['key']] = $field['label'];
        }

        return $requirements;
    }

    protected static function resolveField(array|string $field): array
    {
        $field = is_string($field) ? ['key' => $field] : $field;
        $key = $field['key'];
        $base = self::fieldCatalog()[$key] ?? [
            'label' => ucwords(str_replace('_', ' ', $key)),
            'type' => 'text',
            'section' => 'connection',
            'sensitive' => false,
            'placeholder' => null,
            'help' => null,
        ];

        return array_merge($base, $field);
    }

    protected static function fieldCatalog(): array
    {
        return [
            'merchant_code' => [
                'label' => 'Merchant Code',
                'type' => 'text',
                'section' => 'connection',
                'sensitive' => false,
                'placeholder' => 'TRIPAY',
                'help' => 'Kode merchant dari dashboard provider.',
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'password',
                'section' => 'secret',
                'sensitive' => true,
                'placeholder' => 'Isi untuk mengganti API key',
                'help' => 'Credential server-to-server. Kosongkan untuk mempertahankan nilai yang sudah tersimpan.',
            ],
            'private_key' => [
                'label' => 'Private Key',
                'type' => 'password',
                'section' => 'secret',
                'sensitive' => true,
                'placeholder' => 'Isi untuk mengganti private key',
                'help' => 'Dipakai untuk signature request atau verifikasi callback.',
            ],
            'client_key' => [
                'label' => 'Client Key',
                'type' => 'password',
                'section' => 'secret',
                'sensitive' => true,
                'placeholder' => 'Isi untuk mengganti client key',
                'help' => 'Dipakai untuk integrasi client-side bila diperlukan.',
            ],
            'server_key' => [
                'label' => 'Server Key',
                'type' => 'password',
                'section' => 'secret',
                'sensitive' => true,
                'placeholder' => 'Isi untuk mengganti server key',
                'help' => 'Credential server-side untuk autentikasi API dan verifikasi notifikasi.',
            ],
            'secret_key' => [
                'label' => 'Secret Key',
                'type' => 'password',
                'section' => 'secret',
                'sensitive' => true,
                'placeholder' => 'Isi untuk mengganti secret key',
                'help' => 'Secret API key untuk request server-side.',
            ],
            'callback_token' => [
                'label' => 'Callback Token',
                'type' => 'password',
                'section' => 'secret',
                'sensitive' => true,
                'placeholder' => 'Isi untuk mengganti callback token',
                'help' => 'Token verifikasi webhook/callback dari provider.',
            ],
            'api_base_url' => [
                'label' => 'API Base URL',
                'type' => 'url',
                'section' => 'endpoint',
                'sensitive' => false,
                'placeholder' => 'https://provider.test/api',
                'help' => 'Override endpoint API. Kosongkan untuk memakai endpoint bawaan adapter.',
            ],
            'public_base_url' => [
                'label' => 'Public Base URL',
                'type' => 'url',
                'section' => 'endpoint',
                'sensitive' => false,
                'placeholder' => 'https://provider.test',
                'help' => 'Override URL publik untuk link checkout atau stub/testing lokal.',
            ],
            'return_url' => [
                'label' => 'Return URL',
                'type' => 'url',
                'section' => 'endpoint',
                'sensitive' => false,
                'placeholder' => 'https://merchant.test/payment/return',
                'help' => 'URL redirect setelah pembayaran selesai jika provider mendukung flow redirect.',
            ],
            'notification_url' => [
                'label' => 'Payment Notification URL',
                'type' => 'url',
                'section' => 'endpoint',
                'sensitive' => false,
                'placeholder' => 'https://merchant.test/api/midtrans/notifications',
                'help' => 'Override URL webhook/notifikasi pembayaran jika provider mendukung override per request.',
            ],
        ];
    }

    protected static function definitions(): array
    {
        return [
            'tripay' => [
                'label' => 'Tripay',
                'integration_summary' => 'Tripay server API memakai Merchant Code, API Key, dan Private Key untuk create transaction serta verifikasi callback.',
                'highlights' => [
                    'Aktivasi minimal membutuhkan Merchant Code, API Key, dan Private Key.',
                    'Request create transaction dikirim dengan Bearer API Key dan signature HMAC-SHA256.',
                    'Callback diverifikasi dari header X-Callback-Signature menggunakan Private Key.',
                ],
                'docs' => [
                    [
                        'label' => 'Tripay Developer',
                        'url' => 'https://tripay.co.id/developer',
                        'caption' => 'Referensi transaksi, signature, endpoint sandbox/production, dan callback.',
                    ],
                ],
                'fields' => [
                    [
                        'key' => 'merchant_code',
                        'help' => 'Kode merchant Tripay. Dipakai dalam pembentukan signature transaksi.',
                        'placeholder' => 'T12345',
                    ],
                    [
                        'key' => 'api_key',
                        'help' => 'Bearer token untuk request ke Tripay API.',
                    ],
                    [
                        'key' => 'private_key',
                        'help' => 'Dipakai untuk signature create transaction dan verifikasi callback Tripay.',
                    ],
                    [
                        'key' => 'api_base_url',
                        'help' => 'Opsional. Kosongkan untuk memakai endpoint bawaan Tripay sesuai mode sandbox atau produksi.',
                        'placeholder' => 'https://tripay.co.id/api-sandbox',
                    ],
                    [
                        'key' => 'public_base_url',
                        'help' => 'Opsional. Dipakai hanya untuk override link publik atau kebutuhan testing/stub lokal.',
                        'placeholder' => 'https://tripay.co.id',
                    ],
                    [
                        'key' => 'return_url',
                        'help' => 'Opsional. Dikirim ke Tripay sebagai return_url saat membuat transaksi.',
                    ],
                ],
                'activation_requirements' => [
                    'merchant_code',
                    'api_key',
                    'private_key',
                ],
                'supports_refund_toggle' => false,
            ],
            'midtrans' => [
                'label' => 'Midtrans',
                'integration_summary' => 'Midtrans Snap API di aplikasi ini memakai Server Key untuk server-side request, sementara Client Key hanya opsional untuk channel client-side seperti Snap.js.',
                'highlights' => [
                    'Aktivasi minimal membutuhkan Server Key.',
                    'Client Key opsional untuk integrasi client-side dan tidak wajib untuk flow redirect server-side yang dipakai aplikasi ini.',
                    'Payment Notification URL dan Finish Redirect URL bersifat opsional sesuai kebutuhan konfigurasi Midtrans.',
                ],
                'docs' => [
                    [
                        'label' => 'Midtrans Access Keys',
                        'url' => 'https://docs.midtrans.com/docs/what-is-client-key-and-server-key',
                        'caption' => 'Penjelasan peran Server Key dan Client Key.',
                    ],
                    [
                        'label' => 'Midtrans Snap Guide',
                        'url' => 'https://docs.midtrans.com/docs/snap-snap-integration-guide',
                        'caption' => 'Rujukan flow Snap, redirect, dan parameter transaksi.',
                    ],
                    [
                        'label' => 'Midtrans Payment Notifications',
                        'url' => 'https://docs.midtrans.com/reference/receiving-notifications',
                        'caption' => 'Rujukan notifikasi pembayaran, signature, dan endpoint callback.',
                    ],
                ],
                'fields' => [
                    [
                        'key' => 'server_key',
                        'help' => 'Wajib. Dipakai oleh Midtrans PHP SDK untuk autentikasi API dan verifikasi signature notifikasi.',
                    ],
                    [
                        'key' => 'client_key',
                        'help' => 'Opsional. Relevan untuk integrasi client-side seperti Snap.js; flow redirect server-side aplikasi ini tetap bisa jalan tanpa field ini.',
                    ],
                    [
                        'key' => 'notification_url',
                        'help' => 'Opsional. Jika diisi, aplikasi akan mengirim override Payment Notification URL ke Midtrans.',
                    ],
                    [
                        'key' => 'return_url',
                        'help' => 'Opsional. Dipakai sebagai finish redirect URL pada payload Snap.',
                    ],
                ],
                'activation_requirements' => [
                    'server_key',
                ],
                'supports_refund_toggle' => true,
            ],
            'xendit' => [
                'label' => 'Xendit',
                'integration_summary' => 'Xendit Invoice API di aplikasi ini memakai Secret Key untuk request invoice/refund dan Callback Token untuk memverifikasi webhook invoice.',
                'highlights' => [
                    'Aktivasi minimal membutuhkan Secret Key dan Callback Token.',
                    'Create invoice memakai Secret API Key, bukan public/client key.',
                    'Webhook diverifikasi dari header x-callback-token sesuai token yang diatur di dashboard Xendit.',
                ],
                'docs' => [
                    [
                        'label' => 'Xendit Create Invoice',
                        'url' => 'https://docs.xendit.co/apidocs/create-invoice',
                        'caption' => 'Referensi parameter invoice, redirect URL, dan payment methods.',
                    ],
                    [
                        'label' => 'Xendit Webhooks',
                        'url' => 'https://docs.xendit.co/docs/getting-started-with-webhooks',
                        'caption' => 'Konfigurasi callback dan verifikasi webhook Xendit.',
                    ],
                ],
                'fields' => [
                    [
                        'key' => 'secret_key',
                        'help' => 'Wajib. Dipakai untuk request invoice dan refund lewat API Xendit.',
                    ],
                    [
                        'key' => 'callback_token',
                        'help' => 'Wajib. Harus sama dengan token pada dashboard Xendit untuk verifikasi header x-callback-token.',
                    ],
                    [
                        'key' => 'return_url',
                        'help' => 'Opsional. Dipakai sebagai success_redirect_url dan failure_redirect_url saat membuat invoice.',
                    ],
                    [
                        'key' => 'public_base_url',
                        'help' => 'Opsional. Dipakai hanya untuk override link checkout stub/testing lokal.',
                        'placeholder' => 'https://checkout.xendit.co',
                    ],
                ],
                'activation_requirements' => [
                    'secret_key',
                    'callback_token',
                ],
                'supports_refund_toggle' => true,
            ],
        ];
    }

    protected static function fallbackDefinition(string $code): array
    {
        return [
            'label' => strtoupper($code),
            'integration_summary' => 'Provider ini belum memiliki profil konfigurasi khusus. Gunakan extra config untuk kebutuhan tambahan.',
            'highlights' => [],
            'docs' => [],
            'fields' => [
                'api_base_url',
                'return_url',
            ],
            'activation_requirements' => [],
            'supports_refund_toggle' => false,
        ];
    }
}
