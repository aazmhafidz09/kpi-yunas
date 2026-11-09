<?php
/**
 * Migrasi database otomatis.
 *
 * Setiap perubahan struktur database = satu file di lib/migrasi/, diberi nomor urut:
 *   lib/migrasi/004_tambah_sesuatu.php  →  return function (PDO $db): void { ... };
 * Saat kode baru terpasang (laptop atau hosting), migrasi yang belum pernah dijalankan
 * akan dijalankan otomatis SEKALI dan dicatat di tabel `migrasi`.
 *
 * Aturan menulis migrasi:
 *  - Jangan mengubah file migrasi yang sudah pernah dijalankan; buat file baru.
 *  - Buat agar aman diulang (pakai tabel_ada / kolom_ada / CREATE TABLE IF NOT EXISTS),
 *    karena perintah DDL MySQL tidak bisa di-rollback.
 */

const FOLDER_MIGRASI = __DIR__ . '/migrasi';

function tabel_ada(string $tabel): bool
{
    return (bool)q('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$tabel])->fetchColumn();
}

function kolom_ada(string $tabel, string $kolom): bool
{
    return (bool)q('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$tabel, $kolom])->fetchColumn();
}

function indeks_ada(string $tabel, string $indeks): bool
{
    return (bool)q('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$tabel, $indeks])->fetchColumn();
}

/** [nama => path] semua file migrasi, urut nama. */
function migrasi_berkas(): array
{
    $hasil = [];
    foreach (glob(FOLDER_MIGRASI . '/*.php') ?: [] as $f) $hasil[basename($f, '.php')] = $f;
    ksort($hasil, SORT_STRING);
    return $hasil;
}

/** [nama => waktu] migrasi yang sudah dijalankan. */
function migrasi_sudah(): array
{
    if (!tabel_ada('migrasi')) return [];
    $hasil = [];
    foreach (q('SELECT nama, dijalankan_pada FROM migrasi') as $r) $hasil[$r['nama']] = $r['dijalankan_pada'];
    return $hasil;
}

function migrasi_tertunda(): array
{
    return array_diff_key(migrasi_berkas(), migrasi_sudah());
}

/**
 * Jalankan semua migrasi tertunda. Dikunci dengan GET_LOCK agar dua permintaan bersamaan
 * tidak menjalankan migrasi yang sama. Mengembalikan daftar nama yang dijalankan.
 */
function jalankan_migrasi(): array
{
    q('CREATE TABLE IF NOT EXISTS migrasi (
         nama VARCHAR(150) PRIMARY KEY,
         dijalankan_pada DATETIME NOT NULL
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $kunci = 'kpi_migrasi_' . q('SELECT DATABASE()')->fetchColumn(); // unik per database (server hosting dipakai bersama)
    if (!(int)q('SELECT GET_LOCK(?, 30)', [$kunci])->fetchColumn()) {
        throw new RuntimeException('Pembaruan database sedang dijalankan proses lain.');
    }
    $dijalankan = [];
    try {
        foreach (migrasi_tertunda() as $nama => $path) { // dicek ulang setelah memegang kunci
            $fn = require $path;
            if (!is_callable($fn)) throw new RuntimeException("Migrasi $nama tidak mengembalikan function.");
            $fn(db());
            q('INSERT INTO migrasi (nama, dijalankan_pada) VALUES (?, NOW())', [$nama]);
            $dijalankan[] = $nama;
        }
    } finally {
        q('SELECT RELEASE_LOCK(?)', [$kunci]);
    }
    return $dijalankan;
}

/** Dipanggil di setiap permintaan: jalankan migrasi tertunda bila ada (biasanya tidak ada → 2 query ringan). */
function pastikan_migrasi(): void
{
    global $CONFIG;
    try {
        if (migrasi_tertunda()) {
            $nama = jalankan_migrasi();
            if ($nama) error_log('[kpi-yunas] migrasi dijalankan: ' . implode(', ', $nama));
        }
    } catch (Throwable $e) {
        error_log('[kpi-yunas] migrasi gagal: ' . $e);
        if (!empty($CONFIG['debug']) || PHP_SAPI === 'cli') throw $e;
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(503);
        header('Retry-After: 30');
        exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Sedang diperbarui</title><body style="font-family:system-ui;padding:24px;max-width:480px;margin:auto">'
            . '<h1>Sedang diperbarui</h1><p>Aplikasi sedang menerapkan pembaruan. Coba lagi dalam satu menit. '
            . 'Bila pesan ini terus muncul, Pemilik perlu memeriksa log error di hosting.</p></body>');
    }
}

/** Info rilis yang terpasang: dari rilis.txt (paket zip) atau dari Git (deploy Git). */
function info_rilis(): string
{
    $akar = __DIR__ . '/..';
    if (is_file("$akar/rilis.txt")) return trim((string)file_get_contents("$akar/rilis.txt"));
    if (is_file("$akar/.git/HEAD")) {
        $head = trim((string)file_get_contents("$akar/.git/HEAD"));
        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            $hash = is_file("$akar/.git/$ref") ? trim((string)file_get_contents("$akar/.git/$ref")) : '';
            return 'Git ' . basename($ref) . ($hash ? ' @ ' . substr($hash, 0, 7) : '');
        }
        return 'Git @ ' . substr($head, 0, 7);
    }
    return 'versi pengembangan (laptop)';
}
