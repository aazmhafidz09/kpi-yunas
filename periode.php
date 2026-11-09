<?php
require __DIR__ . '/lib/bootstrap.php';
wajib_boleh('kelola_periode');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $data = [
        parse_rupiah(post('batas_minimum')),
        parse_rupiah(post('batas_target')),
        parse_rupiah(post('batas_stretch')),
        parse_rupiah(post('omzet')),
        post('laba_kas_ok') === '' || post('laba_kas_ok') === null ? null : (int)post('laba_kas_ok'),
        post('status') === 'final' ? 'final' : 'berjalan',
        trim((string)post('catatan')) ?: null,
    ];
    if ($data[0] !== null && $data[1] !== null && $data[2] !== null && !($data[0] <= $data[1] && $data[1] <= $data[2])) {
        flash('Batas omzet harus berurutan: Minimum ≤ Target ≤ Stretch.', 'galat');
        redirect('periode.php' . (post('id') ? '?edit=' . (int)post('id') : ''));
    }
    if ($aksi === 'simpan') {
        q('UPDATE periode SET batas_minimum = ?, batas_target = ?, batas_stretch = ?, omzet = ?, laba_kas_ok = ?, status = ?, catatan = ? WHERE id = ?',
            [...$data, (int)post('id')]);
        flash('Periode tersimpan.');
        redirect('index.php?periode=' . (int)post('id'));
    }
    if ($aksi === 'tambah') {
        $tahun = (int)post('tahun');
        $bulan = (int)post('bulan');
        $ada = q('SELECT id FROM periode WHERE tahun = ? AND bulan = ?', [$tahun, $bulan])->fetchColumn();
        if ($ada) {
            flash('Periode ' . NAMA_BULAN[$bulan] . " $tahun sudah ada.", 'galat');
        } else {
            q('INSERT INTO periode (tahun, bulan, batas_minimum, batas_target, batas_stretch, omzet, laba_kas_ok, status, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$tahun, $bulan, ...$data]);
            flash('Periode ' . NAMA_BULAN[$bulan] . " $tahun ditambahkan.");
        }
        redirect('periode.php');
    }
}

$daftar = daftar_periode();
$edit = isset($_GET['edit']) ? ambil_periode((int)$_GET['edit']) : null;
$akhir = end($daftar);
$saranBulan = $akhir ? ((int)$akhir['bulan'] % 12) + 1 : (int)date('n');
$saranTahun = $akhir ? (int)$akhir['tahun'] + ((int)$akhir['bulan'] === 12 ? 1 : 0) : (int)date('Y');

tampil_header('Omzet & Periode', 'periode');

function form_periode(?array $p): void
{
    $rp = fn($v) => $v === null ? '' : number_format((float)$v, 0, ',', '.');
    ?>
  <label>Batas Minimum (Rp)<input type="text" inputmode="numeric" name="batas_minimum" value="<?= $rp($p['batas_minimum'] ?? null) ?>" placeholder="mis. 23.000.000 atau 23 jt"></label>
  <label>Batas Target (Rp)<input type="text" inputmode="numeric" name="batas_target" value="<?= $rp($p['batas_target'] ?? null) ?>"></label>
  <label>Batas Stretch (Rp)<input type="text" inputmode="numeric" name="batas_stretch" value="<?= $rp($p['batas_stretch'] ?? null) ?>"></label>
  <label>Omzet bulan ini (Rp)<input type="text" inputmode="numeric" name="omzet" value="<?= $rp($p['omzet'] ?? null) ?>" placeholder="kosongkan bila belum direkap"></label>
  <label>Laba kas bulan ini
    <select name="laba_kas_ok">
      <option value="" <?= ($p['laba_kas_ok'] ?? null) === null ? 'selected' : '' ?>>Belum diisi</option>
      <option value="1" <?= isset($p['laba_kas_ok']) && (int)$p['laba_kas_ok'] === 1 ? 'selected' : '' ?>>Tidak negatif (≥ 0)</option>
      <option value="0" <?= isset($p['laba_kas_ok']) && $p['laba_kas_ok'] !== null && (int)$p['laba_kas_ok'] === 0 ? 'selected' : '' ?>>Negatif</option>
    </select>
  </label>
  <label>Status
    <select name="status">
      <option value="berjalan" <?= ($p['status'] ?? '') !== 'final' ? 'selected' : '' ?>>Berjalan</option>
      <option value="final" <?= ($p['status'] ?? '') === 'final' ? 'selected' : '' ?>>Final (sudah direkap)</option>
    </select>
  </label>
  <label class="lebar">Catatan<input type="text" name="catatan" value="<?= h($p['catatan'] ?? '') ?>"></label>
<?php
}
?>
<h1>Omzet &amp; Periode</h1>
<p class="catatan">Batas omzet mengikuti Lampiran A. Bonus A hanya cair bila omzet mencapai Minimum <em>dan</em> laba kas tidak negatif (Pasal 4).</p>

<?php if ($edit): ?>
<h2>Ubah <?= h(nama_periode($edit)) ?></h2>
<form method="post" class="form-kotak grid-form">
  <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit['id'] ?>">
  <?php form_periode($edit); ?>
  <div><button class="tombol">Simpan</button> <a class="tombol sekunder" href="periode.php">Batal</a></div>
</form>
<?php endif; ?>

<table class="tabel">
  <thead><tr><th>Bulan</th><th class="angka">Minimum</th><th class="angka">Target</th><th class="angka">Stretch</th><th class="angka">Omzet</th><th>Laba kas</th><th>Tingkat</th><th class="angka">Bonus A</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($daftar as $p): $a = hitung_bonus_a($p); ?>
    <tr>
      <td><?= h(nama_periode($p)) ?></td>
      <td class="angka"><?= $p['batas_minimum'] === null ? '–' : rupiah($p['batas_minimum']) ?></td>
      <td class="angka"><?= $p['batas_target'] === null ? '–' : rupiah($p['batas_target']) ?></td>
      <td class="angka"><?= $p['batas_stretch'] === null ? '–' : rupiah($p['batas_stretch']) ?></td>
      <td class="angka"><?= $p['omzet'] === null ? '–' : rupiah($p['omzet']) ?></td>
      <td><?= $p['laba_kas_ok'] === null ? '–' : ((int)$p['laba_kas_ok'] === 1 ? '≥ 0' : 'negatif') ?></td>
      <td><?= h($a['tingkat']) ?></td>
      <td class="angka"><?= rupiah($a['bonus']) ?></td>
      <td><?= $p['status'] === 'final' ? '<span class="lencana final">Final</span>' : '<span class="lencana">Berjalan</span>' ?></td>
      <td><a class="tombol kecil" href="?edit=<?= $p['id'] ?>">Ubah</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Tambah periode</h2>
<form method="post" class="form-kotak grid-form">
  <input type="hidden" name="aksi" value="tambah">
  <label>Bulan<select name="bulan"><?php foreach (NAMA_BULAN as $i => $n): ?><option value="<?= $i ?>" <?= $i === $saranBulan ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
  <label>Tahun<input type="number" name="tahun" value="<?= $saranTahun ?>" min="2026" max="2100"></label>
  <?php form_periode(null); ?>
  <div><button class="tombol">Tambah periode</button></div>
</form>
<?php tampil_footer();
