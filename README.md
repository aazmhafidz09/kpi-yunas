# KPI & Bonus Pegawai Yunas Studio

Aplikasi PHP + MySQL (XAMPP) pengganti file Excel KPI. Rumus mengikuti
*SK Kebijakan Bonus Pegawai Yunas Studio* (berlaku 1 November 2026).

- **Online:** <https://kpi.yunas-studio.com> (Hostinger, deploy otomatis dari GitHub `aazmhafidz09/kpi-yunas` branch `main`)
- **Laptop (pengembangan):** <http://localhost/kpi-yunas/> (Apache & MySQL harus menyala di XAMPP Control Panel)

Lokasi di Hostinger (paket Premium, situs `yunas-studio.com`):

| | |
|---|---|
| Folder aplikasi | `/home/u485208612/domains/yunas-studio.com/public_html/kpi` (diisi otomatis oleh Git — jangan diedit manual) |
| Konfigurasi server | `/home/u485208612/domains/yunas-studio.com/kpi-yunas-config.php` (berisi password DB, di luar folder web) |
| Database | `u485208612_kpi` (user `u485208612_kpi`) — terpisah dari database situs lain |
| Deploy | hPanel → yunas-studio.com → Tingkat lanjut → GIT (aplikasi GitHub Hostinger, akses hanya ke repo ini) |

## Halaman

| Halaman | Fungsi | Pasal SK |
|---|---|---|
| Isi Harian (`hariini.php`) | Tampilan HP per hari: pegawai mengisi usulan; Manajer/Pemilik memeriksa & menyimpan nilai final | Pasal 5, 9 |
| Verifikasi (`verifikasi.php`) | Antrian isian harian & usulan apresiasi dari pegawai; terima / tolak dengan alasan | Pasal 7.3, 9.1 |
| Rekap Bonus (`index.php`) | Bonus A + B per pegawai per bulan, ringkasan semua bulan | Pasal 2, 3, 6 |
| Checklist Bulanan (`checklist.php`) | Grid tanggal × butir, klik ✓ / ✗ / kosong; status gugur & konfirmasi | Pasal 5, 9 |
| Apresiasi (`apresiasi.php`) | Catat, usulkan, setujui/tolak poin di luar job desk | Pasal 7, 8 |
| Omzet & Periode (`periode.php`) | Batas Minimum/Target/Stretch, omzet, laba kas, status final | Pasal 3, 4, Lampiran A |
| Pegawai (`pegawai.php`) | Tambah/ubah/nonaktifkan pegawai | Pasal 1 |
| Butir Checklist (`butir.php`) | Template butir per jabatan, atau butir khusus satu pegawai di satu bulan | Lampiran B |
| Pengaturan (`pengaturan.php`) | Nominal bonus, batas skor, batas apresiasi, nama penyetuju | Bab II–IV |

## Login & hak akses

Saat pertama dibuka (belum ada akun), aplikasi meminta pembuatan akun **Pemilik**. Akun lain dibuat Pemilik lewat menu **Pengguna**.

| | Pemilik | Manajer | Pegawai |
|---|:-:|:-:|:-:|
| Lihat rekap & checklist semua pegawai | ✓ | ✓ | hanya diri sendiri |
| Lihat nominal omzet | ✓ | ✓ | – |
| Isi checklist harian sebagai **usulan** | – | – | ✓ (hari ini s.d. 3 hari ke belakang) |
| Simpan nilai final / terima / tolak usulan (Pasal 9.1) | ✓ | ✓ | – |
| Usulkan poin apresiasi | – | – | ✓ |
| Catat & setujui/tolak apresiasi (Pasal 7.3) | ✓ | ✓ | – |
| Isi omzet & laba kas | ✓ | ✓ | – |
| Centang konfirmasi Manajer / Pemilik (Pasal 9.2) | Pemilik | Manajer | – |
| Gugurkan Bonus B (Pasal 5.6) | ✓ | – | – |
| Butir checklist, pegawai, pengguna, pengaturan | ✓ | – | – |

- Nama penyetuju apresiasi diambil otomatis dari akun yang login.
- Password disimpan sebagai hash (`password_hash`). Lupa password: Pemilik mengatur ulang di menu Pengguna.
- Semua formulir POST dilindungi token CSRF (disisipkan otomatis di `lib/auth.php`).
- "Ingat saya" (60 hari) memakai token selector + hash validator yang dirotasi tiap dipakai; dicabut saat password diganti/direset atau akun dinonaktifkan.

## Alur isian mandiri pegawai

