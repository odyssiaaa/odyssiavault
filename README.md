# Odyssiavault Dropshipper (PHP Native + XAMPP)

## Fitur Utama
- Website fokus buyer/customer Odyssiavault.
- Login & register user (session PHP) dengan proses cepat.
- Pembayaran direct tanpa deposit/wallet.
- Search bar kategori & layanan dengan prioritas hasil relevan.
- Optimasi daftar layanan: kategori/search/katalog dimuat bertahap dari server (tidak tarik semua layanan sekaligus), jadi jauh lebih ringan di mobile/laptop dan aman untuk deploy serverless.
- Optimasi performa produksi:
  - auto-index database untuk query order/ticket yang sering dipakai,
  - auto-expire order unpaid ditrigger terukur (anti query berulang berlebihan),
  - proteksi race-condition request async di frontend (order/ticket/admin payment).
- Hardening keamanan dasar:
  - rate-limit endpoint kritikal (login/register/checkout/konfirmasi pembayaran/tiket),
  - cookie secure detection lebih stabil di reverse proxy (Vercel/Cloud).
- Kolom komentar dinamis (wajib hanya untuk layanan tipe Komen).
- Harga layanan mengikuti provider (basis per 1000) + markup 35% semua layanan + ditampilkan juga harga satuan.
- Dukungan field order kondisional API:
  - `komen`, `comments`, `usernames`, `username`, `hashtags`, `keywords`.
  - quantity otomatis untuk tipe Comment, Comment Replies, dan Mentions Custom List.
- Order ke provider dengan markup harga otomatis.
- Riwayat order user + cek status order provider.
- Fitur refill provider:
  - ajukan refill berdasarkan ID order.
  - cek status refill (`status_refill`) dari provider.
  - riwayat refill tersimpan per user.
- Dashboard buyer bergaya panel (menu Dashboard/Top 5/Pembelian/Refill/Pembayaran/Tiket/Daftar Layanan/Halaman).
- Sistem tiket laporan (buat tiket, balas tiket, tutup/buka kembali, riwayat tiket).
- Dashboard awal difokuskan untuk berita/pengumuman/update layanan.
- Sistem berita otomatis dari API provider (mode `provider_only`), dengan opsi hybrid/manual.
- Top 5 layanan terpopuler otomatis berdasarkan jumlah order sukses (tanpa data dummy).
  - Top 5 hanya muncul jika sudah ada order sukses valid.
- Alur pembayaran direct:
  - pilih layanan -> checkout -> transfer -> konfirmasi pembayaran -> admin verifikasi -> order diproses.
- Notifikasi WhatsApp admin (opsional): otomatis kirim alert saat buyer submit konfirmasi pembayaran.

## Struktur Penting
- `public/index.php` : UI dashboard.
- `public/assets/app.css` : tema ungu Odyssiavault.
- `public/assets/app.js` : logic auth/order/history.
- `public/api/*.php` : endpoint backend.
- `database/schema.sql` : skema MySQL.
- `config/config.php` : konfigurasi app, db, provider.

## 1) Setup Folder
Pastikan project di:
- `d:/Farrel/xampp/htdocs/dropshipper`

## 2) Setup Database (phpMyAdmin)
1. Buka phpMyAdmin.
2. Import file `database/schema.sql`.
3. Pastikan database `odyssiavault` terbentuk.
4. Jika database sudah lama dipakai, pastikan tabel baru `news_posts`, `tickets`, dan `ticket_messages` sudah dibuat.
5. Untuk mode direct payment terbaru, pastikan kolom payment pada tabel `orders` sudah ada (import ulang schema direkomendasikan).
6. Untuk fitur refill, pastikan tabel `order_refills` sudah ada (import ulang `database/schema.sql` direkomendasikan).

## 3) Setup Konfigurasi
Edit `config/config.php`:
- `db` (host, database, username, password).
- `provider.api_key` dan `provider.secret_key`.
- `provider.request_content_type`:
  - `form` (default) atau `json`.
- `pricing` untuk aturan markup.
- `checkout` untuk direct payment:
  - `unpaid_timeout_minutes` (batas bayar otomatis cancel)
  - `payment_methods` (BCA / DANA / GoPay)

## 4) Jalankan
1. Start Apache + MySQL dari XAMPP.
2. Buka:
- `http://localhost/dropshipper/public`

## 5) Alur Awal
- Register akun pertama -> otomatis role `admin`.
- User checkout layanan, lalu bayar langsung dan konfirmasi pembayaran.

