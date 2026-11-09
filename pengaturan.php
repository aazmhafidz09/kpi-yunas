<?php
require __DIR__ . '/lib/bootstrap.php';
wajib_boleh('pengaturan');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (($_POST['p'] ?? []) as $kunci => $nilai) {
        q('UPDATE pengaturan SET nilai = ? WHERE kunci = ?', [trim((string)$nilai), (string)$kunci]);
    }
    flash('Pengaturan tersimpan. Semua perhitungan memakai angka baru.');
    redirect('pengaturan.php');
}

$rows = q('SELECT * FROM pengaturan ORDER BY urutan')->fetchAll();
tampil_header('Pengaturan', 'pengaturan');
?>
<h1>Pengaturan kebijakan</h1>
<p class="catatan">Angka di sini mengikuti SK (Bab II–IV). Perubahan kebijakan wajib diberitahukan tertulis ke pegawai paling lambat 30 hari sebelum berlaku (Pasal 11 ayat 2).
  Perubahan di sini <strong>langsung berlaku untuk semua bulan</strong>, termasuk bulan yang sudah lewat.</p>
<form method="post" class="form-kotak grid-form">
  <?php foreach ($rows as $r): ?>
    <label><?= h($r['label']) ?><input type="text" name="p[<?= h($r['kunci']) ?>]" value="<?= h($r['nilai']) ?>"></label>
  <?php endforeach; ?>
  <div><button class="tombol">Simpan</button></div>
</form>
<?php tampil_footer();
