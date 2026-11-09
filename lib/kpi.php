<?php
// Logika perhitungan sesuai SK Kebijakan Bonus Pegawai Yunas Studio.

function pengaturan(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT kunci, nilai FROM pengaturan')->fetchAll() as $r) {
            $cache[$r['kunci']] = $r['nilai'];
        }
    }
    return $cache;
}

function cfg(string $kunci): float
{
    return (float)(pengaturan()[$kunci] ?? 0);
}

// Setiap periode yang dimuat lewat fungsi di bawah otomatis disegarkan omzetnya (lib/omzet.php).
function ambil_periode(int $id): ?array
{
    $p = q('SELECT * FROM periode WHERE id = ?', [$id])->fetch();
    return $p ? sinkron_omzet($p) : null;
}

/** Periode default: bulan berjalan bila ada, jika tidak periode terakhir. */
function periode_default(): ?array
{
    $p = q('SELECT * FROM periode WHERE tahun = ? AND bulan = ?', [(int)date('Y'), (int)date('n')])->fetch();
    if (!$p) $p = q('SELECT * FROM periode ORDER BY tahun DESC, bulan DESC LIMIT 1')->fetch();
    return $p ? sinkron_omzet($p) : null;
}

function daftar_periode(): array
{
    return array_map('sinkron_omzet', q('SELECT * FROM periode ORDER BY tahun, bulan')->fetchAll());
}

function daftar_pegawai(bool $hanyaAktif = true): array
{
    $sql = 'SELECT p.*, j.nama AS jabatan, j.lampiran FROM pegawai p JOIN jabatan j ON j.id = p.jabatan_id';
    if ($hanyaAktif) $sql .= ' WHERE p.aktif = 1';
    return q($sql . ' ORDER BY p.nama')->fetchAll();
}

function ambil_pegawai(int $id): ?array
{
    $p = q('SELECT p.*, j.nama AS jabatan, j.lampiran FROM pegawai p JOIN jabatan j ON j.id = p.jabatan_id WHERE p.id = ?', [$id])->fetch();
    return $p ?: null;
}

function rentang_periode(array $periode): array
{
    $awal = sprintf('%04d-%02d-01', $periode['tahun'], $periode['bulan']);
    $akhir = date('Y-m-t', strtotime($awal));
    return [$awal, $akhir];
}

function jumlah_hari(array $periode): int
{
    return (int)date('t', mktime(0, 0, 0, (int)$periode['bulan'], 1, (int)$periode['tahun']));
}

/**
 * Pastikan pegawai punya salinan butir checklist untuk periode ini.
 * Bila belum ada, salin dari template jabatannya.
 */
