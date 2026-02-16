# Odyssiavault Dropshipper (PHP Native + XAMPP)

## Fitur Utama
- Website fokus buyer/customer Odyssiavault.
- Login & register user (session PHP) dengan proses cepat.
- Pembayaran direct tanpa deposit/wallet.
- Search bar kategori & layanan dengan prioritas hasil relevan.
- Optimasi daftar layanan: kategori/search/katalog dimuat bertahap dari server (tidak tarik semua layanan sekaligus), jadi jauh lebih ringan di mobile/laptop dan aman untuk deploy serverless.
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
- Dashboard awal difokuskan untuk berita/pengumuman/update layanan.
- Sistem berita otomatis dari API provider (mode `provider_only`), dengan opsi hybrid/manual.
- Top 5 layanan terpopuler otomatis berdasarkan jumlah order sukses (tanpa data dummy).
  - Top 5 hanya muncul jika sudah ada order sukses valid.
- Alur pembayaran direct:
  - pilih layanan -> checkout -> transfer -> konfirmasi pembayaran -> admin verifikasi -> order diproses.

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
4. Jika database sudah lama dipakai, pastikan tabel baru `news_posts` sudah dibuat.
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

## 4) Logo Store
Taruh logo kamu di:
- `public/assets/logo.png`

Jika file tidak ada, aplikasi tetap jalan (logo disembunyikan otomatis).

## 5) Jalankan
1. Start Apache + MySQL dari XAMPP.
2. Buka:
- `http://localhost/dropshipper/public`

## 6) Alur Awal
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
  - `provider_only` (otomatis dari API provider)
  - `hybrid` (gabungan provider + manual admin)
  - `manual` (hanya input admin)
- `news.provider_services_variant`: `services`, `services_1`, `services2`, `services3`
- `news.provider_limit`: jumlah item provider yang ditampilkan

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
