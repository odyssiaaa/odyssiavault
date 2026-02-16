<?php
return [
    'app' => [
        'name' => 'Odyssiavault',
        'base_url' => 'http://localhost/dropshipper/public',
        'session_name' => 'odyssiavault_session',
        'session_save_path' => '',
        'logo_path' => 'assets/logo.png',
        'default_new_user_balance' => 0,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'odyssiavault',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'ssl_mode' => 'DISABLED',
        'ssl_ca' => '',
        'ssl_ca_base64' => '',
        'ssl_cert' => '',
        'ssl_cert_base64' => '',
        'ssl_key' => '',
        'ssl_key_base64' => '',
        'ssl_verify_server_cert' => false,
    ],
    'provider' => [
        'api_url' => 'https://buzzerpanel.id/api/json.php',
        'api_key' => 'GANTI_DENGAN_API_KEY_ANDA',
        'secret_key' => 'GANTI_DENGAN_SECRET_KEY_ANDA',
        'services_variant' => 'services_1',
        'timeout' => 30,
        'request_content_type' => 'form',
        'services_cache_ttl' => 21600,
        'services_mapped_cache_ttl' => 21600,
        'cache_dir' => '',
        'services_mapped_cache_dir' => '',
    ],
    'pricing' => [
        'default_markup_percent' => 35,
        'fixed_markup' => 0,
        'round_to' => 100,
        'category_markup_percent' => [],
    ],
    'checkout' => [
        'unpaid_timeout_minutes' => 180,
        'payment_methods' => [
            [
                'code' => 'bca',
                'name' => 'Bank BCA',
                'account_name' => 'Odyssiavault',
                'account_number' => 'ISI_REKENING_BCA',
                'note' => 'Transfer sesuai total pesanan.',
            ],
            [
                'code' => 'dana',
                'name' => 'DANA',
                'account_name' => 'Odyssiavault',
                'account_number' => 'ISI_NOMOR_DANA',
                'note' => 'Transfer sesuai total pesanan.',
            ],
            [
                'code' => 'gopay',
                'name' => 'GoPay',
                'account_name' => 'Odyssiavault',
                'account_number' => 'ISI_NOMOR_GOPAY',
                'note' => 'Transfer sesuai total pesanan.',
            ],
        ],
    ],
    'payment' => [
        'deposit_min' => 10000,
        'deposit_max' => 10000000,
        'qris_image' => 'assets/qris.png',
        'qris_receiver_name' => 'Odyssiavault',
        'use_unique_code' => true,
        'unique_code_min' => 11,
        'unique_code_max' => 99,
    ],
    'payment_gateway' => [
        // Aktifkan jika sudah pakai QRIS merchant + webhook.
        'enabled' => false,
        // custom | pakasir | midtrans | tripay | xendit | duitku (label internal)
        'provider' => 'custom',
        // Pakasir
        'pakasir_api_key' => '',
        'pakasir_project_slug' => '',
        'pakasir_method' => 'qris',
        'pakasir_base_url' => 'https://app.pakasir.com',
        'pakasir_timeout' => 20,
        'pakasir_min_amount' => 500,
        'pakasir_qris_only' => true,
        // Rahasia webhook yang sama persis dengan konfigurasi di gateway.
        'webhook_secret' => '',
        // Kunci khusus provider (opsional sesuai provider yang dipakai).
        'tripay_private_key' => '',
        'midtrans_server_key' => '',
        'xendit_callback_token' => '',
        // Status yang dianggap "sudah dibayar".
        'success_statuses' => ['PAID', 'SETTLEMENT', 'SUCCESS', 'COMPLETED'],
        // Prioritas field order id yang dibaca dari payload callback.
        'order_id_keys' => ['order_id', 'merchant_ref', 'external_id', 'reference'],
    ],
    'news' => [
        // manual | provider_only | hybrid | web_only | web_provider
        'source_mode' => 'provider_only',
        'provider_services_variant' => 'services_1',
        'provider_limit' => 30,
        'provider_note_chars' => 650,
        'web_source_url' => 'https://buzzerpanel.id/',
        'web_limit' => 25,
        'web_timeout' => 12,
        'web_cache_ttl' => 900,
        'web_fail_cache_ttl' => 300,
        'web_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    ],
    'notifications' => [
        'whatsapp' => [
            'enabled' => false,
            // fonnte | webhook
            'provider' => 'fonnte',
            'admin_phone' => '6285178232383',
            'timeout' => 6,
            'fonnte_token' => '',
            'webhook_url' => '',
            'webhook_secret' => '',
        ],
        'telegram' => [
            'enabled' => false,
            'bot_token' => '',
            // Bisa isi beberapa chat id dipisah koma/spasi
            // Contoh private chat: 123456789
            // Contoh group/supergroup: -1001234567890
            // Contoh channel username: @nama_channel
            'chat_id' => '',
            'timeout' => 6,
            'disable_web_page_preview' => true,
        ],
    ],
];
