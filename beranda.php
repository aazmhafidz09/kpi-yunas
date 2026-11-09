<?php
// Halaman awal aplikasi (start_url PWA): arahkan sesuai peran.
require __DIR__ . '/lib/bootstrap.php';

if (boleh('usul_harian')) redirect('hariini.php');
if (boleh('isi_checklist') && jumlah_menunggu() > 0) redirect('verifikasi.php');
redirect('index.php');
