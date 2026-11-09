<?php
// Akun login: Pemilik, Manajer, Pegawai.
return function (PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS pengguna (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
