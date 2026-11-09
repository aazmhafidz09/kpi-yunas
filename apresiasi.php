<?php
// Log apresiasi (Pasal 7-8). Pegawai bisa MENGUSULKAN; hanya Pemilik/Manajer yang menyetujui/menolak.
require __DIR__ . '/lib/bootstrap.php';

[$periode, $pegawai] = konteks();
$pid = (int)$periode['id'];
$gid = (int)$pegawai['id'];
$kembali = "apresiasi.php?periode=$pid&pegawai=$gid";
[$awal, $akhir] = rentang_periode($periode);
$saya = pengguna();
$bisaKelola = boleh('kelola_apresiasi');
$bisaUsul = boleh('usul_apresiasi') && $periode['status'] !== 'final';
// Penyetuju = nama akun Pemilik/Manajer yang sedang login (Pasal 7 ayat 3).
$namaPenyetuju = $saya['nama'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bisaKelola && !$bisaUsul) wajib_boleh('kelola_apresiasi');
    $aksi = post('aksi');
    $id = (int)post('id');

    if ($aksi === 'tambah') {
        $tanggal = (string)post('tanggal');
        $kegiatan = mb_substr(trim((string)post('kegiatan')), 0, 255);
        $poin = (float)str_replace(',', '.', (string)post('poin'));
        $disetujui = $bisaKelola && post('disetujui') ? 1 : 0;
        $batasAkhir = $bisaKelola ? $akhir : min($akhir, date('Y-m-d'));
        if ($kegiatan === '' || $poin <= 0 || $poin > 50 || !tanggal_valid($tanggal) || $tanggal < $awal || $tanggal > $batasAkhir) {
            flash('Isi tanggal (dalam bulan ' . nama_periode($periode) . ', tidak di masa depan), kegiatan, dan poin 0–50.', 'galat');
        } else {
            q('INSERT INTO apresiasi (pegawai_id, tanggal, kegiatan, poin, disetujui, penyetuju, alasan, diusulkan_oleh) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$gid, $tanggal, $kegiatan, $poin, $disetujui, $disetujui ? $namaPenyetuju : null,
                    mb_substr(trim((string)post('alasan')), 0, 255) ?: null, $bisaKelola ? null : (int)$saya['id']]);
            flash($bisaKelola
                ? 'Catatan apresiasi ditambahkan' . ($disetujui ? ' dan disetujui.' : '. Belum dihitung sampai disetujui.')
                : 'Usulan apresiasi terkirim. Poin dihitung setelah disetujui Pemilik/Manajer.');
        }
        redirect($kembali);
    }
    if ($aksi === 'hapus') {
        if ($bisaKelola) {
            q('DELETE FROM apresiasi WHERE id = ? AND pegawai_id = ?', [$id, $gid]);
        } else {
            // Pegawai hanya boleh menarik usulannya sendiri yang belum diproses.
            q('DELETE FROM apresiasi WHERE id = ? AND pegawai_id = ? AND diusulkan_oleh = ? AND disetujui = 0 AND ditolak = 0', [$id, $gid, $saya['id']]);
        }
        flash('Catatan apresiasi dihapus.');
        redirect($kembali);
    }
    wajib_boleh('kelola_apresiasi');
    if ($aksi === 'setujui') {
        q('UPDATE apresiasi SET disetujui = 1, ditolak = 0, alasan_tolak = NULL, penyetuju = ? WHERE id = ? AND pegawai_id = ?', [$namaPenyetuju, $id, $gid]);
        flash('Poin apresiasi disetujui oleh ' . $namaPenyetuju . '.');
    } elseif ($aksi === 'tolak') {
        $alasan = trim((string)post('alasan_tolak'));
        if ($alasan === '') {
            flash('Tuliskan alasan penolakan.', 'galat');
        } else {
            q('UPDATE apresiasi SET disetujui = 0, ditolak = 1, alasan_tolak = ?, penyetuju = ? WHERE id = ? AND pegawai_id = ?', [mb_substr($alasan, 0, 255), $namaPenyetuju, $id, $gid]);
            flash('Usulan apresiasi ditolak.');
        }
    } elseif ($aksi === 'batal') {
        q('UPDATE apresiasi SET disetujui = 0, ditolak = 0, alasan_tolak = NULL, penyetuju = NULL WHERE id = ? AND pegawai_id = ?', [$id, $gid]);
        flash('Status dikembalikan ke menunggu.');
    }
    redirect($kembali);
}

$log = q('SELECT * FROM apresiasi WHERE pegawai_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal, id', [$gid, $awal, $akhir])->fetchAll();
$jenis = q('SELECT * FROM jenis_apresiasi ORDER BY id')->fetchAll();
$hasil = hitung_pegawai($periode, $gid);
$hariIni = date('Y-m-d');
$tglDefault = ($hariIni >= $awal && $hariIni <= $akhir) ? $hariIni : $awal;

tampil_header('Apresiasi ' . $pegawai['nama'], 'apresiasi');
?>
<h1>Log Apresiasi<?= boleh('lihat_semua') ? ' — ' . h($pegawai['nama']) : '' ?> <span class="redup"><?= h(nama_periode($periode)) ?></span></h1>
<?php pemilih($periode, $pegawai); ?>

<section class="kartu-baris">
  <div class="kartu"><div class="label">Poin disetujui</div><div class="nilai"><?= angka($hasil['apresiasi_disetujui'], 0) ?></div><div class="sub">Menunggu persetujuan: <?= angka($hasil['apresiasi_menunggu'], 0) ?></div></div>
  <div class="kartu"><div class="label">Skor dasar</div><div class="nilai"><?= angka($hasil['skor_dasar'], 2) ?></div><div class="sub">Syarat apresiasi dihitung: ≥ <?= angka(cfg('apresiasi_syarat'), 0) ?></div></div>
  <div class="kartu aksen"><div class="label">Poin dihitung ke skor total</div><div class="nilai"><?= angka($hasil['apresiasi_dihitung'], 0) ?></div><div class="sub"><?= h($hasil['apresiasi_catatan'] ?? 'Maksimal ' . angka(cfg('apresiasi_maks'), 0) . ' poin per bulan.') ?></div></div>
