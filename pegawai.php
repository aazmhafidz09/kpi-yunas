<?php
require __DIR__ . '/lib/bootstrap.php';
wajib_boleh('kelola_pegawai');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim((string)post('nama'));
    $jabatan = (int)post('jabatan_id');
    if ($nama === '' || !$jabatan) {
        flash('Nama dan jabatan wajib diisi.', 'galat');
        redirect('pegawai.php');
    }
    if (post('aksi') === 'tambah') {
        q('INSERT INTO pegawai (nama, jabatan_id, catatan) VALUES (?, ?, ?)', [$nama, $jabatan, trim((string)post('catatan')) ?: null]);
        flash("Pegawai $nama ditambahkan.");
    } elseif (post('aksi') === 'simpan') {
        $id = (int)post('id');
        $lama = ambil_pegawai($id);
        q('UPDATE pegawai SET nama = ?, jabatan_id = ?, aktif = ?, catatan = ? WHERE id = ?',
            [$nama, $jabatan, post('aktif') ? 1 : 0, trim((string)post('catatan')) ?: null, $id]);
        $pesan = "Data $nama tersimpan.";
        if ($lama && (int)$lama['jabatan_id'] !== $jabatan) {
            $pesan .= ' Jabatan berubah: butir checklist bulan yang sudah dibuka tetap memakai butir lama; ubah lewat "Ubah butir bulan ini" bila perlu.';
        }
        flash($pesan);
    }
    redirect('pegawai.php');
}

$jabatan = q('SELECT * FROM jabatan ORDER BY id')->fetchAll();
$daftar = daftar_pegawai(false);
$edit = isset($_GET['edit']) ? ambil_pegawai((int)$_GET['edit']) : null;

tampil_header('Pegawai', 'pegawai');
?>
<h1>Pegawai</h1>
<p class="catatan">Pegawai = pekerja tetap selain Manajer (Pasal 1). Bonus Manajer diatur dalam Perjanjian Kerjasama Bagi Hasil, jadi tidak dicatat di sini.</p>

<table class="tabel">
  <thead><tr><th>Nama</th><th>Jabatan</th><th>Checklist</th><th>Status</th><th>Catatan</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($daftar as $p): ?>
    <tr class="<?= $p['aktif'] ? '' : 'redup' ?>">
      <td><strong><?= h($p['nama']) ?></strong></td>
      <td><?= h($p['jabatan']) ?></td>
      <td>Lampiran <?= h($p['lampiran']) ?></td>
      <td><?= $p['aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
      <td><?= h($p['catatan']) ?></td>
      <td><a class="tombol kecil" href="?edit=<?= $p['id'] ?>">Ubah</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2><?= $edit ? 'Ubah ' . h($edit['nama']) : 'Tambah pegawai' ?></h2>
<form method="post" class="form-kotak grid-form">
  <input type="hidden" name="aksi" value="<?= $edit ? 'simpan' : 'tambah' ?>">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
  <label>Nama<input type="text" name="nama" value="<?= h($edit['nama'] ?? '') ?>" required></label>
  <label>Jabatan
    <select name="jabatan_id">
      <?php foreach ($jabatan as $j): ?><option value="<?= $j['id'] ?>" <?= ($edit['jabatan_id'] ?? null) == $j['id'] ? 'selected' : '' ?>><?= h($j['nama']) ?> (Lampiran <?= h($j['lampiran']) ?>)</option><?php endforeach; ?>
    </select>
  </label>
  <label class="lebar">Catatan<input type="text" name="catatan" value="<?= h($edit['catatan'] ?? '') ?>"></label>
  <?php if ($edit): ?><label class="cek"><input type="checkbox" name="aktif" value="1" <?= $edit['aktif'] ? 'checked' : '' ?>> Aktif (nonaktifkan bila pegawai berhenti; riwayat tetap tersimpan)</label><?php endif; ?>
  <div><button class="tombol"><?= $edit ? 'Simpan' : 'Tambah' ?></button><?php if ($edit): ?> <a class="tombol sekunder" href="pegawai.php">Batal</a><?php endif; ?></div>
</form>
<?php tampil_footer();
