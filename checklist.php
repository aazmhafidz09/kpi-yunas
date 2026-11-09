<?php
require __DIR__ . '/lib/bootstrap.php';

[$periode, $pegawai] = konteks();
$pid = (int)$periode['id'];
$gid = (int)$pegawai['id'];
siapkan_butir_periode($pid, $gid);
$kembali = "checklist.php?periode=$pid&pegawai=$gid";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $butir = butir_periode($pid, $gid);
    $idValid = array_column($butir, 'id');
    $nHari = jumlah_hari($periode);

    if ($aksi === 'simpan_checklist') {
        wajib_boleh('isi_checklist');
        $db = db();
        $db->beginTransaction();
        $c = $_POST['c'] ?? [];
        $ins = $db->prepare('INSERT INTO checklist_harian (butir_periode_id, tanggal, nilai) VALUES (?, ?, ?)');
        foreach ($idValid as $bid) {
            q('DELETE FROM checklist_harian WHERE butir_periode_id = ?', [$bid]);
            foreach (($c[$bid] ?? []) as $hari => $nilai) {
                $hari = (int)$hari;
                if ($nilai === '' || $hari < 1 || $hari > $nHari) continue;
                $tgl = sprintf('%04d-%02d-%02d', $periode['tahun'], $periode['bulan'], $hari);
                $ins->execute([$bid, $tgl, $nilai === '1' ? 1 : 0]);
            }
        }
        foreach (($_POST['nb'] ?? []) as $bid => $nilai) {
            if (!in_array((int)$bid, array_map('intval', $idValid), true)) continue;
            q('UPDATE butir_periode SET nilai_bulanan = ? WHERE id = ?', [$nilai === '' ? null : (int)$nilai, (int)$bid]);
        }
        $db->commit();
        flash('Checklist ' . $pegawai['nama'] . ' tersimpan.');
        redirect($kembali);
    }

    if ($aksi === 'simpan_status') {
        // Setiap kolom hanya bisa diubah oleh peran yang berwenang; sisanya dipertahankan.
        $lama = rekap_status($pid, $gid);
        $bolehGugur = boleh('gugurkan_bonus');
        if (!$bolehGugur && !boleh('konfirmasi_manajer') && !boleh('konfirmasi_pemilik')) wajib_boleh('gugurkan_bonus');
        q('INSERT INTO rekap_pegawai (periode_id, pegawai_id, gugur_bonus_b, alasan_gugur, konfirmasi_manajer, konfirmasi_pemilik)
           VALUES (?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE gugur_bonus_b = VALUES(gugur_bonus_b), alasan_gugur = VALUES(alasan_gugur),
             konfirmasi_manajer = VALUES(konfirmasi_manajer), konfirmasi_pemilik = VALUES(konfirmasi_pemilik)', [
            $pid, $gid,
            $bolehGugur ? (post('gugur') ? 1 : 0) : (int)$lama['gugur_bonus_b'],
            $bolehGugur ? (trim((string)post('alasan_gugur', '')) ?: null) : $lama['alasan_gugur'],
            boleh('konfirmasi_manajer') ? (post('konf_manajer') ? 1 : 0) : (int)$lama['konfirmasi_manajer'],
            boleh('konfirmasi_pemilik') ? (post('konf_pemilik') ? 1 : 0) : (int)$lama['konfirmasi_pemilik'],
        ]);
        flash('Status bulanan tersimpan.');
        redirect($kembali . '#status');
    }
}

$hasil = hitung_pegawai($periode, $gid);
$butir = $hasil['butir'];
$nHari = jumlah_hari($periode);

