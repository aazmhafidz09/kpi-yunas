<?php
const HALAMAN_PUBLIK = true;
require __DIR__ . '/lib/bootstrap.php';

// Keluar hanya lewat POST (dengan token CSRF), agar tidak bisa dipicu tautan dari luar.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    keluar();
}
redirect('login.php');
