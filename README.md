# Loaz Industries

Loaz Industries adalah aplikasi web berbasis PHP untuk menghubungkan pengguna, teknisi, dan admin dalam satu platform. Aplikasi ini mendukung layanan perbaikan elektronik, penjualan suku cadang, pembayaran, serta komunikasi antar pengguna dan teknisi.

## Fitur Utama

- Autentikasi multi-role untuk admin, user, dan technician
- Dashboard khusus untuk setiap role
- Pengajuan dan pelacakan layanan perbaikan
- Manajemen pesanan suku cadang dan keranjang belanja
- Proses pembayaran dengan bukti transfer
- Chat layanan dan support chat antar pengguna/teknisi/admin
- Halaman karier untuk rekrutmen teknisi
- Laporan transaksi dan aktivitas admin

## Teknologi yang Digunakan

- PHP 8+
- MySQL / MariaDB
- Bootstrap 5
- JavaScript, jQuery
- Library frontend: DataTables, Select2, Flatpickr, Dropzone, SweetAlert2, Font Awesome

## Struktur Folder

- admin/ - panel administrasi
- auth/ - halaman login, registrasi, dan reset password
- user/ - panel pengguna
- technician/ - panel teknisi
- career/ - halaman rekrutmen teknisi
- api/ - endpoint AJAX dan chat
- assets/ - file CSS, JavaScript, gambar, dan library pihak ketiga
- config/ - konfigurasi database
- includes/ - komponen layout seperti header, footer, dan sidebar
- uploads/ - berkas unggahan pengguna

## Persyaratan Sistem

- PHP 8.0 atau lebih tinggi
- MySQL / MariaDB
- Web server seperti Apache atau Nginx
- Browser modern

## Instalasi

1. Clone repository ke direktori web server Anda.
2. Buat database MySQL baru.
3. Impor file SQL yang tersedia di root project, yaitu loaz_industries.sql.
4. Sesuaikan konfigurasi database pada file config/database.php.
5. Pastikan folder uploads/ dapat ditulis oleh server.
6. Buka aplikasi melalui browser.

Contoh konfigurasi database:

```php
<?php
$host = 'localhost';
$dbname = 'loaz_industries';
$username = 'root';
$password = '';
```

## Cara Menjalankan

- Halaman utama: /index.php atau /homepage.php
- Login: /auth/login.php
- Registrasi: /auth/register.php
- Admin: /admin/dashboard.php
- User: /user/dashboard.php
- Technician: /technician/dashboard.php

## Catatan Penting

- Pastikan folder uploads/ memiliki izin write agar upload berkas berhasil.
- Gunakan kredensial database yang aman untuk environment produksi.
- Untuk pengembangan lokal, Anda dapat menggunakan Laragon, XAMPP, atau WAMP.

## Kontribusi

Jika Anda ingin berkontribusi, silakan fork repository ini, buat branch baru, lalu kirim pull request.

## Lisensi

Proyek ini bersifat internal/komersial sesuai kebutuhan tim pengembang Loaz Industries.