// Isi grid
$grid = [];
if ($butir) {
    $ids = array_column($butir, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (q("SELECT butir_periode_id, DAY(tanggal) hari, nilai FROM checklist_harian WHERE butir_periode_id IN ($in)", $ids) as $r) {
        $grid[$r['butir_periode_id']][(int)$r['hari']] = (int)$r['nilai'];
    }
}
$hariIni = ((int)date('Y') === (int)$periode['tahun'] && (int)date('n') === (int)$periode['bulan']) ? (int)date('j') : 0;
$namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$bonusA = hitung_bonus_a($periode);
$bisaIsi = boleh('isi_checklist');
[$awalBln, $akhirBln] = rentang_periode($periode);
$statusUsulan = status_usulan_rentang($gid, $awalBln, $akhirBln);
$bisaStatus = boleh('gugurkan_bonus') || boleh('konfirmasi_manajer') || boleh('konfirmasi_pemilik');
$s = $hasil['status'];

tampil_header('Checklist ' . $pegawai['nama'], 'checklist');
?>
<h1>Checklist Harian — <?= h($pegawai['nama']) ?> <span class="redup"><?= h(nama_periode($periode)) ?></span></h1>
<?php pemilih($periode, $pegawai); ?>

<section class="kartu-baris">
  <div class="kartu"><div class="label">Hari dinilai</div><div class="nilai"><?= $hasil['hari_dinilai'] ?></div><div class="sub">dari <?= $nHari ?> hari kalender</div></div>
  <div class="kartu"><div class="label">Skor dasar</div><div class="nilai"><?= angka($hasil['skor_dasar'], 2) ?></div><div class="sub">Total poin butir: <?= angka($hasil['total_poin'], 0) ?><?= abs($hasil['total_poin'] - 100) > 0.001 ? ' ⚠ seharusnya 100' : '' ?></div></div>
  <div class="kartu"><div class="label">Poin apresiasi dihitung</div><div class="nilai"><?= angka($hasil['apresiasi_dihitung'], 0) ?></div><div class="sub"><?= h($hasil['apresiasi_catatan'] ?? 'Disetujui: ' . angka($hasil['apresiasi_disetujui'], 0) . ' poin') ?> · <a href="apresiasi.php?periode=<?= $pid ?>&pegawai=<?= $gid ?>">Log apresiasi</a></div></div>
  <div class="kartu aksen"><div class="label">Skor total → Bonus B</div><div class="nilai"><?= angka($hasil['skor_total'], 2) ?> → <?= rupiah($hasil['bonus_b']) ?></div><div class="sub">Tingkat <?= h($hasil['tingkat']) ?> · + Bonus A <?= rupiah($bonusA['bonus']) ?> = <strong><?= rupiah($bonusA['bonus'] + $hasil['bonus_b']) ?></strong></div></div>
</section>

<?php if (!$bisaIsi): ?>
<p class="petunjuk">Tampilan baca saja. Checklist diisi oleh Manajer atau Pemilik (Pasal 9). Keberatan atas penilaian disampaikan ke Pemilik paling lambat 3 hari kerja setelah hasil bulanan diumumkan.</p>
<?php else: ?>
<p class="petunjuk">Klik sel untuk berganti: <span class="sel-contoh v1">✓</span> terpenuhi (1) → <span class="sel-contoh v0">✗</span> tidak (0) → kosong (libur / tidak dinilai). Klik <strong>nomor tanggal</strong> untuk mengisi ✓ satu kolom sekaligus (klik lagi untuk mengosongkan). Jangan lupa <strong>Simpan</strong>.
  Titik di bawah tanggal = ada isian dari pegawai (<span class="titik s-menunggu"></span> menunggu verifikasi; klik titiknya untuk memeriksa).</p>
<?php endif; ?>

<form method="post" id="<?= $bisaIsi ? 'form-checklist' : 'form-baca' ?>">
  <input type="hidden" name="aksi" value="simpan_checklist">
  <div class="gulir">
  <table class="grid">
    <thead>
      <tr>
        <th class="kol-butir">Butir checklist</th><th>Poin</th>
        <?php for ($d = 1; $d <= $nHari; $d++):
            $w = (int)date('w', mktime(0, 0, 0, (int)$periode['bulan'], $d, (int)$periode['tahun']));
            $tglD = sprintf('%04d-%02d-%02d', $periode['tahun'], $periode['bulan'], $d);
            $su = $statusUsulan[$tglD] ?? null; ?>
          <th class="hari <?= $bisaIsi ? 'bisa-klik' : '' ?> <?= $w === 0 ? 'minggu' : '' ?> <?= $d === $hariIni ? 'hari-ini' : '' ?>" data-hari="<?= $d ?>" <?= $bisaIsi ? 'title="Isi/kosongkan kolom tanggal ' . $d . '"' : '' ?>><span class="nh"><?= $namaHari[$w] ?></span><?= $d ?><?php if ($su): ?><a class="titik s-<?= $su ?>" href="hariini.php?<?= h(http_build_query(['pegawai' => $gid, 'tanggal' => $tglD])) ?>" title="Isian pegawai: <?= h(label_status_usulan($su)) ?>"></a><?php endif; ?></th>
        <?php endfor; ?>
        <th>Bulanan</th><th class="angka">% terpenuhi</th>
      </tr>
    </thead>
    <tbody>
    <?php $komponenSebelum = null; foreach ($butir as $b): $det = $hasil['detail'][$b['id']];
        if ($b['komponen'] !== $komponenSebelum): $komponenSebelum = $b['komponen']; $k = $hasil['komponen'][$b['komponen']]; ?>
      <tr class="baris-komponen"><td colspan="<?= $nHari + 4 ?>"><?= h($b['komponen']) ?> <span class="redup">— <?= angka($k['diperoleh'], 1) ?> / <?= angka($k['maks'], 0) ?> poin</span></td></tr>
    <?php endif; ?>
      <tr>
        <td class="kol-butir"><?= h($b['butir']) ?></td>
        <td class="tengah"><?= angka($b['poin'], 0) ?></td>
        <?php if ($b['frekuensi'] === 'harian'): ?>
          <?php for ($d = 1; $d <= $nHari; $d++): $v = $grid[$b['id']][$d] ?? ''; ?>
            <?php if ($bisaIsi): ?>
            <td class="sel v<?= $v ?> <?= $d === $hariIni ? 'hari-ini' : '' ?>" data-hari="<?= $d ?>"><input type="hidden" name="c[<?= $b['id'] ?>][<?= $d ?>]" value="<?= $v ?>"><?= $v === 1 ? '✓' : ($v === 0 ? '✗' : '') ?></td>
            <?php else: ?>
            <td class="sel-baca v<?= $v ?> <?= $d === $hariIni ? 'hari-ini' : '' ?>"><?= $v === 1 ? '✓' : ($v === 0 ? '✗' : '') ?></td>
            <?php endif; ?>
          <?php endfor; ?>
          <td class="tengah redup">harian</td>
        <?php else: ?>
          <td colspan="<?= $nHari ?>" class="redup bulanan-info">Butir bulanan, diisi sekali di akhir bulan →</td>
          <td class="tengah">
            <?php if (!$bisaIsi): ?>
              <?= $b['nilai_bulanan'] === null ? '–' : ((int)$b['nilai_bulanan'] === 1 ? '✓' : '✗') ?>
            <?php else: ?>
            <select name="nb[<?= $b['id'] ?>]">
              <option value="" <?= $b['nilai_bulanan'] === null ? 'selected' : '' ?>>–</option>
              <option value="1" <?= $b['nilai_bulanan'] !== null && (int)$b['nilai_bulanan'] === 1 ? 'selected' : '' ?>>✓ 1</option>
              <option value="0" <?= $b['nilai_bulanan'] !== null && (int)$b['nilai_bulanan'] === 0 ? 'selected' : '' ?>>✗ 0</option>
            </select>
            <?php endif; ?>
          </td>
        <?php endif; ?>
        <td class="angka"><?= persen($det['pct']) ?><?php if ($det['dinilai'] !== null): ?><div class="kecil"><?= $det['terpenuhi'] ?>/<?= $det['dinilai'] ?></div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="aksi-form">
    <?php if ($bisaIsi): ?><button class="tombol" type="submit">Simpan checklist</button><?php endif; ?>
    <?php if (boleh('ubah_butir')): ?><a class="tombol sekunder" href="butir.php?periode=<?= $pid ?>&pegawai=<?= $gid ?>">Ubah butir bulan ini</a><?php endif; ?>
    <button class="tombol sekunder" type="button" onclick="window.print()">Cetak</button>
    <span id="belum-simpan" class="peringatan-teks" hidden>Ada perubahan yang belum disimpan.</span>
  </div>
</form>

<h2 id="status">Status &amp; konfirmasi bulan ini</h2>
<?php if ($bisaStatus): $dis = fn($hak) => boleh($hak) ? '' : 'disabled'; ?>
<form method="post" class="form-kotak">
  <input type="hidden" name="aksi" value="simpan_status">
  <label class="cek"><input type="checkbox" name="konf_manajer" value="1" <?= $s['konfirmasi_manajer'] ? 'checked' : '' ?> <?= $dis('konfirmasi_manajer') ?>> Skor dasar dikonfirmasi Manajer</label>
  <label class="cek"><input type="checkbox" name="konf_pemilik" value="1" <?= $s['konfirmasi_pemilik'] ? 'checked' : '' ?> <?= $dis('konfirmasi_pemilik') ?>> Skor dasar dikonfirmasi Pemilik</label>
  <label class="cek bahaya"><input type="checkbox" name="gugur" value="1" <?= $s['gugur_bonus_b'] ? 'checked' : '' ?> <?= $dis('gugurkan_bonus') ?>> Tidak berhak Bonus B bulan ini (pelanggaran berat / alpha berulang, Pasal 5 ayat 6, diputuskan Pemilik)</label>
  <label>Alasan (bila gugur)<input type="text" name="alasan_gugur" value="<?= h($s['alasan_gugur']) ?>" <?= $dis('gugurkan_bonus') ?>></label>
  <button class="tombol" type="submit">Simpan status</button>
</form>
<?php else: ?>
<div class="form-kotak">
  <div>Konfirmasi Manajer: <strong><?= $s['konfirmasi_manajer'] ? 'Sudah ✓' : 'Belum' ?></strong></div>
  <div>Konfirmasi Pemilik: <strong><?= $s['konfirmasi_pemilik'] ? 'Sudah ✓' : 'Belum' ?></strong></div>
  <?php if ($s['gugur_bonus_b']): ?><div class="peringatan-teks">Tidak berhak Bonus B bulan ini<?= $s['alasan_gugur'] ? ': ' . h($s['alasan_gugur']) : '' ?></div><?php endif; ?>
</div>
<?php endif; ?>

<script src="assets/checklist.js?v=<?= APP_VERSI ?>"></script>
<?php tampil_footer();
