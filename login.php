<?php
const HALAMAN_PUBLIK = true;
require __DIR__ . '/lib/bootstrap.php';

if (!ada_pengguna()) redirect('setup.php');
if (pengguna()) redirect('beranda.php');

// Hanya izinkan tujuan internal berupa path di situs ini (cegah open redirect).
$ke = (string)($_GET['ke'] ?? $_POST['ke'] ?? '');
if (!preg_match('#^/(?!/)[A-Za-z0-9_\-/]*[A-Za-z0-9_\-]+\.php(\?[^\s<>"\\\\]*)?$#', $ke)) $ke = 'beranda.php';

$galat = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mb_substr(strtolower(trim((string)post('username'))), 0, 50);
    $terkunci = login_terkunci($username);
    if ($terkunci > 0) {
        // Password tidak diperiksa sama sekali selama terkunci.
        $galat = "Terlalu banyak percobaan gagal. Coba lagi dalam $terkunci menit, atau minta Pemilik mengatur ulang password.";
    } else {
        $u = q('SELECT * FROM pengguna WHERE username = ? AND aktif = 1', [$username])->fetch();
        if ($u && password_verify((string)post('password'), $u['password_hash'])) {
            hapus_gagal_login($username);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE pengguna SET password_hash = ? WHERE id = ?', [password_hash((string)post('password'), PASSWORD_DEFAULT), $u['id']]);
            }
            masuk($u, (bool)post('ingat'));
            redirect($ke);
        }
        catat_gagal_login($username);
        usleep(800000); // perlambat tebakan password
        $galat = 'Username atau password salah.';
    }
}

tampil_header_polos('Masuk');
?>
<form method="post" class="form-kotak kotak-login">
  <h1>Masuk</h1>
  <p class="redup">KPI &amp; Bonus Pegawai Yunas Studio</p>
  <?php if ($galat): ?><div class="flash galat"><?= h($galat) ?></div><?php endif; ?>
  <input type="hidden" name="ke" value="<?= h($ke) ?>">
  <label>Username<input type="text" name="username" value="<?= h($username) ?>" autocomplete="username" autofocus required></label>
  <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
  <label class="cek"><input type="checkbox" name="ingat" value="1" checked> Ingat saya di perangkat ini (<?= HARI_INGAT ?> hari)</label>
  <button class="tombol">Masuk</button>
  <p class="kecil">Lupa password? Minta Pemilik mengatur ulang lewat menu Pengguna.</p>
</form>
<?php tampil_footer_polos();
