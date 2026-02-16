<?php

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Config.php';

$config = Config::load();
$appConfig = (array)($config['app'] ?? []);
$appName = htmlspecialchars((string)($appConfig['name'] ?? 'Odyssiavault'), ENT_QUOTES, 'UTF-8');
$logoPath = htmlspecialchars((string)($appConfig['logo_path'] ?? 'assets/logo.png'), ENT_QUOTES, 'UTF-8');
$shareUrl = htmlspecialchars(trim((string)($appConfig['base_url'] ?? '')), ENT_QUOTES, 'UTF-8');
$paymentConfig = (array)($config['payment'] ?? []);
$qrisPath = htmlspecialchars((string)($paymentConfig['qris_image'] ?? 'assets/qris.png'), ENT_QUOTES, 'UTF-8');
$paymentMethods = checkoutPaymentMethods($config);
$paymentMethodsJson = htmlspecialchars((string)json_encode($paymentMethods, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$allowedViews = ['dashboard', 'profile', 'top5', 'purchase', 'refill', 'deposit', 'ticket', 'services', 'pages', 'admin'];
$requestedView = mb_strtolower(trim((string)($_GET['page'] ?? 'dashboard')));
$initialView = in_array($requestedView, $allowedViews, true) ? $requestedView : 'dashboard';
$cssVersion = (string)(@filemtime(__DIR__ . '/assets/app.css') ?: time());
$jsVersion = (string)(@filemtime(__DIR__ . '/assets/app.js') ?: time());
$indexVersion = (string)(@filemtime(__FILE__) ?: time());
$buildTag = htmlspecialchars(substr(sha1($cssVersion . '-' . $jsVersion . '-' . $indexVersion), 0, 12), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= $appName ?> - Panel Topup</title>
  <link rel="stylesheet" href="./assets/app.css?v=<?= htmlspecialchars($cssVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
  <div class="page" data-app-name="<?= $appName ?>" data-share-url="<?= $shareUrl ?>" data-logo-path="<?= $logoPath ?>" data-payment-methods="<?= $paymentMethodsJson ?>" data-qris-path="<?= $qrisPath ?>" data-initial-view="<?= htmlspecialchars($initialView, ENT_QUOTES, 'UTF-8') ?>" data-build="<?= $buildTag ?>">
    <section id="authView" class="auth-shell hidden">
      <div class="auth-card">
        <span class="badge">Odyssiavault - SMM & Kebsos Marketplace</span>
        <div class="brand">
          <img id="authLogo" src="<?= $logoPath ?>" alt="Logo <?= $appName ?>">
          <div>
            <h1><?= $appName ?></h1>
            <p>Platform topup digital untuk buyer Odyssiavault. Proses cepat, tampilan simpel, dan transaksi mudah dipantau.</p>
          </div>
        </div>
        <div class="list">
          <div>Registrasi cepat, cukup username dan password.</div>
          <div>Pilih layanan, isi target, lalu checkout dalam satu halaman.</div>
          <div>Riwayat order dan status berjalan real-time.</div>
        </div>
      </div>

      <div class="auth-panel">
        <div class="tabs">
          <button id="tabLogin" class="tab-btn active" type="button">Login</button>
          <button id="tabRegister" class="tab-btn" type="button">Daftar</button>
        </div>

        <form id="loginForm" class="form-grid">
          <div>
            <label>Username</label>
            <input id="loginIdentity" placeholder="Masukkan username" required>
          </div>
          <div>
            <label>Password</label>
            <div class="password-field">
              <input id="loginPassword" type="password" placeholder="Masukkan password" autocomplete="current-password" required>
              <button id="loginPasswordToggle" class="password-toggle" type="button" aria-label="Lihat password" aria-pressed="false">Lihat</button>
            </div>
          </div>
          <button type="submit">Masuk Dashboard</button>
        </form>

        <form id="registerForm" class="form-grid hidden">
          <div>
            <label>Username</label>
            <input id="regUsername" placeholder="Contoh: odyssiabuyer01" required>
          </div>
          <div>
            <label>Password (min 6 karakter)</label>
            <input id="regPassword" type="password" placeholder="Buat password" required>
          </div>
          <button type="submit">Buat Akun</button>
          <p class="muted">Pendaftaran dibuat cepat agar buyer bisa langsung checkout.</p>
        </form>
        <div id="authNotice" class="notice info hidden"></div>
      </div>
    </section>

    <section id="appView" class="app-layout hidden">
      <aside class="sidebar">
        <div class="side-brand">
          <img id="sideLogo" src="<?= $logoPath ?>" alt="Logo <?= $appName ?>">
          <div><strong><?= $appName ?></strong><span>Buyer Information Center</span></div>
        </div>
        <nav class="menu">
          <a href="./?page=dashboard" data-view="dashboard" class="<?= $initialView === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
          <a href="./?page=top5" data-view="top5" class="<?= $initialView === 'top5' ? 'active' : '' ?>">Top 5</a>
          <a href="./?page=purchase" data-view="purchase" class="<?= $initialView === 'purchase' ? 'active' : '' ?>">Pembelian</a>
          <a href="./?page=refill" data-view="refill" class="<?= $initialView === 'refill' ? 'active' : '' ?>">Refill</a>
          <a href="./?page=deposit" data-view="deposit" class="<?= $initialView === 'deposit' ? 'active' : '' ?>">Deposit</a>
          <a href="./?page=ticket" data-view="ticket" class="<?= $initialView === 'ticket' ? 'active' : '' ?>">Tiket</a>
          <a id="adminMenuLink" href="./?page=admin" data-view="admin" class="<?= $initialView === 'admin' ? 'active' : '' ?> hidden">Admin Panel</a>
          <a href="./?page=services" data-view="services" class="<?= $initialView === 'services' ? 'active' : '' ?>">Daftar Layanan</a>
          <a href="./?page=pages" data-view="pages" class="<?= $initialView === 'pages' ? 'active' : '' ?>">Halaman</a>
        </nav>
      </aside>

      <main class="main">
        <header id="dashboardSection" class="topbar" data-view-section="dashboard,profile,top5,purchase,refill,deposit,ticket,services,pages,admin">
          <div>
            <h2>Dashboard Odyssiavault</h2>
            <p id="welcomeText">Memuat akun buyer...</p>
          </div>
          <div class="actions">
            <button id="btnRefresh" class="ghost" type="button">Refresh Data</button>
            <div id="accountMenu" class="account-menu">
              <button id="accountMenuToggle" class="ghost account-trigger" type="button" aria-expanded="false">
                <span id="accountAvatar" class="account-avatar">OV</span>
                <span class="account-text">
                  <strong id="accountMenuName">@buyer</strong>
                  <small id="accountMenuRole">user</small>
                </span>
                <span class="account-caret" aria-hidden="true">v</span>
              </button>
              <div id="accountMenuPanel" class="account-panel hidden">
                <button id="btnOpenProfile" class="account-item" type="button">Profil</button>
                <button id="btnOpenSettings" class="account-item" type="button">Pengaturan</button>
                <button id="btnLogout" class="account-item danger" type="button">Logout</button>
              </div>
            </div>
          </div>
        </header>
        <section id="panelInfoSection" class="panel-info-bar" data-view-section="dashboard,profile,top5,purchase,refill,deposit,ticket,services,pages,admin">
          <div class="panel-info-label">Info Panel</div>
          <div class="panel-info-track">
            <div id="panelInfoTickerText" class="panel-info-ticker">Memuat informasi panel...</div>
          </div>
          <div class="panel-info-actions">
            <button id="panelInfoRefreshBtn" type="button" class="ghost mini-btn">Refresh Info</button>
            <button id="panelInfoCloseBtn" type="button" class="ghost mini-btn">Tutup</button>
          </div>
        </section>
        <section class="stats" data-view-section="dashboard">
          <article><small>Menunggu Konfirmasi Admin</small><strong id="statBalance">0</strong></article>
          <article><small>Sedang Diproses</small><strong id="statOrders">0</strong></article>
          <article><small>Order Selesai</small><strong id="statSpent">0</strong></article>
        </section>
        <section id="dashboardQuickSection" class="card" data-view-section="dashboard">
          <div class="headline-row">
            <h3 style="margin:0;">Akses Cepat</h3>
            <span class="muted">Navigasi ringkas untuk buyer</span>
          </div>
          <div class="quick-actions">
            <button type="button" class="quick-action-btn" data-quick-view="purchase">Buat Pesanan</button>
            <button type="button" class="quick-action-btn" data-quick-view="top5">Top 5</button>
            <button type="button" class="quick-action-btn" data-quick-view="services">Daftar Layanan</button>
            <button type="button" class="quick-action-btn" data-quick-view="ticket">Buat Tiket</button>
          </div>
        </section>

        <section id="dashboardUpdateSection" class="card" data-view-section="dashboard">
          <div class="headline-row">
            <h3 style="margin:0;">Update Layanan Terbaru</h3>
            <span id="servicesSyncMeta" class="muted">Sinkronisasi data layanan...</span>
          </div>
          <div id="dashboardHighlights" class="dashboard-highlight-grid">
            <div class="box">Memuat update layanan terbaru...</div>
          </div>
        </section>
        <section id="profileSection" class="card" data-view-section="profile">
          <h3>Profil Akun</h3>
          <div class="profile-grid">
            <div class="profile-item">
              <span class="profile-label">Username</span>
              <strong id="profileUsername">-</strong>
            </div>
            <div class="profile-item">
              <span class="profile-label">Role</span>
              <strong id="profileRole">-</strong>
            </div>
            <div class="profile-item">
              <span class="profile-label">Email</span>
              <strong id="profileEmail">-</strong>
            </div>
            <div class="profile-item">
              <span class="profile-label">Terdaftar Sejak</span>
              <strong id="profileCreatedAt">-</strong>
            </div>
            <div class="profile-item">
              <span class="profile-label">Login Terakhir</span>
              <strong id="profileLastLoginAt">-</strong>
            </div>
            <div class="profile-item">
              <span class="profile-label">Total Belanja</span>
              <strong id="profileTotalSpent">Rp0</strong>
            </div>
          </div>
          <div class="profile-grid compact" style="margin-top:10px;">
            <div class="profile-item">
              <span class="profile-label">Total Order</span>
              <strong id="profileTotalOrders">0</strong>
            </div>
            <div class="profile-item">
              <span class="profile-label">Menunggu Konfirmasi</span>
              <strong id="profileWaitingOrders">0</strong>
            </div>
            <div class="profile-item">
              <span class="profile-label">Sedang Diproses</span>
              <strong id="profileProcessingOrders">0</strong>
            </div>
            <div class="profile-item">
              <span class="profile-label">Selesai</span>
              <strong id="profileCompletedOrders">0</strong>
            </div>
          </div>

          <div class="profile-settings">
            <h4>Pengaturan Akun</h4>
            <form id="profilePasswordForm" class="form-grid" autocomplete="off">
              <div class="two">
                <div>
                  <label>Password Saat Ini</label>
                  <input id="currentPassword" type="password" placeholder="Masukkan password saat ini" required>
                </div>
                <div>
                  <label>Password Baru</label>
                  <input id="newPassword" type="password" placeholder="Minimal 6 karakter" required>
                </div>
              </div>
              <div>
                <label>Konfirmasi Password Baru</label>
                <input id="confirmPassword" type="password" placeholder="Ulangi password baru" required>
              </div>
              <div class="headline-row">
                <span class="muted">Gunakan password unik agar akun lebih aman.</span>
                <button type="submit" class="mini-btn">Simpan Pengaturan</button>
              </div>
              <div id="profilePasswordNotice" class="notice info hidden"></div>
            </form>
          </div>
        </section>

        <section id="newsSection" class="card" data-view-section="dashboard">
          <div class="headline-row">
            <h3 style="margin:0;">Berita & Pengumuman</h3>
            <span class="muted">Sumber update: Sinkronisasi layanan otomatis</span>
          </div>
          <p class="muted" style="margin:0 0 10px;">
            Update ditarik otomatis dari layanan aktif agar buyer selalu melihat informasi terbaru.
          </p>
          <div id="newsList" class="news-list">
            <div class="box">Memuat berita terbaru...</div>
          </div>
        </section>

        <section id="top5Section" class="card hidden" data-view-section="top5">
          <div class="headline-row">
            <h3 style="margin:0;">Top 5 Layanan Terpopuler</h3>
            <span class="muted">Berdasarkan order sukses</span>
          </div>
          <div id="top5List" class="list-box"></div>
        </section>

        <section id="top5EmptyState" class="card hidden" data-view-section="top5">
          <div class="box" style="margin-top:0;">Belum ada transaksi sukses, jadi Top 5 belum tersedia.</div>
        </section>

        <section id="newsAdminSection" class="card hidden" data-view-section="dashboard">
          <h3>Kelola Berita (Admin)</h3>
          <form id="newsForm" class="form-grid">
            <input id="newsId" type="hidden">
            <div class="two">
              <div>
                <label>Judul Berita</label>
                <input id="newsTitle" placeholder="Contoh: Update layanan TikTok terbaru" required>
              </div>
              <div>
                <label>Tanggal Publish (Opsional)</label>
                <input id="newsPublishedAt" type="datetime-local">
              </div>
            </div>
            <div class="two">
              <div>
                <label>Sumber</label>
                <input id="newsSourceName" value="Odyssiavault">
              </div>
              <div>
                <label>URL Sumber (Opsional)</label>
                <input id="newsSourceUrl" placeholder="https://sumber-update.com/...">
              </div>
            </div>
            <div>
              <label>Ringkasan Singkat</label>
              <textarea id="newsSummary" placeholder="Ringkasan untuk buyer..." required></textarea>
            </div>
            <div>
              <label>Konten Lengkap</label>
              <textarea id="newsContent" placeholder="Isi detail berita/pengumuman..." required></textarea>
            </div>
            <div class="headline-row">
              <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:12px;text-transform:none;letter-spacing:0;">
                <input id="newsIsPublished" type="checkbox" checked style="width:auto;"> Publish sekarang
              </label>
              <div style="display:flex;gap:8px;">
                <button id="newsResetBtn" type="button" class="ghost mini-btn">Reset</button>
                <button type="submit">Simpan Berita</button>
              </div>
            </div>
            <div id="newsNotice" class="notice info hidden"></div>
          </form>

          <h3 style="margin-top:14px;">Daftar Berita (Admin)</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Judul</th>
                  <th>Status</th>
                  <th>Publish</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="newsAdminBody"><tr><td colspan="5">Belum ada data berita.</td></tr></tbody>
            </table>
          </div>
        </section>

        <section id="orderSection" class="card" data-view-section="purchase">
          <h3>Buat Pesanan</h3>
          <div class="info-alert">
            <strong>Info!!!</strong>
            <span>Agar proses top-up lebih cepat, gunakan metode QRIS/Virtual Account dan pastikan data target benar sebelum checkout. Butuh bantuan? WhatsApp: <a href="https://wa.me/6285178232383" target="_blank" rel="noopener noreferrer">+62 851-7823-2383</a></span>
          </div>
          <div class="order-layout">
            <form id="orderForm" class="form-grid" novalidate>
              <div class="two">
                <div>
                  <label>Kategori (Cari / Pilih)</label>
                  <div class="suggestion-field">
                    <input id="categoryInput" placeholder="Ketik nama kategori atau ID..." autocomplete="off">
                    <div id="categorySuggestPanel" class="suggest-panel hidden" role="listbox" aria-label="Saran kategori"></div>
                    <datalist id="categoryOptions"></datalist>
                  </div>
                </div>
                <div>
                  <label>Layanan (Cari / Pilih)</label>
                  <div class="suggestion-field">
                    <input id="serviceInput" placeholder="Ketik nama layanan atau ID..." autocomplete="off" required>
                    <div id="serviceSuggestPanel" class="suggest-panel hidden" role="listbox" aria-label="Saran layanan"></div>
                    <datalist id="serviceOptions"></datalist>
                  </div>
                </div>
              </div>
              <div class="three">
                <div><label>Target (URL / Username)</label><input id="target" placeholder="Contoh: https://instagram.com/..." required></div>
                <div><label>Jumlah (Quantity)</label><input id="quantity" placeholder="Contoh: 1000"></div>
                <div><label>Harga/K</label><input id="pricePer1000" value="Rp0" readonly></div>
              </div>
              <div id="commentGroup" class="hidden">
                <label>Komentar (Wajib untuk layanan Komen)</label>
                <textarea id="komen" placeholder="Tulis komentar, 1 baris = 1 komentar"></textarea>
                <p id="commentHint" class="muted">Field komentar hanya tampil jika layanan bertipe Komen.</p>
              </div>
              <div id="mentionGroup" class="hidden">
                <label>Usernames (Wajib untuk layanan Mentions Custom List)</label>
                <textarea id="usernames" placeholder="1 baris = 1 username"></textarea>
                <p id="mentionHint" class="muted">Masukkan daftar username target untuk layanan mention custom list.</p>
              </div>
              <details id="advancedFields" class="hidden">
                <summary>Field Tambahan (Opsional)</summary>
                <div class="two">
                  <div>
                    <label>comments</label>
                    <textarea id="comments" placeholder="Alternatif field komentar tambahan"></textarea>
                  </div>
                  <div>
                    <label>username</label>
                    <input id="singleUsername" placeholder="Untuk layanan tertentu (contoh: CommentLike)">
                  </div>
                </div>
                <div class="two">
                  <div>
                    <label>hashtags</label>
                    <textarea id="hashtags" placeholder="#hashtag atau format sesuai info layanan"></textarea>
                  </div>
                  <div>
                    <label>keywords</label>
                    <input id="keywords" placeholder="Untuk layanan SEO tertentu">
                  </div>
                </div>
              </details>
              <div id="serviceInfo" class="box">Memuat layanan...</div>
              <button type="submit">Kirim Order Sekarang</button>
              <div id="orderNotice" class="notice info hidden"></div>
              <div id="checkoutPanel" class="box hidden">
                <strong>Checkout Berhasil - Menunggu Konfirmasi Admin</strong>
                <div id="checkoutSummary" class="muted" style="margin-top:8px;"></div>
                <select id="paymentMethodSelect" class="hidden" aria-hidden="true" tabindex="-1"></select>
              </div>
            </form>

            <aside class="order-side">
              <div class="box">
                <strong>Emergency Instagram Follower & Like</strong>
                <div id="emergencyServiceText" style="margin-top:8px;">Memuat layanan rekomendasi...</div>
                <div class="muted" style="margin-top:8px;">
                  Daftar emergency akan diperbarui otomatis sesuai layanan yang sering dipakai buyer Odyssiavault.
                </div>
              </div>
              <div class="box">
                <strong>Penting Untuk Instagram Followers</strong>
                <div class="muted" style="margin-top:8px;">
                  Sebelum order followers, nonaktifkan opsi <em>Laporkan untuk ditinjau / Flag for review</em> agar followers tidak tertahan.
                </div>
                <ul class="info-list" style="margin-top:8px;">
                  <li>Indonesia: Pengaturan dan aktivitas > Ikuti dan undang teman > Laporkan untuk ditinjau (nonaktifkan).</li>
                  <li>English: Settings and privacy > Follow and invite friends > Flag for review (disable).</li>
                  <li>Contoh panduan: <a href="https://prnt.sc/e71qXX2LkHQ4" target="_blank" rel="noopener noreferrer">Indonesia</a> | <a href="https://prnt.sc/ITf2lcZ_ZSkP" target="_blank" rel="noopener noreferrer">English</a>.</li>
                  <li>Tidak ada refill/refund jika fitur tersebut belum dimatikan saat order dibuat.</li>
                </ul>
              </div>
              <div class="box">
                <strong>Rekomendasi ID Layanan</strong>
                <ul class="info-list" style="margin-top:8px;">
                  <li>Instagram Followers: 27700, 25193, 17878, 25186</li>
                  <li>Instagram Views: 5325, 6460, 21884, 74, 5624</li>
                  <li>TikTok Followers: 7331, 22678, 6949, 21136, 25476, 25475</li>
                  <li>TikTok Likes: 19069, 23603, 25241, 6112, 26014, 9989</li>
                </ul>
                <div class="muted" style="margin-top:8px;">
                  Kontak resmi: <a href="https://instagram.com/odyssiavault" target="_blank" rel="noopener noreferrer">@odyssiavault</a> | <a href="https://chat.whatsapp.com/I1HfRLDI9ie3xjItKj7e41" target="_blank" rel="noopener noreferrer">Grup WA</a>
                </div>
              </div>
              <div class="box">
                <strong>MOHON DIBACA !!!</strong>
                <ul class="info-list" style="margin-top:8px;">
                  <li>Pastikan akun/link target tidak private dan format data benar.</li>
                  <li>Jangan ganti username/link selama order belum status selesai.</li>
                  <li>Dilarang memasukkan target yang sama jika order sebelumnya belum selesai.</li>
                  <li>Order yang sudah disubmit tidak bisa dibatalkan, kecuali dibatalkan sistem layanan.</li>
                  <li>Proses bisa mulai dari 1 menit hingga maksimal 3x24 jam saat server padat.</li>
                  <li>Jika status tidak berubah lebih dari 1x24 jam, laporkan dengan menyertakan ID order.</li>
                  <li>Kesalahan input dari buyer bukan tanggung jawab admin, mohon cek ulang sebelum checkout.</li>
                </ul>
              </div>
            </aside>
          </div>
        </section>

        <section id="howtoSection" class="card" data-view-section="purchase">
          <h3>Petunjuk Pesanan Odyssiavault</h3>
          <div class="box">
            <strong>Langkah-langkah:</strong>
            <ol class="info-list">
              <li>Pilih salah satu kategori dan layanan yang tersedia.</li>
              <li>Masukkan data/target sesuai ketentuan layanan (username atau URL).</li>
              <li>Masukkan jumlah pesan sesuai batas min/max layanan.</li>
              <li>Klik <em>Kirim Order Sekarang</em>, lakukan pembayaran, lalu tunggu konfirmasi admin.</li>
              <li>Pantau status order di menu Riwayat Pesanan.</li>
            </ol>
          </div>
          <div class="box">
            <strong>Keterangan simbol layanan:</strong>
            <ul class="info-list" style="margin-top:8px;">
              <li><strong>TOP</strong> Layanan favorit / performa tinggi.</li>
              <li><strong>DRIP</strong> Mendukung dripfeed.</li>
              <li><strong>REFILL</strong> Mendukung refill.</li>
              <li><strong>CANCEL</strong> Mendukung cancel pada kondisi tertentu.</li>
              <li><strong>Rxx / ARxx</strong> Periode refill manual / otomatis.</li>
            </ul>
            <div class="muted" style="margin-top:8px;">
              Kecepatan pada deskripsi layanan bersifat estimasi dan bisa berubah sesuai kondisi server.
            </div>
          </div>
        </section>

        <section id="historySection" class="card" data-view-section="purchase">
          <h3>Riwayat Pesanan</h3>
          <p class="muted" style="margin:0 0 10px;">Pantau status pembayaran dan proses order kamu di sini.</p>

          <div id="historyStatusTabs" class="status-tabs">
            <button type="button" class="status-tab active" data-status="ALL">Semua</button>
            <button type="button" class="status-tab" data-status="Menunggu Pembayaran">Menunggu Konfirmasi Admin</button>
            <button type="button" class="status-tab" data-status="Diproses">Diproses</button>
            <button type="button" class="status-tab" data-status="Selesai">Selesai</button>
            <button type="button" class="status-tab" data-status="Dibatalkan">Dibatalkan</button>
          </div>

          <div class="three" style="margin-top:10px;">
            <div>
              <label>Cari ID Order</label>
              <input id="historyOrderIdSearch" placeholder="Masukkan ID order">
            </div>
            <div>
              <label>Cari data/target order</label>
              <input id="historyTargetSearch" placeholder="Masukkan data/target order">
            </div>
            <div>
              <label>Cari nama layanan</label>
              <input id="historyServiceSearch" placeholder="Masukkan nama layanan">
            </div>
          </div>

          <div class="headline-row" style="margin-top:10px;">
            <div id="historySummary" class="muted">Menampilkan 0 data</div>
            <div style="display:flex;align-items:center;gap:8px;">
              <label style="margin:0;">Menampilkan</label>
              <select id="historyPerPage" style="width:auto;">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
              <span class="muted">data</span>
            </div>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID Lokal</th>
                  <th>Layanan</th>
                  <th>Data / Target</th>
                  <th>Jumlah</th>
                  <th>Total Bayar</th>
                  <th>Status</th>
                  <th>Batas Bayar</th>
                  <th>Tanggal & Waktu</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="ordersBody"><tr><td colspan="9">Belum ada data.</td></tr></tbody>
            </table>
          </div>
          <div id="ordersPagination" class="pagination"></div>
          <div id="ordersNotice" class="notice info hidden"></div>
        </section>

        <section id="refillSection" class="card" data-view-section="refill">
          <h3>Refill</h3>
          <div class="box">
            Refill digunakan untuk order yang sudah masuk server layanan namun butuh pengisian ulang.
            Masukkan ID Order Lokal, lalu klik Ajukan Refill.
          </div>
          <form id="refillForm" class="form-grid" style="margin-top:10px;">
            <div class="three">
              <div>
                <label>ID Order Lokal</label>
                <input id="refillOrderId" placeholder="Contoh: 12345" required>
              </div>
              <div style="align-self:end;">
                <button type="submit">Ajukan Refill</button>
              </div>
              <div>
                <label>Info</label>
                <div id="refillSummary" class="box" style="margin:0;min-height:42px;display:flex;align-items:center;">Belum ada permintaan refill.</div>
              </div>
            </div>
            <div id="refillNotice" class="notice info hidden"></div>
          </form>

          <h3 style="margin-top:12px;">Riwayat Refill</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID Refill</th>
                  <th>ID Order</th>
                  <th>Layanan</th>
                  <th>Refill Server</th>
                  <th>Status</th>
                  <th>Dibuat</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="refillBody"><tr><td colspan="7">Belum ada data refill.</td></tr></tbody>
            </table>
          </div>
          <div id="refillStatusNotice" class="notice info hidden"></div>
        </section>

        <section id="depositSection" class="card" data-view-section="deposit">
          <h3>Deposit Saldo (QRIS)</h3>
          <div class="box">
            Fitur ini untuk buyer yang ingin isi saldo akun internal. Buat nominal deposit, transfer sesuai QRIS, lalu tunggu verifikasi admin.
          </div>
          <div class="order-layout" style="margin-top:10px;">
            <form id="depositForm" class="form-grid">
              <div class="two">
                <div>
                  <label>Nominal Deposit</label>
                  <input id="depositAmount" inputmode="numeric" placeholder="Contoh: 50000" required>
                </div>
                <div>
                  <label>Nama Pengirim (Opsional)</label>
                  <input id="depositPayerName" placeholder="Contoh: Odyssia Buyer">
                </div>
              </div>
              <div>
                <label>Catatan Deposit (Opsional)</label>
                <textarea id="depositPayerNote" placeholder="Contoh: Transfer dari DANA / Bank ..."></textarea>
              </div>
              <button type="submit">Buat Permintaan Deposit</button>
              <div id="depositNotice" class="notice info hidden"></div>
            </form>

            <aside class="order-side">
              <div class="box">
                <strong>QRIS Odyssiavault</strong>
                <img id="qrisImage" class="qris-img" alt="QRIS Odyssiavault" style="margin-top:10px;">
                <div id="qrisMeta" class="muted" style="margin-top:8px;">Penerima: Odyssiavault</div>
              </div>
              <div id="depositInstruction" class="box">
                Scan QRIS, transfer sesuai nominal final, lalu tunggu verifikasi admin.
              </div>
            </aside>
          </div>

          <h3 style="margin-top:14px;">Riwayat Deposit</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nominal</th>
                  <th>Nominal Transfer</th>
                  <th>Status</th>
                  <th>Waktu</th>
                  <th>Catatan Admin</th>
                </tr>
              </thead>
              <tbody id="depositHistoryBody"><tr><td colspan="6">Belum ada data deposit.</td></tr></tbody>
            </table>
          </div>

          <div id="depositAdminPanel" class="hidden" style="margin-top:14px;">
            <h3 style="margin:0 0 10px;">Verifikasi Deposit (Admin)</h3>
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Nominal Final</th>
                    <th>Status</th>
                    <th>Info Buyer</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody id="depositAdminBody"><tr><td colspan="7">Tidak ada deposit pending.</td></tr></tbody>
              </table>
            </div>
            <div id="depositAdminNotice" class="notice info hidden"></div>
          </div>
        </section>

        <section id="ticketSection" class="card" data-view-section="ticket">
          <h3>Tiket Laporan</h3>
          <div class="box">
            Buat tiket jika ada kendala order, pembayaran, atau layanan. Sertakan ID order dan kronologi agar tim Odyssiavault bisa membantu lebih cepat.
          </div>

          <form id="ticketForm" class="form-grid" style="margin-top:10px;">
            <div class="two">
              <div>
                <label>Subjek Tiket</label>
                <input id="ticketSubject" placeholder="Contoh: Order belum diproses" maxlength="180" required>
              </div>
              <div>
                <label>Kategori</label>
                <select id="ticketCategory">
                  <option value="Laporan Order">Laporan Order</option>
                  <option value="Pembayaran">Pembayaran</option>
                  <option value="Layanan">Layanan</option>
                  <option value="Lainnya">Lainnya</option>
                </select>
              </div>
            </div>
            <div class="two">
              <div>
                <label>ID Order (Opsional)</label>
                <input id="ticketOrderId" placeholder="Contoh: 12345">
              </div>
              <div>
                <label>Prioritas</label>
                <select id="ticketPriority">
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                  <option value="low">Low</option>
                </select>
              </div>
            </div>
            <div>
              <label>Pesan Laporan</label>
              <textarea id="ticketMessage" placeholder="Jelaskan masalah secara singkat dan jelas..." required></textarea>
            </div>
            <button type="submit">Buat Tiket</button>
            <div id="ticketNotice" class="notice info hidden"></div>
          </form>

          <div class="headline-row" style="margin-top:14px;">
            <h3 style="margin:0;">Daftar Tiket</h3>
            <button id="ticketRefreshBtn" type="button" class="ghost mini-btn">Refresh Tiket</button>
          </div>

          <div class="two" style="margin-bottom:10px;">
            <div>
              <label>Filter Status</label>
              <select id="ticketStatusFilter">
                <option value="all">Semua</option>
                <option value="open">Open</option>
                <option value="answered">Answered</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div>
              <label>Cari Tiket</label>
              <input id="ticketSearchInput" placeholder="Cari ID tiket / subjek / username">
            </div>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Subjek</th>
                  <th>Order</th>
                  <th>Status</th>
                  <th>Prioritas</th>
                  <th>Update</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="ticketBody"><tr><td colspan="7">Belum ada tiket.</td></tr></tbody>
            </table>
          </div>

          <div id="ticketDetailPanel" class="ticket-detail hidden">
            <div class="headline-row">
              <h3 id="ticketDetailTitle" style="margin:0;">Detail Tiket</h3>
              <button id="ticketCloseDetailBtn" type="button" class="ghost mini-btn">Tutup Detail</button>
            </div>
            <div id="ticketDetailMeta" class="muted" style="margin-bottom:10px;">Pilih tiket untuk melihat percakapan.</div>
            <div id="ticketMessages" class="ticket-messages"></div>
            <div class="ticket-reply-box">
              <label>Balas Tiket</label>
              <textarea id="ticketReplyMessage" placeholder="Tulis balasan..."></textarea>
              <div class="ticket-reply-actions">
                <button id="ticketReplyBtn" type="button" class="mini-btn">Kirim Balasan</button>
                <button id="ticketCloseBtn" type="button" class="mini-btn danger hidden">Tutup Tiket</button>
                <button id="ticketReopenBtn" type="button" class="mini-btn success hidden">Buka Kembali</button>
              </div>
            </div>
            <div id="ticketDetailNotice" class="notice info hidden"></div>
          </div>
        </section>

        <section id="adminSection" class="card hidden" data-view-section="admin">
          <h3>Panel Admin - ACC Pembayaran</h3>
          <p class="muted" style="margin:0 0 10px;">Halaman khusus admin untuk verifikasi pembayaran buyer agar proses order ke server layanan lebih cepat.</p>
          <div id="adminPaymentSection" class="hidden">
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Layanan</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Konfirmasi Buyer</th>
                    <th>Batas Bayar</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody id="adminPaymentBody"><tr><td colspan="8">Tidak ada order menunggu pembayaran.</td></tr></tbody>
              </table>
            </div>
            <div id="adminPaymentNotice" class="notice info hidden"></div>
          </div>

          <div id="adminOrderHistorySection" class="hidden" style="margin-top:14px;">
            <div class="headline-row" style="margin-bottom:10px;">
              <h3 style="margin:0;">Riwayat Pembelian (Semua Status)</h3>
              <button id="adminOrderHistoryRefreshBtn" type="button" class="ghost mini-btn">Refresh Riwayat</button>
            </div>

            <div class="three" style="margin-bottom:10px;">
              <div>
                <label>Cari Riwayat</label>
                <input id="adminOrderHistorySearch" placeholder="Cari ID order, username, layanan, target...">
              </div>
              <div>
                <label>Filter Status</label>
                <select id="adminOrderHistoryStatus">
                  <option value="all">Semua Status</option>
                  <option value="waiting">Menunggu Pembayaran</option>
                  <option value="processing">Diproses</option>
                  <option value="success">Selesai</option>
                  <option value="failed">Dibatalkan / Gagal</option>
                </select>
              </div>
              <div>
                <label>Tampil per Halaman</label>
                <select id="adminOrderHistoryPerPage">
                  <option value="25" selected>25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>

            <div id="adminOrderHistorySummary" class="box" style="margin:0 0 10px;min-height:42px;display:flex;align-items:center;">Memuat riwayat pembelian...</div>

            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Layanan</th>
                    <th>Target</th>
                    <th>Jumlah</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Pembayaran</th>
                    <th>Order Server</th>
                    <th>Waktu</th>
                    <th>Catatan</th>
                  </tr>
                </thead>
                <tbody id="adminOrderHistoryBody"><tr><td colspan="11">Belum ada data riwayat pembelian.</td></tr></tbody>
              </table>
            </div>
            <div id="adminOrderHistoryPagination" class="pagination"></div>
            <div id="adminOrderHistoryNotice" class="notice info hidden"></div>
          </div>
        </section>

        <section id="servicesSection" class="card" data-view-section="services">
          <h3>Daftar Layanan</h3>
          <div class="services-tools" style="margin-bottom:10px;">
            <div>
              <label>Cari Nama Layanan</label>
              <input id="serviceCatalogSearch" placeholder="Cari layanan...">
            </div>
            <div>
              <label>Filter Kategori</label>
              <select id="serviceCatalogCategory">
                <option value="">Semua Kategori</option>
              </select>
            </div>
            <div>
              <label>Urutkan Berdasarkan</label>
              <select id="serviceCatalogSortBy">
                <option value="category_name" selected>Kategori + Nama</option>
                <option value="price">Harga / 1000</option>
                <option value="id">ID Layanan</option>
                <option value="name">Nama Layanan</option>
                <option value="min">Minimum</option>
                <option value="max">Maximum</option>
              </select>
            </div>
            <div>
              <label>Arah Urutan</label>
              <select id="serviceCatalogSortDir">
                <option value="asc" selected>Naik (A-Z / Murah)</option>
                <option value="desc">Turun (Z-A / Mahal)</option>
              </select>
            </div>
            <div>
              <label>Tampil per Halaman</label>
              <select id="servicesCatalogPerPage">
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
                <option value="200">200</option>
              </select>
            </div>
          </div>
          <div id="servicesCatalogSummary" class="box" style="margin:0 0 10px;min-height:42px;display:flex;align-items:center;">Memuat data layanan...</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nama Layanan</th>
                  <th>Kategori</th>
                  <th>Harga/K</th>
                  <th>Min</th>
                  <th>Max</th>
                </tr>
              </thead>
              <tbody id="servicesCatalogBody"><tr><td colspan="6">Belum ada data layanan.</td></tr></tbody>
            </table>
          </div>
          <div id="servicesCatalogPagination" class="pagination"></div>
        </section>

        <section id="contactSection" class="card" data-view-section="pages">
          <h3>Kontak Resmi Odyssiavault</h3>
          <div class="contact-links">
            <a href="https://wa.me/6285178232383" target="_blank" rel="noopener noreferrer" class="contact-link">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.52 3.48A11.88 11.88 0 0 0 12.07 0C5.53 0 .2 5.33.2 11.87c0 2.09.55 4.14 1.58 5.95L0 24l6.34-1.66a11.8 11.8 0 0 0 5.73 1.47h.01c6.54 0 11.87-5.33 11.87-11.87 0-3.17-1.23-6.15-3.43-8.36Zm-8.45 18.3h-.01a9.8 9.8 0 0 1-5-1.37l-.36-.21-3.76.99 1-3.66-.24-.38a9.84 9.84 0 0 1-1.5-5.28c0-5.44 4.43-9.87 9.88-9.87a9.8 9.8 0 0 1 6.99 2.9 9.8 9.8 0 0 1 2.89 6.97c0 5.44-4.43 9.87-9.89 9.87Zm5.41-7.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.66.15-.2.3-.76.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.24-.45-2.36-1.43-.87-.78-1.46-1.74-1.63-2.04-.17-.3-.02-.45.13-.6.14-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.48-.5-.66-.5h-.56c-.2 0-.52.08-.8.37-.27.3-1.04 1.02-1.04 2.49 0 1.47 1.07 2.9 1.22 3.1.15.2 2.1 3.2 5.1 4.49.71.31 1.27.49 1.7.62.71.22 1.36.19 1.88.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35Z"></path></svg>
              <div><strong>WhatsApp</strong><span>+62 851-7823-2383</span></div>
            </a>
            <a href="https://instagram.com/odyssiavault" target="_blank" rel="noopener noreferrer" class="contact-link">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.75 2h8.5A5.76 5.76 0 0 1 22 7.75v8.5A5.76 5.76 0 0 1 16.25 22h-8.5A5.76 5.76 0 0 1 2 16.25v-8.5A5.76 5.76 0 0 1 7.75 2Zm8.36 1.73h-8.22A4.16 4.16 0 0 0 3.73 7.9v8.22a4.16 4.16 0 0 0 4.16 4.16h8.22a4.16 4.16 0 0 0 4.16-4.16V7.9a4.16 4.16 0 0 0-4.16-4.16ZM12 7.14A4.86 4.86 0 1 1 7.14 12 4.86 4.86 0 0 1 12 7.14Zm0 1.73A3.13 3.13 0 1 0 15.13 12 3.13 3.13 0 0 0 12 8.87Zm5.08-3.08a1.14 1.14 0 1 1-1.14 1.14 1.14 1.14 0 0 1 1.14-1.14Z"></path></svg>
              <div><strong>Instagram</strong><span>@odyssiavault</span></div>
            </a>
            <a href="https://chat.whatsapp.com/I1HfRLDI9ie3xjItKj7e41" target="_blank" rel="noopener noreferrer" class="contact-link">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 5H5a2 2 0 0 0-2 2v13l4-3h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm-7 9H7v-2h5v2Zm5-4H7V8h10v2Z"></path></svg>
              <div><strong>Grup WhatsApp</strong><span>App Premium Lengkap</span></div>
            </a>
          </div>
          <div class="box">
            <strong>Pertanyaan Umum (FAQ)</strong>
            <ul class="info-list" style="margin-top:8px;">
              <li>Order baru diproses setelah pembayaran diverifikasi admin.</li>
              <li>Gunakan target valid (link/username sesuai ketentuan layanan).</li>
              <li>Jangan kirim order ganda pada target sama sebelum order sebelumnya selesai.</li>
              <li>Jika order lambat lebih dari 1x24 jam, buat tiket laporan.</li>
            </ul>
          </div>
          <div class="box">
            <strong>Ketentuan Layanan</strong>
            <ul class="info-list" style="margin-top:8px;">
              <li>Order yang sudah disubmit tidak bisa dibatalkan, kecuali gagal dari server layanan.</li>
              <li>Kesalahan input target oleh buyer bukan tanggung jawab admin.</li>
              <li>Estimasi kecepatan layanan dapat berubah sesuai kondisi server.</li>
              <li>Dengan melakukan order, buyer dianggap menyetujui semua ketentuan Odyssiavault.</li>
            </ul>
          </div>
        </section>

        <section id="shareSection" class="card" data-view-section="pages">
          <div class="headline-row">
            <h3 style="margin:0;">Bagikan Website Odyssiavault</h3>
            <span class="muted">Share langsung ke aplikasi sosial media</span>
          </div>
          <div class="share-toolbar">
            <button id="shareNativeBtn" type="button" class="mini-btn">Bagikan Sekarang</button>
            <button id="shareCopyBtn" type="button" class="ghost mini-btn">Copy Link</button>
          </div>
          <div class="share-link-box">
            <label for="shareWebsiteUrl">Link Website</label>
            <input id="shareWebsiteUrl" type="text" readonly value="">
          </div>
          <div class="share-actions-grid">
            <button type="button" class="ghost share-btn" data-share-provider="whatsapp">WhatsApp</button>
            <button type="button" class="ghost share-btn" data-share-provider="telegram">Telegram</button>
            <button type="button" class="ghost share-btn" data-share-provider="discord">Discord</button>
            <button type="button" class="ghost share-btn" data-share-provider="instagram">Instagram</button>
            <button type="button" class="ghost share-btn" data-share-provider="facebook">Facebook</button>
            <button type="button" class="ghost share-btn" data-share-provider="x">X / Twitter</button>
            <button type="button" class="ghost share-btn" data-share-provider="linkedin">LinkedIn</button>
            <button type="button" class="ghost share-btn" data-share-provider="line">Line</button>
            <button type="button" class="ghost share-btn" data-share-provider="email">Email</button>
          </div>
          <div id="shareNotice" class="notice info hidden"></div>
        </section>

        <footer class="site-footer" data-view-section="pages">
          <div class="footer-brand">
            <img src="<?= $logoPath ?>" alt="Logo <?= $appName ?>">
            <div>
              <strong><?= $appName ?></strong>
              <span>Premium Accounts - Top Up Game - Digital Services</span>
            </div>
          </div>
          <div class="footer-links">
            <a href="https://wa.me/6285178232383" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            <a href="https://instagram.com/odyssiavault" target="_blank" rel="noopener noreferrer">Instagram</a>
            <a href="https://chat.whatsapp.com/I1HfRLDI9ie3xjItKj7e41" target="_blank" rel="noopener noreferrer">Grup WA</a>
          </div>
        </footer>
      </main>
    </section>
  </div>
  <div id="newsModal" class="modal hidden">
    <div class="modal-card">
      <button id="newsModalClose" type="button" class="ghost mini-btn modal-close">Tutup</button>
      <h3 id="newsModalTitle">Detail Berita</h3>
      <p id="newsModalMeta" class="muted"></p>
      <div id="newsModalContent" class="box" style="margin-top:8px;"></div>
      <a id="newsModalSource" class="contact-link hidden" target="_blank" rel="noopener noreferrer" style="margin-top:10px;">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.59 13.41a1 1 0 0 0 1.41 0l4.59-4.59V12a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1h-6a1 1 0 1 0 0 2h3.17l-4.17 4.17a1 1 0 0 0 0 1.41ZM19 19H5V5h6a1 1 0 0 0 0-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a1 1 0 0 0-2 0v6Z"></path></svg>
        <div><strong>Buka Sumber Asli</strong><span id="newsModalSourceName">Odyssiavault</span></div>
      </a>
    </div>
  </div>
  <div id="paymentQrModal" class="modal hidden">
    <div class="modal-card">
      <button id="paymentQrModalClose" type="button" class="ghost mini-btn modal-close">Tutup</button>
      <h3 id="paymentQrTitle">Pembayaran Order</h3>
      <div id="paymentQrSummary" class="box" style="margin-top:8px;"></div>
      <img id="paymentQrImage" class="qris-img" alt="QR Pembayaran" style="margin-top:10px;">
      <div id="paymentQrInstruction" class="box" style="margin-top:10px;">
        Scan QR, bayar sesuai nominal, lalu tunggu verifikasi admin.
      </div>
      <div id="paymentQrNotice" class="notice info hidden" style="margin-top:10px;"></div>
      <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:8px;">
        <button id="paymentQrConfirmBtn" type="button" class="mini-btn success">Saya Sudah Bayar</button>
        <button id="paymentQrToHistory" type="button" class="mini-btn">Buka Riwayat</button>
      </div>
    </div>
  </div>
  <script src="./assets/app.js?v=<?= htmlspecialchars($jsVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>






