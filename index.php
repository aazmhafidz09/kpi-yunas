<?php
require __DIR__ . '/lib/bootstrap.php';

[$periode] = konteks(false);
$bonusA = hitung_bonus_a($periode);
$pegawai = daftar_pegawai();
if (!boleh('lihat_semua')) {
    // Peran pegawai hanya melihat dirinya sendiri.
    $idSaya = (int)(pengguna()['pegawai_id'] ?? 0);
    $pegawai = array_values(array_filter($pegawai, fn($p) => (int)$p['id'] === $idSaya));
}
$lihatOmzet = boleh('lihat_omzet');
$hasil = [];
[$awalP, $akhirP] = rentang_periode($periode);
foreach ($pegawai as $pg) {
    $hasil[$pg['id']] = hitung_pegawai($periode, (int)$pg['id']);
    $hasil[$pg['id']]['usulan_menunggu'] = count(array_filter(status_usulan_rentang((int)$pg['id'], $awalP, $akhirP), fn($s) => $s === 'menunggu'));
}

// Ringkasan semua periode (seperti sheet Kalkulator)
$semuaPeriode = daftar_periode();
$ringkas = [];
$totalPer = [];
foreach ($semuaPeriode as $p) {
    $a = hitung_bonus_a($p);
    $row = ['periode' => $p, 'a' => $a, 'pegawai' => []];
    foreach ($pegawai as $pg) {
        $r = hitung_pegawai($p, (int)$pg['id']);
        $tot = $a['bonus'] + $r['bonus_b'];
        $row['pegawai'][$pg['id']] = ['skor' => $r['skor_total'], 'b' => $r['bonus_b'], 'total' => $tot];
        $totalPer[$pg['id']] = ($totalPer[$pg['id']] ?? 0) + $tot;
    }
    $ringkas[] = $row;
}

tampil_header('Rekap Bonus', 'index');
?>
<h1>Rekap Bonus — <?= h(nama_periode($periode)) ?>
  <?php if ($periode['status'] === 'final'): ?><span class="lencana final">Final</span><?php else: ?><span class="lencana">Berjalan</span><?php endif; ?>
</h1>
<?php pemilih($periode, null, false); ?>

<section class="kartu-baris">
  <?php if ($lihatOmzet): ?>
  <div class="kartu">
    <div class="label">Omzet bulan ini</div>
    <div class="nilai"><?= $periode['omzet'] === null ? '–' : rupiah($periode['omzet']) ?></div>
    <div class="sub">Min <?= rupiah($periode['batas_minimum']) ?> · Target <?= rupiah($periode['batas_target']) ?> · Stretch <?= rupiah($periode['batas_stretch']) ?></div>
    <div class="sub"><?= h(keterangan_omzet($periode)) ?></div>
    <?php if ($periode['omzet_galat']): ?><div class="sub peringatan-teks"><?= h($periode['omzet_galat']) ?></div><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="kartu">
    <div class="label">Tingkat omzet</div>
    <div class="nilai"><?= h($bonusA['tingkat']) ?></div>
    <div class="sub">Laba kas: <?= $periode['laba_kas_ok'] === null ? 'belum diisi' : ((int)$periode['laba_kas_ok'] === 1 ? 'tidak negatif ✓' : 'negatif ✗') ?></div>
  </div>
  <div class="kartu aksen">
    <div class="label">Bonus A per pegawai</div>
    <div class="nilai"><?= rupiah($bonusA['bonus']) ?></div>
    <div class="sub"><?= h($bonusA['catatan'] ?? 'Sama untuk semua pegawai.') ?><?php if (boleh('kelola_periode')): ?> <a href="periode.php?edit=<?= $periode['id'] ?>">Ubah omzet</a><?php endif; ?></div>
  </div>
</section>

