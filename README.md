# Shopify Laravel Middleware

Aplikasi middleware untuk sinkronisasi produk antara database lokal dan Shopify menggunakan Laravel 12.

## 📋 Fitur

- **Dashboard Web** - Manajemen produk dengan UI lengkap
- **REST API** - API endpoints untuk integrasi eksternal
- **Shopify Sync** - Sinkronisasi dua arah dengan Shopify
- **Image Upload** - Upload gambar langsung ke Shopify CDN
- **Variant Management** - Manajemen varian produk

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.5+
- Composer
- Node.js & Bun
- SQLite (default) atau MySQL/PostgreSQL

### Installation

```bash
# Clone repository
git clone <repository-url>
cd shopify-laravel

# Install dependencies
composer install
bun install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure Shopify credentials in .env
SHOPIFY_ACCESS_TOKEN=your_shopify_access_token
SHOPIFY_DOMAIN=your-store.myshopify.com
SHOPIFY_API_VERSION=2025-10

# Run migrations
php artisan migrate

# Build assets
bun run build

# Start server
php artisan serve
```

### Create Admin User

```bash
php artisan tinker --execute="
\$user = \App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password')
]);
echo 'User created!' . PHP_EOL;
"
```

---

## 🖥️ Web Dashboard

Akses dashboard di `http://localhost:8000` setelah login.

### Routes Web

| Method | URL | Deskripsi |
|--------|-----|-----------|
| GET | `/login` | Halaman login |
| POST | `/login` | Proses login |
| POST | `/logout` | Logout |
| GET | `/products` | Daftar produk |
| GET | `/products/create` | Form tambah produk |
| POST | `/products` | Simpan produk baru (+ sync ke Shopify) |
| GET | `/products/{id}` | Detail produk |
| GET | `/products/{id}/edit` | Form edit produk |
| PUT | `/products/{id}` | Update produk (+ sync ke Shopify) |
| DELETE | `/products/{id}` | Hapus produk (+ hapus dari Shopify) |
| POST | `/products/{id}/upload-image` | Upload gambar ke Shopify |
| POST | `/products/sync-from-shopify` | Import produk dari Shopify |

### Fitur Dashboard

1. **List Products** - Lihat semua produk dengan pagination
2. **Create Product** - Buat produk baru dengan variants, langsung sync ke Shopify
3. **Edit Product** - Edit produk dan variants
4. **View Product** - Lihat detail produk, variants, dan images
5. **Upload Image** - Upload gambar langsung ke Shopify CDN
6. **Sync from Shopify** - Import/update produk dari Shopify

---

## 🔌 REST API

### Base URL
```
http://localhost:8000/api/v1
```

### Authentication

API menggunakan **Laravel Sanctum** untuk autentikasi. Lihat [AUTHENTICATION_GUIDE.md](AUTHENTICATION_GUIDE.md) untuk detail lengkap.

#### Mendapatkan Token

```bash
# Login untuk mendapatkan token
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "password"}'
```

#### Menggunakan Token

```bash
curl -X GET http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer {your_token}"
```

### Public Endpoints (Tanpa Auth)

#### Health Check
```
GET /api/v1/health              # Status aplikasi
GET /api/v1/health/detailed     # Status detail
GET /api/v1/health/database     # Status database
GET /api/v1/health/shopify      # Status koneksi Shopify
```

#### Search
```
GET /api/v1/search/products     # Cari produk
GET /api/v1/search/variants     # Cari variant
GET /api/v1/search/suggestions  # Saran pencarian
GET /api/v1/search/filters      # Filter tersedia
```

### Protected Endpoints (Memerlukan Auth)

#### Authentication
```
POST /api/v1/auth/login         # Login, mendapat token
POST /api/v1/auth/logout        # Logout, revoke token
GET  /api/v1/auth/me            # Info user saat ini
POST /api/v1/auth/refresh       # Refresh token
```

#### Products
```
GET    /api/v1/products                 # List semua produk
POST   /api/v1/products                 # Buat produk baru
GET    /api/v1/products/{id}            # Detail produk
PUT    /api/v1/products/{id}            # Update produk
DELETE /api/v1/products/{id}            # Hapus produk
POST   /api/v1/products/{id}/images     # Upload gambar
```

#### Bulk Operations
```
POST   /api/v1/products/bulk                    # Bulk create
PATCH  /api/v1/products/bulk                    # Bulk update
DELETE /api/v1/products/bulk                    # Bulk delete
GET    /api/v1/products/bulk/status/{id}        # Status operasi
```

