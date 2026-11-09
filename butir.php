<?php
// Kelola butir checklist: template per jabatan (Lampiran B) ATAU butir khusus satu pegawai di satu bulan.
require __DIR__ . '/lib/bootstrap.php';
wajib_boleh('ubah_butir');

$modePeriode = isset($_GET['periode'], $_GET['pegawai']);
if ($modePeriode) {
    [$periode, $pegawai] = konteks();
    siapkan_butir_periode((int)$periode['id'], (int)$pegawai['id']);
    $kembali = 'butir.php?periode=' . $periode['id'] . '&pegawai=' . $pegawai['id'];
    $judul = 'Butir ' . $pegawai['nama'] . ' — ' . nama_periode($periode);
} else {
    $jabatanList = q('SELECT * FROM jabatan ORDER BY id')->fetchAll();
    $jabatan = null;
    foreach ($jabatanList as $j) {
        if ((int)($_GET['jabatan'] ?? $jabatanList[0]['id']) === (int)$j['id']) $jabatan = $j;
    }
    $jabatan ??= $jabatanList[0];
    $kembali = 'butir.php?jabatan=' . $jabatan['id'];
    $judul = 'Template ' . $jabatan['nama'];
}

function muat_butir(): array
{
    global $modePeriode, $periode, $pegawai, $jabatan;
    return $modePeriode
        ? butir_periode((int)$periode['id'], (int)$pegawai['id'])
        : q('SELECT * FROM template_butir WHERE jabatan_id = ? ORDER BY urutan, id', [$jabatan['id']])->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tabel = $modePeriode ? 'butir_periode' : 'template_butir';
    $idValid = array_map('intval', array_column(muat_butir(), 'id'));
    $db = db();
    $db->beginTransaction();
    foreach (($_POST['b'] ?? []) as $id => $r) {
        $id = (int)$id;
        if (!in_array($id, $idValid, true)) continue;
        if (!empty($r['hapus'])) {
            q("DELETE FROM $tabel WHERE id = ?", [$id]);
            continue;
        }
        q("UPDATE $tabel SET urutan = ?, komponen = ?, butir = ?, poin = ?, frekuensi = ? WHERE id = ?", [
            (int)$r['urutan'], trim($r['komponen']), trim($r['butir']), (float)str_replace(',', '.', $r['poin']),
            $r['frekuensi'] === 'bulanan' ? 'bulanan' : 'harian', $id,
        ]);
    }
    $baru = $_POST['baru'] ?? [];
    if (trim($baru['butir'] ?? '') !== '') {
        $nilai = [(int)$baru['urutan'], trim($baru['komponen']), trim($baru['butir']), (float)str_replace(',', '.', $baru['poin']),
            ($baru['frekuensi'] ?? '') === 'bulanan' ? 'bulanan' : 'harian'];
        if ($modePeriode) {
            q('INSERT INTO butir_periode (periode_id, pegawai_id, urutan, komponen, butir, poin, frekuensi) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$periode['id'], $pegawai['id'], ...$nilai]);
        } else {
            q('INSERT INTO template_butir (jabatan_id, urutan, komponen, butir, poin, frekuensi) VALUES (?, ?, ?, ?, ?, ?)',
                [$jabatan['id'], ...$nilai]);
        }
    }
    $db->commit();
    $total = array_sum(array_map(fn($b) => (float)$b['poin'], muat_butir()));
    flash('Butir tersimpan. Total poin: ' . angka($total, 0) . (abs($total - 100) > 0.001 ? ' — perhatikan, SK mensyaratkan total 100.' : '.'),
        abs($total - 100) > 0.001 ? 'peringatan' : 'ok');
    redirect($kembali);
}

$butir = muat_butir();
$total = array_sum(array_map(fn($b) => (float)$b['poin'], $butir));
$komponenAda = array_values(array_unique(array_column($butir, 'komponen')));

tampil_header($judul, 'butir');
?>
<h1><?= h($judul) ?></h1>
<?php if ($modePeriode): ?>
  <?php pemilih($periode, $pegawai); ?>
  <p class="catatan">Perubahan di sini <strong>hanya berlaku untuk <?= h($pegawai['nama']) ?> di <?= h(nama_periode($periode)) ?></strong>. Menghapus butir juga menghapus isian hariannya.
    Untuk mengubah butir standar semua bulan berikutnya, ubah <a href="butir.php?jabatan=<?= $pegawai['jabatan_id'] ?>">template jabatan</a>.
    <a href="checklist.php?periode=<?= $periode['id'] ?>&pegawai=<?= $pegawai['id'] ?>">← Kembali ke checklist</a></p>
<?php else: ?>
  <nav class="tab">
    <?php foreach ($jabatanList as $j): ?><a class="<?= $j['id'] == $jabatan['id'] ? 'aktif' : '' ?>" href="?jabatan=<?= $j['id'] ?>"><?= h($j['nama']) ?> (<?= h($j['lampiran']) ?>)</a><?php endforeach; ?>
  </nav>
  <p class="catatan">Template dipakai saat checklist seorang pegawai dibuka untuk bulan baru. Bulan yang sudah dibuka tidak ikut berubah.</p>
<?php endif; ?>

<p>Total poin: <strong class="<?= abs($total - 100) > 0.001 ? 'peringatan-teks' : '' ?>"><?= angka($total, 0) ?></strong> / 100</p>

<form method="post">
<datalist id="daftar-komponen"><?php foreach ($komponenAda as $k): ?><option value="<?= h($k) ?>"><?php endforeach; ?></datalist>
<div class="gulir">
<table class="tabel form-tabel">
  <thead><tr><th>Urut</th><th>Komponen</th><th>Butir</th><th>Poin</th><th>Frekuensi</th><th>Hapus</th></tr></thead>
  <tbody>
  <?php foreach ($butir as $b): ?>
    <tr>
      <td><input type="number" name="b[<?= $b['id'] ?>][urutan]" value="<?= (int)$b['urutan'] ?>" class="sempit"></td>
      <td><input type="text" name="b[<?= $b['id'] ?>][komponen]" value="<?= h($b['komponen']) ?>" list="daftar-komponen" required></td>
      <td><input type="text" name="b[<?= $b['id'] ?>][butir]" value="<?= h($b['butir']) ?>" class="lebar-input" required></td>
      <td><input type="number" name="b[<?= $b['id'] ?>][poin]" value="<?= (float)$b['poin'] ?>" step="0.5" min="0" class="sempit" required></td>
      <td><select name="b[<?= $b['id'] ?>][frekuensi]"><option value="harian">Harian</option><option value="bulanan" <?= $b['frekuensi'] === 'bulanan' ? 'selected' : '' ?>>Bulanan</option></select></td>
      <td class="tengah"><input type="checkbox" name="b[<?= $b['id'] ?>][hapus]" value="1"></td>
    </tr>
  <?php endforeach; ?>
    <tr class="baris-baru">
      <td><input type="number" name="baru[urutan]" value="<?= count($butir) + 1 ?>" class="sempit"></td>
      <td><input type="text" name="baru[komponen]" list="daftar-komponen" placeholder="Komponen"></td>
      <td><input type="text" name="baru[butir]" class="lebar-input" placeholder="Butir baru (kosongkan bila tidak menambah)"></td>
      <td><input type="number" name="baru[poin]" step="0.5" min="0" class="sempit"></td>
      <td><select name="baru[frekuensi]"><option value="harian">Harian</option><option value="bulanan">Bulanan</option></select></td>
      <td></td>
    </tr>
  </tbody>
</table>
</div>
<button class="tombol">Simpan butir</button>
</form>
<?php tampil_footer();
