<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

# Centralized Fingerprint Management System

A robust and modern web-based application to manage **ZKTeco Biometric Devices**, Employee Data, and Attendance Logs centrally. Built with **Laravel 11**, **Filament v3**, and a custom **Smart Sync Engine**.

## 🚀 Key Features

### 1. Smart Sync Engine
The heart of the system is the **Smart Sync** feature (`/admin/smart-sync`), which offers:
-   **Bi-Directional Scanning**: Compares data between the Database and a selected Machine in real-time.
-   **Granular Control**: Sync users One-by-One or Bulk (Push to Device / Import to DB).
-   **Device Explorer**: View **ALL** users currently stored on a device, including their **Fingerprint Count**.
-   **Visual Diff**: Color-coded lists show exactly who is missing where.

### 2. Intelligent Device Monitoring
-   **Sticky Status**: Machine status (Online/Offline) is persistent based on the last interaction (Ping/Connect). No more flickering statuses.
-   **Real-time Actions**:
    -   **Test Connection**: Verifies network, updates status immediately.
    -   **Ping**: Simple ICMP check.
    -   **Pull Employees**: Fetches user data from the machine.
-   **Detailed Info**: View IP, Port, Protocol, and User Count at a glance.

### 3. Strict Admin Panel (Filament)
Organized strictly according to the operational hierarchy:
-   **Monitoring**: Data Mesin, Status Sinkronisasi, Smart Sync.
-   **Personalia**: Data Karyawan (with Distribution Matrix & Fingerprint Count), Data Jari & Wajah.
-   **Absensi**: Log Kehadiran, Laporan.
-   **Pengaturan**: User Management, App Settings.

### 4. Simulation & Testing
-   **Dummy Data Seeder**: Built-in seeder to populate the DB with 50+ fake employees, devices, and attendance logs for testing.
-   **Visual Feedback**: Loading indicators on all long-running processes (Scanning, Pushing, Importing).

---

## 🛠️ Technology Stack
-   **Framework**: Laravel 11.x
-   **Admin Panel**: FilamentPHP v3
-   **Biometric Library**: `rats/zkteco` (Modified for persistent connections)
-   **Frontend**: Livewire + Blade + TailwindCSS
-   **Database**: MySQL / MariaDB

---

## 📦 Installation

1.  **Clone Repository**
    ```bash
    git clone https://github.com/BobbyHM-21/web-fingerprint-system.git
    cd web-fingerprint-system
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    npm install && npm run build
    ```

3.  **Setup Environment**
    ```bash
    cp .env.example .env
    # Configure your DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env
    php artisan key:generate
    ```

4.  **Migrate Database**
    ```bash
    php artisan migrate
    ```

5.  **Seed Dummy Data (Optional)**
    To see the system in action with fake data:
    ```bash
    php artisan db:seed --class=DummyDataSeeder
    ```

6.  **Create Admin User**
    ```bash
    php artisan make:filament-user
    ```

7.  **Run Server**
    ```bash
    php artisan serve
    ```

---

## 📸 Usage

### Using Smart Sync
1.  Go to **Monitoring > Smart Sync**.
2.  Select a **Target Device** from the dropdown.
3.  Click **Start Scan**.
4.  Review the **Differences** (Left: Missing in Device, Right: Missing in DB).
5.  Use **Push** or **Import** buttons to sync data.
6.  Scroll down to see the **Full Device Content** table.

### Device Management
1.  Go to **Monitoring > Data Mesin**.
2.  Click **Test** or **Ping** to check connectivity.
3.  If successful, the status becomes **Green (Online)**.
4.  If failed, the status becomes **Red (Offline)**. 

---

## 🔒 Security
-   **CSRF Protection**: ADMS routes are excluded for device communication compatibility.
-   **Strict Types**: ZKTeco communication enforces strict Integer types for critical IDs to prevent device errors.

---

© 2026 Fingerprint Management System.
