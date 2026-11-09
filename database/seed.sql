-- Data awal sesuai SK Kebijakan Bonus Pegawai Yunas Studio
USE kpi_yunas;

INSERT INTO pengaturan (kunci, nilai, label, urutan) VALUES
 ('bonus_a_minimum',   '100000', 'Bonus A bila omzet mencapai Minimum (Rp)', 1),
 ('bonus_a_target',    '200000', 'Bonus A bila omzet mencapai Target (Rp)', 2),
 ('bonus_a_stretch',   '300000', 'Bonus A bila omzet mencapai Stretch (Rp)', 3),
 ('skor_istimewa',     '105',    'Skor total minimal tingkat Istimewa', 4),
 ('bonus_b_istimewa',  '200000', 'Bonus B tingkat Istimewa (Rp)', 5),
 ('skor_atas',         '90',     'Skor total minimal tingkat atas', 6),
 ('bonus_b_atas',      '150000', 'Bonus B tingkat atas (Rp)', 7),
 ('skor_tengah',       '75',     'Skor total minimal tingkat tengah', 8),
 ('bonus_b_tengah',    '100000', 'Bonus B tingkat tengah (Rp)', 9),
 ('apresiasi_maks',    '15',     'Batas poin apresiasi dihitung per bulan', 10),
 ('apresiasi_syarat',  '75',     'Skor dasar minimal agar poin apresiasi dihitung', 11),
 ('nama_pemilik',      'Aaz',    'Nama Pemilik (penyetuju)', 12),
 ('nama_manajer',      'Hasan',  'Nama Manajer (penyetuju)', 13);

INSERT INTO jabatan (id, nama, lampiran) VALUES
 (1, 'Admin dan Fotografer', 'B-1'),
 (2, 'Karyawan Konten, Keuangan, dan Admin', 'B-2');

-- Lampiran B-1
INSERT INTO template_butir (jabatan_id, urutan, komponen, butir, poin, frekuensi) VALUES
 (1, 1,  'Kehadiran dan ketepatan waktu', 'Hadir sesuai jadwal tanpa alpha', 10, 'harian'),
 (1, 2,  'Kehadiran dan ketepatan waktu', 'Tidak terlambat', 8, 'harian'),
 (1, 3,  'Kehadiran dan ketepatan waktu', 'Serah terima shift dilakukan lengkap', 7, 'harian'),
 (1, 4,  'Kualitas foto dan hasil edit', 'Hasil diserahkan sesuai janji waktu', 10, 'harian'),
 (1, 5,  'Kualitas foto dan hasil edit', 'Tidak ada komplain pelanggan', 10, 'harian'),
 (1, 6,  'Kualitas foto dan hasil edit', 'Mengikuti standar pose, pencahayaan, editing', 10, 'harian'),
 (1, 7,  'Kerapian admin dan kasir', 'Transaksi hari itu tercatat lengkap', 10, 'harian'),
 (1, 8,  'Kerapian admin dan kasir', 'Rekonsiliasi kas harian sesuai', 10, 'harian'),
 (1, 9,  'Kerapian admin dan kasir', 'Jadwal/booking tercatat rapi tanpa bentrok', 5, 'harian'),
 (1, 10, 'Kebersihan dan kesiapan studio', 'Checklist buka toko dikerjakan', 7, 'harian'),
 (1, 11, 'Kebersihan dan kesiapan studio', 'Checklist tutup toko dikerjakan', 7, 'harian'),
 (1, 12, 'Kebersihan dan kesiapan studio', 'Area studio, properti, peralatan rapi dan bersih', 6, 'harian');

-- Lampiran B-2
INSERT INTO template_butir (jabatan_id, urutan, komponen, butir, poin, frekuensi) VALUES
 (2, 1,  'Konten', 'Jumlah konten terbit sesuai target bulanan', 13, 'bulanan'),
 (2, 2,  'Konten', 'Jadwal posting hari itu terpenuhi', 9, 'harian'),
 (2, 3,  'Konten', 'Memakai brief, caption, hashtag standar', 7, 'harian'),
 (2, 4,  'Konten', 'Laporan performa konten dibuat', 6, 'bulanan'),
 (2, 5,  'Keuangan', 'Transaksi hari itu dicatat tepat waktu', 10, 'harian'),
 (2, 6,  'Keuangan', 'Rekap mingguan diserahkan tepat waktu', 9, 'bulanan'),
 (2, 7,  'Keuangan', 'Kas kecil dan bukti sesuai', 6, 'harian'),
 (2, 8,  'Kualitas konten', 'Konten lolos review tanpa revisi besar', 8, 'harian'),
 (2, 9,  'Kualitas konten', 'Konsisten dengan identitas visual studio', 7, 'harian'),
 (2, 10, 'Kehadiran', 'Hadir sesuai jadwal tanpa alpha', 5, 'harian'),
 (2, 11, 'Kehadiran', 'Tepat waktu', 5, 'harian'),
 (2, 12, 'Kerapian dan kebersihan', 'Meja kerja dan perangkat rapi', 8, 'harian'),
 (2, 13, 'Kerapian dan kebersihan', 'Ikut checklist buka/tutup dan menjaga kebersihan area', 7, 'harian');

INSERT INTO pegawai (id, nama, jabatan_id) VALUES
 (1, 'Reza', 1),
 (2, 'Irsyad', 2);

-- Lampiran C
INSERT INTO jenis_apresiasi (kegiatan, poin) VALUES
 ('Menggantikan shift rekan atau masuk di hari libur', 3),
 ('Mengerjakan tugas dadakan dari Pemilik/Manajer di luar tugas biasa', 2),
 ('Ide atau perbaikan yang diterapkan studio (alur kerja, promo, konten)', 5),
 ('Review atau testimoni positif yang menyebut nama pegawai', 2),
 ('Membawa pelanggan baru lewat referral', 3),
 ('Membantu acara atau closing di luar jam kerja', 3);

-- Lampiran A (Rp)
INSERT INTO periode (tahun, bulan, batas_minimum, batas_target, batas_stretch) VALUES
 (2026, 11, 23000000, 28000000, 33000000),
 (2026, 12, 25000000, 31000000, 37000000),
 (2027, 1,  22000000, 26000000, 31000000),
 (2027, 2,  25000000, 32000000, 38000000),
 (2027, 3,  65000000, 85000000, 100000000),
 (2027, 4,  24000000, 29000000, 34000000);
