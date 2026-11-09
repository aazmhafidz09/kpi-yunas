<?php
// Kelola akun login (khusus Pemilik).
require __DIR__ . '/lib/bootstrap.php';
wajib_boleh('kelola_pengguna');

$saya = pengguna();

/** Validasi isian akun; mengembalikan [data, galat]. */
function baca_isian(bool $baru): array
{
    $d = [
        'username' => strtolower(trim((string)post('username'))),
        'nama' => trim((string)post('nama')),
        'peran' => (string)post('peran'),
        'pegawai_id' => (int)post('pegawai_id') ?: null,
    ];
    if (!preg_match('/^[a-z0-9_.]{3,50}$/', $d['username'])) return [$d, 'Username 3–50 karakter: huruf kecil, angka, titik, atau garis bawah.'];
    if ($d['nama'] === '') return [$d, 'Nama wajib diisi.'];
    if (!isset(PERAN_LABEL[$d['peran']])) return [$d, 'Peran tidak valid.'];
    if ($d['peran'] === 'pegawai' && !$d['pegawai_id']) return [$d, 'Akun pegawai harus dihubungkan ke data pegawai.'];
    if ($d['peran'] !== 'pegawai') $d['pegawai_id'] = null;
    if ($baru || post('password') !== '') {
        if ($g = cek_password((string)post('password'), (string)post('password_ulang'))) return [$d, $g];
    }
    return [$d, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'tambah') {
        [$d, $g] = baca_isian(true);
        if (!$g && q('SELECT id FROM pengguna WHERE username = ?', [$d['username']])->fetch()) $g = 'Username sudah dipakai.';
        if ($g) {
            flash($g, 'galat');
        } else {
            q('INSERT INTO pengguna (username, nama, password_hash, peran, pegawai_id) VALUES (?, ?, ?, ?, ?)',
                [$d['username'], $d['nama'], password_hash((string)post('password'), PASSWORD_DEFAULT), $d['peran'], $d['pegawai_id']]);
            flash('Akun ' . $d['username'] . ' dibuat. Sampaikan passwordnya langsung ke yang bersangkutan dan minta diganti lewat menu Akun.');
        }
        redirect('pengguna.php');
    }
    if ($aksi === 'simpan') {
        $id = (int)post('id');
        [$d, $g] = baca_isian(false);
        if (!$g && q('SELECT id FROM pengguna WHERE username = ? AND id <> ?', [$d['username'], $id])->fetch()) $g = 'Username sudah dipakai.';
        $aktif = post('aktif') ? 1 : 0;
        // Cegah Pemilik mengunci dirinya sendiri / menghilangkan Pemilik terakhir.
        if (!$g && $id === (int)$saya['id'] && ($d['peran'] !== 'pemilik' || !$aktif)) {
            $g = 'Anda tidak bisa menurunkan peran atau menonaktifkan akun Anda sendiri.';
        }
        if ($g) {
            flash($g, 'galat');
            redirect('pengguna.php?edit=' . $id);
        }
        q('UPDATE pengguna SET username = ?, nama = ?, peran = ?, pegawai_id = ?, aktif = ? WHERE id = ?',
            [$d['username'], $d['nama'], $d['peran'], $d['pegawai_id'], $aktif, $id]);
        if (post('password') !== '') {
            q('UPDATE pengguna SET password_hash = ? WHERE id = ?', [password_hash((string)post('password'), PASSWORD_DEFAULT), $id]);
        }
        if (post('password') !== '' || !$aktif) {
            cabut_token_ingat($id); // paksa login ulang di semua perangkat
        }
        if (post('password') !== '') {
            hapus_gagal_login($d['username']); // buka kunci login setelah password diatur ulang
        }
        flash('Akun ' . $d['username'] . ' tersimpan' . (post('password') !== '' ? ' dan password diatur ulang.' : '.'));
        redirect('pengguna.php');
    }
}

