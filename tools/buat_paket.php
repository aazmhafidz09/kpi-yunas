<?php
/**
 * Membuat paket unggah ke hosting:
 *   dist/kpi-yunas-aplikasi-<waktu>.zip   -> diunggah & diekstrak di folder situs (menimpa file lama)
 *   dist/kpi-yunas-data-<waktu>.sql       -> HANYA pemasangan pertama (opsi --dengan-data),
 *                                            diimpor lewat phpMyAdmin (berisi data & akun, RAHASIAKAN)
 *
 * Pemakaian (dari folder kpi-yunas):
 *   Update biasa     : C:\xampp\php\php.exe -d extension=zip tools\buat_paket.php
 *   Pemasangan awal  : C:\xampp\php\php.exe -d extension=zip tools\buat_paket.php --dengan-data
 *
 * Zip tidak pernah berisi config.local.php, jadi mengekstraknya di server tidak menimpa
 * pengaturan database hosting. Perubahan database ikut di lib/migrasi/ dan berjalan otomatis.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI saja.'); }
require __DIR__ . '/../lib/bootstrap.php';

$akar = realpath(__DIR__ . '/..');
$dist = $akar . DIRECTORY_SEPARATOR . 'dist';
if (!is_dir($dist)) mkdir($dist, 0777, true);
file_put_contents("$dist/.htaccess", "Require all denied\n"); // jangan pernah tersaji lewat web
$waktu = date('Ymd-Hi');

// ---------- 1. Zip aplikasi ----------
// Tidak ikut: alat & SQL (tidak perlu di server), dokumentasi, konfigurasi lokal, file uji, hasil paket.
$kecuali = ['#^dist/#', '#^tools/#', '#^database/#', '#^config\.local\.php$#', '#^_uji#', '#^\.git/#', '#^\.git#', '#\.md$#', '#^\.claude/#', '#^rilis\.txt$#'];
$denganData = in_array('--dengan-data', $argv, true);

// Cap rilis: tanggal + commit Git (bila folder ini repositori Git), tampil di halaman Sistem.
$commit = trim((string)@shell_exec('git -C ' . escapeshellarg($akar) . ' rev-parse --short HEAD 2>NUL'));
$kotor = $commit !== '' && trim((string)@shell_exec('git -C ' . escapeshellarg($akar) . ' status --porcelain 2>NUL')) !== '';
$rilis = 'Paket ' . date('Y-m-d H:i') . ($commit ? " · commit $commit" . ($kotor ? ' (+perubahan belum di-commit)' : '') : '');
$zipPath = "$dist/kpi-yunas-aplikasi-$waktu.zip";
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) exit("Gagal membuat zip\n");
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS));
$daftar = [];
foreach ($it as $f) {
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($akar) + 1)); // pemisah "/" agar benar di Linux
    foreach ($kecuali as $pola) if (preg_match($pola, $rel)) continue 2;
    $zip->addFile($f->getPathname(), $rel);
    $daftar[] = $rel;
}
$zip->addFromString('rilis.txt', $rilis . "\n");
$zip->close();
sort($daftar);
printf("Zip aplikasi : %s (%d file, %s KB)\n", basename($zipPath), count($daftar) + 1, number_format(filesize($zipPath) / 1024, 1));
printf("Rilis        : %s\n", $rilis);
$tertunda = array_keys(migrasi_berkas());
printf("Migrasi ikut : %s (dijalankan otomatis di server bila belum)\n", implode(', ', $tertunda));

if (!$denganData) {
    echo "\nUpdate: unggah zip ke folder situs di hosting, ekstrak (timpa file lama), selesai.\n";
    echo "Lokasi: $dist\n";
    exit(0);
}

// ---------- 2. Salinan data (pemasangan pertama saja) ----------
global $CONFIG;
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$sqlPath = "$dist/kpi-yunas-data-$waktu.sql";
$umum = sprintf('--host=%s --user=%s %s --default-character-set=utf8mb4 --single-transaction --skip-comments --no-tablespaces',
    escapeshellarg($CONFIG['db_host']), escapeshellarg($CONFIG['db_user']),
    $CONFIG['db_pass'] !== '' ? '--password=' . escapeshellarg($CONFIG['db_pass']) : '');
$db = escapeshellarg($CONFIG['db_name']);
// Token login & catatan login gagal: hanya strukturnya (sesi di laptop tidak dibawa ke server).
$tanpaData = ['token_ingat', 'gagal_login'];
$abaikan = implode(' ', array_map(fn($t) => '--ignore-table=' . escapeshellarg($CONFIG['db_name'] . '.' . $t), $tanpaData));
$isi = "-- Data KPI & Bonus Yunas Studio, diekspor " . date('Y-m-d H:i') . " WIB\n"
     . "-- Impor ke database KOSONG lewat phpMyAdmin. Berisi akun & hash password: jangan dibagikan.\n"
     . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
foreach (["$umum $abaikan $db", "$umum --no-data $db " . implode(' ', $tanpaData)] as $arg) {
    $keluaran = [];
    exec('"' . $mysqldump . '" ' . $arg . ' 2>&1', $keluaran, $kode);
    if ($kode !== 0) exit("mysqldump gagal:\n" . implode("\n", $keluaran) . "\n");
    $isi .= implode("\n", $keluaran) . "\n";
}
$isi .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
if (preg_match('/^\s*(CREATE DATABASE|USE )/mi', $isi)) exit("Dump berisi CREATE DATABASE/USE, periksa opsi mysqldump\n");
file_put_contents($sqlPath, $isi);
printf("Data SQL     : %s (%s KB)\n", basename($sqlPath), number_format(filesize($sqlPath) / 1024, 1));

// Ringkasan isi data
echo "\nIsi data:\n";
foreach (q("SELECT table_name t, table_rows n FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name") as $r) {
    $n = in_array($r['t'], $tanpaData, true) ? '(struktur saja)' : (int)q("SELECT COUNT(*) FROM `{$r['t']}`")->fetchColumn() . ' baris';
    printf("  %-18s %s\n", $r['t'], $n);
}
echo "\nLokasi: $dist\n";