<table class="tabel tabel-hp">
  <thead>
    <tr>
      <th>Pegawai</th><th>Hari dinilai</th><th class="angka">Skor dasar</th><th class="angka">Apresiasi dihitung</th>
      <th class="angka">Skor total</th><th>Tingkat</th><th class="angka">Bonus A</th><th class="angka">Bonus B</th><th class="angka">Total bonus</th><th>Konfirmasi</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php $grand = 0; foreach ($pegawai as $pg): $r = $hasil[$pg['id']]; $total = $bonusA['bonus'] + $r['bonus_b']; $grand += $total; ?>
    <tr>
      <td><strong><?= h($pg['nama']) ?></strong><div class="kecil"><?= h($pg['jabatan']) ?></div></td>
      <td data-label="Hari dinilai"><?= $r['hari_dinilai'] ?><?php if ($hasil[$pg['id']]['usulan_menunggu'] ?? 0): ?> <span class="kecil">(+<?= $hasil[$pg['id']]['usulan_menunggu'] ?> menunggu)</span><?php endif; ?></td>
      <td data-label="Skor dasar" class="angka"><?= angka($r['skor_dasar'], 2) ?></td>
      <td data-label="Apresiasi dihitung" class="angka"><?= angka($r['apresiasi_dihitung'], 0) ?>
        <?php if ($r['apresiasi_catatan']): ?><div class="kecil peringatan-teks"><?= h($r['apresiasi_catatan']) ?></div><?php endif; ?>
        <?php if ($r['apresiasi_menunggu'] > 0): ?><div class="kecil"><?= angka($r['apresiasi_menunggu'], 0) ?> poin menunggu persetujuan</div><?php endif; ?>
      </td>
      <td data-label="Skor total" class="angka"><strong><?= angka($r['skor_total'], 2) ?></strong></td>
      <td data-label="Tingkat"><span class="tingkat t-<?= strtolower(str_replace(' ', '-', $r['tingkat'])) ?>"><?= h($r['tingkat']) ?></span></td>
      <td data-label="Bonus A" class="angka"><?= rupiah($bonusA['bonus']) ?></td>
      <td data-label="Bonus B" class="angka"><?= rupiah($r['bonus_b']) ?>
        <?php if ((int)$r['status']['gugur_bonus_b'] === 1): ?><div class="kecil peringatan-teks">Gugur (Pasal 5 ayat 6)</div><?php endif; ?>
      </td>
      <td data-label="Total bonus" class="angka"><strong><?= rupiah($total) ?></strong></td>
      <td data-label="Konfirmasi" class="kecil">Manajer <?= $r['status']['konfirmasi_manajer'] ? '✓' : '—' ?><br>Pemilik <?= $r['status']['konfirmasi_pemilik'] ? '✓' : '—' ?></td>
      <td class="aksi">
        <a class="tombol kecil" href="checklist.php?periode=<?= $periode['id'] ?>&pegawai=<?= $pg['id'] ?>">Checklist</a>
        <a class="tombol kecil sekunder" href="apresiasi.php?periode=<?= $periode['id'] ?>&pegawai=<?= $pg['id'] ?>">Apresiasi</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="8"><?= boleh('lihat_semua') ? 'Total bonus seluruh pegawai bulan ini' : 'Total bonus Anda bulan ini' ?></td><td class="angka"><strong><?= rupiah($grand) ?></strong></td><td colspan="2"></td></tr>
  </tfoot>
</table>
<?php if ($periode['status'] !== 'final'): ?>
<p class="catatan">Selama bulan berjalan, angka ini bersifat sementara. Butir bulanan yang belum diisi dihitung 0 sampai diisi di akhir bulan.</p>
<?php endif; ?>

<h2>Ringkasan semua periode</h2>
<div class="gulir">
<table class="tabel ringkas">
  <thead>
    <tr><th>Bulan</th><?php if ($lihatOmzet): ?><th class="angka">Omzet</th><?php endif; ?><th>Tingkat</th><th class="angka">Bonus A</th>
      <?php foreach ($pegawai as $pg): ?><th class="angka"><?= h($pg['nama']) ?><br><span class="kecil">skor · total</span></th><?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($ringkas as $row): $p = $row['periode']; ?>
    <tr class="<?= $p['id'] == $periode['id'] ? 'terpilih' : '' ?>">
      <td><a href="?periode=<?= $p['id'] ?>"><?= h(nama_periode($p)) ?></a></td>
      <?php if ($lihatOmzet): ?><td class="angka"><?= $p['omzet'] === null ? '–' : rupiah($p['omzet']) ?></td><?php endif; ?>
      <td><?= h($row['a']['tingkat']) ?></td>
      <td class="angka"><?= rupiah($row['a']['bonus']) ?></td>
      <?php foreach ($pegawai as $pg): $x = $row['pegawai'][$pg['id']]; ?>
        <td class="angka"><?= angka($x['skor'], 1) ?> · <strong><?= rupiah($x['total']) ?></strong></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="<?= $lihatOmzet ? 4 : 3 ?>">Total</td>
      <?php foreach ($pegawai as $pg): ?><td class="angka"><strong><?= rupiah($totalPer[$pg['id']] ?? 0) ?></strong></td><?php endforeach; ?>
    </tr>
  </tfoot>
</table>
</div>
<?php tampil_footer();
