# Loaz Industries - Service & Marketplace Web

Loaz Industries adalah aplikasi web berbasis PHP untuk menghubungkan pengguna, teknisi, dan admin dalam satu platform. Aplikasi ini mendukung layanan perbaikan elektronik, penjualan suku cadang, pembayaran, serta komunikasi antar pengguna dan teknisi.

## Fitur Utama

- Autentikasi multi-role: user, technician, dan admin
- Dashboard khusus untuk masing-masing role
- Pengajuan dan pelacakan layanan perbaikan
- Manajemen pesanan suku cadang dan keranjang belanja
- Proses pembayaran beserta bukti transfer
- Chat layanan dan support chat
- Rekrutmen teknisi melalui halaman karier
- Laporan transaksi dan aktivitas admin

## Teknologi yang Digunakan

- PHP
- MySQL / MariaDB
- Bootstrap 5
- JavaScript, jQuery
- DataTables, Select2, Flatpickr, Dropzone, SweetAlert2, Font Awesome

## Struktur Folder

- auth/ - halaman login, register, reset password
- admin/ - panel administrasi
- user/ - panel pengguna
- technician/ - panel teknisi
- career/ - halaman rekrutmen teknisi
- api/ - endpoint AJAX dan chat
- assets/ - file CSS, JS, gambar, dan library pihak ketiga
- config/ - konfigurasi database
- includes/ - header, footer, sidebar, dan helper
- uploads/ - file unggahan pengguna

## Persyaratan Sistem

- PHP 8.0+
- MySQL / MariaDB
- Web server seperti Apache atau Nginx
- Browser modern

## Instalasi

1. Clone repository ke direktori web server Anda.
2. Buat database MySQL baru.
3. Impor file SQL yang tersedia di root project.
4. Edit konfigurasi database di file config/database.php.
5. Pastikan folder uploads/ dapat ditulis oleh server.
6. Buka aplikasi di browser.

Contoh konfigurasi database:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'loaz_industries');
```

## Cara Menjalankan

- Halaman utama: /index.php atau /homepage.php
- Login: /auth/login.php
- Registrasi: /auth/register.php
- Admin: /admin/dashboard.php
- User: /user/dashboard.php
- Technician: /technician/dashboard.php

## Catatan Penting

- Pastikan folder uploads/ memiliki permission write.
- Gunakan kredensial database yang aman untuk environment produksi.
- Untuk pengembangan lokal, Anda dapat menggunakan XAMPP, Laragon, atau WAMP.

## Kontribusi

Jika Anda ingin berkontribusi, silakan fork repository ini, buat branch baru, lalu kirim pull request.

## Lisensi

Proyek ini bersifat internal/komersial sesuai kebutuhan tim pengembang Loaz Industries.