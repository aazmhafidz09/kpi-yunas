-- =====================================================================
-- Skema database KPI & Bonus Pegawai Yunas Studio
-- Mengacu pada SK Kebijakan Bonus Pegawai Yunas Studio (berlaku 1 Nov 2026)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS kpi_yunas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kpi_yunas;

-- Jabatan menentukan checklist mana yang dipakai (Lampiran B-1 / B-2).
CREATE TABLE IF NOT EXISTS jabatan (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(100) NOT NULL,
  lampiran    VARCHAR(10)  NULL COMMENT 'Rujukan lampiran SK, mis. B-1'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pegawai (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(100) NOT NULL,
  jabatan_id  INT NOT NULL,
  aktif       TINYINT(1) NOT NULL DEFAULT 1,
  catatan     VARCHAR(255) NULL,
  FOREIGN KEY (jabatan_id) REFERENCES jabatan(id)
) ENGINE=InnoDB;

-- Template butir checklist per jabatan (Lampiran B). Total poin per jabatan = 100.
CREATE TABLE IF NOT EXISTS template_butir (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  jabatan_id  INT NOT NULL,
  urutan      INT NOT NULL DEFAULT 0,
  komponen    VARCHAR(100) NOT NULL,
  butir       VARCHAR(255) NOT NULL,
  poin        DECIMAL(5,2) NOT NULL,
  frekuensi   ENUM('harian','bulanan') NOT NULL DEFAULT 'harian',
  FOREIGN KEY (jabatan_id) REFERENCES jabatan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Satu baris per bulan penilaian: omzet, laba kas, dan batas omzet (Lampiran A).
CREATE TABLE IF NOT EXISTS periode (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  tahun          SMALLINT NOT NULL,
  bulan          TINYINT  NOT NULL,
  batas_minimum  BIGINT NULL,
  batas_target   BIGINT NULL,
  batas_stretch  BIGINT NULL,
  omzet          BIGINT NULL COMMENT 'Kosong = belum direkap',
  laba_kas_ok    TINYINT(1) NULL COMMENT '1 = laba kas >= 0, 0 = negatif, NULL = belum diisi',
  status         ENUM('berjalan','final') NOT NULL DEFAULT 'berjalan',
  catatan        VARCHAR(255) NULL,
  UNIQUE KEY uk_periode (tahun, bulan)
) ENGINE=InnoDB;

-- Salinan butir checklist untuk satu pegawai di satu periode.
-- Disalin dari template saat bulan dibuka, sehingga perubahan butir
-- (mis. butir khusus Reza) tidak mengubah riwayat bulan lain.
CREATE TABLE IF NOT EXISTS butir_periode (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  periode_id     INT NOT NULL,
  pegawai_id     INT NOT NULL,
  urutan         INT NOT NULL DEFAULT 0,
  komponen       VARCHAR(100) NOT NULL,
  butir          VARCHAR(255) NOT NULL,
  poin           DECIMAL(5,2) NOT NULL,
  frekuensi      ENUM('harian','bulanan') NOT NULL DEFAULT 'harian',
  nilai_bulanan  TINYINT(1) NULL COMMENT 'Untuk butir bulanan: 1/0/NULL',
  FOREIGN KEY (periode_id) REFERENCES periode(id) ON DELETE CASCADE,
  FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE,
  KEY idx_pp (periode_id, pegawai_id)
) ENGINE=InnoDB;

-- Isian harian: 1 = terpenuhi, 0 = tidak. Hari libur/tidak dinilai = tidak ada baris.
CREATE TABLE IF NOT EXISTS checklist_harian (
  butir_periode_id INT NOT NULL,
  tanggal          DATE NOT NULL,
  nilai            TINYINT(1) NOT NULL,
  PRIMARY KEY (butir_periode_id, tanggal),
  FOREIGN KEY (butir_periode_id) REFERENCES butir_periode(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Contoh kegiatan apresiasi (Lampiran C), untuk pilihan cepat.
CREATE TABLE IF NOT EXISTS jenis_apresiasi (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  kegiatan  VARCHAR(255) NOT NULL,
  poin      DECIMAL(5,2) NOT NULL
) ENGINE=InnoDB;

-- Log apresiasi (Pasal 7): tanggal, kegiatan, poin, penyetuju.
CREATE TABLE IF NOT EXISTS apresiasi (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  pegawai_id  INT NOT NULL,
  tanggal     DATE NOT NULL,
  kegiatan    VARCHAR(255) NOT NULL,
  poin        DECIMAL(5,2) NOT NULL,
  disetujui   TINYINT(1) NOT NULL DEFAULT 0,
  penyetuju   VARCHAR(100) NULL,
  alasan      VARCHAR(255) NULL COMMENT 'Wajib untuk kegiatan di luar Lampiran C',
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE,
  KEY idx_peg_tgl (pegawai_id, tanggal)
) ENGINE=InnoDB;

-- Status per pegawai per bulan: gugur Bonus B (Pasal 5 ayat 6) & konfirmasi (Pasal 9 ayat 2).
CREATE TABLE IF NOT EXISTS rekap_pegawai (
  periode_id        INT NOT NULL,
  pegawai_id        INT NOT NULL,
  gugur_bonus_b     TINYINT(1) NOT NULL DEFAULT 0,
  alasan_gugur      VARCHAR(255) NULL,
  konfirmasi_manajer TINYINT(1) NOT NULL DEFAULT 0,
  konfirmasi_pemilik TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (periode_id, pegawai_id),
  FOREIGN KEY (periode_id) REFERENCES periode(id) ON DELETE CASCADE,
  FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Akun login. Pemilik: semua akses. Manajer: isi checklist, apresiasi, omzet.
-- Pegawai: hanya melihat checklist & apresiasinya sendiri (Pasal 9 ayat 3).
CREATE TABLE IF NOT EXISTS pengguna (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(50)  NOT NULL UNIQUE,
  nama           VARCHAR(100) NOT NULL COMMENT 'Dipakai sebagai nama penyetuju',
  password_hash  VARCHAR(255) NOT NULL,
  peran          ENUM('pemilik','manajer','pegawai') NOT NULL,
  pegawai_id     INT NULL COMMENT 'Wajib untuk peran pegawai',
  aktif          TINYINT(1) NOT NULL DEFAULT 1,
  login_terakhir DATETIME NULL,
  dibuat_pada    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Angka-angka kebijakan (Bab II-IV) agar bisa diubah tanpa mengubah kode.
CREATE TABLE IF NOT EXISTS pengaturan (
  kunci   VARCHAR(50) PRIMARY KEY,
  nilai   VARCHAR(100) NOT NULL,
  label   VARCHAR(150) NOT NULL,
  urutan  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;
