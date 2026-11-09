<?php
// Login, peran, hak akses, dan perlindungan CSRF.

const PERAN_LABEL = ['pemilik' => 'Pemilik', 'manajer' => 'Manajer', 'pegawai' => 'Pegawai'];

/**
 * Hak akses per peran (mengacu pada SK):
 * - Pasal 9 ayat 1: checklist diisi Manajer atau Pemilik
 * - Pasal 7 ayat 3: apresiasi disetujui Pemilik atau Manajer
 * - Pasal 5 ayat 6: gugur Bonus B diputuskan Pemilik
 * - Pasal 9 ayat 3: pegawai berhak melihat checklist & log apresiasinya sendiri
 */
const HAK_AKSES = [
    'lihat_semua'        => ['pemilik', 'manajer'],
    'lihat_omzet'        => ['pemilik', 'manajer'],
    'isi_checklist'      => ['pemilik', 'manajer'],
    'kelola_apresiasi'   => ['pemilik', 'manajer'],
    'kelola_periode'     => ['pemilik', 'manajer'],
    'konfirmasi_manajer' => ['manajer'],
    'konfirmasi_pemilik' => ['pemilik'],
    'gugurkan_bonus'     => ['pemilik'],
    'ubah_butir'         => ['pemilik'],
    'kelola_pegawai'     => ['pemilik'],
    'kelola_pengguna'    => ['pemilik'],
    'pengaturan'         => ['pemilik'],
    // Isian mandiri pegawai: berstatus usulan sampai diverifikasi Manajer/Pemilik.
    'usul_harian'        => ['pegawai'],
    'usul_apresiasi'     => ['pegawai'],
];

const COOKIE_INGAT = 'kpiyunas_ingat';
const HARI_INGAT = 60;

function pengguna(): ?array
{
    static $cache = false;
    if ($cache === false) {
        $cache = null;
        if (empty($_SESSION['pengguna_id'])) {
            masuk_dengan_token();
        }
        if (!empty($_SESSION['pengguna_id'])) {
            $u = q('SELECT u.*, p.nama AS nama_pegawai FROM pengguna u LEFT JOIN pegawai p ON p.id = u.pegawai_id
                    WHERE u.id = ? AND u.aktif = 1', [$_SESSION['pengguna_id']])->fetch();
            $cache = $u ?: null;
            if (!$u) unset($_SESSION['pengguna_id']);
        }
    }
    return $cache;
}

function boleh(string $hak): bool
{
    $u = pengguna();
    return $u !== null && in_array($u['peran'], HAK_AKSES[$hak] ?? [], true);
}

function wajib_boleh(string $hak): void
{
    if (!boleh($hak)) {
        http_response_code(403);
        flash('Anda tidak punya akses untuk tindakan itu.', 'galat');
        redirect('index.php');
    }
}

function ada_pengguna(): bool
{
    return (int)q('SELECT COUNT(*) FROM pengguna')->fetchColumn() > 0;
}

function wajib_login(): void
{
    if (pengguna()) return;
    if (!ada_pengguna()) redirect('setup.php');
    $tujuan = $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_SERVER['REQUEST_URI'] ?? '') : '';
    redirect('login.php' . ($tujuan ? '?ke=' . urlencode($tujuan) : ''));
}

function masuk(array $u, bool $ingat = false): void
{
    session_regenerate_id(true);
    $_SESSION['pengguna_id'] = (int)$u['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE pengguna SET login_terakhir = NOW() WHERE id = ?', [$u['id']]);
    if ($ingat) buat_token_ingat((int)$u['id']);
}

// ---------- "Ingat saya" (token selector:validator, validator disimpan sebagai hash) ----------

function set_cookie_ingat(string $nilai, int $kedaluwarsa): void
{
    setcookie(COOKIE_INGAT, $nilai, [
        'expires' => $kedaluwarsa, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => https(),
    ]);
}

function buat_token_ingat(int $penggunaId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $exp = time() + HARI_INGAT * 86400;
    q('INSERT INTO token_ingat (pengguna_id, selector, validator_hash, kedaluwarsa) VALUES (?, ?, ?, FROM_UNIXTIME(?))',
        [$penggunaId, $selector, hash('sha256', $validator), $exp]);
    set_cookie_ingat("$selector:$validator", $exp);
}

