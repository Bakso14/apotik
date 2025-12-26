# SID Apotek — Aplikasi Apotek Sederhana (PHP Native + MySQL)

Aplikasi web ringan untuk operasional apotek: master data obat/pelanggan/dokter/supplier, transaksi pembelian dan penjualan, validasi klinis dasar, serta laporan. Dirancang agar mudah dipasang (cocok untuk Laragon/XAMPP di Windows).

Catatan kredensial: autentikasi saat ini memakai MD5 (server-side) untuk kesederhanaan. Untuk produksi sangat disarankan migrasi ke password hashing modern (password_hash). Lihat bagian Catatan Produksi.

## Instalasi Cepat (Windows + Laragon)

1) Siapkan lingkungan
- Install Laragon (https://laragon.org) atau XAMPP. Pastikan MySQL aktif.

2) Buat database
- Buka MySQL (phpMyAdmin/HeidiSQL). Buat database: `apotikdb` (utf8mb4).
- Import skema: file `sql/schema.sql`.

3) Buat akun admin pertama
- Jalankan SQL berikut untuk membuat user admin:
  INSERT INTO users (username,password_hash,nama,role) VALUES ('admin', MD5('admin123'), 'Administrator', 'admin');

4) Konfigurasi koneksi database
- Edit file `src/config/env.php` sesuai environment Anda:
  - DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT
  - OPTIONAL: `REQUIRE_API_KEY` dan `API_KEY` jika ingin kunci API.

5) Jalankan aplikasi
- Pastikan document root mengarah ke folder `public`.
  - Di Laragon: Menu > www > atur project path ke `.../apotik/public` atau akses via `http://localhost/apotik/public`.
- Buka UI: `http://localhost/apotik/public/app/login.html`
  - Login contoh: username `admin`, password `admin123` (dari langkah 3).

6) Struktur folder penting
- `public/` — Front controller PHP (`index.php`) dan UI (`public/app/*`).
- `src/` — Kode PHP (Database, Audit, Config, dll).
- `sql/` — Skema dan data contoh.

## Cara Pakai Singkat

1. Login melalui `app/login.html` lalu otomatis diarahkan ke dashboard.
2. Modul Obat: tambah/edit/hapus dan pantau stok/kedaluwarsa.
3. Modul Pembelian: input faktur, item, dan update stok otomatis + kartu stok.
4. Modul Penjualan: buat nota, cek alergi/interaksi dasar, update stok dan total.
5. Laporan: ringkasan harian dan rentang tanggal (penjualan, top item, jenis transaksi).

Tip UI:
- Kolom search cepat tersedia di banyak tabel.
- Tombol Prev/Next untuk paginasi sederhana.

## Endpoint API (Ringkas)

- GET `/api/obat` — list 100 obat.
- POST `/api/obat` — tambah obat.
- GET `/api/obat/{kode}` — detail.
- PUT/PATCH `/api/obat/{kode}` — update parsial.
- DELETE `/api/obat/{kode}` — hapus.
- GET `/api/health` — cek.

Body POST contoh:

```json
{
  "kode": "AMOX500",
  "nama": "Amoxicillin 500 mg",
  "produsen": "PT Farmasi",
  "harga": 12000,
  "stok": 50,
  "expired_date": "2026-12-31",
  "golongan": "OK"
}
```

## Catatan Keselamatan & Kepatuhan
- Validasi tanggal kadaluarsa saat penjualan (tidak boleh lewat).
- Cek alergi pasien dan interaksi obat (tabel `interaksi_obat`).
- Narkotika/psikotropika butuh pencatatan khusus (kolom flag disediakan).
- Audit log untuk semua transaksi penting.

 
Keamanan & Konfigurasi:
- Ganti MD5 ke `password_hash()`/`password_verify()` pada endpoint login (PHP >= 7.4). Simpan password dengan bcrypt/argon2.
- Atur `REQUIRE_API_KEY` ke `true` dan set `API_KEY` unik untuk integrasi API.
- Pastikan `public/` saja yang bisa diakses web server. Folder `src/`, `sql/` jangan diekspos publik.
- Nonaktifkan display_errors di PHP untuk produksi; gunakan logging saja.

Database & Backup:
- Gunakan user MySQL khusus dengan hak minimal untuk database `apotikdb`.
- Jadwalkan backup harian (mysqldump) dan uji restore berkala.

Deployment:
- Gunakan domain/HTTPS (Let’s Encrypt). Pastikan cookie sesi aman (secure/httponly).
- Set time zone server ke `Asia/Jakarta` (php.ini/date.timezone).

Kustomisasi & Branding:
- Logo dan nama aplikasi dapat diubah di `public/app/login.html` (elemen .logo/.brand) dan `public/app/partials/*`.
- Warna tema UI di `public/app/admin.css` dan `styles.css`.

## Dukungan

Jika Anda membeli paket ini untuk dijual ke klien:
- Sertakan panduan instalasi ini dan kredensial awal (username/password admin).
- Sediakan kontak dukungan dan perjanjian SLA sesuai kebutuhan.