function siapkan_butir_periode(int $periodeId, int $pegawaiId): void
{
    $ada = (int)q('SELECT COUNT(*) FROM butir_periode WHERE periode_id = ? AND pegawai_id = ?', [$periodeId, $pegawaiId])->fetchColumn();
    if ($ada > 0) return;
    q('INSERT INTO butir_periode (periode_id, pegawai_id, urutan, komponen, butir, poin, frekuensi)
       SELECT ?, p.id, t.urutan, t.komponen, t.butir, t.poin, t.frekuensi
       FROM pegawai p JOIN template_butir t ON t.jabatan_id = p.jabatan_id
       WHERE p.id = ?', [$periodeId, $pegawaiId]);
}

function butir_periode(int $periodeId, int $pegawaiId): array
{
    return q('SELECT * FROM butir_periode WHERE periode_id = ? AND pegawai_id = ? ORDER BY urutan, id',
        [$periodeId, $pegawaiId])->fetchAll();
}

/** Bonus A (Bab II): tingkat omzet & syarat laba kas. */
function hitung_bonus_a(array $periode): array
{
    $omzet = $periode['omzet'];
    $hasil = ['tingkat' => 'Belum diisi', 'bonus' => 0, 'catatan' => null];
    if ($omzet === null) {
        $hasil['catatan'] = 'Omzet bulan ini belum diisi.';
        return $hasil;
    }
    $omzet = (int)$omzet;
    if ($periode['batas_minimum'] === null) {
        $hasil['tingkat'] = 'Batas belum ditetapkan';
        $hasil['catatan'] = 'Batas omzet bulan ini belum ditetapkan Pemilik.';
        return $hasil;
    }
    if ($omzet >= (int)$periode['batas_stretch']) {
        $hasil['tingkat'] = 'Stretch';
        $bonus = cfg('bonus_a_stretch');
    } elseif ($omzet >= (int)$periode['batas_target']) {
        $hasil['tingkat'] = 'Target';
        $bonus = cfg('bonus_a_target');
    } elseif ($omzet >= (int)$periode['batas_minimum']) {
        $hasil['tingkat'] = 'Minimum';
        $bonus = cfg('bonus_a_minimum');
    } else {
        $hasil['tingkat'] = 'Di bawah Minimum';
        $bonus = 0;
    }
    if ($periode['laba_kas_ok'] === null) {
        $hasil['catatan'] = 'Status laba kas belum diisi; Bonus A ditahan.';
        $bonus = 0;
    } elseif ((int)$periode['laba_kas_ok'] !== 1) {
        $hasil['catatan'] = 'Laba kas negatif: Bonus A = Rp 0 (Pasal 4).';
        $bonus = 0;
    }
    $hasil['bonus'] = (int)$bonus;
    return $hasil;
}

/** Bonus B (Pasal 6) dari skor total. */
function bonus_b_dari_skor(?float $skorTotal): int
{
    if ($skorTotal === null) return 0;
    if ($skorTotal >= cfg('skor_istimewa')) return (int)cfg('bonus_b_istimewa');
    if ($skorTotal >= cfg('skor_atas')) return (int)cfg('bonus_b_atas');
    if ($skorTotal >= cfg('skor_tengah')) return (int)cfg('bonus_b_tengah');
    return 0;
}

function tingkat_skor(?float $skorTotal): string
{
    if ($skorTotal === null) return '–';
    if ($skorTotal >= cfg('skor_istimewa')) return 'Istimewa';
    if ($skorTotal >= cfg('skor_atas')) return 'Atas';
    if ($skorTotal >= cfg('skor_tengah')) return 'Tengah';
    return 'Di bawah';
}

function rekap_status(int $periodeId, int $pegawaiId): array
{
    $r = q('SELECT * FROM rekap_pegawai WHERE periode_id = ? AND pegawai_id = ?', [$periodeId, $pegawaiId])->fetch();
    return $r ?: ['gugur_bonus_b' => 0, 'alasan_gugur' => null, 'konfirmasi_manajer' => 0, 'konfirmasi_pemilik' => 0];
}

/**
 * Hitung skor dasar, apresiasi, skor total, dan Bonus B seorang pegawai di satu periode.
 * Skor dasar (Pasal 5): jumlah(poin butir × % terpenuhi) ÷ total poin × 100.
 *  - butir harian: % = hari terpenuhi ÷ hari dinilai (hari kosong tidak dihitung)
 *  - butir bulanan: % = nilai bulanan (1/0), kosong dianggap 0
 */
function hitung_pegawai(array $periode, int $pegawaiId): array
{
    $pid = (int)$periode['id'];
    $butir = butir_periode($pid, $pegawaiId);

    $stat = [];
    $hariDinilai = 0;
    if ($butir) {
        $ids = array_column($butir, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q("SELECT butir_periode_id, COUNT(*) dinilai, SUM(nilai) terpenuhi
                    FROM checklist_harian WHERE butir_periode_id IN ($in) GROUP BY butir_periode_id", $ids) as $r) {
            $stat[$r['butir_periode_id']] = $r;
        }
        $hariDinilai = (int)q("SELECT COUNT(DISTINCT tanggal) FROM checklist_harian WHERE butir_periode_id IN ($in)", $ids)->fetchColumn();
    }

    $adaData = false;
    $totalPoin = 0.0;
    $poinDiperoleh = 0.0;
    $komponen = [];
    $detail = [];
    foreach ($butir as $b) {
        $poin = (float)$b['poin'];
        if ($b['frekuensi'] === 'harian') {
            $d = (int)($stat[$b['id']]['dinilai'] ?? 0);
            $t = (int)($stat[$b['id']]['terpenuhi'] ?? 0);
            $pct = $d > 0 ? $t / $d : 0.0;
            if ($d > 0) $adaData = true;
        } else {
            $d = $t = null;
            $pct = $b['nilai_bulanan'] === null ? 0.0 : (float)$b['nilai_bulanan'];
            if ($b['nilai_bulanan'] !== null) $adaData = true;
        }
        $totalPoin += $poin;
        $poinDiperoleh += $poin * $pct;
        $k = $b['komponen'];
        $komponen[$k] ??= ['maks' => 0.0, 'diperoleh' => 0.0];
        $komponen[$k]['maks'] += $poin;
        $komponen[$k]['diperoleh'] += $poin * $pct;
        $detail[$b['id']] = ['dinilai' => $d, 'terpenuhi' => $t, 'pct' => $pct];
    }

    $skorDasar = ($adaData && $totalPoin > 0) ? $poinDiperoleh / $totalPoin * 100 : null;
    // Normalisasi poin komponen ke skala 100 bila total poin bukan 100.
    if ($totalPoin > 0 && abs($totalPoin - 100) > 0.001) {
        foreach ($komponen as &$kk) {
            $kk['maks'] = $kk['maks'] / $totalPoin * 100;
            $kk['diperoleh'] = $kk['diperoleh'] / $totalPoin * 100;
        }
        unset($kk);
    }

    [$awal, $akhir] = rentang_periode($periode);
    $apr = q('SELECT COALESCE(SUM(CASE WHEN disetujui = 1 THEN poin END), 0) disetujui,
                     COALESCE(SUM(CASE WHEN disetujui = 0 AND ditolak = 0 THEN poin END), 0) menunggu
              FROM apresiasi WHERE pegawai_id = ? AND tanggal BETWEEN ? AND ?', [$pegawaiId, $awal, $akhir])->fetch();
    $aprDisetujui = (float)$apr['disetujui'];
    $aprDihitung = 0.0;
    $aprCatatan = null;
    if ($skorDasar !== null) {
        if ($skorDasar >= cfg('apresiasi_syarat')) {
            $aprDihitung = min(cfg('apresiasi_maks'), $aprDisetujui);
            if ($aprDisetujui > $aprDihitung) $aprCatatan = 'Dibatasi maksimal ' . angka(cfg('apresiasi_maks'), 0) . ' poin.';
        } elseif ($aprDisetujui > 0) {
            $aprCatatan = 'Tidak dihitung: skor dasar di bawah ' . angka(cfg('apresiasi_syarat'), 0) . '.';
        }
    }

    $skorTotal = $skorDasar === null ? null : $skorDasar + $aprDihitung;
    $status = rekap_status($pid, $pegawaiId);
    $bonusB = (int)$status['gugur_bonus_b'] === 1 ? 0 : bonus_b_dari_skor($skorTotal);

    return [
        'butir' => $butir,
        'detail' => $detail,
        'komponen' => $komponen,
        'total_poin' => $totalPoin,
        'hari_dinilai' => $hariDinilai,
        'skor_dasar' => $skorDasar,
        'apresiasi_disetujui' => $aprDisetujui,
        'apresiasi_menunggu' => (float)$apr['menunggu'],
        'apresiasi_dihitung' => $aprDihitung,
        'apresiasi_catatan' => $aprCatatan,
        'skor_total' => $skorTotal,
        'tingkat' => tingkat_skor($skorTotal),
        'status' => $status,
        'bonus_b' => $bonusB,
    ];
}
