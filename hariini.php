<?php
// Isi Harian: tampilan per hari yang nyaman di HP.
// - Pegawai: mengisi checklist sendiri sebagai USULAN (menunggu verifikasi).
// - Manajer/Pemilik: memeriksa usulan, mengoreksi, lalu menyimpan nilai final (Pasal 9 ayat 1).
require __DIR__ . '/lib/bootstrap.php';

$saya = pengguna();
$modePegawai = boleh('usul_harian');
$modePenilai = boleh('isi_checklist');
if (!$modePegawai && !$modePenilai) wajib_boleh('isi_checklist');

[, $pegawai] = konteks();
$gid = (int)$pegawai['id'];
$tgl = (string)($_GET['tanggal'] ?? date('Y-m-d'));
if (!tanggal_valid($tgl)) $tgl = date('Y-m-d');
$periode = periode_untuk_tanggal($tgl);
$url = 'hariini.php?' . http_build_query(['pegawai' => $gid, 'tanggal' => $tgl]);

$butirHarian = [];
if ($periode) {
    siapkan_butir_periode((int)$periode['id'], $gid);
    $butirHarian = array_values(array_filter(butir_periode((int)$periode['id'], $gid), fn($b) => $b['frekuensi'] === 'harian'));
}
$ids = array_map(fn($b) => (int)$b['id'], $butirHarian);
$usulan = ambil_usulan($gid, $tgl);
$final = nilai_final($ids, $tgl);

// Apakah halaman ini bisa diisi oleh pengguna sekarang?
$alasanKunci = null;
if (!$periode) {
    $alasanKunci = 'Periode ' . NAMA_BULAN[(int)date('n', strtotime($tgl))] . ' ' . date('Y', strtotime($tgl)) . ' belum dibuat.';
} elseif ($modePegawai) {
    $alasanKunci = cek_boleh_usul($tgl, $periode);
    if (!$alasanKunci && $usulan && $usulan['status'] === 'diterima') $alasanKunci = 'Isian hari ini sudah diverifikasi.';
    if (!$alasanKunci && !$usulan && $final) $alasanKunci = 'Hari ini sudah dinilai langsung oleh Manajer/Pemilik.';
} elseif ($tgl > date('Y-m-d')) {
    $alasanKunci = 'Tanggal belum lewat.';
}
$bisaIsi = $alasanKunci === null;

function hasil_post(bool $ok, string $pesan, string $ke): never
{
    if (minta_json()) {
        if ($ok) flash($pesan); // tampil di halaman tujuan setelah JS berpindah halaman
        json_keluar(['ok' => $ok, 'pesan' => $pesan, 'redirect' => $ok ? $ke : null], $ok ? 200 : 422);
    }
    flash($pesan, $ok ? 'ok' : 'galat');
    redirect($ke);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if (!$bisaIsi) hasil_post(false, $alasanKunci ?? 'Tidak bisa disimpan.', $url);
    $nilai = baca_nilai_form($butirHarian, (array)($_POST['n'] ?? []));

    if ($aksi === 'kirim' && $modePegawai) {
        if (count($nilai) < count($butirHarian)) hasil_post(false, 'Masih ada butir yang belum dijawab.', $url);
        $catatan = mb_substr(trim((string)post('catatan')), 0, 500) ?: null;
        simpan_usulan($gid, $tgl, $nilai, $catatan, (int)$saya['id']);
        hasil_post(true, 'Terkirim. Menunggu verifikasi Manajer/Pemilik.', $url);
    }
    if ($aksi === 'final' && $modePenilai) {
        if (count($nilai) < count($butirHarian)) hasil_post(false, 'Masih ada butir yang belum dijawab.', $url);
        simpan_nilai_final($gid, $tgl, $nilai, $saya['nama']);
        $ke = post('lanjut') === 'verifikasi' ? 'verifikasi.php' : $url;
        hasil_post(true, 'Nilai ' . $pegawai['nama'] . ' untuk ' . nama_hari_tanggal($tgl) . ' tersimpan' . ($usulan && $usulan['status'] !== 'diterima' ? ' dan usulan diterima.' : '.'), $ke);
    }
    if ($aksi === 'tolak' && $modePenilai && $usulan) {
        $alasan = trim((string)post('alasan_tolak'));
        if ($alasan === '') hasil_post(false, 'Tuliskan alasan penolakan agar pegawai bisa memperbaiki.', $url);
        tolak_usulan((int)$usulan['id'], $saya['nama'], mb_substr($alasan, 0, 255));
        hasil_post(true, 'Usulan ditolak. Pegawai bisa memperbaiki dan mengirim ulang.', post('lanjut') === 'verifikasi' ? 'verifikasi.php' : $url);
    }
    hasil_post(false, 'Tindakan tidak dikenal.', $url);
}

