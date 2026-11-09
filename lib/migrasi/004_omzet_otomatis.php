<?php
// Omzet otomatis dari "Total Pendapatan" aplikasi utama yunas-studio.com.
return function (PDO $db): void {
    if (!kolom_ada('periode', 'omzet_sumber')) {
        $db->exec("ALTER TABLE periode ADD COLUMN omzet_sumber ENUM('otomatis','manual') NOT NULL DEFAULT 'otomatis' AFTER omzet");
        // Omzet yang sudah diketik sebelumnya tetap dianggap manual (tidak ditimpa).
        $db->exec("UPDATE periode SET omzet_sumber = 'manual' WHERE omzet IS NOT NULL");
    }
    if (!kolom_ada('periode', 'omzet_diperbarui')) {
        $db->exec('ALTER TABLE periode ADD COLUMN omzet_diperbarui DATETIME NULL AFTER omzet_sumber');
    }
    if (!kolom_ada('periode', 'omzet_info')) {
        $db->exec('ALTER TABLE periode ADD COLUMN omzet_info VARCHAR(150) NULL AFTER omzet_diperbarui');
    }
    if (!kolom_ada('periode', 'omzet_galat')) {
        $db->exec('ALTER TABLE periode ADD COLUMN omzet_galat VARCHAR(255) NULL AFTER omzet_info');
    }
};
