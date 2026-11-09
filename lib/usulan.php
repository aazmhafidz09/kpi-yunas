<?php
// Isian mandiri pegawai (usulan) dan verifikasinya oleh Manajer/Pemilik.
// Skor hanya dihitung dari checklist_harian; usulan baru masuk ke sana setelah diterima.

function periode_untuk_tanggal(string $tgl): ?array
{
    $t = strtotime($tgl);
    $p = q('SELECT * FROM periode WHERE tahun = ? AND bulan = ?', [(int)date('Y', $t), (int)date('n', $t)])->fetch();
    return $p ?: null;
}

function tanggal_valid(string $tgl): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $tgl);
    return $d && $d->format('Y-m-d') === $tgl;
}

/** Tanggal yang boleh diisi pegawai: hari ini s.d. N hari ke belakang (Pengaturan). */
function tanggal_boleh_usul(): array
{
    $n = max(0, (int)cfg('usulan_maks_mundur'));
    $hasil = [];
    for ($i = 0; $i <= $n; $i++) $hasil[] = date('Y-m-d', strtotime("-$i day"));
    return $hasil;
}

/** Alasan pegawai TIDAK boleh mengusulkan untuk tanggal ini, atau null bila boleh. */
function cek_boleh_usul(string $tgl, ?array $periode): ?string
{
    if (!in_array($tgl, tanggal_boleh_usul(), true)) {
        return 'Isian hanya bisa untuk hari ini sampai ' . (int)cfg('usulan_maks_mundur') . ' hari ke belakang.';
    }
    if (!$periode) return 'Periode bulan ini belum dibuat oleh Pemilik/Manajer.';
    if ($periode['status'] === 'final') return 'Periode ' . nama_periode($periode) . ' sudah final.';
    return null;
}

function ambil_usulan(int $pegawaiId, string $tgl): ?array
{
    $u = q('SELECT * FROM usulan_hari WHERE pegawai_id = ? AND tanggal = ?', [$pegawaiId, $tgl])->fetch();
    if (!$u) return null;
    $u['nilai'] = [];
    foreach (q('SELECT butir_periode_id, nilai FROM usulan_butir WHERE usulan_id = ?', [$u['id']]) as $r) {
        $u['nilai'][(int)$r['butir_periode_id']] = $r['nilai'] === null ? null : (int)$r['nilai'];
    }
    return $u;
}

/** Nilai final (sudah dinilai) per butir pada satu tanggal: [butir_id => 1|0]. */
function nilai_final(array $butirIds, string $tgl): array
{
    if (!$butirIds) return [];
    $in = implode(',', array_fill(0, count($butirIds), '?'));
    $hasil = [];
    foreach (q("SELECT butir_periode_id, nilai FROM checklist_harian WHERE tanggal = ? AND butir_periode_id IN ($in)", [$tgl, ...$butirIds]) as $r) {
        $hasil[(int)$r['butir_periode_id']] = (int)$r['nilai'];
    }
    return $hasil;
}

/** Ubah isian form ('1' / '0' / 'na') menjadi [butir_id => 1|0|null] untuk butir harian yang sah. */
function baca_nilai_form(array $butirHarian, array $input): array
{
    $hasil = [];
    foreach ($butirHarian as $b) {
        $v = $input[$b['id']] ?? '';
        if ($v === '1') $hasil[(int)$b['id']] = 1;
        elseif ($v === '0') $hasil[(int)$b['id']] = 0;
        elseif ($v === 'na') $hasil[(int)$b['id']] = null;
    }
    return $hasil;
}