## Alur Dashboard Berita
1. Setelah login, user langsung diarahkan ke menu `Dashboard`.
2. Dashboard menampilkan feed berita terbaru (judul, tanggal, ringkasan).
3. Tombol `Baca Selengkapnya` membuka detail internal + link sumber (jika ada).
4. Jika tidak ada data, tampil pesan: `Belum ada berita terbaru saat ini.`
5. Mode default saat ini: berita otomatis dari API provider (`action=services_1`) sehingga update layanan muncul realtime dari provider.

## Kelola Berita (Admin)
- Form admin tersedia di dashboard (`Kelola Berita (Admin)`):
  - judul, ringkasan, konten lengkap, sumber, URL sumber, status publish.
- API terkait:
- `public/api/news_list.php`
- `public/api/news_admin_list.php`
- `public/api/news_admin_save.php`
- `public/api/news_admin_delete.php`
- `public/api/top_services.php`

Konfigurasi sumber berita (`config/config.php`):
- `news.source_mode`:
  - `web_provider` (default, tarik berita/pengumuman dari web sumber lalu fallback ke provider)
  - `web_only` (hanya web sumber, tanpa fallback)
  - `provider_only` (otomatis dari API provider)
  - `hybrid` (gabungan provider + manual admin)
  - `manual` (hanya input admin)
- `news.provider_services_variant`: `services`, `services_1`, `services2`, `services3`
- `news.provider_limit`: jumlah item provider yang ditampilkan
- `news.web_source_url`: URL web sumber berita (default `https://buzzerpanel.id/`)
- `news.web_timeout`: timeout fetch web (detik)
- `news.web_cache_ttl`: TTL cache saat fetch web berhasil
- `news.web_fail_cache_ttl`: TTL cache gagal untuk mengurangi retry berulang

## Kontak Resmi Odyssiavault
- WhatsApp: `+62 851-7823-2383`
- Instagram: `@odyssiavault`
- Grup WhatsApp (App Premium Lengkap): `https://chat.whatsapp.com/I1HfRLDI9ie3xjItKj7e41`

## Mekanisme Pembayaran Dropshipper (Direct Payment)
1. Buyer checkout order (status awal: `Menunggu Pembayaran`).
2. Sistem tampilkan metode pembayaran dan batas waktu bayar.
3. Buyer transfer lalu klik konfirmasi pembayaran.
4. Admin verifikasi pembayaran:
   - `verify` -> order dikirim ke provider, status `Diproses`
   - `cancel` -> status `Dibatalkan`
5. Status provider sukses akan menjadi `Selesai`.
6. Jika tidak dibayar sampai batas waktu, order otomatis `Dibatalkan`.

Catatan: endpoint `public/api/profile.php` hanya bisa diakses admin, jadi data profile/saldo provider tidak terekspos ke buyer.

## Endpoint Pembayaran Direct
- `public/api/order.php` (buat checkout)
- `public/api/order_payment_confirm.php` (konfirmasi pembayaran buyer)
- `public/api/order_admin_payments.php` (list verifikasi admin)
- `public/api/order_admin_verify.php` (verify/cancel admin)
- `public/api/payment_gateway_webhook.php` (callback webhook auto-verifikasi pembayaran + auto-kirim order ke provider)

## Endpoint Tiket
- `public/api/tickets.php` (list tiket)
- `public/api/ticket_create.php` (buat tiket baru)
- `public/api/ticket_detail.php` (detail + percakapan tiket)
- `public/api/ticket_reply.php` (balas tiket)
- `public/api/ticket_update.php` (tutup / buka kembali tiket)

## Endpoint Refill
- `public/api/order_refill.php` (ajukan refill ke provider)
- `public/api/order_refill_status.php` (cek status refill provider)
- `public/api/order_refills.php` (riwayat refill buyer)

## Catatan Keamanan
- Jangan expose `config/config.php` ke publik repository.
- Jika API key sempat tersebar, regenerate key dari provider.

## Deploy ke Vercel (Siap Pakai)

Project ini sudah dirapikan untuk Vercel:
- Ada `vercel.json` (routing + runtime PHP).
- Konfigurasi bisa dibaca dari Environment Variables.
- Jika `config/config.php` tidak ada di repo, sistem fallback ke `config/config.example.php`.
- Template env tersedia di `.env.example`.

### Prasyarat
1. Kode ada di GitHub repository.
2. Database MySQL online (bukan MySQL lokal XAMPP).
3. Akun Vercel sudah aktif.

### 1) Import Project ke Vercel
1. Masuk Vercel Dashboard.
2. Klik `Add New...` -> `Project`.
3. Pilih repository project ini.
4. Klik `Deploy` (build command tidak perlu diisi).

Alternatif via CLI:
1. Install CLI: `npm i -g vercel`
2. Login: `vercel login`
3. Dari root project jalankan: `vercel`
4. Untuk production: `vercel --prod`

