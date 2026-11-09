<?php
require __DIR__ . '/lib/bootstrap.php';
wajib_boleh('kelola_periode');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');

    // Ambil ulang omzet dari aplikasi utama sekarang juga (periode berjalan, sumber otomatis).
    if ($aksi === 'perbarui_omzet') {
        $p = periode_mentah((int)post('id'));
        if ($p && $p['status'] !== 'final') {
            $p = sinkron_omzet($p, true);
            flash($p['omzet_galat'] ?: 'Omzet ' . nama_periode($p) . ' diperbarui: ' . rupiah($p['omzet']) . '.', $p['omzet_galat'] ? 'galat' : 'ok');
        }
        redirect('periode.php' . ($p ? '?edit=' . $p['id'] : ''));
    }

    $sumber = post('omzet_sumber') === 'manual' ? 'manual' : 'otomatis';
    $status = post('status') === 'final' ? 'final' : 'berjalan';
    $batas = [parse_rupiah(post('batas_minimum')), parse_rupiah(post('batas_target')), parse_rupiah(post('batas_stretch'))];
    $omzetForm = parse_rupiah(post('omzet'));
    $labaKas = post('laba_kas_ok') === '' || post('laba_kas_ok') === null ? null : (int)post('laba_kas_ok');
    $catatan = trim((string)post('catatan')) ?: null;
    if ($batas[0] !== null && $batas[1] !== null && $batas[2] !== null && !($batas[0] <= $batas[1] && $batas[1] <= $batas[2])) {
        flash('Batas omzet harus berurutan: Minimum ≤ Target ≤ Stretch.', 'galat');
        redirect('periode.php' . (post('id') ? '?edit=' . (int)post('id') : ''));
    }

    if ($aksi === 'simpan') {
        $lama = periode_mentah((int)post('id'));
        if (!$lama) redirect('periode.php');
        q('UPDATE periode SET batas_minimum = ?, batas_target = ?, batas_stretch = ?, laba_kas_ok = ?, catatan = ?, omzet_sumber = ? WHERE id = ?',
            [...$batas, $labaKas, $catatan, $sumber, $lama['id']]);
        if ($sumber === 'manual') {
            q('UPDATE periode SET omzet = ?, omzet_info = NULL, omzet_galat = NULL WHERE id = ?', [$omzetForm, $lama['id']]);
        }
        // Saat menjadi Final: ambil omzet otomatis terakhir kali, lalu dibekukan.
        $pesan = 'Periode tersimpan.';
        if ($sumber === 'otomatis' && $status === 'final' && $lama['status'] !== 'final') {
            $segar = sinkron_omzet(periode_mentah((int)$lama['id']), true);
            $pesan = $segar['omzet_galat']
                ? 'Periode Final, tetapi omzet terakhir gagal diambil; angka sebelumnya yang dibekukan.'
                : 'Periode Final. Omzet dibekukan di ' . rupiah($segar['omzet']) . '.';
        } elseif ($sumber === 'otomatis' && $status === 'berjalan') {
            q('UPDATE periode SET omzet_diperbarui = NULL WHERE id = ?', [$lama['id']]); // segarkan di pemuatan berikutnya
        }
        q('UPDATE periode SET status = ? WHERE id = ?', [$status, $lama['id']]);
        flash($pesan);
        redirect('index.php?periode=' . (int)$lama['id']);
    }

    if ($aksi === 'tambah') {
        $tahun = (int)post('tahun');
        $bulan = (int)post('bulan');
        $ada = q('SELECT id FROM periode WHERE tahun = ? AND bulan = ?', [$tahun, $bulan])->fetchColumn();
        if ($ada) {
            flash('Periode ' . NAMA_BULAN[$bulan] . " $tahun sudah ada.", 'galat');
        } else {
            q('INSERT INTO periode (tahun, bulan, batas_minimum, batas_target, batas_stretch, omzet, omzet_sumber, laba_kas_ok, status, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$tahun, $bulan, ...$batas, $sumber === 'manual' ? $omzetForm : null, $sumber, $labaKas, 'berjalan', $catatan]);
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
$sumberAktif = sumber_omzet_aktif();

tampil_header('Omzet & Periode', 'periode');

function form_periode(?array $p, bool $sumberAktif): void
{
    $rp = fn($v) => $v === null ? '' : number_format((float)$v, 0, ',', '.');
    $sumber = $p['omzet_sumber'] ?? ($sumberAktif ? 'otomatis' : 'manual');
    ?>
  <label>Batas Minimum (Rp)<input type="text" inputmode="numeric" name="batas_minimum" value="<?= $rp($p['batas_minimum'] ?? null) ?>" placeholder="mis. 23.000.000 atau 23 jt"></label>
  <label>Batas Target (Rp)<input type="text" inputmode="numeric" name="batas_target" value="<?= $rp($p['batas_target'] ?? null) ?>"></label>
  <label>Batas Stretch (Rp)<input type="text" inputmode="numeric" name="batas_stretch" value="<?= $rp($p['batas_stretch'] ?? null) ?>"></label>
  <fieldset class="lebar pilih-sumber" data-pilih-sumber>
    <legend>Sumber omzet</legend>
    <label class="cek"><input type="radio" name="omzet_sumber" value="otomatis" <?= $sumber === 'otomatis' ? 'checked' : '' ?>>
      Otomatis — "Total Pendapatan" yunas-studio.com (DP dibayar + transaksi lunas, per bulan transaksi dibuat)<?= $sumberAktif ? '' : ' · <span class="peringatan-teks">sumber belum diatur</span>' ?></label>
    <label class="cek"><input type="radio" name="omzet_sumber" value="manual" <?= $sumber === 'manual' ? 'checked' : '' ?>> Manual — ketik sendiri</label>
  </fieldset>
  <label>Omzet bulan ini (Rp)<input type="text" inputmode="numeric" name="omzet" value="<?= $rp($p['omzet'] ?? null) ?>" placeholder="kosongkan bila belum direkap" data-omzet></label>
  <label>Laba kas bulan ini
    <select name="laba_kas_ok">
      <option value="" <?= ($p['laba_kas_ok'] ?? null) === null ? 'selected' : '' ?>>Belum diisi</option>
      <option value="1" <?= isset($p['laba_kas_ok']) && (int)$p['laba_kas_ok'] === 1 ? 'selected' : '' ?>>Tidak negatif (≥ 0)</option>
      <option value="0" <?= isset($p['laba_kas_ok']) && $p['laba_kas_ok'] !== null && (int)$p['laba_kas_ok'] === 0 ? 'selected' : '' ?>>Negatif</option>
    </select>
  </label>
  <?php if ($p): ?>
  <label>Status
    <select name="status">
      <option value="berjalan" <?= ($p['status'] ?? '') !== 'final' ? 'selected' : '' ?>>Berjalan</option>
      <option value="final" <?= ($p['status'] ?? '') === 'final' ? 'selected' : '' ?>>Final (sudah direkap — omzet dibekukan)</option>
    </select>
  </label>
  <?php endif; ?>
  <label class="lebar">Catatan<input type="text" name="catatan" value="<?= h($p['catatan'] ?? '') ?>"></label>
<?php
}
?>
<h1>Omzet &amp; Periode</h1>
<p class="catatan">Batas omzet mengikuti Lampiran A. Bonus A hanya cair bila omzet mencapai Minimum <em>dan</em> laba kas tidak negatif (Pasal 4).
  Omzet <strong>otomatis</strong> diperbarui dari aplikasi utama paling lambat tiap <?= MENIT_SEGAR_OMZET ?> menit selama periode Berjalan, dan dibekukan saat periode ditandai Final.</p>

<?php if ($edit): ?>
<h2>Ubah <?= h(nama_periode($edit)) ?></h2>
<?php if ($edit['omzet_galat']): ?><div class="flash galat"><?= h($edit['omzet_galat']) ?></div><?php endif; ?>
<p class="kecil"><?= h(keterangan_omzet($edit)) ?></p>
<form method="post" class="form-kotak grid-form">
  <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit['id'] ?>">
  <?php form_periode($edit, $sumberAktif); ?>
  <div><button class="tombol">Simpan</button> <a class="tombol sekunder" href="periode.php">Batal</a></div>
</form>
<?php if ($edit['omzet_sumber'] === 'otomatis' && $edit['status'] !== 'final' && $sumberAktif): ?>
<form method="post" style="margin-top:8px"><input type="hidden" name="aksi" value="perbarui_omzet"><input type="hidden" name="id" value="<?= $edit['id'] ?>"><button class="tombol sekunder">Perbarui omzet sekarang</button></form>
<?php endif; ?>
<?php endif; ?>

<div class="gulir">
<table class="tabel">
  <thead><tr><th>Bulan</th><th class="angka">Minimum</th><th class="angka">Target</th><th class="angka">Stretch</th><th class="angka">Omzet</th><th>Laba kas</th><th>Tingkat</th><th class="angka">Bonus A</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($daftar as $p): $a = hitung_bonus_a($p); ?>
    <tr>
      <td><?= h(nama_periode($p)) ?></td>
      <td class="angka"><?= $p['batas_minimum'] === null ? '–' : rupiah($p['batas_minimum']) ?></td>
      <td class="angka"><?= $p['batas_target'] === null ? '–' : rupiah($p['batas_target']) ?></td>
      <td class="angka"><?= $p['batas_stretch'] === null ? '–' : rupiah($p['batas_stretch']) ?></td>
      <td class="angka"><?= $p['omzet'] === null ? '–' : rupiah($p['omzet']) ?>
        <div class="kecil"><?= $p['omzet_sumber'] === 'otomatis' ? ($p['omzet_galat'] ? '<span class="peringatan-teks">otomatis · gagal</span>' : 'otomatis') : 'manual' ?></div></td>
      <td><?= $p['laba_kas_ok'] === null ? '–' : ((int)$p['laba_kas_ok'] === 1 ? '≥ 0' : 'negatif') ?></td>
      <td><?= h($a['tingkat']) ?></td>
      <td class="angka"><?= rupiah($a['bonus']) ?></td>
      <td><?= $p['status'] === 'final' ? '<span class="lencana final">Final</span>' : '<span class="lencana">Berjalan</span>' ?></td>
      <td><a class="tombol kecil" href="?edit=<?= $p['id'] ?>">Ubah</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>Tambah periode</h2>
<form method="post" class="form-kotak grid-form">
  <input type="hidden" name="aksi" value="tambah">
  <label>Bulan<select name="bulan"><?php foreach (NAMA_BULAN as $i => $n): ?><option value="<?= $i ?>" <?= $i === $saranBulan ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
  <label>Tahun<input type="number" name="tahun" value="<?= $saranTahun ?>" min="2026" max="2100"></label>
  <?php form_periode(null, $sumberAktif); ?>
  <div><button class="tombol">Tambah periode</button></div>
</form>
<script>
// Kolom omzet hanya bisa diketik bila sumbernya Manual.
document.querySelectorAll('form').forEach(function (f) {
  const pilih = f.querySelector('[data-pilih-sumber]'), omzet = f.querySelector('[data-omzet]');
  if (!pilih || !omzet) return;
  const atur = () => { const manual = f.querySelector('input[name=omzet_sumber]:checked')?.value === 'manual'; omzet.readOnly = !manual; omzet.classList.toggle('redup', !manual); };
  pilih.addEventListener('change', atur); atur();
});
</script>
<?php tampil_footer();
