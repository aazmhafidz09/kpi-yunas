<?php
/**
 * Omzet otomatis = kartu "Total Pendapatan" di aplikasi utama yunas-studio.com (TransaksiController):
 *   SUM(dp_amount)   untuk transaksi berstatus 'dp'
 * + SUM(total_price) untuk transaksi berstatus 'sudah dibayar'
 * dari tabel `transaksi`, berdasarkan tanggal transaksi DIBUAT (created_at) di bulan tersebut.
 *
 * Keamanan:
 * - Koneksi ke database aplikasi utama dibuat BACA-SAJA (SET SESSION TRANSACTION READ ONLY),
 *   jadi tidak ada perintah yang bisa mengubah data di sana.
 * - Hanya satu kueri penjumlahan; tidak membaca nama pelanggan atau data lain.
 * - Kredensial dibaca dari file konfigurasi di luar folder web (kpi-yunas-sumber-omzet.php).
 *
 * Aturan pembaruan:
 * - Hanya periode berstatus "berjalan" dengan sumber omzet "otomatis", dan bulannya sudah dimulai.
 * - Paling sering sekali tiap MENIT_SEGAR_OMZET menit (atau segera lewat tombol "Perbarui sekarang").
 * - Saat periode diubah menjadi "final", omzet diambil sekali lagi lalu DIBEKUKAN.
 */

const MENIT_SEGAR_OMZET = 10;

function sumber_omzet_aktif(): bool
{
    global $CONFIG;
    $s = $CONFIG['sumber_omzet'] ?? null;
    return is_array($s) && !empty($s['db_name']) && !empty($s['db_user'])
        && isset($s['db_pass']) && !str_starts_with((string)$s['db_pass'], 'GANTI_');
}

/** Koneksi baca-saja ke database aplikasi utama. */
function db_sumber(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $s = $CONFIG['sumber_omzet'];
        $dsn = 'mysql:host=' . ($s['db_host'] ?? 'localhost') . ';dbname=' . $s['db_name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $s['db_user'], $s['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        // Pengaman utama: setiap transaksi di koneksi ini hanya boleh membaca.
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        // Zona waktu sesi sengaja TIDAK diubah, agar DATE(created_at) sama persis dengan yang dilihat aplikasi utama.
    }
    return $pdo;
}

/** Hitung Total Pendapatan satu bulan dari aplikasi utama. */
function hitung_omzet_sumber(int $tahun, int $bulan): array
{
    $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
    $akhir = date('Y-m-t', strtotime($awal));
    $st = db_sumber()->prepare(
        "SELECT COALESCE(SUM(CASE WHEN status = 'sudah dibayar' THEN total_price
                                  WHEN status = 'dp' THEN COALESCE(dp_amount, 0)
                                  ELSE 0 END), 0) AS omzet,
                COALESCE(SUM(status = 'sudah dibayar'), 0) AS lunas,
                COALESCE(SUM(status = 'dp'), 0) AS dp
         FROM transaksi
         WHERE DATE(created_at) BETWEEN ? AND ?");
    $st->execute([$awal, $akhir]);
    $r = $st->fetch();
    return ['omzet' => (int)round((float)$r['omzet']), 'lunas' => (int)$r['lunas'], 'dp' => (int)$r['dp']];
}

/** Ambil ulang satu baris periode langsung dari database (tanpa sinkron, untuk menghindari rekursi). */
function periode_mentah(int $id): ?array
{
    $p = q('SELECT * FROM periode WHERE id = ?', [$id])->fetch();
    return $p ?: null;
}

/**
 * Perbarui omzet periode dari aplikasi utama bila perlu. Mengembalikan baris periode terbaru.
 * Kegagalan koneksi tidak menghentikan halaman: dicatat di omzet_galat, omzet terakhir dipertahankan.
 */
function sinkron_omzet(array $p, bool $paksa = false): array
{
    if (!sumber_omzet_aktif() || ($p['omzet_sumber'] ?? 'manual') !== 'otomatis') return $p;
    if ($p['status'] === 'final' && !$paksa) return $p;
    if (sprintf('%04d-%02d-01', $p['tahun'], $p['bulan']) > date('Y-m-d')) return $p; // bulan belum dimulai
    if (!$paksa && !empty($p['omzet_diperbarui'])
        && strtotime($p['omzet_diperbarui']) > time() - MENIT_SEGAR_OMZET * 60) {
        return $p;
    }
    try {
        $h = hitung_omzet_sumber((int)$p['tahun'], (int)$p['bulan']);
        q('UPDATE periode SET omzet = ?, omzet_diperbarui = NOW(), omzet_info = ?, omzet_galat = NULL WHERE id = ?',
            [$h['omzet'], "{$h['lunas']} transaksi lunas + {$h['dp']} DP", $p['id']]);
    } catch (Throwable $e) {
        error_log('[kpi-yunas] sinkron omzet gagal: ' . $e->getMessage());
        $kode = $e instanceof PDOException ? ' (kode ' . $e->getCode() . ')' : '';
        q('UPDATE periode SET omzet_diperbarui = NOW(), omzet_galat = ? WHERE id = ?',
            ['Gagal membaca Total Pendapatan dari aplikasi utama' . $kode . '. Omzet terakhir dipertahankan.', $p['id']]);
    }
    return periode_mentah((int)$p['id']) ?? $p;
}

/** Keterangan singkat sumber omzet untuk ditampilkan di halaman. */
function keterangan_omzet(array $p): string
{
    if (($p['omzet_sumber'] ?? 'manual') !== 'otomatis') return 'Diisi manual.';
    if (!sumber_omzet_aktif()) return 'Otomatis, tetapi sumber belum diatur (lihat menu Sistem).';
    $t = 'Otomatis dari Total Pendapatan yunas-studio.com';
    if (!empty($p['omzet_info'])) $t .= ' · ' . $p['omzet_info'];
    if (!empty($p['omzet_diperbarui'])) $t .= ' · diperbarui ' . date('j M H:i', strtotime($p['omzet_diperbarui']));
    if ($p['status'] === 'final') $t .= ' · dibekukan (Final)';
    return $t . '.';
}

/** Alasan sumber omzet belum aktif (untuk halaman Sistem), atau null bila sudah lengkap. */
function alasan_sumber_omzet(): ?string
{
    global $CONFIG;
    $s = $CONFIG['sumber_omzet'] ?? null;
    if ($s === null) return 'file kpi-yunas-sumber-omzet.php tidak ditemukan di samping kpi-yunas-config.php';
    if (!empty($s['galat'])) return 'file kpi-yunas-sumber-omzet.php salah tulis (periksa tanda kutip di baris db_pass)';
    if (!is_array($s) || empty($s['db_name']) || empty($s['db_user']) || !isset($s['db_pass'])) return 'isi file kpi-yunas-sumber-omzet.php tidak lengkap';
    if (str_starts_with((string)$s['db_pass'], 'GANTI_')) return 'password masih berupa penanda GANTI_… — isi dengan DB_PASSWORD dari .env aplikasi utama';
    return null;
}

/** Uji koneksi untuk halaman Sistem. */
function uji_sumber_omzet(): array
{
    if (!sumber_omzet_aktif()) return [false, 'belum diatur: ' . alasan_sumber_omzet()];
    try {
        $h = hitung_omzet_sumber((int)date('Y'), (int)date('n'));
        return [true, 'terhubung · bulan ini ' . rupiah($h['omzet'])];
    } catch (Throwable $e) {
        return [false, 'gagal terhubung' . ($e instanceof PDOException ? ' (kode ' . $e->getCode() . ')' : '')];
    }
}
