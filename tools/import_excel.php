<?php
/**
 * Impor satu sheet checklist harian dari file Excel KPI lama ke database.
 *
 * Pemakaian (dari folder kpi-yunas):
 *   C:\xampp\php\php.exe -d extension=zip tools\import_excel.php "<file.xlsx>" "<nama sheet>" "<nama pegawai>" <YYYY-MM> [--apresiasi]
 * Contoh:
 *   C:\xampp\php\php.exe -d extension=zip tools\import_excel.php "C:\...\KPI REZA.xlsx" "Reza Nov26" Reza 2026-11
 *
 * - Butir checklist pegawai di bulan itu DIGANTI dengan butir dari sheet (supaya butir khusus ikut terbawa).
 * - Isian harian (kolom tanggal 1–31) dan nilai bulanan ikut diimpor.
 * - Log apresiasi hanya diimpor bila diberi opsi --apresiasi.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI saja.'); }
require __DIR__ . '/../lib/bootstrap.php';

[$_, $file, $sheet, $namaPegawai, $ym] = array_pad($argv, 5, null);
$denganApresiasi = in_array('--apresiasi', $argv, true);
if (!$file || !$sheet || !$namaPegawai || !preg_match('/^(\d{4})-(\d{2})$/', (string)$ym, $m)) {
    fwrite(STDERR, "Pemakaian: php -d extension=zip tools/import_excel.php <file.xlsx> <sheet> <pegawai> <YYYY-MM> [--apresiasi]\n");
    exit(1);
}
[$tahun, $bulan] = [(int)$m[1], (int)$m[2]];

function kolom_ke_indeks(string $kol): int
{
    $n = 0;
    foreach (str_split($kol) as $c) $n = $n * 26 + (ord($c) - 64);
    return $n;
}

function baca_sheet(string $file, string $namaSheet): array
{
    $z = new ZipArchive();
    if ($z->open($file) !== true) throw new RuntimeException("Tidak bisa membuka $file");
    $shared = [];
    if (($s = $z->getFromName('xl/sharedStrings.xml')) !== false) {
        foreach (simplexml_load_string($s)->si as $si) {
            $t = isset($si->t) ? (string)$si->t : '';
            if (!isset($si->t)) foreach ($si->r as $r) $t .= (string)$r->t;
            $shared[] = $t;
        }
    }
    $wb = simplexml_load_string($z->getFromName('xl/workbook.xml'));
    $rels = [];
    foreach (simplexml_load_string($z->getFromName('xl/_rels/workbook.xml.rels'))->Relationship as $r) {
        $rels[(string)$r['Id']] = (string)$r['Target'];
    }
    $path = null;
    foreach ($wb->sheets->sheet as $sh) {
        if ((string)$sh['name'] === $namaSheet) {
            $path = 'xl/' . ltrim(str_replace('/xl/', '', $rels[(string)$sh->attributes('r', true)['id']]), '/');
        }
    }
    if (!$path) throw new RuntimeException("Sheet '$namaSheet' tidak ditemukan");
    $rows = [];
    foreach (simplexml_load_string($z->getFromName($path))->sheetData->row as $row) {
        foreach ($row->c as $c) {
            preg_match('/^([A-Z]+)(\d+)$/', (string)$c['r'], $mm);
            $v = (string)$c->v;
            if ((string)$c['t'] === 's') $v = $shared[(int)$v] ?? '';
            elseif ((string)$c['t'] === 'inlineStr') $v = (string)$c->is->t;
            if ($v === '') continue;
            $rows[(int)$mm[2]][kolom_ke_indeks($mm[1])] = $v;
        }
    }
    return $rows;
}

$pegawai = q('SELECT * FROM pegawai WHERE nama = ?', [$namaPegawai])->fetch();
if (!$pegawai) { fwrite(STDERR, "Pegawai '$namaPegawai' tidak ada di database.\n"); exit(1); }
$periode = q('SELECT * FROM periode WHERE tahun = ? AND bulan = ?', [$tahun, $bulan])->fetch();
if (!$periode) { fwrite(STDERR, "Periode $ym belum ada di database.\n"); exit(1); }

$rows = baca_sheet($file, $sheet);
$nHari = jumlah_hari($periode);
// Kolom: A=1 komponen, B=2 butir, C=3 poin, D=4 frekuensi, E=5 nilai bulanan, F..AJ = tanggal 1..31
$butir = [];
$apresiasi = [];
$diLog = false;
ksort($rows);
foreach ($rows as $r => $cells) {
    $b = trim($cells[2] ?? '');
    if (stripos(trim($cells[1] ?? ''), 'LOG APRESIASI') === 0) { $diLog = true; continue; }
    if ($diLog) {
        $tgl = trim($cells[1] ?? '');
        if ($tgl === '' || $tgl === 'Tanggal' || $b === '' || !isset($cells[3]) || !is_numeric($cells[3])) continue;
        $apresiasi[] = ['tgl' => $cells[1] ?? '', 'kegiatan' => $b, 'poin' => (float)$cells[3],
            'disetujui' => (int)($cells[4] ?? 0), 'penyetuju' => $cells[5] ?? null];
        continue;
    }
    if ($r < 5 || $b === '' || !isset($cells[3]) || !is_numeric($cells[3]) || !isset($cells[4])) continue;
    $hari = [];
    for ($d = 1; $d <= 31; $d++) {
        if (isset($cells[5 + $d]) && is_numeric($cells[5 + $d])) $hari[$d] = (int)round((float)$cells[5 + $d]);
    }
    $butir[] = [
        'komponen' => trim($cells[1] ?? ''), 'butir' => $b, 'poin' => (float)$cells[3],
        'frekuensi' => strtolower(trim($cells[4])) === 'bulanan' ? 'bulanan' : 'harian',
        'nilai_bulanan' => isset($cells[5]) && is_numeric($cells[5]) ? (int)round((float)$cells[5]) : null,
        'hari' => $hari,
    ];
}
if (!$butir) { fwrite(STDERR, "Tidak ada butir checklist terbaca dari sheet.\n"); exit(1); }

$db = db();
$db->beginTransaction();
q('DELETE FROM butir_periode WHERE periode_id = ? AND pegawai_id = ?', [$periode['id'], $pegawai['id']]);
$insB = $db->prepare('INSERT INTO butir_periode (periode_id, pegawai_id, urutan, komponen, butir, poin, frekuensi, nilai_bulanan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$insH = $db->prepare('INSERT INTO checklist_harian (butir_periode_id, tanggal, nilai) VALUES (?, ?, ?)');
$jmlIsian = 0;
foreach ($butir as $i => $b) {
    $insB->execute([$periode['id'], $pegawai['id'], $i + 1, $b['komponen'], $b['butir'], $b['poin'], $b['frekuensi'],
        $b['frekuensi'] === 'bulanan' ? $b['nilai_bulanan'] : null]);
    $bid = (int)$db->lastInsertId();
    if ($b['frekuensi'] !== 'harian') continue;
    foreach ($b['hari'] as $d => $v) {
        if ($d > $nHari) continue;
        $insH->execute([$bid, sprintf('%04d-%02d-%02d', $tahun, $bulan, $d), $v ? 1 : 0]);
        $jmlIsian++;
    }
}
$jmlApr = 0;
if ($denganApresiasi) {
    foreach ($apresiasi as $a) {
        $hari = (int)preg_replace('/\D.*/', '', trim($a['tgl'])) ?: 1;
        q('INSERT INTO apresiasi (pegawai_id, tanggal, kegiatan, poin, disetujui, penyetuju) VALUES (?, ?, ?, ?, ?, ?)',
            [$pegawai['id'], sprintf('%04d-%02d-%02d', $tahun, $bulan, min($hari, $nHari)), $a['kegiatan'], $a['poin'], $a['disetujui'] ? 1 : 0, $a['penyetuju']]);
        $jmlApr++;
    }
}
$db->commit();

$h = hitung_pegawai($periode, (int)$pegawai['id']);
printf("OK: %s %s — %d butir (total poin %s), %d isian harian, %d hari dinilai, %d apresiasi diimpor%s.\n",
    $pegawai['nama'], nama_periode($periode), count($butir), angka($h['total_poin'], 0), $jmlIsian, $h['hari_dinilai'],
    $jmlApr, $denganApresiasi ? '' : ' (log apresiasi dilewati; ' . count($apresiasi) . ' baris di sheet)');
printf("    Skor dasar: %s\n", angka($h['skor_dasar'], 2));