</section>

<table class="tabel tabel-hp">
  <thead><tr><th>Tanggal</th><th>Kegiatan di luar job desk</th><th class="angka">Poin</th><th>Status</th><th>Penyetuju</th><th></th></tr></thead>
  <tbody>
  <?php if (!$log): ?><tr><td colspan="6" class="redup">Belum ada catatan apresiasi bulan ini.</td></tr><?php endif; ?>
  <?php foreach ($log as $r):
      $menunggu = !$r['disetujui'] && !$r['ditolak'];
      $milikSaya = !$bisaKelola && (int)$r['diusulkan_oleh'] === (int)$saya['id']; ?>
    <tr>
      <td data-label="Tanggal"><?= h(date('j M', strtotime($r['tanggal']))) ?></td>
      <td data-label="Kegiatan"><?= h($r['kegiatan']) ?><?php if ($r['alasan']): ?><div class="kecil"><?= h($r['alasan']) ?></div><?php endif; ?>
        <?php if ($r['diusulkan_oleh']): ?><div class="kecil">diusulkan pegawai</div><?php endif; ?></td>
      <td data-label="Poin" class="angka"><?= angka($r['poin'], 0) ?></td>
      <td data-label="Status">
        <?php if ($r['disetujui']): ?><span class="tingkat t-atas">Disetujui</span>
        <?php elseif ($r['ditolak']): ?><span class="tingkat t-di-bawah">Ditolak</span><?php if ($r['alasan_tolak']): ?><div class="kecil"><?= h($r['alasan_tolak']) ?></div><?php endif; ?>
        <?php else: ?><span class="tingkat t-tengah">Menunggu</span><?php endif; ?>
      </td>
      <td data-label="Penyetuju"><?= h($r['penyetuju'] ?? '–') ?></td>
      <td class="aksi">
        <?php if ($bisaKelola): ?>
          <?php if ($menunggu): ?>
            <form method="post" class="sebaris"><input type="hidden" name="aksi" value="setujui"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="tombol kecil">Setujui</button></form>
            <details class="sebaris-detail"><summary class="tombol kecil sekunder">Tolak…</summary>
              <form method="post" class="sebaris"><input type="hidden" name="aksi" value="tolak"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="text" name="alasan_tolak" placeholder="Alasan" required maxlength="255"><button class="tombol kecil bahaya">Tolak</button></form>
            </details>
          <?php else: ?>
            <form method="post" class="sebaris"><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="tombol kecil sekunder">Batalkan</button></form>
          <?php endif; ?>
          <form method="post" class="sebaris" onsubmit="return confirm('Hapus catatan ini?')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="tombol kecil bahaya">Hapus</button></form>
        <?php elseif ($milikSaya && $menunggu && $bisaUsul): ?>
          <form method="post" class="sebaris" onsubmit="return confirm('Tarik usulan ini?')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="tombol kecil sekunder">Tarik usulan</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($bisaKelola || $bisaUsul): ?>
<h2><?= $bisaKelola ? 'Tambah catatan' : 'Usulkan poin apresiasi' ?></h2>
<?php if (!$bisaKelola): ?><p class="catatan">Untuk pekerjaan nyata di luar job desk (Lampiran C). Poin baru dihitung setelah disetujui Pemilik/Manajer, dan hanya bila skor dasar minimal <?= angka(cfg('apresiasi_syarat'), 0) ?> (Pasal 8).</p><?php endif; ?>
<form method="post" class="form-kotak grid-form">
  <input type="hidden" name="aksi" value="tambah">
  <label>Contoh kegiatan (Lampiran C)
    <select id="pilih-jenis">
      <option value="">— pilih untuk mengisi otomatis —</option>
      <?php foreach ($jenis as $j): ?><option data-poin="<?= (float)$j['poin'] ?>"><?= h($j['kegiatan']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Tanggal<input type="date" name="tanggal" value="<?= $tglDefault ?>" min="<?= $awal ?>" max="<?= $bisaKelola ? $akhir : min($akhir, $hariIni) ?>" required></label>
  <label class="lebar">Kegiatan<input type="text" name="kegiatan" id="kegiatan" maxlength="255" required></label>
  <label>Poin<input type="number" name="poin" id="poin" min="0.5" max="50" step="0.5" required></label>
  <?php if ($bisaKelola): ?><label class="cek"><input type="checkbox" name="disetujui" value="1"> Langsung disetujui oleh saya (<?= h($namaPenyetuju) ?>)</label><?php endif; ?>
  <label class="lebar"><?= $bisaKelola ? 'Alasan tertulis (wajib bila kegiatan di luar daftar Lampiran C)' : 'Keterangan (siapa yang meminta, bukti, dsb.)' ?><input type="text" name="alasan" maxlength="255"></label>
  <div><button class="tombol"><?= $bisaKelola ? 'Tambah' : 'Kirim usulan' ?></button></div>
</form>
<script>
document.getElementById('pilih-jenis').addEventListener('change', function () {
  const o = this.selectedOptions[0];
  if (!o.value && !o.dataset.poin) return;
  document.getElementById('kegiatan').value = o.textContent;
  document.getElementById('poin').value = o.dataset.poin;
});
</script>
<?php endif; ?>
<?php tampil_footer();
