<?php
// Antrian verifikasi untuk Manajer/Pemilik: isian harian & usulan apresiasi dari pegawai.
require __DIR__ . '/lib/bootstrap.php';
wajib_boleh('isi_checklist');
$saya = pengguna();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'terima') {
        flash(terima_usulan((int)post('id'), $saya['nama']) ? 'Usulan diterima.' : 'Usulan sudah diproses sebelumnya.');
    } elseif ($aksi === 'terima_semua') {
        $n = 0;
        foreach ((array)($_POST['ids'] ?? []) as $id) $n += terima_usulan((int)$id, $saya['nama']) ? 1 : 0;
        flash("$n usulan harian diterima.");
    } elseif ($aksi === 'setujui_apr') {
        wajib_boleh('kelola_apresiasi');
        q('UPDATE apresiasi SET disetujui = 1, ditolak = 0, alasan_tolak = NULL, penyetuju = ? WHERE id = ?', [$saya['nama'], (int)post('id')]);
        flash('Poin apresiasi disetujui.');
    } elseif ($aksi === 'tolak_apr') {
        wajib_boleh('kelola_apresiasi');
        $alasan = trim((string)post('alasan'));
        if ($alasan === '') {
            flash('Tuliskan alasan penolakan.', 'galat');
        } else {
            q('UPDATE apresiasi SET ditolak = 1, disetujui = 0, alasan_tolak = ?, penyetuju = ? WHERE id = ?', [mb_substr($alasan, 0, 255), $saya['nama'], (int)post('id')]);
            flash('Usulan apresiasi ditolak.');
        }
    }
    redirect('verifikasi.php');
}