#### Variants
```
GET    /api/v1/variants                         # List semua variant
GET    /api/v1/variants/{id}                    # Detail variant
PUT    /api/v1/variants/{id}                    # Update variant
DELETE /api/v1/variants/{id}                    # Hapus variant
PATCH  /api/v1/variants/{id}/inventory          # Update inventory
GET    /api/v1/products/{id}/variants           # Variants per produk
POST   /api/v1/variants/products/{id}/variants  # Buat variant baru
```

#### Shopify Sync
```
POST /api/v1/shopify/import                 # Import dari Shopify
POST /api/v1/shopify/export/{id}            # Export produk ke Shopify
POST /api/v1/shopify/export/bulk            # Bulk export
GET  /api/v1/shopify/sync/status            # Status sync
POST /api/v1/shopify/sync/validate          # Validasi kredensial
GET  /api/v1/shopify/sync/conflicts         # Cek konflik
```

---

## 📦 Data Models

### Product

```json
{
  "id": 1,
  "shopify_product_id": 123456789,
  "title": "Product Name",
  "body_html": "<p>Description</p>",
  "vendor": "Vendor Name",
  "product_type": "Category",
  "status": "active",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "variants": [...],
  "images": [...]
}
```

**Status values:** `active`, `draft`, `archived`

### Variant

```json
{
  "id": 1,
  "product_id": 1,
  "shopify_variant_id": 987654321,
  "title": "Default Title",
  "option1": "Size M",
  "option2": "Color Red",
  "price": "29.99",
  "sku": "SKU-001",
  "inventory_quantity": 100
}
```

### Product Image

```json
{
  "id": 1,
  "product_id": 1,
  "shopify_image_id": 111222333,
  "path": "https://cdn.shopify.com/...",
  "url": "https://cdn.shopify.com/..."
}
```

---

## 🔄 Shopify Integration

### Konfigurasi

Tambahkan credentials di `.env`:

```env
SHOPIFY_ACCESS_TOKEN=shpat_xxxxxxxxxxxxx
SHOPIFY_DOMAIN=your-store.myshopify.com
SHOPIFY_API_VERSION=2025-10
```

### Cara Kerja Sync

1. **Create Product** - Produk otomatis dibuat di Shopify saat dibuat di dashboard
2. **Update Product** - Perubahan otomatis di-sync ke Shopify
3. **Delete Product** - Produk dihapus dari Shopify dan database lokal
4. **Import from Shopify** - Klik "Sync from Shopify" untuk import/update produk
5. **Upload Image** - Gambar di-upload langsung ke Shopify CDN, URL disimpan di database

### Image Upload Flow

```
User pilih file → Upload ke Shopify API → Dapat URL dari Shopify CDN → Simpan URL ke database
```

**Note:** Produk harus sudah ter-sync ke Shopify sebelum bisa upload gambar.

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=ProductApiTest

# Run with coverage
php artisan test --coverage
```

---

## 📁 Project Structure

```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/          # API Controllers
│   │   │   ├── Auth/            # Auth Controllers
│   │   │   └── ProductController.php  # Web Controller
│   │   └── Requests/            # Form Requests
│   ├── Models/                  # Eloquent Models
│   └── Services/
│       └── ShopifyService.php   # Shopify API Integration
├── resources/
│   └── views/
│       ├── auth/                # Login views
│       ├── layouts/             # Layout templates
│       └── products/            # Product views
├── routes/
│   ├── api.php                  # API routes
│   └── web.php                  # Web routes
└── tests/
    └── Feature/                 # Feature tests
```

---

## 🔧 Artisan Commands

```bash
# Clear caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# List routes
php artisan route:list

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed
```

---

## 📚 Documentation

- [Authentication Guide](AUTHENTICATION_GUIDE.md) - Detail autentikasi API
- [API Documentation](http://localhost:8000/docs/api) - Swagger/OpenAPI docs (jika tersedia)

---

## 🛠️ Tech Stack

- **Framework:** Laravel 12
- **PHP:** 8.5+
- **Database:** SQLite (default) / MySQL / PostgreSQL
- **Authentication:** Laravel Sanctum
- **Frontend:** Blade + Tailwind CSS v4
- **Package Manager:** Composer + Bun
- **API:** Shopify GraphQL Admin API

---

## 📄 License

MIT License
