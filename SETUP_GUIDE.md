# 📘 Panduan Setup Lokal - Fingerprint Management System

Panduan lengkap untuk menjalankan project ini di komputer lokal Anda.

---

## 📋 Prasyarat

Pastikan sudah terinstall:
- **Docker Desktop** (untuk Windows/Mac) atau **Docker Engine** (untuk Linux)
- **Git**
- **Composer** (PHP Dependency Manager)
- **Node.js** (v18 atau lebih baru)

---

## 🚀 Langkah-Langkah Setup

### 1. Clone Repository

```bash
git clone https://github.com/BobbyHM-21/web-fingerprint-system.git
cd web-fingerprint-system
```

### 2. Copy File Environment

```bash
# Windows (PowerShell)
copy .env.example .env

# Linux/Mac
cp .env.example .env
```

### 3. Konfigurasi Database di `.env`

Buka file `.env` dan pastikan konfigurasi database seperti ini:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=fingerprint_db
DB_USERNAME=sail
DB_PASSWORD=password
```

> **Catatan**: Jangan ubah `DB_HOST=mysql` karena ini adalah nama service Docker.

### 4. Install Dependencies PHP

```bash
composer install
```

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Jalankan Docker Container

```bash
# Start semua container (MySQL + Laravel)
docker compose up -d

# Cek status container
docker compose ps
```

Output yang benar:
```
NAME                    STATUS
web-fp-laravel.test-1   Up
web-fp-mysql-1          Up (healthy)
```

### 7. Jalankan Migrasi Database

```bash
# Masuk ke container Laravel
docker compose exec laravel.test bash

# Jalankan migrasi
php artisan migrate

# Keluar dari container
exit
```

**Atau langsung dari luar container:**
```bash
docker compose exec -T laravel.test php artisan migrate
```

### 8. Seed Data Dummy (Opsional)

Untuk testing, isi database dengan data palsu:

```bash
docker compose exec -T laravel.test php artisan db:seed --class=DummyDataSeeder
```

Data yang dibuat:
- 50 Karyawan palsu
- 3 Device simulasi
- Random Attendance Logs

### 9. Buat User Admin

```bash
docker compose exec laravel.test php artisan make:filament-user
```

Isi data yang diminta:
```
Name: Admin
Email: admin@example.com
Password: password (atau password pilihan Anda)
```

### 10. Install Dependencies Frontend

```bash
npm install
```

### 11. Build Assets (Production)

```bash
npm run build
```

**Atau untuk Development (Hot Reload):**

> ⚠️ **Catatan PowerShell**: Jika Anda menggunakan PowerShell dan mendapat error "running scripts is disabled", jalankan:
> ```powershell
> Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
> ```

```bash
npm run dev
```

### 12. Akses Aplikasi

Buka browser dan akses:
```
http://localhost/admin
```

Login dengan kredensial yang dibuat di Step 9.

---

## 🔧 Troubleshooting

### Problem: Container MySQL tidak mau start

**Solusi:**
```bash
# Stop semua container
docker compose down

# Hapus volume (HATI-HATI: Data akan hilang)
docker compose down -v

# Start ulang
docker compose up -d
```

### Problem: Port 80 sudah dipakai

**Solusi:** Edit file `compose.yaml`, ubah mapping port:
```yaml
ports:
    - '8080:80'  # Ganti 80 menjadi 8080
```

Akses menjadi: `http://localhost:8080/admin`

### Problem: Error "SQLSTATE[HY000] [2002] Connection refused"

**Penyebab:** Database belum siap.

**Solusi:**
```bash
# Tunggu sampai MySQL healthy
docker compose ps

# Jika status "starting", tunggu 30 detik lalu coba lagi
```

### Problem: npm run dev error "scripts is disabled"

**Solusi (PowerShell):**
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Problem: Halaman blank / error 500

**Solusi:**
```bash
# Clear cache
docker compose exec laravel.test php artisan cache:clear
docker compose exec laravel.test php artisan config:clear
docker compose exec laravel.test php artisan view:clear

# Set permission storage (Linux/Mac)
docker compose exec laravel.test chmod -R 775 storage bootstrap/cache
```

---

## 📊 Verifikasi Setup

Setelah login, pastikan menu sidebar muncul dengan struktur:

- **Monitoring**
  - Data Mesin
  - Status Sinkronisasi
  - Smart Sync
- **Personalia**
  - Data Karyawan
  - Data Jari & Wajah
- **Absensi**
  - Log Kehadiran
  - Laporan
- **Pengaturan**
  - User Management
  - App Settings

---

## 🧪 Testing Smart Sync

1. Buka menu **Monitoring > Smart Sync**
2. Pilih device dari dropdown (jika sudah seed dummy data)
3. Klik **Start Scan**
4. Lihat hasil diff dan tabel "Full Device Content"

> **Catatan**: Karena device adalah simulasi, koneksi akan gagal. Untuk testing real, tambahkan device ZKTeco yang sebenarnya di menu **Data Mesin**.

---

## 🛑 Menghentikan Project

```bash
# Stop container (data tetap ada)
docker compose stop

# Stop dan hapus container (data tetap ada di volume)
docker compose down

# Stop dan hapus SEMUA termasuk data
docker compose down -v
```

---

## 🔄 Update Code dari GitHub

```bash
# Pull update terbaru
git pull origin main

# Install dependency baru (jika ada)
composer install
npm install

# Jalankan migrasi baru (jika ada)
docker compose exec -T laravel.test php artisan migrate

# Rebuild assets
npm run build
```

---

## 📞 Bantuan Lebih Lanjut

Jika ada masalah:
1. Cek log container: `docker compose logs -f laravel.test`
2. Cek log Laravel: `storage/logs/laravel.log`
3. Pastikan semua service running: `docker compose ps`

---

© 2026 Fingerprint Management System