$usulan = q('SELECT u.*, p.nama AS pegawai, j.nama AS jabatan,
               SUM(b.nilai = 1) AS ya, SUM(b.nilai = 0) AS tidak, SUM(b.nilai IS NULL AND b.usulan_id IS NOT NULL) AS na
             FROM usulan_hari u
             JOIN pegawai p ON p.id = u.pegawai_id JOIN jabatan j ON j.id = p.jabatan_id
             LEFT JOIN usulan_butir b ON b.usulan_id = u.id
             WHERE u.status = ?
             GROUP BY u.id ORDER BY u.tanggal, p.nama', ['menunggu'])->fetchAll();
$apresiasi = q('SELECT a.*, p.nama AS pegawai FROM apresiasi a JOIN pegawai p ON p.id = a.pegawai_id
                WHERE a.disetujui = 0 AND a.ditolak = 0 ORDER BY a.tanggal, a.id')->fetchAll();
$riwayat = q('SELECT u.*, p.nama AS pegawai FROM usulan_hari u JOIN pegawai p ON p.id = u.pegawai_id
              WHERE u.status <> ? ORDER BY u.diproses_pada DESC LIMIT 10', ['menunggu'])->fetchAll();

tampil_header('Verifikasi', 'verifikasi');
?>
<div class="hp-wadah lebar">
<h1>Verifikasi</h1>
<p class="catatan">Isian pegawai baru dihitung ke skor setelah diterima di sini (Pasal 9 ayat 1). <strong>Periksa</strong> untuk melihat dan mengoreksi per butir sebelum menerima.</p>

<h2>Isian harian menunggu (<?= count($usulan) ?>)</h2>
<?php if (!$usulan): ?>
  <p class="redup">Tidak ada isian yang menunggu. 🎉</p>
<?php else: ?>
<form method="post" id="form-semua">
  <input type="hidden" name="aksi" value="terima_semua">
  <?php foreach ($usulan as $u): ?><input type="hidden" name="ids[]" value="<?= $u['id'] ?>"><?php endforeach; ?>
</form>
<div class="daftar-kartu">
  <?php foreach ($usulan as $u): ?>
  <div class="kartu verif">
    <div class="verif-kepala">
      <div><strong><?= h($u['pegawai']) ?></strong> <span class="kecil"><?= h($u['jabatan']) ?></span></div>
      <div class="kecil"><?= h(nama_hari_tanggal($u['tanggal'])) ?></div>
    </div>
    <div class="verif-ringkas">
      <span class="chip ya">✓ <?= (int)$u['ya'] ?></span>
      <span class="chip tidak">✗ <?= (int)$u['tidak'] ?></span>
      <span class="chip na">– <?= (int)$u['na'] ?></span>
      <span class="kecil">dikirim <?= h(date('j M H:i', strtotime($u['dikirim_pada']))) ?></span>
    </div>
    <?php if ($u['catatan']): ?><div class="hp-catatan-baca">“<?= h($u['catatan']) ?>”</div><?php endif; ?>
    <div class="verif-aksi">
      <a class="tombol sekunder" href="hariini.php?<?= h(http_build_query(['pegawai' => $u['pegawai_id'], 'tanggal' => $u['tanggal'], 'dari' => 'verifikasi'])) ?>">Periksa</a>
      <form method="post" class="sebaris"><input type="hidden" name="aksi" value="terima"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="tombol">Terima</button></form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php if (count($usulan) > 1): ?>
  <p><button class="tombol" form="form-semua" onclick="return confirm('Terima semua <?= count($usulan) ?> isian apa adanya?')">Terima semua (<?= count($usulan) ?>)</button></p>
<?php endif; ?>
<?php endif; ?>

<h2>Usulan apresiasi menunggu (<?= count($apresiasi) ?>)</h2>
<?php if (!$apresiasi): ?>
  <p class="redup">Tidak ada usulan apresiasi yang menunggu.</p>
<?php else: ?>
<div class="daftar-kartu">
  <?php foreach ($apresiasi as $a): ?>
  <div class="kartu verif">
    <div class="verif-kepala">
      <div><strong><?= h($a['pegawai']) ?></strong> · <?= angka($a['poin'], 0) ?> poin</div>
      <div class="kecil"><?= h(nama_hari_tanggal($a['tanggal'])) ?></div>
    </div>
    <div><?= h($a['kegiatan']) ?></div>
    <?php if ($a['alasan']): ?><div class="kecil"><?= h($a['alasan']) ?></div><?php endif; ?>
    <div class="verif-aksi">
      <form method="post" class="sebaris"><input type="hidden" name="aksi" value="setujui_apr"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="tombol">Setujui</button></form>
      <details class="sebaris-detail">
        <summary class="tombol sekunder">Tolak…</summary>
        <form method="post" class="sebaris"><input type="hidden" name="aksi" value="tolak_apr"><input type="hidden" name="id" value="<?= $a['id'] ?>">
          <input type="text" name="alasan" placeholder="Alasan" required maxlength="255"><button class="tombol bahaya">Tolak</button></form>
      </details>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($riwayat): ?>
<h2>Baru diproses</h2>
<table class="tabel tabel-hp">
  <thead><tr><th>Pegawai</th><th>Tanggal</th><th>Status</th><th>Oleh</th><th>Waktu</th></tr></thead>
  <tbody>
  <?php foreach ($riwayat as $r): ?>
    <tr>
      <td data-label="Pegawai"><?= h($r['pegawai']) ?></td>
      <td data-label="Tanggal"><a href="hariini.php?<?= h(http_build_query(['pegawai' => $r['pegawai_id'], 'tanggal' => $r['tanggal']])) ?>"><?= h(nama_hari_tanggal($r['tanggal'])) ?></a></td>
      <td data-label="Status"><span class="status-usulan s-<?= $r['status'] ?>"><?= h(label_status_usulan($r['status'])) ?></span><?php if ($r['alasan_tolak']): ?><div class="kecil"><?= h($r['alasan_tolak']) ?></div><?php endif; ?></td>
      <td data-label="Oleh"><?= h($r['diproses_oleh']) ?></td>
      <td data-label="Waktu" class="kecil"><?= h(date('j M H:i', strtotime($r['diproses_pada']))) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php tampil_footer();