1. Pegawai membuka **Isi Harian** di HP, menjawab tiap butir harian (✓ Ya / ✗ Tidak / – Tidak berlaku), boleh menambah catatan, lalu **Kirim**. Butir bulanan tetap diisi Manajer/Pemilik.
2. Isian berstatus **Menunggu verifikasi** dan **belum masuk skor**.
3. Manajer/Pemilik membuka **Verifikasi**: *Terima* apa adanya, atau *Periksa* untuk mengoreksi per butir lalu *Simpan & terima*, atau *Tolak* dengan alasan.
4. Bila ditolak, pegawai melihat alasannya, memperbaiki, dan mengirim ulang.
5. Usulan apresiasi dari pegawai juga masuk antrian Verifikasi.

Batas hari ke belakang diatur di Pengaturan (`usulan_maks_mundur`, default 3). Periode berstatus Final terkunci untuk pegawai.

## Aplikasi HP (PWA)

- `manifest.json`, `sw.js`, `offline.html`, ikon di `assets/ikon/`.
- Service worker hanya menyimpan aset statis (CSS/JS/ikon); **halaman PHP tidak disimpan** karena berisi data pribadi.
- Bila sinyal putus saat mengirim Isi Harian, isian disimpan di HP (localStorage) dan dikirim otomatis saat online.
- **Pemasangan di HP wajib lewat HTTPS** (di laptop, `http://localhost` juga bisa untuk uji).
  Android/Chrome: tombol *Pasang aplikasi* di menu Lainnya / Akun, atau menu ⋮ → *Instal aplikasi*.
  iPhone/Safari: *Bagikan* → *Tambahkan ke Layar Utama*.
- Versi aset (`APP_VERSI`) dihitung **otomatis** dari isi CSS/JS/service worker; service worker didaftarkan sebagai `sw.js?v=<versi>`, jadi setiap perubahan otomatis memasang service worker baru dan membuang cache lama.

## Pindah ke hosting

