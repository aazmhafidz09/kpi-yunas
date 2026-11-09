<?php
function tampil_header(string $judul, string $aktif = ''): void
{
    $u = pengguna();
    // [url, label, label pendek (HP), ikon, hak akses yang dibutuhkan (null = semua yang login)]
    $menu = [
        'verifikasi' => ['verifikasi.php', 'Verifikasi', 'Verifikasi', 'cek-ganda', 'isi_checklist'],
        'hariini' => ['hariini.php', 'Isi Harian', 'Isi Harian', 'pensil', null],
        'index' => ['index.php', 'Rekap Bonus', 'Rekap', 'grafik', null],
        'checklist' => ['checklist.php', 'Checklist Bulanan', 'Checklist', 'kalender', null],
        'apresiasi' => ['apresiasi.php', 'Apresiasi', 'Apresiasi', 'bintang', null],
        'periode' => ['periode.php', 'Omzet & Periode', 'Omzet', 'uang', 'kelola_periode'],
        'pegawai' => ['pegawai.php', 'Pegawai', 'Pegawai', 'orang', 'kelola_pegawai'],
        'butir' => ['butir.php', 'Butir Checklist', 'Butir', 'daftar', 'ubah_butir'],
        'pengguna' => ['pengguna.php', 'Pengguna', 'Pengguna', 'kunci', 'kelola_pengguna'],
        'pengaturan' => ['pengaturan.php', 'Pengaturan', 'Pengaturan', 'gir', 'pengaturan'],
        'sistem' => ['sistem.php', 'Sistem', 'Sistem', 'gir', 'pengaturan'],
    ];
    $menu = array_filter($menu, fn($m) => $m[4] === null || boleh($m[4]));
    $menunggu = boleh('isi_checklist') ? jumlah_menunggu() : 0;
    $lencana = fn($k) => $k === 'verifikasi' && $menunggu > 0 ? '<span class="lencana-angka">' . $menunggu . '</span>' : '';
    // HP: 4 menu utama di bilah bawah, sisanya di "Lainnya".
    $utama = array_slice($menu, 0, 4, true);
    $lainnya = array_slice($menu, 4, null, true);
    kepala_html($judul);
    ?>
<header class="topbar">
  <a class="brand" href="beranda.php">Yunas Studio <span>KPI &amp; Bonus</span></a>
  <nav class="nav-desktop">
    <?php foreach ($menu as $k => [$url, $label]): ?>
      <a href="<?= $url ?>" class="<?= $k === $aktif ? 'aktif' : '' ?>"><?= h($label) ?><?= $lencana($k) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="akun">
    <a href="akun.php" class="<?= $aktif === 'akun' ? 'aktif' : '' ?>" title="Akun saya"><?= h($u['nama']) ?> <span>· <?= h(PERAN_LABEL[$u['peran']]) ?></span></a>
    <form method="post" action="logout.php" class="hanya-desktop"><button class="tombol-keluar">Keluar</button></form>
  </div>
</header>
<nav class="nav-bawah" aria-label="Menu utama">
  <?php foreach ($utama as $k => [$url, , $pendek, $ikon]): ?>
    <a href="<?= $url ?>" class="<?= $k === $aktif ? 'aktif' : '' ?>"><?= ikon($ikon) ?><span><?= h($pendek) ?></span><?= $lencana($k) ?></a>
  <?php endforeach; ?>
  <details class="nav-lainnya <?= isset($lainnya[$aktif]) || $aktif === 'akun' ? 'aktif' : '' ?>">
    <summary><?= ikon('menu') ?><span>Lainnya</span></summary>
    <div class="lembar">
      <?php foreach ($lainnya as $k => [$url, $label, , $ikon]): ?>
        <a href="<?= $url ?>" class="<?= $k === $aktif ? 'aktif' : '' ?>"><?= ikon($ikon) ?> <?= h($label) ?></a>
      <?php endforeach; ?>
      <a href="akun.php" class="<?= $aktif === 'akun' ? 'aktif' : '' ?>"><?= ikon('orang') ?> Akun saya (<?= h($u['nama']) ?>)</a>
      <button type="button" class="tombol-install" hidden><?= ikon('unduh') ?> Pasang aplikasi di HP</button>
      <form method="post" action="logout.php"><button><?= ikon('keluar') ?> Keluar</button></form>
    </div>
  </details>
</nav>
<main>
<?php if ($f = flash()): ?>
  <div class="flash <?= h($f['jenis']) ?>"><?= h($f['pesan']) ?></div>
<?php endif;
}