### 2) Isi Environment Variables (Wajib)
Masuk ke:
`Project Settings -> Environment Variables`

Minimal isi:
- `APP_BASE_URL` = `https://nama-project-kamu.vercel.app`
- salah satu:
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - atau `DATABASE_URL` (format: `mysql://user:pass@host:port/dbname?ssl-mode=REQUIRED`)
- untuk Aiven/DB SSL:
  - `DB_SSL_MODE` = `REQUIRED`
  - opsional `DB_SSL_CA` (path) atau `DB_SSL_CA_BASE64` (isi cert CA dalam base64)
- `PROVIDER_API_URL` = `https://buzzerpanel.id/api/json.php`
- `PROVIDER_API_KEY`
- `PROVIDER_SECRET_KEY`

Direkomendasikan isi juga:
- `APP_NAME` = `Odyssiavault`
- `SESSION_NAME` = `odyssiavault_session`
- `APP_AUTH_SECRET` = secret random panjang (wajib untuk login stabil di serverless)
- `PROVIDER_REQUEST_CONTENT_TYPE` = `form`
- `PRICING_DEFAULT_MARKUP_PERCENT` = `35`
- `CHECKOUT_UNPAID_TIMEOUT_MINUTES` = `180`
- `PAYMENT_QRIS_IMAGE` = `assets/qris.png`
- `PAYMENT_QRIS_RECEIVER_NAME` = `Odyssiavault`
- `NEWS_SOURCE_MODE` = `provider_only`
- `NEWS_PROVIDER_VARIANT` = `services_1`
- `WHATSAPP_ADMIN_NOTIFY_ENABLED` = `true` (jika ingin aktif)
- `WHATSAPP_PROVIDER` = `fonnte`
- `WHATSAPP_ADMIN_PHONE` = `628xxxxxxxxxx`
- `WHATSAPP_FONNTE_TOKEN` = `TOKEN_API_FONNTE`
- `TELEGRAM_ADMIN_NOTIFY_ENABLED` = `true` (jika ingin aktif)
- `TELEGRAM_BOT_TOKEN` = `123456789:ABC...`
- `TELEGRAM_ADMIN_CHAT_ID` = `123456789` (atau `-100...` untuk grup)
- `PAYMENT_GATEWAY_ENABLED` = `false` (ubah ke `true` saat siap auto-ACC webhook)
- `PAYMENT_GATEWAY_PROVIDER` = `custom|pakasir|tripay|midtrans|xendit`
- `PAYMENT_GATEWAY_WEBHOOK_SECRET` = `...` (untuk mode `custom`)
- `PAYMENT_GATEWAY_PAKASIR_API_KEY` = `...` (untuk mode `pakasir`)
- `PAYMENT_GATEWAY_PAKASIR_PROJECT_SLUG` = `...` (slug project Pakasir)
- `PAYMENT_GATEWAY_PAKASIR_METHOD` = `qris`
- `PAYMENT_GATEWAY_PAKASIR_BASE_URL` = `https://app.pakasir.com`
- `PAYMENT_GATEWAY_PAKASIR_MIN_AMOUNT` = `500`
- `PAYMENT_GATEWAY_TRIPAY_PRIVATE_KEY` = `...` (untuk mode `tripay`)
- `PAYMENT_GATEWAY_MIDTRANS_SERVER_KEY` = `...` (untuk mode `midtrans`)
- `PAYMENT_GATEWAY_XENDIT_CALLBACK_TOKEN` = `...` (untuk mode `xendit`)

Opsional (jika mau set metode bayar dari ENV):
- `PAYMENT_METHODS_JSON`
```json
[
  {"code":"bca","name":"Bank BCA","account_name":"Odyssiavault","account_number":"ISI_REKENING_BCA","note":"Transfer sesuai total pesanan."},
  {"code":"dana","name":"DANA","account_name":"Odyssiavault","account_number":"ISI_NOMOR_DANA","note":"Transfer sesuai total pesanan."},
  {"code":"gopay","name":"GoPay","account_name":"Odyssiavault","account_number":"ISI_NOMOR_GOPAY","note":"Transfer sesuai total pesanan."}
]
```

### 3) Inisialisasi Database Online
1. Buka phpMyAdmin/DB admin dari provider database kamu.
2. Import `database/schema.sql`.
3. Pastikan semua tabel terbentuk (`users`, `orders`, `news_posts`, dll).

### 4) Redeploy
Setelah env sudah diisi:
1. Buka tab `Deployments`.
2. Klik deployment terakhir.
3. Klik `Redeploy`.

