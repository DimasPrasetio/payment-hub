<?php

return [
    'navigation' => [
        [
            'label' => 'Menu Utama',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'active' => ['admin.dashboard'],
                    'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 8.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z',
                ],
                [
                    'label' => 'Riwayat Transaksi',
                    'route' => 'admin.transactions',
                    'active' => ['admin.transactions', 'admin.transactions.show'],
                    'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
                ],
                [
                    'label' => 'Statistics',
                    'route' => 'admin.statistics',
                    'active' => ['admin.statistics'],
                    'icon' => 'M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25h12A2.25 2.25 0 0 0 20.25 18V9.75m-12.75-6h4.5m0 0v4.5m0-4.5L6.75 9m4.5 6 2.25-2.25 1.5 1.5 3.75-3.75',
                ],
            ],
        ],
        [
            'label' => 'Data Master',
            'items' => [
                [
                    'label' => 'Daftar Aplikasi',
                    'route' => 'admin.applications',
                    'active' => ['admin.applications', 'admin.applications.create', 'admin.applications.show'],
                    'icon' => 'M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L12 12.75 6.429 9.75m11.142 0 4.179 2.25-9.75 5.25-9.75-5.25 4.179-2.25',
                ],
                [
                    'label' => 'Saluran Pembayaran',
                    'route' => 'admin.providers',
                    'active' => ['admin.providers', 'admin.providers.show'],
                    'icon' => 'M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008ZM6.75 8.25h.008v.008H6.75V8.25Z',
                ],
                [
                    'label' => 'Metode Pembayaran',
                    'route' => 'admin.payment-methods',
                    'active' => ['admin.payment-methods'],
                    'icon' => 'M3.75 15.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-7.5a3 3 0 0 0-3-3H6.75a3 3 0 0 0-3 3v7.5ZM7.5 7.5h9m-9 3h6',
                ],
            ],
        ],
        [
            'label' => 'Sistem & Log',
            'items' => [
                [
                    'label' => 'Admin Users',
                    'route' => 'admin.users.index',
                    'active' => ['admin.users.index', 'admin.users.create', 'admin.users.edit'],
                    'icon' => 'M18 18.72a8.94 8.94 0 0 0 3.75.78 8.94 8.94 0 0 0 3.75-.78m-7.5 0a9 9 0 1 1 7.5 0m-7.5 0A7.5 7.5 0 1 0 6 18.72m0 0a8.94 8.94 0 0 1-3.75.78 8.94 8.94 0 0 1-3.75-.78m7.5 0v-.972c0-1.21-.907-2.269-2.25-2.592A5.98 5.98 0 0 1 4.5 15a5.98 5.98 0 0 1 1.254-.315C7.093 14.362 8 13.303 8 12.093V11.12m10 7.6v-.972c0-1.21.907-2.269 2.25-2.592A5.98 5.98 0 0 0 19.5 15a5.98 5.98 0 0 0-1.254-.315C16.907 14.362 16 13.303 16 12.093V11.12M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
                ],
                [
                    'label' => 'Riwayat Notifikasi',
                    'route' => 'admin.callbacks',
                    'active' => ['admin.callbacks'],
                    'icon' => 'M3.75 12h16.5m-16.5 0 3.75-3.75M3.75 12l3.75 3.75M20.25 6.75h-16.5m16.5 0-3.75-3.75m3.75 3.75-3.75 3.75',
                ],
                [
                    'label' => 'Sinkronisasi Data',
                    'route' => 'admin.webhooks',
                    'active' => ['admin.webhooks'],
                    'icon' => 'M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.98l-7.5 4.136a2.25 2.25 0 0 1-2.134 0l-7.5-4.136A2.25 2.25 0 0 1 2.25 9.906V9m19.5 0a2.25 2.25 0 0 0-1.183-1.98l-7.5-4.136a2.25 2.25 0 0 0-2.134 0l-7.5 4.136A2.25 2.25 0 0 0 2.25 9m19.5 0-7.5 4.136a2.25 2.25 0 0 1-2.134 0L2.25 9',
                ],
                [
                    'label' => 'Aktivitas Pengguna',
                    'route' => 'admin.audit-trail',
                    'active' => ['admin.audit-trail'],
                    'icon' => 'M9 12.75 11.25 15 15 9.75m5.25 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                ],
                [
                    'label' => 'Pengecekan Data',
                    'route' => 'admin.reconciliation',
                    'active' => ['admin.reconciliation'],
                    'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v5.25a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 18v-5.25A2.25 2.25 0 0 1 6.75 10.5Z',
                ],
                [
                    'label' => 'Pengembalian Dana',
                    'route' => 'admin.refunds',
                    'active' => ['admin.refunds'],
                    'icon' => 'M15 15l6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3',
                ],
            ],
        ],
    ],
];