/** Simpan/ubah usulan pegawai. Status kembali "menunggu" (juga bila sebelumnya ditolak). */
function simpan_usulan(int $pegawaiId, string $tgl, array $nilai, ?string $catatan, int $penggunaId): void
{
    $db = db();
    $db->beginTransaction();
    q('INSERT INTO usulan_hari (pegawai_id, tanggal, catatan, status, dikirim_oleh, dikirim_pada)
       VALUES (?, ?, ?, \'menunggu\', ?, NOW())
       ON DUPLICATE KEY UPDATE catatan = VALUES(catatan), status = \'menunggu\', dikirim_oleh = VALUES(dikirim_oleh),
         dikirim_pada = NOW(), diproses_oleh = NULL, diproses_pada = NULL, alasan_tolak = NULL',
        [$pegawaiId, $tgl, $catatan, $penggunaId]);
    $id = (int)q('SELECT id FROM usulan_hari WHERE pegawai_id = ? AND tanggal = ?', [$pegawaiId, $tgl])->fetchColumn();
    q('DELETE FROM usulan_butir WHERE usulan_id = ?', [$id]);
    $ins = $db->prepare('INSERT INTO usulan_butir (usulan_id, butir_periode_id, nilai) VALUES (?, ?, ?)');
    foreach ($nilai as $bid => $v) $ins->execute([$id, $bid, $v]);
    $db->commit();
}

/**
 * Simpan nilai final satu hari (oleh Manajer/Pemilik) ke checklist_harian.
 * Bila ada usulan pegawai yang menunggu/ditolak untuk tanggal itu, usulan ditandai diterima.
 */
function simpan_nilai_final(int $pegawaiId, string $tgl, array $nilai, string $penyetuju): void
{
    $db = db();
    $db->beginTransaction();
    $ins = $db->prepare('INSERT INTO checklist_harian (butir_periode_id, tanggal, nilai) VALUES (?, ?, ?)');
    foreach ($nilai as $bid => $v) {
        q('DELETE FROM checklist_harian WHERE butir_periode_id = ? AND tanggal = ?', [$bid, $tgl]);
        if ($v !== null) $ins->execute([$bid, $tgl, $v]);
    }
    q('UPDATE usulan_hari SET status = \'diterima\', diproses_oleh = ?, diproses_pada = NOW(), alasan_tolak = NULL
       WHERE pegawai_id = ? AND tanggal = ? AND status <> \'diterima\'', [$penyetuju, $pegawaiId, $tgl]);
    $db->commit();
}

/** Terima usulan apa adanya (tombol cepat di halaman Verifikasi). */
function terima_usulan(int $usulanId, string $penyetuju): bool
{
    $u = q('SELECT * FROM usulan_hari WHERE id = ? AND status = \'menunggu\'', [$usulanId])->fetch();
    if (!$u) return false;
    $u = ambil_usulan((int)$u['pegawai_id'], $u['tanggal']);
    simpan_nilai_final((int)$u['pegawai_id'], $u['tanggal'], $u['nilai'], $penyetuju);
    return true;
}

function tolak_usulan(int $usulanId, string $penyetuju, string $alasan): bool
{
    return q('UPDATE usulan_hari SET status = \'ditolak\', diproses_oleh = ?, diproses_pada = NOW(), alasan_tolak = ?
              WHERE id = ? AND status = \'menunggu\'', [$penyetuju, $alasan, $usulanId])->rowCount() > 0;
}

/** Jumlah hal yang menunggu verifikasi (untuk lencana di menu). */
function jumlah_menunggu(): int
{
    return (int)q('SELECT (SELECT COUNT(*) FROM usulan_hari WHERE status = \'menunggu\')
                        + (SELECT COUNT(*) FROM apresiasi WHERE disetujui = 0 AND ditolak = 0)')->fetchColumn();
}

/** Status usulan per tanggal untuk satu pegawai dalam rentang: [tgl => status]. */
function status_usulan_rentang(int $pegawaiId, string $awal, string $akhir): array
{
    $h = [];
    foreach (q('SELECT tanggal, status FROM usulan_hari WHERE pegawai_id = ? AND tanggal BETWEEN ? AND ?', [$pegawaiId, $awal, $akhir]) as $r) {
        $h[$r['tanggal']] = $r['status'];
    }
    return $h;
}

function label_status_usulan(?string $s): string
{
    return ['menunggu' => 'Menunggu verifikasi', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'][$s] ?? 'Belum diisi';
}

function nama_hari_tanggal(string $tgl): string
{
    static $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $t = strtotime($tgl);
    return $hari[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . NAMA_BULAN[(int)date('n', $t)];
}
