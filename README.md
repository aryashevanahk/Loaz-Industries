# Loaz Industries - Service Management Platform

Loaz Industries adalah platform manajemen layanan terintegrasi yang menghubungkan pengguna dengan teknisi profesional. Aplikasi ini menyediakan sistem lengkap untuk pemesanan layanan perbaikan elektronik, manajemen pesanan suku cadang (e-commerce), pembayaran, serta komunikasi real-time antara pengguna dan teknisi.

## 📋 Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Arsitektur Sistem](#arsitektur-sistem)
- [Struktur Proyek](#struktur-proyek)
- [Struktur Database](#struktur-database)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi](#instalasi)
- [Konfigurasi Database](#konfigurasi-database)
- [Panduan Penggunaan](#panduan-penggunaan)
- [API Endpoints](#api-endpoints)
- [Alur Sistem](#alur-sistem)

## 🎯 Fitur Utama

### 🔐 Autentikasi & Manajemen Akun
- **Login/Registrasi Multi-Role**: Dukungan untuk pengguna (user), teknisi (technician), dan admin
- **Manajemen Profil**: Edit data pribadi, foto profil, dan informasi kontak
- **Keamanan Password**: Sistem reset password, lupa password, dan pertanyaan keamanan
- **Session Management**: Manajemen sesi pengguna yang aman dengan PDO prepared statements

### 👥 Dashboard & Panel Pengguna
- **User Dashboard**: Ringkasan layanan, pesanan, dan statistik pengguna
- **Technician Dashboard**: Manajemen layanan yang sedang dikerjakan, penghasilan, dan review
- **Admin Dashboard**: Analytics komprehensif (pengguna, teknisi, layanan, pendapatan, transaksi)

### 🛠️ Manajemen Layanan Perbaikan
- **Request Layanan**: Pengguna dapat memesan layanan perbaikan (onsite/pickup)
- **Service Tracking**: Tracking real-time status layanan (pending → visit → accepted → repairing → done)
- **Service Updates**: Teknisi dapat memberikan update dengan foto dan catatan
- **Service History**: Riwayat lengkap layanan dengan detail teknisi, biaya, dan suku cadang yang digunakan
- **Rating & Review**: Sistem penilaian pengguna terhadap teknisi (rating 1-5 bintang)

### 🛒 E-Commerce Suku Cadang
- **Katalog Produk**: Daftar suku cadang dengan kategori, brand, harga, dan stok
- **Keranjang Belanja**: Sistem keranjang dengan validasi stok dan update jumlah
- **Checkout & Pengiriman**: Proses checkout dengan opsi alamat pengiriman dan biaya ongkir
- **Manajemen Pesanan**: Tracking pesanan dari pending hingga completed
- **Order History**: Riwayat pembelian dengan detail item dan status pengiriman

### 💳 Sistem Pembayaran & Transaksi
- **Multiple Payment Methods**: Dukungan berbagai metode pembayaran
- **Payment Tracking**: Status pembayaran (pending → pending_confirmation → paid)
- **Payment Proof Upload**: Unggah bukti pembayaran untuk verifikasi manual
- **Technician Earnings**: Perhitungan otomatis penghasilan teknisi dengan fee percentage
- **Transactions Report**: Laporan transaksi lengkap untuk admin dan technician
- **Revenue Dashboard**: Analytics pendapatan bulanan dan harian

### 💬 Sistem Chat & Komunikasi
- **Direct Messaging**: Chat real-time antara pengguna dan teknisi per layanan
- **Support Chat**: Sistem chat support antara pengguna dan admin
- **Admin Messaging**: Komunikasi admin ke berbagai pengguna
- **Typing Status**: Indikator ketika user sedang mengetik
- **Message History**: Riwayat pesan tersimpan dengan timestamp

### 🎓 Manajemen Karir Teknisi
- **Halaman Karir**: Landing page untuk rekrutmen teknisi
- **Form Aplikasi**: Form aplikasi dengan unggah CV dan sertifikat
- **Status Tracking**: Pengguna dapat track status aplikasi mereka
- **Admin Review**: Admin dashboard untuk review aplikasi dan approve/reject
- **Recruitment Analytics**: Statistik pelamar, diproses, dan status

### 📊 Laporan & Analytics
- **Sales Report**: Laporan penjualan dengan grafik pendapatan bulanan
- **User Analytics**: Jumlah pengguna, teknisi, dan distribusi role
- **Service Analytics**: Statistik layanan (total, pending, selesai)
- **Part Inventory**: Stok suku cadang dan kategori
- **Order Statistics**: Data pesanan dengan status breakdown
- **Financial Summary**: Total revenue, fee, dan penghasilan teknisi

### 📁 Manajemen File Upload
- **CV & Sertifikat**: Upload untuk aplikasi karir teknisi
- **Foto Profil**: Upload foto profil pengguna
- **Service Photos**: Dokumentasi foto selama proses perbaikan
- **Payment Proofs**: Upload bukti pembayaran untuk verifikasi
- **Temp Storage**: Folder temporary untuk file sementara

## 🏗️ Arsitektur Sistem

### Teknologi Stack
- **Backend**: PHP 7.4+ (PDO, Prepared Statements untuk security)
- **Database**: MySQL 8.0 / MariaDB 5.7+
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript (ES6+)
- **Libraries**: jQuery 4.0, DataTables, Select2, FlatPickr, Dropzone, Font Awesome 6, SweetAlert2

### Pola Arsitektur
- **MVC-like Structure**: Separation of concerns dengan folder terpisah untuk auth, user, admin, technician
- **API Endpoints**: RESTful JSON API untuk operasi chat dan AJAX requests
- **Session Management**: PHP session-based authentication
- **Database Layer**: PDO dengan prepared statements untuk mencegah SQL injection

### Flow Autentikasi
```
Pengunjung → Homepage → Login → Role Check → Dashboard Sesuai Role
              ↓
           Registrasi → Email Verification → Profile Setup → Dashboard
```

### Flow Layanan Perbaikan
```
User Request Service → Technician Accept → Visit → Repairing → Done → Review
                    ↓
               Payment → Confirmation → Technician Earning
```

### Flow E-Commerce Suku Cadang
```
Browse Parts → Add to Cart → Checkout → Payment → Order Tracking → Delivered → Complete
```

## 📁 Struktur Proyek

```
loaz_industries/
│
├── 📄 index.php                          # Entry point utama (redirect ke homepage)
├── 📄 homepage.php                       # Halaman utama dengan statistik & featured content
├── 📄 about.php                          # Halaman tentang Loaz Industries
├── 📄 contact.php                        # Halaman kontak & form hubungi kami
├── 📄 loaz_industries.sql                # Database dump lengkap
├── 📄 README.md                          # Dokumentasi proyek
│
├── 📁 config/
│   └── database.php                      # Konfigurasi koneksi MySQL (PDO)
│
├── 📁 includes/
│   ├── header.php                        # Header & navigation bar yang reusable
│   ├── footer.php                        # Footer yang reusable
│   ├── sidebar.php                       # Sidebar untuk admin & dashboard pages
│   └── functions.php                     # Fungsi-fungsi helper global
│                                         #   - formatCurrency()
│                                         #   - getStatusBadge()
│                                         #   - isLoggedIn(), isAdmin(), isTechnician()
│                                         #   - redirectIfNotLoggedIn(), dll
│
├── 📁 auth/                              # Authentication Module
│   ├── login.php                         # Halaman login (POST handler included)
│   ├── register.php                      # Halaman registrasi user baru
│   ├── logout.php                        # Logout handler
│   ├── forgot_password.php               # Halaman lupa password
│   └── reset_password.php                # Halaman reset password via security questions
│
├── 📁 admin/                             # Admin Panel - Accessible by admin role only
│   ├── dashboard.php                     # Admin dashboard dengan analytics
│   ├── users.php                         # Manajemen users (CRUD)
│   ├── technicians.php                   # Manajemen technicians & approval
│   ├── technician_applications.php       # Review aplikasi karir technician
│   ├── services.php                      # Manajemen layanan perbaikan
│   ├── orders.php                        # Manajemen pesanan suku cadang
│   ├── parts.php                         # Katalog suku cadang (CRUD)
│   ├── transactions.php                  # Manajemen transaksi & pembayaran
│   ├── reports.php                       # Laporan penjualan & revenue
│   ├── support_chat.php                  # Chat support dengan pengguna
│   ├── get_application_detail.php        # AJAX: fetch detail aplikasi
│   ├── get_order_details.php             # AJAX: fetch detail pesanan
│   ├── get_service_detail.php            # AJAX: fetch detail layanan
│   └── get_technician_reviews.php        # AJAX: fetch review technician
│
├── 📁 user/                              # User Panel - Accessible by user role only
│   ├── dashboard.php                     # User dashboard dengan summary
│   ├── my_services.php                   # Riwayat & status layanan perbaikan
│   ├── my_orders.php                     # Riwayat & status pesanan suku cadang
│   ├── request_service.php               # Form request layanan perbaikan baru
│   ├── get_service_detail.php            # Detail layanan perbaikan
│   ├── get_order_detail.php              # Detail pesanan suku cadang
│   ├── cart.php                          # Keranjang belanja
│   ├── get_cart_ajax.php                 # AJAX: load items di cart
│   ├── get_cart_count.php                # AJAX: hitung jumlah item di cart
│   ├── add_to_cart_ajax.php              # AJAX: tambah item ke cart
│   ├── remove_from_cart_ajax.php         # AJAX: hapus item dari cart
│   ├── checkout.php                      # Proses checkout pesanan
│   ├── payment.php                       # Halaman input metode pembayaran
│   ├── payment_service.php               # Pembayaran untuk service
│   ├── payment_success.php               # Halaman sukses pembayaran
│   ├── payment_waiting.php               # Halaman menunggu konfirmasi pembayaran
│   ├── checkout_success.php              # Halaman sukses checkout
│   ├── order_part.php                    # Pesanan suku cadang khusus
│   ├── chat.php                          # Chat dengan technician per service
│   ├── support_chat.php                  # Chat support dengan admin
│   └── profile.php                       # Edit profil pengguna
│
├── 📁 technician/                        # Technician Panel - Accessible by technician role
│   ├── dashboard.php                     # Dashboard technician dengan service queue
│   ├── my_services.php                   # Layanan yang diterima/dikerjakan
│   ├── earnings.php                      # Penghasilan dari services & pembagian fee
│   ├── chat.php                          # Chat dengan customer per service
│   ├── profile.php                       # Edit profil technician (specialty, rating)
│   ├── order_part.php                    # Order suku cadang untuk repair
│   ├── update_status.php                 # Update status perbaikan (dengan foto)
│   └── confirm_payment.php               # Konfirmasi & terima pembayaran
│
├── 📁 career/                            # Career & Recruitment Module (Public)
│   ├── karir.php                         # Halaman karir landing page
│   ├── apply.php                         # Form aplikasi untuk calon technician
│   ├── register_technician.php           # Register as technician setelah approval
│   ├── application_status.php            # Tracking status aplikasi
│   ├── status.php                        # Check status dengan form
│   ├── check.php                         # Endpoint check status aplikasi
│   └── thank_you.php                     # Halaman terima kasih setelah submit
│
├── 📁 api/                               # REST API Endpoints (JSON responses)
│   ├── send_message.php                  # POST: Kirim chat message
│   ├── get_messages.php                  # GET: Load chat messages untuk service
│   ├── send_support_message.php          # POST: Kirim support message
│   ├── get_support_messages.php          # GET: Load support messages
│   ├── send_admin_message.php            # POST: Admin kirim message
│   └── typing_status.php                 # POST/GET: Typing indicator
│
├── 📁 assets/                            # Static Assets
│   ├── css/
│   │   ├── style.css                     # Main stylesheet (shared)
│   │   ├── home.css                      # Homepage specific styles
│   │   └── pages.css                     # Pages specific styles
│   ├── js/
│   │   ├── main.js                       # Main JavaScript utilities
│   │   ├── chat.js                       # Chat functionality (real-time messaging)
│   │   └── home.js                       # Homepage interactivity
│   ├── images/
│   │   ├── default/                      # Default/placeholder images
│   │   └── users/                        # User profile photos (generated at runtime)
│   └── lib/                              # Third-party libraries (CDN backup)
│       ├── bootstrap/                    # Bootstrap 5 framework
│       ├── jquery/                       # jQuery 4.0
│       ├── datatables/                   # DataTables plugin + Indonesian locale
│       ├── flatpickr/                    # Date picker library
│       ├── select2/                      # Enhanced select dropdown
│       ├── fontawesome/                  # Font Awesome 6 icons
│       ├── dropzone/                     # File upload library
│       ├── googlefont/                   # Google Fonts
│       └── sweetalert2/                  # Beautiful alerts & modals
│
├── 📁 uploads/                           # User-generated files storage
│   ├── certificates/                     # Sertifikat dari aplikasi technician
│   ├── cvs/                              # CV files dari aplikasi technician
│   ├── parts/                            # Foto suku cadang
│   ├── service_photos/                   # Foto dokumentasi service
│   ├── payment_proofs/                   # Bukti pembayaran/transfer
│   └── temp/                             # Temporary files
│
└── 📊 Database Tables (lihat Struktur Database di bawah)
```

## 🗄️ Struktur Database

Aplikasi menggunakan MySQL dengan 9 tabel utama:

### Tabel: `users`
Menyimpan semua pengguna (user, technician, admin)
```sql
- id (PK)
- name, email, password
- role (enum: 'user', 'admin', 'technician')
- phone, address, city, province, postal_code
- profile_photo, gender, birth_date
- security_question, security_answer
- is_temp_password, temp_password
- created_at
```

### Tabel: `services`
Menyimpan request layanan perbaikan elektronik
```sql
- id (PK)
- user_id (FK), technician_id (FK)
- device, problem, service_type (enum: 'onsite', 'pickup')
- status (enum: 'pending', 'visit', 'accepted', 'repairing', 'done')
- estimated_cost, used_parts
- order_id (FK - untuk parts yang dipakai)
- created_at
```

### Tabel: `service_updates`
Menyimpan progress update setiap service
```sql
- id (PK)
- service_id (FK)
- status (enum: same as services)
- note, photo (dokumentasi perbaikan)
- updated_at
```

### Tabel: `parts`
Katalog suku cadang yang dijual
```sql
- id (PK)
- name, price, stock
- description, image, category
- brand, warranty_months
```

### Tabel: `orders`
Menyimpan pesanan suku cadang (e-commerce)
```sql
- id (PK)
- user_id (FK)
- total_price, status (enum: 'pending', 'paid', 'shipped', 'completed')
- shipping_address, shipping_city, shipping_postal
- shipping_cost, notes, payment_method
- created_at
```

### Tabel: `order_items`
Detail item dalam setiap order
```sql
- id (PK)
- order_id (FK), part_id (FK)
- quantity, price
```

### Tabel: `transactions`
Menyimpan catatan transaksi pembayaran
```sql
- id (PK)
- service_id (FK) atau order_id (FK)
- total_amount, payment_method
- payment_status (enum: 'pending', 'pending_confirmation', 'paid')
- payment_proof, payment_note
- fee_percentage, fee_amount, technician_earning
- paid_at, confirmed_by, confirmed_at
- created_at
```

### Tabel: `chat_messages`
Menyimpan pesan chat antara user dan technician
```sql
- id (PK)
- from_user_id (FK), to_user_id (FK)
- service_id (FK)
- message, is_read
- created_at
```

### Tabel: `support_chat`
Menyimpan chat support dengan admin
```sql
- id (PK)
- session_id (unique per conversation)
- user_id (FK), admin_id (FK)
- message, sender_type (enum: 'user', 'admin')
- is_read
- created_at
```

### Tabel: `technician_applications`
Menyimpan aplikasi calon technician
```sql
- id (PK)
- name, email, phone
- specialty, experience_years
- status (enum: 'pending', 'approved', 'rejected')
- portfolio, certificate, cv_file
- applied_at, reviewed_at, reviewed_by, admin_note
```

### Tabel: `technicians`
Profile extended untuk technician users
```sql
- id (PK)
- user_id (FK)
- specialty, status (enum: 'available', 'busy')
- experience_years
- application_id (FK), is_active
- created_at, updated_at
```

### Tabel: `reviews`
Penilaian dan review dari customer untuk technician
```sql
- id (PK)
- user_id (FK), technician_id (FK)
- service_id (FK)
- rating (1-5), comment
- created_at
```

### Tabel: `technician_earnings`
Perhitungan earning technician per transaction
```sql
- id (PK)
- technician_id (FK), transaction_id (FK)
- amount, fee_percentage, fee_amount, net_amount
- created_at
```

## 🛠️ Persyaratan Sistem
- Apache Web Server (dengan mod_rewrite)
- jQuery 4.0.0
- Bootstrap 5
- Node.js (opsional, untuk development tools)

### Library Dependencies

- **Bootstrap 5**: CSS Framework
- **DataTables**: Tabel interaktif
- **Select2**: Dropdown enhancement
- **Flatpickr**: Date picker
- **Dropzone**: File upload
- **FontAwesome**: Icon library
- **Google Fonts**: Typography
- **SweetAlert2**: Alert dialogs

## 📦 Instalasi

### 1. Clone Repository
```bash
cd c:/laragon/www
git clone <repository-url> loaz_industries
cd loaz_industries
```

### 2. Setup Database

```bash
# Import database dari file SQL
mysql -u root -p loaz_industries < loaz_industries.sql
```

### 3. Konfigurasi Database

Edit file `config/database.php` dengan kredensial database Anda:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'loaz_industries');
```

### 4. Setup Folder Uploads

Pastikan folder `uploads/` memiliki permission write:

```bash
chmod -R 777 uploads/
```

### 5. Buka di Browser

```
http://localhost/loaz_industries
```

## 🔧 Konfigurasi Database

### Credentials Default (dapat disesuaikan)

- **Host**: localhost
- **User**: root
- **Password**: (kosong atau sesuai konfigurasi)
- **Database**: loaz_industries

Pastikan database sudah dibuat sebelum mengimport SQL:

```sql
CREATE DATABASE loaz_industries;
CREATE DATABASE loaz_industries CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 🚀 Penggunaan

### User (Pengguna)

1. Daftar akun di halaman `/auth/register.php`
2. Login ke dashboard pengguna
3. Browse layanan atau memesan suku cadang
4. Lakukan pembayaran di halaman checkout
5. Track pesanan di `user/my_orders.php`
6. Chat dengan teknisi di `user/chat.php`

### Technician (Teknisi)

1. Apply posisi di halaman `/career/apply.php`
2. Tunggu persetujuan dari admin
3. Login ke dashboard teknisi
4. Terima dan update status pesanan
5. Monitor penghasilan di `technician/earnings.php`
6. Komunikasi dengan pengguna via chat

### Admin

1. Login ke `/admin/dashboard.php`
2. Kelola semua aspek aplikasi:
   - Approve/reject aplikasi teknisi
   - Monitor pesanan dan transaksi
   - Kelola pengguna dan teknisi
   - View laporan penjualan
   - Manage support chat

## 🔐 Fitur Keamanan

- **Password Hashing**: Enkripsi password dengan bcrypt
- **Session Management**: Sesi pengguna yang aman
- **Input Validation**: Validasi input di semua form
- **CSRF Protection**: Token CSRF untuk form submission
- **SQL Injection Prevention**: Prepared statements dengan PDO/MySQLi

## � API Endpoints

Semua API endpoints memerlukan active session (authentication) dan mengembalikan JSON response.

### Chat APIs

#### `POST /api/send_message.php`
Kirim pesan chat ke user/technician dalam konteks service
```json
Request Body: {
    "to_user_id": int,
    "service_id": int,
    "message": string
}

Response: {
    "success": boolean,
    "message_id": int,
    "error": string (jika gagal)
}
```

#### `GET /api/get_messages.php`
Retrieve chat messages untuk service tertentu
```javascript
Query Params: 
    - service_id (required): int
    - limit (optional): int, default 50

Response: {
    "success": boolean,
    "messages": [
        {
            "id": int,
            "from_user_id": int,
            "to_user_id": int,
            "message": string,
            "created_at": datetime,
            "is_read": boolean
        }
    ]
}
```

#### `POST /api/send_support_message.php`
Kirim pesan ke support admin
```json
Request Body: {
    "session_id": string,
    "message": string
}

Response: {
    "success": boolean,
    "message_id": int
}
```

#### `GET /api/get_support_messages.php`
Ambil support chat history
```javascript
Query Params:
    - session_id (required): string

Response: {
    "messages": [
        {
            "id": int,
            "message": string,
            "sender_type": "user|admin",
            "created_at": datetime
        }
    ]
}
```

#### `POST /api/typing_status.php`
Update typing indicator
```json
Request Body: {
    "service_id": int,
    "is_typing": boolean
}

Response: {
    "success": boolean
}
```

### AJAX Endpoints (User Panel)

#### `/user/get_cart_ajax.php`
Load cart items dengan detail harga
```response: HTML table dengan item details, quantity, price```

#### `/user/get_cart_count.php`
Get jumlah items di cart
```response: JSON { "count": int }```

#### `/user/add_to_cart_ajax.php`
Tambah item ke cart
```request POST: { part_id, quantity }
response: JSON { success, message, count }```

#### `/user/remove_from_cart_ajax.php`
Hapus item dari cart
```request GET: ?part_id=id
response: JSON { success, message }```

### AJAX Endpoints (Admin Panel)

#### `/admin/get_application_detail.php`
Ambil detail aplikasi calon technician
```request GET: ?id=application_id
response: JSON dengan aplikasi data + file paths```

#### `/admin/get_order_details.php`
Ambil detail pesanan dengan items
```request GET: ?id=order_id
response: JSON dengan order data + items + customer info```

#### `/admin/get_service_detail.php`
Ambil detail service dengan history updates
```request GET: ?id=service_id
response: JSON dengan service + updates + customer + technician```

#### `/admin/get_technician_reviews.php`
Ambil reviews untuk technician
```request GET: ?id=technician_id
response: JSON {
    reviews: [{rating, comment, customer_name, device, created_at}],
    avg_rating: float,
    total_reviews: int
}```

## 📊 Alur Sistem Utama

### 1. Alur Registrasi & Role Assignment
```
Pengunjung
    ↓
Klik "Daftar" → /auth/register.php
    ↓
Input: Nama, Email, Password, Konfirmasi Password
    ↓
Validasi:
    - Email unique (cek di database)
    - Password strength check
    - Email format valid
    ↓
Hash Password → Insert ke users table
    {
        name: input,
        email: input,
        password: password_hash(),
        role: 'user' (default),
        created_at: now
    }
    ↓
Redirect → /auth/login.php
    ↓
User dapat login dengan email/password
```

### 2. Alur Service Request & Completion
```
User Login → /user/dashboard.php
    ↓
Klik "Request Service" → /user/request_service.php
    ↓
Input:
    - Device type (TV, AC, Lemari Es, dll)
    - Problem description
    - Service type (onsite/pickup)
    - Location/address
    ↓
Submit → INSERT services table
    {
        user_id: session user_id,
        device: input,
        problem: input,
        service_type: input,
        status: 'pending',
        estimated_cost: NULL,
        created_at: now
    }
    ↓
Service appears in /admin/dashboard.php & /admin/services.php
    ↓
Technician sees in /technician/dashboard.php
    ↓
Technician Accept → UPDATE services
    {
        status: 'visit',
        technician_id: tech_id
    }
    ↓
Technician Visit Customer → /technician/update_status.php
    {
        status: 'accepted',
        note: 'assessment notes',
        photo: 'foto dokumentasi'
    }
    ↓
Repair Process → /technician/my_services.php
    {
        status: 'repairing',
        updates: multiple updates dengan foto progress
    }
    ↓
Complete Service → /technician/my_services.php
    {
        status: 'done',
        final_note: 'completion details'
    }
    ↓
Create Transaction (INSERT transactions)
    {
        service_id: id,
        total_amount: final cost,
        payment_status: 'pending',
        created_at: now
    }
    ↓
User notified → Go to /user/payment_service.php
    ↓
User Upload Payment Proof or Pay Online
    {
        payment_proof: upload file,
        payment_method: 'transfer/others'
    }
    ↓
Admin Review → /admin/transactions.php
    ↓
Admin Confirm Payment → UPDATE transactions
    {
        payment_status: 'paid',
        confirmed_by: admin_id,
        confirmed_at: now
    }
    ↓
INSERT technician_earnings
    {
        technician_id: id,
        transaction_id: id,
        amount: total_amount,
        fee_percentage: 15 (contoh),
        fee_amount: calculated,
        net_amount: calculated
    }
    ↓
User can Review → /user/my_services.php
    {
        rating: 1-5,
        comment: review text
    }
    ↓
INSERT reviews table
    Complete! ✓
```

### 3. Alur E-Commerce Checkout
```
User Browse Parts (Homepage)
    ↓
Click "Tambah Ke Keranjang"
    ↓
AJAX POST → /user/add_to_cart_ajax.php
    {
        part_id: id,
        quantity: qty
    }
    ↓
Server validate:
    - Part exists
    - Stock available
    - Quantity <= stock
    ↓
Session['cart'][part_id] = qty
    ↓
Response: {success, count}
    ↓
User View /user/cart.php
    ↓
Load cart items via get_cart_ajax.php
    ↓
User can:
    - Update quantity
    - Remove items
    - View total price
    ↓
Click "Checkout"
    ↓
Redirect → /user/checkout.php
    ↓
Input Shipping Details:
    - Address
    - City
    - Postal Code
    - Shipping method
    ↓
Calculate Shipping Cost
    ↓
Submit → CREATE order
    {
        user_id: id,
        total_price: calculated,
        status: 'pending',
        shipping_address: input,
        shipping_city: input,
        shipping_cost: calculated,
        created_at: now
    }
    ↓
INSERT order_items
    For each cart item:
    {
        order_id: id,
        part_id: part_id,
        quantity: qty,
        price: price
    }
    ↓
CREATE transaction
    {
        order_id: id,
        total_amount: order.total_price + shipping,
        payment_status: 'pending',
        created_at: now
    }
    ↓
Redirect → /user/payment.php
    ↓
User Payment:
    - Upload proof OR
    - Pay online (jika terintegrasi)
    ↓
Transaction status: 'pending_confirmation'
    ↓
Admin confirm in /admin/transactions.php
    ↓
Transaction status: 'paid'
    ↓
Order status: 'shipped'
    ↓
Tracked in /user/my_orders.php
    ↓
Admin Update → order status: 'completed'
    ↓
Complete! ✓
```

### 4. Alur Karir & Recruitment
```
Prospek Technician
    ↓
Visit /career/karir.php
    ↓
Click "Lamar Sekarang" → /career/apply.php
    ↓
Fill Form:
    - Name, Email, Phone
    - Specialty
    - Years of Experience
    - Upload CV
    - Upload Certificate
    - Portfolio link
    ↓
Submit → INSERT technician_applications
    {
        name: input,
        email: input,
        phone: input,
        specialty: input,
        experience_years: input,
        status: 'pending',
        cv_file: uploaded_file,
        certificate: uploaded_file,
        portfolio: input,
        applied_at: now
    }
    ↓
Redirect → /career/thank_you.php
    ↓
Admin sees in /admin/technician_applications.php
    ↓
Admin Review Details
    ↓
Admin Approve:
    ↓
    Step 1: Create user account (role='technician')
    {
        name: input,
        email: input,
        password: generated,
        role: 'technician',
        created_at: now
    }
    ↓
    Step 2: INSERT technicians table
    {
        user_id: created_user_id,
        specialty: input,
        experience_years: input,
        application_id: application_id,
        status: 'available',
        is_active: 1
    }
    ↓
    Step 3: UPDATE technician_applications
    {
        status: 'approved',
        reviewed_by: admin_id,
        reviewed_at: now
    }
    ↓
Applicant can Login → /technician/dashboard.php
    ↓
Career Complete! ✓
```

## 🎨 Frontend Libraries & Technologies

| Library | Versi | Fungsi |
|---------|-------|--------|
| Bootstrap | 5.3+ | CSS Framework & Responsive Design |
| jQuery | 4.0.0 | DOM Manipulation & AJAX |
| DataTables | Latest | Interactive Data Tables dengan sorting, filtering |
| Select2 | Latest | Enhanced Select Dropdowns dengan search |
| Flatpickr | Latest | Date & Time Picker |
| Dropzone | Latest | Drag-drop File Upload |
| FontAwesome | 6.4+ | Icon Library (1000+ icons) |
| SweetAlert2 | Latest | Beautiful modals & alerts |
| GoogleFonts | CDN | Typography (Inter font) |

## 🔒 Keamanan

### Implemented Security Measures
- **PDO Prepared Statements**: Mencegah SQL injection
- **Password Hashing**: password_hash() dengan algoritma bcrypt
- **Session Management**: Session-based authentication dengan user_id, role, name
- **Access Control**: Role-based redirects (redirectIfNotAdmin, redirectIfNotLoggedIn)
- **CORS Headers**: Proper origin validation untuk API calls
- **File Upload Validation**: MIME type check, file rename untuk prevent execution
- **Input Validation**: Sanitization di semua form inputs
- **Output Encoding**: htmlspecialchars() untuk prevent XSS

### Security Best Practices (Recommended)
- Implementasi CSRF tokens untuk semua forms
- Rate limiting untuk login attempts
- Two-factor authentication untuk admin
- Email verification untuk registrasi
- API rate limiting untuk endpoints publik
- HTTPS/SSL enforcement di production

## 🐛 Troubleshooting

### Database Connection Issues
```
Error: "Koneksi database gagal"

Solusi:
1. Verifikasi credentials di config/database.php
2. Pastikan MySQL service running (Services > MySQL)
3. Confirm database 'loaz_industries' exists (phpMyAdmin)
4. Check MySQL port (default 3306)
5. Verify charset: utf8mb4
```

### Session/Login Issues
```
Error: "Session lost setelah login" atau redirect loop

Solusi:
1. Cek session_start() di awal setiap file
2. Verify PHP session folder writable (Linux/Mac):
   - /var/lib/php/sessions
   - atau run: chmod 1777 /var/lib/php/sessions
3. Check php.ini session settings:
   - session.cookie_httponly = 1
   - session.cookie_secure = 0 (untuk local dev)
4. Clear browser cookies & try again
```

### File Upload Failures
```
Error: "File tidak bisa diupload" atau "Maximum file size exceeded"

Solusi:
1. Check folder permissions (755 atau 777):
   - chmod -R 777 uploads/
2. Verify php.ini settings:
   - upload_max_filesize = 100M
   - post_max_size = 100M
3. Check disk space availability
4. Verify target folder exists
5. Check temp folder writable
```

### Chart/Report Empty
```
Error: "Chart tidak menampilkan data" di dashboard/reports

Solusi:
1. Verify data in database (phpMyAdmin queries)
2. Check date format consistency (YYYY-MM-DD)
3. Verify SQL query syntax (admin/dashboard.php lines 30-50)
4. Check browser console untuk JavaScript errors:
   - F12 > Console tab
   - Look for undefined variables atau fetch errors
5. Verify Chart.js library loaded correctly
```

### Payment Confirmation Stuck
```
Error: "Transaction tetap 'pending_confirmation'"

Solusi:
1. Admin verify payment proof uploaded (check /uploads/payment_proofs/)
2. Verify transaction record exists in database
3. Check admin permissions (role='admin')
4. Try reload /admin/transactions.php page
5. Check browser console untuk errors
```

## 📋 Maintenance Checklist

### Regular Tasks
- [ ] Backup database mingguan
- [ ] Check error logs (tail -f error.log)
- [ ] Verify uploads folder disk usage
- [ ] Review new technician applications (weekly)
- [ ] Monitor payment confirmations (daily)
- [ ] Check service completion rates
- [ ] Review customer feedback & ratings

### Monthly
- [ ] Archive old transactions
- [ ] Review & update pricing/fees
- [ ] Analyze traffic & usage statistics
- [ ] Update currency rates (jika ada)
- [ ] Clean temp upload folder

### Quarterly
- [ ] Security audit (password policies, access logs)
- [ ] Database optimization (ANALYZE, OPTIMIZE tables)
- [ ] Update third-party libraries (if needed)
- [ ] Review & test disaster recovery
- [ ] Performance optimization

## 📝 Dokumentasi Tambahan

### Environment Variables (Future)
Untuk production, pertimbangkan menggunakan .env file:
```
DB_HOST=localhost
DB_NAME=loaz_industries
DB_USER=production_user
DB_PASS=secure_password
APP_ENV=production
APP_DEBUG=false
```

### Logging
Log file locations (untuk setup):
```
/logs/error.log          # PHP errors
/logs/access.log         # Access logs
/logs/database.log       # Database queries (debug mode)
```

### Performance Tips
- Use browser caching untuk static assets
- Implement CDN untuk images
- Database query optimization dengan indexes
- Implement Redis/Memcached untuk session caching
- Minify CSS & JavaScript di production

## 🤝 Kontribusi

Untuk berkontribusi pada proyek ini:

1. Fork repository
2. Buat branch fitur (`git checkout -b feature/AmazingFeature`)
3. Follow coding standards (PSR-12 untuk PHP)
4. Commit dengan pesan jelas (`git commit -m 'Add some AmazingFeature'`)
5. Push ke branch (`git push origin feature/AmazingFeature`)
6. Buat Pull Request dengan deskripsi detail

## 📄 Lisensi

Proyek ini dilindungi oleh hak cipta **Loaz Industries 2026**.
Penggunaan komersial tanpa izin dilarang.

## 📧 Support & Contact

Untuk pertanyaan, bug reports, atau feature requests:
- **Email**: support@loaz.com
- **Support Chat**: Gunakan support_chat.php di aplikasi
- **Career Inquiries**: /career/karir.php
- **Developer**: aryashevana20@gmail.com

## 📞 Quick Links

- [Halaman Utama](http://localhost/loaz_industries)
- [Login](http://localhost/loaz_industries/auth/login.php)
- [Registrasi](http://localhost/loaz_industries/auth/register.php)
- [Karir/Rekrutmen](http://localhost/loaz_industries/career/karir.php)
- [Admin Panel](http://localhost/loaz_industries/admin/dashboard.php)

---

**Project Version**: 1.0 (Stable)
**Last Updated**: April 29, 2026
**Status**: Production Ready ✓
**Database**: MySQL 8.0.30
**PHP Version**: 8.1.10+
**Framework**: Vanilla PHP with Bootstrap 5