/** Login otomatis dari cookie "ingat saya"; token dirotasi setiap kali dipakai. */
function masuk_dengan_token(): void
{
    $c = $_COOKIE[COOKIE_INGAT] ?? '';
    if (!is_string($c) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $c, $m)) return;
    $t = q('SELECT t.*, u.aktif FROM token_ingat t JOIN pengguna u ON u.id = t.pengguna_id
            WHERE t.selector = ? AND t.kedaluwarsa > NOW()', [$m[1]])->fetch();
    // Selector tidak ditemukan: bisa karena dua permintaan bersamaan sudah merotasinya; abaikan saja.
    if (!$t) return;
    if (!(int)$t['aktif'] || !hash_equals($t['validator_hash'], hash('sha256', $m[2]))) {
        q('DELETE FROM token_ingat WHERE id = ?', [$t['id']]); // validator salah / akun nonaktif: buang token
        set_cookie_ingat('', time() - 3600);
        return;
    }
    q('DELETE FROM token_ingat WHERE id = ?', [$t['id']]);
    masuk(['id' => $t['pengguna_id']], true);
}

/** Cabut semua sesi "ingat saya" seorang pengguna (ganti/reset password, dinonaktifkan). */
function cabut_token_ingat(int $penggunaId): void
{
    q('DELETE FROM token_ingat WHERE pengguna_id = ?', [$penggunaId]);
}

function keluar(): void
{
    $c = $_COOKIE[COOKIE_INGAT] ?? '';
    if (is_string($c) && preg_match('/^([a-f0-9]{24}):/', $c, $m)) {
        q('DELETE FROM token_ingat WHERE selector = ?', [$m[1]]);
    }
    set_cookie_ingat('', time() - 3600);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------- Pembatasan percobaan login ----------
const BATAS_GAGAL_USER = 5;   // per username
const BATAS_GAGAL_IP = 20;    // per alamat IP (menebak banyak username)
const MENIT_KUNCI = 15;

function ip_klien(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'tidak-diketahui'), 0, 45);
}

/** Mengembalikan sisa menit terkunci, atau 0 bila boleh mencoba login. */
function login_terkunci(string $username): int
{
    $r = q('SELECT
              (SELECT COUNT(*) FROM gagal_login WHERE username = ? AND waktu > NOW() - INTERVAL ? MINUTE) AS per_user,
              (SELECT MIN(waktu) FROM (SELECT waktu FROM gagal_login WHERE username = ? AND waktu > NOW() - INTERVAL ? MINUTE ORDER BY waktu DESC LIMIT ' . BATAS_GAGAL_USER . ') x) AS awal_user,
              (SELECT COUNT(*) FROM gagal_login WHERE ip = ? AND waktu > NOW() - INTERVAL ? MINUTE) AS per_ip',
        [$username, MENIT_KUNCI, $username, MENIT_KUNCI, ip_klien(), MENIT_KUNCI])->fetch();
    if ((int)$r['per_user'] >= BATAS_GAGAL_USER || (int)$r['per_ip'] >= BATAS_GAGAL_IP) {
        $sisa = $r['awal_user'] ? MENIT_KUNCI - (int)floor((time() - strtotime($r['awal_user'])) / 60) : MENIT_KUNCI;
        return max(1, $sisa);
    }
    return 0;
}

function catat_gagal_login(string $username): void
{
    q('INSERT INTO gagal_login (username, ip, waktu) VALUES (?, ?, NOW())', [mb_substr($username, 0, 50), ip_klien()]);
    q('DELETE FROM gagal_login WHERE waktu < NOW() - INTERVAL 1 DAY'); // bersihkan catatan lama
}

function hapus_gagal_login(string $username): void
{
    q('DELETE FROM gagal_login WHERE username = ?', [$username]);
}

/** Aturan password minimal: 8 karakter. Mengembalikan pesan galat atau null. */
function cek_password(string $pw, string $ulang): ?string
{
    if (strlen($pw) < 8) return 'Password minimal 8 karakter.';
    if ($pw !== $ulang) return 'Konfirmasi password tidak sama.';
    return null;
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

/** Tolak POST tanpa token yang benar. */
function cek_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        header('X-CSRF-Gagal: 1');
        if (minta_json()) json_keluar(['ok' => false, 'csrf' => true, 'pesan' => 'Sesi kedaluwarsa.'], 400);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/** Permintaan dari JavaScript aplikasi (fetch) yang mengharapkan JSON. */
function minta_json(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function json_keluar(array $data, int $kode = 200): never
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Sisipkan token CSRF otomatis ke setiap <form method="post">. */
function sisipkan_csrf(string $html): string
{
    $field = '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
    return preg_replace('/(<form\b[^>]*method="post"[^>]*>)/i', '$1' . $field, $html);
}
