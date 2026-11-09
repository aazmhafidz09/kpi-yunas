<?php
// Sistem & Pembaruan (khusus Pemilik): versi terpasang, migrasi database, kesehatan server.
require __DIR__ . '/lib/bootstrap.php';
wajib_boleh('pengaturan');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('aksi') === 'migrasi') {
    try {
        $nama = jalankan_migrasi();
        flash($nama ? 'Migrasi dijalankan: ' . implode(', ', $nama) : 'Tidak ada migrasi tertunda.');
    } catch (Throwable $e) {
        error_log('[kpi-yunas] migrasi manual gagal: ' . $e);
        flash('Migrasi gagal: ' . $e->getMessage(), 'galat');
    }
    redirect('sistem.php');
}

$berkas = migrasi_berkas();
$sudah = migrasi_sudah();
$tertunda = array_diff_key($berkas, $sudah);
$dbVersi = q('SELECT VERSION()')->fetchColumn();
$dbJam = q('SELECT NOW()')->fetchColumn();
$cek = [
    'PHP ' . PHP_VERSION . ' (minimal 8.1)' => version_compare(PHP_VERSION, '8.1', '>='),
    'Ekstensi pdo_mysql' => extension_loaded('pdo_mysql'),
    'Ekstensi mbstring' => extension_loaded('mbstring'),
    'HTTPS aktif (wajib untuk instal di HP)' => https(),
    'Mode produksi (debug mati)' => empty($CONFIG['debug']),
    'Jam database = jam aplikasi (WIB)' => abs(strtotime($dbJam) - time()) < 120,
    'Konfigurasi server ditemukan' . (BERKAS_CONFIG_LOKAL ? ' (' . (str_contains(BERKAS_CONFIG_LOKAL, 'kpi-yunas-config.php') ? 'di luar folder web ✓' : 'config.local.php') . ')' : '') => BERKAS_CONFIG_LOKAL !== null,
];

tampil_header('Sistem & Pembaruan', 'sistem');
?>
<div class="hp-wadah lebar">
<h1>Sistem &amp; Pembaruan</h1>

<section class="kartu-baris">
  <div class="kartu"><div class="label">Rilis terpasang</div><div class="nilai" style="font-size:16px"><?= h(info_rilis()) ?></div><div class="sub">Versi aset: <?= h(APP_VERSI) ?> (otomatis dari isi file)</div></div>
  <div class="kartu <?= $tertunda ? '' : 'aksen' ?>"><div class="label">Database</div><div class="nilai" style="font-size:16px"><?= $tertunda ? count($tertunda) . ' migrasi tertunda' : 'Mutakhir ✓' ?></div><div class="sub"><?= h($dbVersi) ?> · jam DB <?= h(date('H:i', strtotime($dbJam))) ?></div></div>
</section>

<h2>Pemeriksaan server</h2>
<table class="tabel tabel-hp">
  <tbody>
  <?php foreach ($cek as $label => $ok): ?>
    <tr><td><?= h($label) ?></td><td><?= $ok ? '<span class="tingkat t-atas">OK</span>' : '<span class="tingkat t-di-bawah">Periksa</span>' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="kecil">Di laptop, "HTTPS", "Mode produksi", dan "Konfigurasi server" memang belum OK; ketiganya baru berlaku di hosting.</p>

<h2>Migrasi database</h2>
<p class="catatan">Perubahan struktur database dijalankan <strong>otomatis</strong> saat kode baru terpasang. Tombol di bawah hanya untuk mencoba ulang bila ada yang gagal.</p>
<table class="tabel tabel-hp">
  <thead><tr><th>Migrasi</th><th>Status</th><th>Dijalankan</th></tr></thead>
  <tbody>
  <?php foreach ($berkas as $nama => $p): ?>
    <tr>
      <td data-label="Migrasi"><code><?= h($nama) ?></code></td>
      <td data-label="Status"><?= isset($sudah[$nama]) ? '<span class="tingkat t-atas">Sudah</span>' : '<span class="tingkat t-tengah">Tertunda</span>' ?></td>
      <td data-label="Dijalankan"><?= h($sudah[$nama] ?? '–') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if ($tertunda): ?>
<form method="post" style="margin-top:12px"><input type="hidden" name="aksi" value="migrasi"><button class="tombol">Jalankan migrasi tertunda</button></form>
<?php endif; ?>
</div>
<?php tampil_footer();
