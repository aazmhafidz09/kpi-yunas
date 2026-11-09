<?php
// Ganti password sendiri (semua peran).
require __DIR__ . '/lib/bootstrap.php';

$u = pengguna();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baris = q('SELECT password_hash FROM pengguna WHERE id = ?', [$u['id']])->fetch();
    if (!password_verify((string)post('password_lama'), $baris['password_hash'])) {
        flash('Password lama salah.', 'galat');
    } elseif ($g = cek_password((string)post('password'), (string)post('password_ulang'))) {
        flash($g, 'galat');
    } else {
        q('UPDATE pengguna SET password_hash = ? WHERE id = ?', [password_hash((string)post('password'), PASSWORD_DEFAULT), $u['id']]);
        cabut_token_ingat((int)$u['id']);
        masuk($u, isset($_COOKIE[COOKIE_INGAT])); // tetap masuk di perangkat ini, perangkat lain harus login ulang
        flash('Password berhasil diganti. Perangkat lain perlu login ulang.');
    }
    redirect('akun.php');
}

tampil_header('Akun saya', 'akun');
?>
<h1>Akun saya</h1>
<p><strong><?= h($u['nama']) ?></strong> · <?= h($u['username']) ?> · <?= h(PERAN_LABEL[$u['peran']]) ?>
  <?php if ($u['nama_pegawai']): ?> · terhubung ke pegawai <strong><?= h($u['nama_pegawai']) ?></strong><?php endif; ?></p>

<h2>Pasang aplikasi di HP</h2>
<div class="form-kotak akun-pasang">
  <p data-sudah-terpasang hidden>✓ Anda sedang memakai aplikasi yang sudah terpasang.</p>
  <button type="button" class="tombol tombol-install" hidden><?= ikon('unduh') ?> Pasang aplikasi KPI Yunas</button>
  <p data-petunjuk-ios hidden><strong>iPhone/iPad:</strong> buka halaman ini di Safari, ketuk tombol <em>Bagikan</em> (kotak dengan panah ke atas), lalu pilih <em>Tambahkan ke Layar Utama</em>.</p>
  <p class="kecil"><strong>Android (Chrome):</strong> bila tombol di atas tidak muncul, buka menu ⋮ lalu pilih <em>Instal aplikasi</em> / <em>Tambahkan ke layar utama</em>.
    Pemasangan hanya bisa bila aplikasi dibuka lewat alamat <strong>https://</strong>.</p>
</div>

<h2>Ganti password</h2>
<form method="post" class="form-kotak" style="max-width:420px">
  <label>Password lama<input type="password" name="password_lama" autocomplete="current-password" required></label>
  <label>Password baru (min. 8 karakter)<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
  <label>Ulangi password baru<input type="password" name="password_ulang" autocomplete="new-password" minlength="8" required></label>
  <button class="tombol">Simpan password</button>
</form>
<?php tampil_footer();
