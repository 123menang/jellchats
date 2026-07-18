# JellChat Pro - Live Chat Application

Aplikasi live chat dengan AI assistant, bot modules, dan multi-agent support.

## Fitur Utama

- **Real-time Chat** - Polling-based chat dengan typing indicator
- **4 Reply Modes** - Manual, Bot Module, AI Assistant, Hybrid Team
- **AI Integration** - Support OpenAI & Claude API
- **Visitor Tracking** - IP, lokasi, user agent, visit history
- **License System** - Starter, Team, Business tiers
- **Embed Widget** - Customizable widget untuk website

## Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/username/jellchat-pro.git
cd jellchat-pro
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Setup Database

Database SQLite akan **otomatis dibuat** saat pertama kali diakses. Struktur tabel akan di-inisialisasi dari `database/schema.sql`.

Atau buat manual:
```bash
mkdir -p storage/database
sqlite3 storage/database/livechat.db < database/schema.sql
```

### 4. Setup Permissions

```bash
chmod -R 775 storage/
chmod -R 775 uploads/
chmod -R 775 public/uploads/
```

### 5. Konfigurasi Web Server

Point document root ke folder `public/` atau gunakan `.htaccess` di root untuk redirect.

### 6. Login

- **URL**: `http://your-domain/login`
- **Username**: `admin`
- **Password**: `admin`

> **PENTING**: Ganti password default setelah login pertama!

## Deploy Otomatis

Gunakan script deploy untuk setup lengkap (nginx + SSL + database):

```bash
chmod +x deploy.sh
sudo ./deploy.sh
```

## Struktur Project

```
├── public/          # Web root (index.php, assets)
├── src/             # PHP classes (PSR-4 autoload)
├── includes/        # Core functions (db.php)
├── views/           # PHP templates
├── routes/          # URL routing
├── widget/          # Embed widget files
├── database/
│   ├── schema.sql   # Database structure
│   └── migrations/  # Migration files
├── storage/         # Runtime (logs, db) - NOT in git
├── uploads/         # User uploads - NOT in git
└── deploy.sh        # Auto-deploy script
```

## Konfigurasi AI

Untuk menggunakan AI Assistant:
1. Login ke admin panel
2. Buka **Agents** > Edit agent
3. Masukkan API token (OpenAI/Claude)
4. Pilih mode **AI** atau **Hybrid**

## Requirements

- PHP 8.1+
- SQLite3
- Composer

## License

Proprietary - JellPlay