// Nilai yang ditampilkan di form
if ($modePenilai) {
    $tampil = [];
    foreach ($ids as $id) {
        if (array_key_exists($id, $final)) $tampil[$id] = (string)$final[$id];
        elseif ($usulan && array_key_exists($id, $usulan['nilai'])) $tampil[$id] = $usulan['nilai'][$id] === null ? 'na' : (string)$usulan['nilai'][$id];
        elseif ($final) $tampil[$id] = 'na'; // hari sudah dinilai, butir ini dikosongkan
    }
} else {
    $sumber = ($usulan && $usulan['status'] === 'diterima') || (!$usulan && $final) ? 'final' : 'usulan';
    $tampil = [];
    foreach ($ids as $id) {
        if ($sumber === 'final') $tampil[$id] = array_key_exists($id, $final) ? (string)$final[$id] : 'na';
        elseif ($usulan && array_key_exists($id, $usulan['nilai'])) $tampil[$id] = $usulan['nilai'][$id] === null ? 'na' : (string)$usulan['nilai'][$id];
    }
}

// Ringkasan bulan
$hasil = $periode ? hitung_pegawai($periode, $gid) : null;
$bonusA = $periode ? hitung_bonus_a($periode) : ['bonus' => 0];
[$awalBln, $akhirBln] = $periode ? rentang_periode($periode) : [$tgl, $tgl];
$statusRentang = status_usulan_rentang($gid, $awalBln, $akhirBln);
$menungguBulan = count(array_filter($statusRentang, fn($s) => $s === 'menunggu'));

// Pilihan tanggal cepat
$pilihanTgl = $modePegawai ? tanggal_boleh_usul() : [];
if ($modePegawai) $statusPilihan = status_usulan_rentang($gid, end($pilihanTgl), $pilihanTgl[0]);

tampil_header('Isi Harian', 'hariini');
$opsi = ['1' => ['✓', 'Ya'], '0' => ['✗', 'Tidak'], 'na' => ['–', 'Tidak berlaku']];
?>
<div class="hp-wadah">
<h1 class="hp-judul"><?= $modePegawai ? 'Isi Harian' : 'Isi Harian — ' . h($pegawai['nama']) ?></h1>

