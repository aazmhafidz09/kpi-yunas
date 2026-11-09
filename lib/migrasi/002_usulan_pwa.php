<?php
// Isian mandiri pegawai (usulan) + verifikasi, usulan/tolak apresiasi, token "ingat saya".
return function (PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS usulan_hari (
      id             INT AUTO_INCREMENT PRIMARY KEY,
      pegawai_id     INT NOT NULL,
      tanggal        DATE NOT NULL,
      catatan        VARCHAR(500) NULL,
      status         ENUM('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
      dikirim_oleh   INT NULL,
      dikirim_pada   DATETIME NOT NULL,
      diproses_oleh  VARCHAR(100) NULL,
      diproses_pada  DATETIME NULL,
      alasan_tolak   VARCHAR(255) NULL,
      UNIQUE KEY uk_usulan (pegawai_id, tanggal),
      KEY idx_status (status),
      FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE,
      FOREIGN KEY (dikirim_oleh) REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS usulan_butir (
      usulan_id        INT NOT NULL,
      butir_periode_id INT NOT NULL,
      nilai            TINYINT(1) NULL,
      PRIMARY KEY (usulan_id, butir_periode_id),
      FOREIGN KEY (usulan_id) REFERENCES usulan_hari(id) ON DELETE CASCADE,
      FOREIGN KEY (butir_periode_id) REFERENCES butir_periode(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ditulis dengan pengecekan kolom (bukan ADD COLUMN IF NOT EXISTS) agar jalan di MySQL maupun MariaDB.
    if (!kolom_ada('apresiasi', 'diusulkan_oleh')) $db->exec('ALTER TABLE apresiasi ADD COLUMN diusulkan_oleh INT NULL AFTER alasan');
    if (!kolom_ada('apresiasi', 'ditolak')) $db->exec('ALTER TABLE apresiasi ADD COLUMN ditolak TINYINT(1) NOT NULL DEFAULT 0 AFTER disetujui');
    if (!kolom_ada('apresiasi', 'alasan_tolak')) $db->exec('ALTER TABLE apresiasi ADD COLUMN alasan_tolak VARCHAR(255) NULL AFTER ditolak');

    $db->exec("CREATE TABLE IF NOT EXISTS token_ingat (
      id              INT AUTO_INCREMENT PRIMARY KEY,
      pengguna_id     INT NOT NULL,
      selector        CHAR(24) NOT NULL UNIQUE,
      validator_hash  CHAR(64) NOT NULL,
      kedaluwarsa     DATETIME NOT NULL,
      dibuat_pada     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (pengguna_id) REFERENCES pengguna(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("INSERT IGNORE INTO pengaturan (kunci, nilai, label, urutan) VALUES
      ('usulan_maks_mundur', '3', 'Pegawai boleh mengisi checklist sampai berapa hari ke belakang', 14)");
};
