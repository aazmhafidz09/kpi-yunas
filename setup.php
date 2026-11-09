<?php
// Pembuatan akun Pemilik pertama. Hanya bisa dibuka selama belum ada pengguna sama sekali.
const HALAMAN_PUBLIK = true;
require __DIR__ . '/lib/bootstrap.php';

if (ada_pengguna()) redirect(pengguna() ? 'index.php' : 'login.php');

// Di server online, siapa pun yang pertama membuka halaman ini bisa menjadi Pemilik.
// Karena itu, selain dari laptop sendiri (localhost), wajib memasukkan kode_setup dari config.local.php.
$dariLokal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$kodeSetup = (string)($CONFIG['kode_setup'] ?? '');
if (!$dariLokal && strlen($kodeSetup) < 12) {
    http_response_code(403);
    tampil_header_polos('Setup dikunci');
    echo '<div class="form-kotak kotak-login"><h1>Setup dikunci</h1><p>Belum ada akun di server ini. Untuk membuat akun Pemilik, isi <code>kode_setup</code> '
        . '(minimal 12 karakter acak) di <code>config.local.php</code>, lalu buka halaman ini lagi. Atau impor data yang sudah berisi akun Pemilik.</p></div>';
    tampil_footer_polos();
    exit;
}

$galat = null;
$isian = ['username' => '', 'nama' => pengaturan()['nama_pemilik'] ?? ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isian = ['username' => trim((string)post('username')), 'nama' => trim((string)post('nama'))];
    $pw = (string)post('password');
    if (!$dariLokal && !hash_equals($kodeSetup, (string)post('kode_setup'))) {
        usleep(800000);
        $galat = 'Kode setup salah.';
    } elseif (!preg_match('/^[a-z0-9_.]{3,50}$/', $isian['username'])) {
        $galat = 'Username 3–50 karakter: huruf kecil, angka, titik, atau garis bawah.';
    } elseif ($isian['nama'] === '') {
        $galat = 'Nama wajib diisi.';
    } else {
        $galat = cek_password($pw, (string)post('password_ulang'));
    }
    if (!$galat) {
        q('INSERT INTO pengguna (username, nama, password_hash, peran) VALUES (?, ?, ?, ?)',
            [$isian['username'], $isian['nama'], password_hash($pw, PASSWORD_DEFAULT), 'pemilik']);
        masuk(q('SELECT * FROM pengguna WHERE username = ?', [$isian['username']])->fetch());
        flash('Akun Pemilik dibuat. Tambahkan akun Manajer dan pegawai lewat menu Pengguna.');
        redirect('pengguna.php');
    }
}

tampil_header_polos('Buat akun Pemilik');
?>
<form method="post" class="form-kotak kotak-login">
  <h1>Buat akun Pemilik</h1>
  <p class="redup">Belum ada akun. Akun pertama ini menjadi Pemilik dengan akses penuh.</p>
  <?php if ($galat): ?><div class="flash galat"><?= h($galat) ?></div><?php endif; ?>
  <?php if (!$dariLokal): ?><label>Kode setup (dari config.local.php)<input type="password" name="kode_setup" autocomplete="off" required></label><?php endif; ?>
  <label>Nama (tampil sebagai penyetuju)<input type="text" name="nama" value="<?= h($isian['nama']) ?>" required></label>
  <label>Username<input type="text" name="username" value="<?= h($isian['username']) ?>" autocomplete="username" pattern="[a-z0-9_.]{3,50}" required></label>
  <label>Password (min. 8 karakter)<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
  <label>Ulangi password<input type="password" name="password_ulang" autocomplete="new-password" minlength="8" required></label>
  <button class="tombol">Buat akun</button>
</form>
<?php tampil_footer_polos();
