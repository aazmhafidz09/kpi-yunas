<?php
// Konfigurasi default (laptop/XAMPP). Di hosting, timpa lewat config.local.php
// (lihat config.local.php.contoh) — file ini tidak perlu diubah.
return [
    'db_host' => '127.0.0.1',
    'db_name' => 'kpi_yunas',
    'db_user' => 'root',
    'db_pass' => '',
    'db_zona_waktu' => '+07:00',   // WIB; semua NOW() di database memakai zona ini
    'app_name' => 'KPI & Bonus Yunas Studio',
    'timezone' => 'Asia/Jakarta',
    'debug' => true,               // tampilkan detail error (HANYA untuk laptop)
    'paksa_https' => false,        // alihkan http:// ke https:// (aktifkan di hosting)
    'kode_setup' => '',            // wajib diisi bila halaman "Buat akun Pemilik" dibuka dari luar laptop
];
