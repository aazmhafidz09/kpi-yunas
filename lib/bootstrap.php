<?php
// Dimuat oleh setiap halaman: koneksi DB, helper, dan logika KPI.
declare(strict_types=1);

// Versi aset dihitung OTOMATIS dari isi file CSS/JS/service worker: begitu salah satunya berubah,
// HP pegawai otomatis mengambil versi baru. Tidak perlu menaikkan nomor versi secara manual.
define('APP_VERSI', (function (): string {
    $akar = __DIR__ . '/../';
    $h = '';
    foreach (['assets/style.css', 'assets/app.js', 'assets/checklist.js', 'sw.js', 'manifest.json', 'offline.html'] as $f) {
        $h .= is_file($akar . $f) ? md5_file($akar . $f) : '';
    }
    return substr(md5($h), 0, 10);
})());

$CONFIG = require __DIR__ . '/../config.php';
// Pengaturan khusus server (mis. hosting) menimpa config.php. Dicari berurutan:
//  1. ../../kpi-yunas-config.php → DUA tingkat di atas folder aplikasi. Untuk subdomain Hostinger yang
//     foldernya public_html/<sub>, ini = folder domain (di luar folder web, tidak menyentuh situs lain).
//  2. ../kpi-yunas-config.php    → SATU tingkat di atas folder aplikasi (bila subdomain punya folder sendiri).
//  3. config.local.php           → di dalam folder aplikasi (praktis di laptop).
// File 1/2 tidak bisa dibuka dari internet dan tidak tersentuh deploy Git.
define('BERKAS_CONFIG_LOKAL', (function (): ?string {
    foreach ([dirname(__DIR__, 3) . '/kpi-yunas-config.php', dirname(__DIR__, 2) . '/kpi-yunas-config.php', dirname(__DIR__) . '/config.local.php'] as $f) {
        if (is_file($f)) return $f;
    }
    return null;
})());
if (BERKAS_CONFIG_LOKAL) {
    // Buang keluaran liar (mis. BOM dari editor Windows) agar tidak merusak header/sesi login.
    ob_start();
    $lokal = require BERKAS_CONFIG_LOKAL;
    ob_end_clean();
    $CONFIG = array_merge($CONFIG, is_array($lokal) ? $lokal : []);
}
date_default_timezone_set($CONFIG['timezone']);

// ---------- Mode produksi: sembunyikan detail error dari pengunjung ----------
if (empty($CONFIG['debug'])) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    set_exception_handler(function (Throwable $e) {
        error_log('[kpi-yunas] ' . $e);
        if (PHP_SAPI === 'cli') { fwrite(STDERR, "Galat: {$e->getMessage()}\n"); exit(1); }
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            header('Content-Type: application/json; charset=utf-8');
            exit('{"ok":false,"pesan":"Terjadi kesalahan di server. Coba lagi sebentar lagi."}');
        }
        exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Terjadi kesalahan</title><body style="font-family:system-ui;padding:24px;max-width:480px;margin:auto">'
            . '<h1>Terjadi kesalahan</h1><p>Maaf, terjadi kesalahan di server. Silakan coba lagi. Bila terus berulang, hubungi Pemilik.</p>'
            . '<p><a href="beranda.php">Kembali ke beranda</a></p></body>');
    });
}

function https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

// ---------- Paksa HTTPS (hosting) ----------
if (PHP_SAPI !== 'cli' && !empty($CONFIG['paksa_https']) && !https()) {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('kpiyunas');
    ini_set('session.gc_maxlifetime', '28800'); // 8 jam; lebih dari itu dipulihkan lewat token "ingat saya"
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Lax', 'path' => '/', 'secure' => https(),
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $dsn = "mysql:host={$CONFIG['db_host']};dbname={$CONFIG['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Samakan jam database dengan WIB (server hosting biasanya UTC).
        $pdo->exec('SET time_zone = ' . $pdo->quote($CONFIG['db_zona_waktu'] ?? '+07:00'));
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function h($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function angka($n, int $desimal = 1): string
{
    if ($n === null) return '–';
    return number_format((float)$n, $desimal, ',', '.');
}

function persen($n): string
{
    return $n === null ? '–' : number_format($n * 100, 1, ',', '.') . '%';
}

const NAMA_BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function nama_periode(array $p): string
{
    return NAMA_BULAN[(int)$p['bulan']] . ' ' . $p['tahun'];
}

function flash(?string $pesan = null, string $jenis = 'ok'): ?array
{
    if ($pesan !== null) {
        $_SESSION['flash'] = ['pesan' => $pesan, 'jenis' => $jenis];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/** Ubah "28.000.000" / "28000000" / "28 jt" menjadi integer rupiah, atau null bila kosong. */
function parse_rupiah($s): ?int
{
    $s = trim((string)$s);
    if ($s === '') return null;
    $s = strtolower($s);
    $kali = 1;
    if (preg_match('/(jt|juta)$/', $s)) {
        $kali = 1000000;
        $s = preg_replace('/(jt|juta)$/', '', $s);
        $s = str_replace(',', '.', trim($s));
        return (int)round((float)$s * $kali);
    }
    return (int)preg_replace('/[^0-9\-]/', '', $s);
}

require __DIR__ . '/kpi.php';
require __DIR__ . '/usulan.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/migrasi.php';

// Terapkan perubahan database yang belum dijalankan (setelah update kode), sebelum halaman apa pun dimuat.
pastikan_migrasi();

// Halaman web: wajib login (kecuali halaman yang mendefinisikan HALAMAN_PUBLIK),
// semua POST wajib membawa token CSRF, dan token disisipkan otomatis ke form.
if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    cek_csrf();
    if (!defined('HALAMAN_PUBLIK')) wajib_login();
    ob_start('sisipkan_csrf');
}