### 5) Verifikasi Setelah Live
1. Coba register user baru.
2. Login.
3. Buka `Pembelian`, pilih layanan, checkout.
4. Cek `Riwayat` dan panel admin verifikasi pembayaran.

## Catatan Penting Vercel
- Runtime PHP di Vercel menggunakan community runtime (`vercel-php`).
- Jangan simpan secret di `config/config.php` untuk production.
- Gunakan Environment Variables untuk semua credential.

## Notifikasi WhatsApp Admin (Opsional)
Notifikasi dikirim saat buyer klik konfirmasi pembayaran (`order_payment_confirm`) agar admin tidak lupa ACC.

Konfigurasi paling cepat via ENV:
- `WHATSAPP_ADMIN_NOTIFY_ENABLED=true`
- `WHATSAPP_PROVIDER=fonnte`
- `WHATSAPP_ADMIN_PHONE=6285178232383`
- `WHATSAPP_FONNTE_TOKEN=...`

Alternatif provider custom:
- `WHATSAPP_PROVIDER=webhook`
- `WHATSAPP_WEBHOOK_URL=https://domain-kamu/webhook/wa`
- `WHATSAPP_WEBHOOK_SECRET=...` (opsional)

## Notifikasi Telegram Admin (Opsional - Disarankan)
Juga dikirim saat buyer klik konfirmasi pembayaran.

Konfigurasi ENV:
- `TELEGRAM_ADMIN_NOTIFY_ENABLED=true`
- `TELEGRAM_BOT_TOKEN=123456789:ABC...`
- `TELEGRAM_ADMIN_CHAT_ID=123456789`

`TELEGRAM_ADMIN_CHAT_ID` bisa satu atau banyak, pisah dengan koma/spasi:
- `123456789`
- `-1001234567890`
- `@channelusername`

Troubleshooting cepat:
1. Buka bot, lalu kirim `/start` dari akun admin.
2. Cek chat id terdeteksi:
  - `public/api/telegram_chat_discovery.php` (khusus admin)
3. Test kirim pesan dari server:
  - `public/api/telegram_notify_test.php` (POST/GET, khusus admin)
4. Jika masih gagal, cek log PHP untuk pesan `Telegram notify failed`.

## Auto-ACC Pembayaran via Webhook Gateway
Jika kamu pakai QRIS merchant yang punya callback webhook, order bisa auto-ACC tanpa klik admin.

Konfigurasi:
- `payment_gateway.enabled = true`
- `payment_gateway.webhook_secret = RAHASIA_WEBHOOK`
- URL callback di gateway: `https://domainkamu/public/api/payment_gateway_webhook.php`

Mode verifikasi webhook yang didukung:
- `custom`: validasi `X-Webhook-Secret` / payload `secret`.
- `pakasir`: validasi callback via API `transactiondetail` Pakasir.
- `tripay`: validasi `X-Callback-Signature` (HMAC SHA-256 raw body).
- `midtrans`: validasi `signature_key` dari payload.
- `xendit`: validasi header `X-Callback-Token`.

### Setup Pakasir (QRIS Auto)
1. Set konfigurasi:
   - `PAYMENT_GATEWAY_ENABLED=true`
   - `PAYMENT_GATEWAY_PROVIDER=pakasir`
   - `PAYMENT_GATEWAY_PAKASIR_API_KEY=...`
   - `PAYMENT_GATEWAY_PAKASIR_PROJECT_SLUG=...`
2. Set callback URL Pakasir ke:
   - `https://domainkamu/public/api/payment_gateway_webhook.php`
3. Setelah checkout dibuat, sistem otomatis membuat transaksi Pakasir QRIS dan menampilkan QR dari Pakasir di popup pembayaran.
4. Saat pembayaran sukses, ada 2 jalur otomatis:
   - webhook callback (jika hosting mendukung inbound webhook), atau
   - fallback `Cek Status Pembayaran` dari halaman buyer (otomatis verifikasi ke Pakasir lalu langsung kirim ke provider).
5. Jika status masih pending, buyer bisa klik `Cek Status Pembayaran` lagi beberapa detik kemudian.

Catatan penting hosting gratis (InfinityFree):
- Beberapa request webhook eksternal bisa terkena proteksi challenge JS hosting.
- Karena itu fitur fallback `Cek Status Pembayaran` disediakan agar order tetap bisa auto-diproses tanpa ACC manual admin.

Perilaku endpoint callback:
1. Validasi secret webhook.
2. Validasi status callback (harus status paid/sukses).
3. Cari `order_id` dari payload callback.
4. Auto verifikasi payment.
5. Auto kirim order ke provider.
6. Jika sukses -> status order `Diproses`.
7. Jika gagal kirim ke provider -> status `Error` agar mudah dimonitor admin.
