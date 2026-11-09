<?php
// Catatan login gagal untuk membatasi tebakan password.
return function (PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS gagal_login (
      id        INT AUTO_INCREMENT PRIMARY KEY,
      username  VARCHAR(50) NOT NULL,
      ip        VARCHAR(45) NOT NULL,
      waktu     DATETIME NOT NULL,
      KEY idx_user_waktu (username, waktu),
      KEY idx_ip_waktu (ip, waktu)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