function kepala_html(string $judul): void
{
    global $CONFIG;
    ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($judul) ?> · <?= h($CONFIG['app_name']) ?></title>
<meta name="theme-color" content="#2f5d50">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="KPI Yunas">
<link rel="manifest" href="manifest.json">
<link rel="icon" href="assets/ikon/ikon.svg" type="image/svg+xml">
<link rel="icon" href="assets/ikon/ikon-192.png" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="assets/ikon/apple-touch-icon.png">
<link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSI ?>">
<script src="assets/app.js?v=<?= APP_VERSI ?>" defer></script>
</head>
<body>
<?php
}

/** Halaman tanpa menu (login, setup). */
function tampil_header_polos(string $judul): void
{
    kepala_html($judul);
    echo '<main class="polos">';
}

function tampil_footer_polos(): void
{
    echo '</main></body></html>';
}

function tampil_footer(): void
{
    ?></main>
<footer class="kaki">Mengacu pada SK Kebijakan Bonus Pegawai Yunas Studio, berlaku 1 November 2026.</footer>
</body>
</html>
<?php
}

/** Ikon garis sederhana (SVG inline, mengikuti warna teks). */
function ikon(string $nama): string
{
    $p = [
        'cek-ganda' => '<path d="M2 12l4 4 8-8M10 16l1 1 10-10"/>',
        'pensil' => '<path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/>',
        'grafik' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'kalender' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'bintang' => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'uang' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/>',
        'orang' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
        'daftar' => '<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'kunci' => '<circle cx="8" cy="14" r="4"/><path d="M11 11l9-9M17 5l3 3"/>',
        'gir' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'unduh' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'keluar' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>',
    ][$nama] ?? '';
    return '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Dropdown pemilih periode & pegawai (GET form). */
function pemilih(array $periodeAktif, ?array $pegawaiAktif = null, bool $denganPegawai = true): void
{
    ?>
<form method="get" class="pemilih">
  <label>Bulan
    <select name="periode" onchange="this.form.submit()">
      <?php foreach (daftar_periode() as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $p['id'] == $periodeAktif['id'] ? 'selected' : '' ?>><?= h(nama_periode($p)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if ($denganPegawai && boleh('lihat_semua')): ?>
  <label>Pegawai
    <select name="pegawai" onchange="this.form.submit()">
      <?php foreach (daftar_pegawai() as $pg): ?>
        <option value="<?= $pg['id'] ?>" <?= $pegawaiAktif && $pg['id'] == $pegawaiAktif['id'] ? 'selected' : '' ?>><?= h($pg['nama']) ?> — <?= h($pg['jabatan']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <noscript><button>Tampilkan</button></noscript>
</form>
<?php
}

/** Ambil periode & pegawai dari query string, dengan nilai default. */
function konteks(bool $butuhPegawai = true): array
{
    $periode = isset($_GET['periode']) ? ambil_periode((int)$_GET['periode']) : periode_default();
    if (!$periode && !boleh('kelola_periode')) {
        http_response_code(404);
        exit('Belum ada periode penilaian. Hubungi Pemilik/Manajer.');
    }
    if (!$periode) {
        flash('Belum ada periode. Tambahkan periode terlebih dahulu.', 'peringatan');
        redirect('periode.php');
    }
    $pegawai = null;
    if ($butuhPegawai && !boleh('lihat_semua')) {
        // Peran pegawai: selalu dirinya sendiri, apa pun isi URL.
        $pegawai = ambil_pegawai((int)(pengguna()['pegawai_id'] ?? 0));
        if (!$pegawai) {
            http_response_code(403);
            exit('Akun Anda belum dihubungkan ke data pegawai. Hubungi Pemilik.');
        }
    } elseif ($butuhPegawai) {
        $pegawai = isset($_GET['pegawai']) ? ambil_pegawai((int)$_GET['pegawai']) : null;
        if (!$pegawai) {
            $semua = daftar_pegawai();
            if (!$semua) {
                flash('Belum ada pegawai aktif. Tambahkan pegawai terlebih dahulu.', 'peringatan');
                redirect('pegawai.php');
            }
            $pegawai = $semua[0];
        }
    }
    return [$periode, $pegawai];
}