Kebutuhan: PHP 8.1+ (ekstensi `pdo_mysql`, `mbstring`), MySQL/MariaDB, **HTTPS** (Let's Encrypt biasanya gratis di hosting), Apache/LiteSpeed (agar `.htaccess` berlaku).

1. **Buat paket pemasangan awal** di laptop:
   `C:\xampp\php\php.exe -d extension=zip tools\buat_paket.php --dengan-data`
   Hasilnya di `dist/`: `kpi-yunas-aplikasi-<waktu>.zip` (aplikasi tanpa alat/SQL/dokumentasi) dan
   `kpi-yunas-data-<waktu>.sql` (seluruh data **termasuk akun & hash password** — jangan dibagikan; token login tidak ikut).
2. Buat database kosong di panel hosting, lalu **impor file data `.sql`** lewat phpMyAdmin (tidak perlu `schema.sql`/migrasi).
3. Unggah zip ke folder situs (mis. `public_html` subdomain) lalu ekstrak.
4. Salin `config.local.php.contoh` menjadi `config.local.php`, isi nama/user/password database hosting.
   Biarkan `debug => false` dan `paksa_https => true`.
5. Buka alamat https-nya dan login dengan akun yang ikut terbawa. Hapus file `.sql` dari server bila sempat diunggah.
6. Bila hosting memakai Nginx (bukan Apache/LiteSpeed), blokir akses ke `lib/`, `dist/`, `tools/`, `database/` dan file `*.md`, `*.sql` di konfigurasi server.

### Pengamanan untuk server online

- **Batas login:** 5× salah untuk satu username (atau 20× dari satu IP) dalam 15 menit → dikunci 15 menit. Pemilik yang mengatur ulang password membuka kuncinya.
- **Mode produksi** (`debug => false`): detail error tidak ditampilkan, hanya dicatat di log error server.
- **Paksa HTTPS** (`paksa_https => true`): semua `http://` dialihkan ke `https://`.
- **Zona waktu database** dikunci ke WIB (`db_zona_waktu`), walau server memakai UTC.
- **Halaman setup** (buat akun Pemilik pertama) hanya terbuka dari laptop sendiri; dari luar wajib memasukkan `kode_setup` (min. 12 karakter) yang diisi di `config.local.php`.

## Omzet otomatis dari yunas-studio.com

Omzet (Bonus A) bisa diambil otomatis dari kartu **Total Pendapatan** aplikasi utama (`TransaksiController`):
`SUM(dp_amount)` transaksi berstatus `dp` + `SUM(total_price)` transaksi `sudah dibayar`, untuk transaksi yang **dibuat** (`created_at`) di bulan itu (tabel `transaksi`, database `u485208612_yunas_db`).

- Kode: `lib/omzet.php`. Koneksi **READ ONLY** (perintah tulis ditolak MySQL), satu kueri penjumlahan, tanpa data pelanggan.
- Kredensial: `kpi-yunas-sumber-omzet.php` di folder yang sama dengan `kpi-yunas-config.php` (contoh: `kpi-yunas-sumber-omzet.php.contoh`; `db_pass` = `DB_PASSWORD` di `.env` aplikasi utama).
- Diperbarui paling lambat tiap 10 menit untuk periode **Berjalan** (tombol *Perbarui omzet sekarang* di Omzet & Periode). Saat periode ditandai **Final**, omzet diambil sekali lagi lalu **dibekukan**.
- Per periode bisa dipilih *Otomatis* atau *Manual*. Gagal terhubung → omzet terakhir dipertahankan + peringatan; status koneksi di menu **Sistem**.
- Catatan: karena berdasarkan tanggal transaksi dibuat, pelunasan DP di bulan berikutnya menaikkan omzet bulan transaksi itu dibuat — itulah alasan omzet dibekukan saat Final.

## Update setelah hosting

Alur kerja: **ubah & uji di laptop → kirim ke hosting → selesai.** Tidak perlu phpMyAdmin, tidak perlu menaikkan nomor versi.

1. Ubah kode dan uji di laptop (`http://localhost/kpi-yunas/`).
2. Kirim ke hosting (cara utama: **Git**):
   ```
   git add -A
   git commit -m "Jelaskan perubahannya"
   git push
   ```
   GitHub memberi tahu Hostinger (webhook) → Hostinger menarik kode baru otomatis dalam hitungan detik.
   Cadangan tanpa Git: `C:\xampp\php\php.exe -d extension=zip tools\buat_paket.php` → unggah zip dari `dist/` → *Extract* (timpa).
3. Selesai. Pada request pertama setelah update, aplikasi otomatis:
   - menjalankan **migrasi database** yang baru (tercatat di tabel `migrasi`);
   - menghitung **versi aset** baru dari isi CSS/JS, sehingga HP pegawai otomatis memuat tampilan baru.

Yang tidak ikut ditimpa saat update: konfigurasi server (`kpi-yunas-config.php`) dan seluruh data.
Cek hasilnya di menu **Sistem** (Pemilik): rilis/commit terpasang, status migrasi, pemeriksaan server.

Aturan: **jangan mengedit kode langsung di server** (File Manager). Semua perubahan lewat laptop → Git, supaya tarikan otomatis tidak bentrok.

## Deploy Git: menyambungkan GitHub ↔ Hostinger (sekali saja)

Repositori GitHub harus **privat** (kode berisi logika bonus & struktur data).

1. **Subdomain & folder kosong.** hPanel → Domains → Subdomains, buat mis. `kpi.domainanda.com`. Buka File Manager, kosongkan folder subdomain itu (hapus `default.php` dsb.) — Hostinger hanya mau deploy ke folder kosong.
2. **Database.** hPanel → Databases → MySQL: buat database + user + password. Catat nama database & user (password Anda simpan sendiri).
3. **Hubungkan Git.** hPanel → (website subdomain) → Advanced → **GIT**:
   - *Private repository*: klik **Generate SSH key**, salin kuncinya → di GitHub: repo → Settings → **Deploy keys** → *Add deploy key* (cukup read-only).
   - Repository: `git@github.com:<akun>/kpi-yunas.git`, Branch: `main`, Directory: kosongkan (= folder subdomain) → **Create** lalu **Deploy**.
   - **Auto deployment**: salin *Webhook URL* → di GitHub: repo → Settings → **Webhooks** → *Add webhook* → tempel di *Payload URL*, event: *Just the push event*.
4. **Konfigurasi server.** Di File Manager, buat `kpi-yunas-config.php` **satu tingkat di atas** folder subdomain (isi dari `config.local.php.contoh`; simpan tanpa BOM). Bila folder induknya ternyata `public_html` situs lain, file tetap aman (PHP tidak menampilkan isinya), atau taruh sebagai `config.local.php` di folder aplikasi.
5. **Data.** Di laptop: `C:\xampp\php\php.exe -d extension=zip tools\buat_paket.php --dengan-data` → impor file `.sql` dari `dist/` lewat phpMyAdmin hPanel (zip-nya tidak dipakai bila memakai Git).
6. **Cek.** Buka `https://kpi.domainanda.com`, login, buka menu **Sistem**: semua pemeriksaan harus OK, rilis menunjukkan commit terakhir.
   Pastikan juga `https://kpi.domainanda.com/lib/kpi.php` dan `/database/seed.sql` **tidak** bisa dibuka (403/404).

Setelah ini, setiap `git push` dari laptop langsung terpasang di hosting.

## Mengubah struktur database (migrasi)

Jangan ubah database hosting lewat phpMyAdmin. Buat file baru di `lib/migrasi/` dengan nomor berikutnya:

```php
<?php
// lib/migrasi/004_tambah_kolom_contoh.php
return function (PDO $db): void {
    if (!kolom_ada('pegawai', 'no_hp')) {
        $db->exec('ALTER TABLE pegawai ADD COLUMN no_hp VARCHAR(20) NULL');
    }
};
```

- Laptop: migrasi berjalan begitu halaman mana pun dibuka. Hosting: setelah kode baru terpasang.
- Jangan mengubah file migrasi yang sudah pernah berjalan; buat file baru.
- Tulis agar aman diulang (`tabel_ada`, `kolom_ada`, `indeks_ada`, `CREATE TABLE IF NOT EXISTS`), dan tanpa sintaks khusus MariaDB, supaya jalan di MySQL maupun MariaDB.
- Bila migrasi gagal di hosting, pengunjung melihat halaman "Sedang diperbarui" (bukan error), kesalahan tercatat di log error hosting, dan Pemilik bisa mencoba ulang di menu Sistem.

## Struktur folder

```
kpi-yunas/
├── config.php              koneksi database
├── index.php … pengaturan.php   halaman
├── lib/
│   ├── bootstrap.php       koneksi PDO & helper
│   ├── kpi.php             SEMUA rumus perhitungan (skor dasar, apresiasi, Bonus A/B)
│   ├── auth.php            login, "ingat saya", peran & hak akses, CSRF
│   ├── usulan.php          isian mandiri pegawai & verifikasinya
│   ├── layout.php          header, menu (desktop & menu bawah HP), pemilih bulan/pegawai
│   ├── migrasi.php         penjalan migrasi otomatis
│   └── migrasi/            satu file per perubahan struktur database (001_, 002_, …)
├── hariini.php, verifikasi.php, beranda.php   halaman HP & halaman awal per peran
├── sistem.php              Sistem & Pembaruan (Pemilik): rilis, migrasi, pemeriksaan server
├── manifest.json, sw.js, offline.html          PWA
├── assets/
│   ├── style.css, checklist.js
│   ├── app.js              daftar service worker, tombol pasang, antrian offline
│   └── ikon/               ikon aplikasi (192, 512, maskable, apple-touch)
├── config.local.php.contoh contoh konfigurasi database untuk hosting
├── database/
│   ├── schema.sql          struktur tabel dasar (instal baru; sisanya lewat lib/migrasi)
│   └── seed.sql            data awal dari SK (jabatan, butir B-1/B-2, Lampiran A & C)
└── tools/
    └── import_excel.php    impor sheet checklist dari file Excel lama
```

## Tabel database (`kpi_yunas`)

- `jabatan`, `pegawai`: pegawai dan checklist yang dipakai (B-1 / B-2)
- `template_butir`: butir standar per jabatan
- `periode`: satu baris per bulan (batas omzet, omzet, laba kas, status)
- `butir_periode`: salinan butir per pegawai per bulan (bisa diubah tanpa mengganggu bulan lain)
- `checklist_harian`: isian 1/0 per butir per tanggal (hari libur = tidak ada baris)
- `apresiasi`, `jenis_apresiasi`: log apresiasi & contoh Lampiran C
- `rekap_pegawai`: gugur Bonus B & konfirmasi Manajer/Pemilik per bulan
- `pengaturan`: angka kebijakan

## Rumus (lib/kpi.php)

- **% terpenuhi** butir harian = hari terpenuhi ÷ hari dinilai; butir bulanan = nilai 1/0 (kosong = 0)
- **Skor dasar** = Σ(poin × % terpenuhi) ÷ total poin × 100
- **Apresiasi dihitung** = 0 bila skor dasar < 75, selain itu min(15, poin disetujui)
- **Skor total** = skor dasar + apresiasi dihitung
- **Bonus B**: ≥105 Rp 200rb · ≥90 Rp 150rb · ≥75 Rp 100rb · lainnya 0 (0 bila gugur)
- **Bonus A**: sesuai tingkat omzet, hanya bila laba kas ≥ 0

Sudah dicocokkan dengan contoh Lampiran D-1, D-2 (skor 91,8), dan D-3.

## Instal ulang database

Hanya untuk database kosong. Perintah ini **menghapus semua data**: jalankan dulu
`C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE kpi_yunas"` lalu:

```
C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\seed.sql
```

Tabel tambahan (akun, usulan, keamanan login, dll.) dibuat otomatis oleh `lib/migrasi/` saat halaman pertama dibuka.

## Impor dari Excel lama

```
C:\xampp\php\php.exe -d extension=zip tools\import_excel.php "C:\path\KPI REZA.xlsx" "Reza Nov26" Reza 2026-11
```

Butir pegawai di bulan itu diganti dengan butir dari sheet. Tambahkan `--apresiasi` untuk ikut mengimpor log apresiasi.