$daftar = q('SELECT u.*, p.nama AS nama_pegawai FROM pengguna u LEFT JOIN pegawai p ON p.id = u.pegawai_id ORDER BY u.aktif DESC, FIELD(u.peran, \'pemilik\', \'manajer\', \'pegawai\'), u.nama')->fetchAll();
$edit = isset($_GET['edit']) ? (q('SELECT * FROM pengguna WHERE id = ?', [(int)$_GET['edit']])->fetch() ?: null) : null;
$pegawaiList = daftar_pegawai();

tampil_header('Pengguna', 'pengguna');
?>
<h1>Pengguna</h1>
<p class="catatan"><strong>Pemilik</strong>: akses penuh. <strong>Manajer</strong>: isi checklist, kelola apresiasi, isi omzet, konfirmasi Manajer.
  <strong>Pegawai</strong>: hanya melihat checklist, apresiasi, dan bonusnya sendiri (Pasal 9 ayat 3).
  Nama akun Pemilik/Manajer otomatis tercatat sebagai penyetuju apresiasi.</p>

<table class="tabel">
  <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Pegawai</th><th>Status</th><th>Login terakhir</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($daftar as $u): ?>
    <tr class="<?= $u['aktif'] ? '' : 'redup' ?>">
      <td><strong><?= h($u['nama']) ?></strong><?= (int)$u['id'] === (int)$saya['id'] ? ' <span class="kecil">(Anda)</span>' : '' ?></td>
      <td><?= h($u['username']) ?></td>
      <td><?= h(PERAN_LABEL[$u['peran']]) ?></td>
      <td><?= h($u['nama_pegawai'] ?? '–') ?></td>
      <td><?= $u['aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
      <td class="kecil"><?= $u['login_terakhir'] ? h(date('j M Y H:i', strtotime($u['login_terakhir']))) : 'belum pernah' ?></td>
      <td><a class="tombol kecil" href="?edit=<?= $u['id'] ?>">Ubah</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2><?= $edit ? 'Ubah akun ' . h($edit['username']) : 'Tambah akun' ?></h2>
<form method="post" class="form-kotak grid-form">
  <input type="hidden" name="aksi" value="<?= $edit ? 'simpan' : 'tambah' ?>">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
  <label>Nama<input type="text" name="nama" value="<?= h($edit['nama'] ?? '') ?>" required></label>
  <label>Username<input type="text" name="username" value="<?= h($edit['username'] ?? '') ?>" pattern="[a-z0-9_.]{3,50}" autocomplete="off" required></label>
  <label>Peran
    <select name="peran" id="peran">
      <?php foreach (PERAN_LABEL as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['peran'] ?? 'pegawai') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
  </label>
  <label id="pilih-pegawai">Hubungkan ke pegawai
    <select name="pegawai_id">
      <option value="">—</option>
      <?php foreach ($pegawaiList as $p): ?><option value="<?= $p['id'] ?>" <?= ($edit['pegawai_id'] ?? null) == $p['id'] ? 'selected' : '' ?>><?= h($p['nama']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label><?= $edit ? 'Password baru (kosongkan bila tidak diubah)' : 'Password (min. 8 karakter)' ?><input type="password" name="password" autocomplete="new-password" <?= $edit ? '' : 'required minlength="8"' ?>></label>
  <label>Ulangi password<input type="password" name="password_ulang" autocomplete="new-password"></label>
  <?php if ($edit): ?><label class="cek"><input type="checkbox" name="aktif" value="1" <?= $edit['aktif'] ? 'checked' : '' ?>> Aktif (nonaktifkan untuk mencabut akses)</label><?php endif; ?>
  <div><button class="tombol"><?= $edit ? 'Simpan' : 'Tambah akun' ?></button><?php if ($edit): ?> <a class="tombol sekunder" href="pengguna.php">Batal</a><?php endif; ?></div>
</form>
<script>
(function () {
  const peran = document.getElementById('peran'), blok = document.getElementById('pilih-pegawai');
  const atur = () => { blok.style.display = peran.value === 'pegawai' ? '' : 'none'; };
  peran.addEventListener('change', atur); atur();
})();
</script>
<?php tampil_footer();