<?php if ($modePenilai): ?>
<form method="get" class="pemilih">
  <label>Pegawai
    <select name="pegawai" onchange="this.form.submit()">
      <?php foreach (daftar_pegawai() as $pg): ?><option value="<?= $pg['id'] ?>" <?= $pg['id'] == $gid ? 'selected' : '' ?>><?= h($pg['nama']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Tanggal <input type="date" name="tanggal" value="<?= h($tgl) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()"></label>
</form>
<?php else: ?>
<nav class="pilih-tanggal" aria-label="Pilih tanggal">
  <?php foreach ($pilihanTgl as $i => $t): $st = $statusPilihan[$t] ?? null; ?>
    <a href="?tanggal=<?= $t ?>" class="<?= $t === $tgl ? 'aktif' : '' ?>">
      <span class="ph-hari"><?= $i === 0 ? 'Hari ini' : ($i === 1 ? 'Kemarin' : h(substr(nama_hari_tanggal($t), 0, 3))) ?></span>
      <span class="ph-tgl"><?= (int)date('j', strtotime($t)) ?></span>
      <span class="titik s-<?= $st ?? 'kosong' ?>" title="<?= h(label_status_usulan($st)) ?>"></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<div class="hp-kepala">
  <div><strong><?= h(nama_hari_tanggal($tgl)) ?></strong> <?= $tgl === date('Y-m-d') ? '<span class="kecil">(hari ini)</span>' : '' ?></div>
  <?php if ($usulan): ?>
    <span class="status-usulan s-<?= $usulan['status'] ?>"><?= h(label_status_usulan($usulan['status'])) ?></span>
  <?php elseif ($final): ?>
    <span class="status-usulan s-diterima">Sudah dinilai</span>
  <?php else: ?>
    <span class="status-usulan s-kosong">Belum diisi</span>
  <?php endif; ?>
</div>

<?php if ($usulan && $usulan['status'] === 'ditolak'): ?>
  <div class="flash galat">Ditolak oleh <?= h($usulan['diproses_oleh']) ?>: <?= h($usulan['alasan_tolak']) ?><?= $modePegawai ? ' Silakan perbaiki lalu kirim ulang.' : '' ?></div>
<?php elseif ($usulan && $usulan['status'] === 'diterima'): ?>
  <p class="kecil">Diverifikasi oleh <?= h($usulan['diproses_oleh']) ?> pada <?= h(date('j M H:i', strtotime($usulan['diproses_pada']))) ?>. Nilai di bawah adalah nilai final.</p>
<?php endif; ?>
<?php if ($modePenilai && $usulan): ?>
  <p class="kecil">Usulan dikirim <?= h(date('j M H:i', strtotime($usulan['dikirim_pada']))) ?>. Label <span class="tanda-usulan">usulan</span> menunjukkan jawaban pegawai bila berbeda dengan pilihan saat ini.</p>
<?php endif; ?>
<?php if ($alasanKunci && !($usulan && $usulan['status'] === 'diterima')): ?>
  <div class="flash peringatan"><?= h($alasanKunci) ?></div>
<?php endif; ?>

<?php if ($butirHarian): ?>
<form method="post" action="<?= h($url) ?>" class="form-harian" data-fetch="1">
  <?php if ($bisaIsi): ?>
  <div class="baris-alat">
    <span class="progres" data-progres>0/<?= count($butirHarian) ?> terjawab</span>
    <button type="button" class="tombol kecil sekunder" data-semua="1">Tandai semua Ya</button>
  </div>
  <?php endif; ?>

  <?php $komponenSebelum = null; foreach ($butirHarian as $b): $id = (int)$b['id']; $v = $tampil[$id] ?? null;
      if ($b['komponen'] !== $komponenSebelum): $komponenSebelum = $b['komponen']; ?>
    <h2 class="hp-komponen"><?= h($b['komponen']) ?></h2>
  <?php endif; ?>
    <fieldset class="butir-kartu <?= $v === null ? '' : 'terjawab' ?>" <?= $bisaIsi ? '' : 'disabled' ?>>
      <legend><?= h($b['butir']) ?> <span class="poin"><?= angka($b['poin'], 0) ?> poin</span></legend>
      <div class="segmen">
        <?php foreach ($opsi as $nilaiOpsi => [$ikon, $label]): $nilaiOpsi = (string)$nilaiOpsi; ?>
          <label class="opsi o-<?= $nilaiOpsi ?>">
            <input type="radio" name="n[<?= $id ?>]" value="<?= $nilaiOpsi ?>" <?= $v === $nilaiOpsi ? 'checked' : '' ?> <?= $bisaIsi ? 'required' : '' ?>>
            <span><b><?= $ikon ?></b> <?= $label ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if ($modePenilai && $usulan && array_key_exists($id, $usulan['nilai'])):
          $uv = $usulan['nilai'][$id] === null ? 'na' : (string)$usulan['nilai'][$id];
          if ($uv !== $v): ?>
        <div class="tanda-usulan">usulan pegawai: <?= $opsi[$uv][0] ?> <?= $opsi[$uv][1] ?></div>
      <?php endif; endif; ?>
    </fieldset>
  <?php endforeach; ?>

  <?php if ($modePegawai): ?>
    <label class="hp-catatan">Catatan untuk Manajer (opsional)
      <textarea name="catatan" rows="2" maxlength="500" <?= $bisaIsi ? '' : 'disabled' ?> placeholder="mis. izin terlambat karena hujan deras"><?= h($usulan['catatan'] ?? '') ?></textarea>
    </label>
  <?php elseif ($usulan && $usulan['catatan']): ?>
    <div class="hp-catatan-baca"><strong>Catatan pegawai:</strong> <?= h($usulan['catatan']) ?></div>
  <?php endif; ?>

  <?php if ($bisaIsi): ?>
  <div class="tombol-lekat">
    <div class="pesan-form" data-pesan hidden></div>
    <?php if ($modePegawai): ?>
      <input type="hidden" name="aksi" value="kirim">
      <button class="tombol besar"><?= $usulan ? 'Kirim ulang' : 'Kirim untuk diverifikasi' ?></button>
    <?php else: ?>
      <input type="hidden" name="aksi" value="final">
      <?php if (isset($_GET['dari'])): ?><input type="hidden" name="lanjut" value="verifikasi"><?php endif; ?>
      <button class="tombol besar"><?= $usulan && $usulan['status'] === 'menunggu' ? 'Simpan & terima usulan' : 'Simpan nilai final' ?></button>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</form>

<?php if ($modePenilai && $usulan && $usulan['status'] === 'menunggu'): ?>
<details class="tolak-usulan">
  <summary>Tolak usulan ini</summary>
  <form method="post" action="<?= h($url) ?>" class="form-kotak">
    <input type="hidden" name="aksi" value="tolak">
    <?php if (isset($_GET['dari'])): ?><input type="hidden" name="lanjut" value="verifikasi"><?php endif; ?>
    <label>Alasan (akan dilihat pegawai)<input type="text" name="alasan_tolak" maxlength="255" required></label>
    <button class="tombol bahaya">Tolak usulan</button>
  </form>
</details>
<?php endif; ?>
<?php elseif ($periode): ?>
  <p class="redup">Tidak ada butir harian untuk pegawai ini.</p>
<?php endif; ?>

<?php if ($hasil): ?>
<section class="kartu-baris hp-ringkas">
  <div class="kartu"><div class="label"><?= h(nama_periode($periode)) ?> · skor dasar</div><div class="nilai"><?= angka($hasil['skor_dasar'], 1) ?></div><div class="sub"><?= $hasil['hari_dinilai'] ?> hari terverifikasi<?= $menungguBulan ? " · $menungguBulan hari menunggu" : '' ?></div></div>
  <div class="kartu aksen"><div class="label">Perkiraan bonus bulan ini</div><div class="nilai"><?= rupiah($bonusA['bonus'] + $hasil['bonus_b']) ?></div><div class="sub">Bonus A <?= rupiah($bonusA['bonus']) ?> + Bonus B <?= rupiah($hasil['bonus_b']) ?> (sementara)</div></div>
</section>
<p class="kecil">Skor hanya dihitung dari isian yang sudah diverifikasi. <a href="checklist.php?periode=<?= $periode['id'] ?>&pegawai=<?= $gid ?>">Lihat checklist sebulan</a> · <a href="apresiasi.php?periode=<?= $periode['id'] ?>&pegawai=<?= $gid ?>"><?= $modePegawai ? 'Usulkan poin apresiasi' : 'Log apresiasi' ?></a></p>
<?php endif; ?>
</div>
<?php tampil_footer();
