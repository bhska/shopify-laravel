# API Authentication Guide

## 📋 Overview

Aplikasi ini menggunakan **Laravel Sanctum** untuk autentikasi API. Ada dua tipe endpoints:

1. **Public Endpoints** - Tidak memerlukan autentikasi
2. **Protected Endpoints** - Memerlukan API Token (Sanctum)

---

## 🔓 Public Endpoints (Tanpa Autentikasi)

Endpoint berikut dapat diakses **tanpa token**:

### Authentication
- `POST /api/v1/auth/login` - Login dan mendapatkan token

### Health Check
- `GET /api/v1/health` - Health check sederhana
- `GET /api/v1/health/detailed` - Health check detail
- `GET /api/v1/health/database` - Cek koneksi database
- `GET /api/v1/health/shopify` - Cek koneksi Shopify

### Search (Public untuk Demo)
- `GET /api/v1/search/products` - Cari produk
- `GET /api/v1/search/variants` - Cari variant
- `GET /api/v1/search/suggestions` - Saran pencarian
- `GET /api/v1/search/filters` - Filter pencarian

---

## 🔒 Protected Endpoints (Memerlukan Autentikasi)

Endpoint berikut **memerlukan API Token**:

### Authentication (Protected)
- `POST /api/v1/auth/logout` - Logout dan revoke token
- `GET /api/v1/auth/me` - Info user yang sedang login
- `POST /api/v1/auth/refresh` - Refresh token

### Product Management
- `GET /api/v1/products` - List semua produk
- `POST /api/v1/products` - Buat produk baru (auto sync ke Shopify)
- `GET /api/v1/products/{id}` - Detail produk
- `PUT/PATCH /api/v1/products/{id}` - Update produk (auto sync ke Shopify)
- `DELETE /api/v1/products/{id}` - Hapus produk (auto hapus dari Shopify)
- `POST /api/v1/products/{id}/images` - Upload gambar ke Shopify

### Bulk Operations
- `POST /api/v1/products/bulk` - Bulk create products
- `PATCH /api/v1/products/bulk` - Bulk update products
- `DELETE /api/v1/products/bulk` - Bulk delete products
- `GET /api/v1/products/bulk/status/{operationId}` - Cek status bulk operation

### Shopify Sync
- `POST /api/v1/shopify/import` - Import produk dari Shopify
- `POST /api/v1/shopify/export/{id}` - Export produk ke Shopify
- `POST /api/v1/shopify/export/bulk` - Bulk export ke Shopify
- `GET /api/v1/shopify/sync/status` - Status sinkronisasi Shopify
- `POST /api/v1/shopify/sync/validate` - Validasi kredensial Shopify
- `GET /api/v1/shopify/sync/conflicts` - Cek konflik sinkronisasi

### Variant Management
- `GET /api/v1/variants` - List semua variant
- `GET /api/v1/variants/{id}` - Detail variant
- `POST /api/v1/variants/products/{id}/variants` - Buat variant baru
- `PUT /api/v1/variants/{id}` - Update variant
- `DELETE /api/v1/variants/{id}` - Hapus variant
- `PATCH /api/v1/variants/{id}/inventory` - Update inventory variant
- `GET /api/v1/products/{id}/variants` - List variant produk

---

## 🔑 Cara Mendapatkan API Token

### Metode 1: Membuat User dan Token via Tinker

```bash
# Masuk ke tinker
php artisan tinker

# Buat user baru
$user = \App\Models\User::create([
    'name' => 'API User',
    'email' => 'api@example.com',
    'password' => bcrypt('password123')
]);

# Buat token untuk user
$token = $user->createToken('api-token')->plainTextToken;

echo $token;
# Simpan token ini: {token}
```

### Metode 2: Membuat User dan Token via Artisan Command

```bash
# Buat user baru
php artisan tinker --execute="
\$user = \App\Models\User::create([
    'name' => 'API User',
    'email' => 'api@example.com',
    'password' => bcrypt('password123')
]);
echo 'User created with ID: ' . \$user->id . PHP_EOL;
\$token = \$user->createToken('api-token')->plainTextToken;
echo 'Token: ' . \$token . PHP_EOL;
"
```

### Metode 3: Membuat Token untuk User yang Sudah Ada

```bash
php artisan tinker --execute="
\$user = \App\Models\User::first(); // Ambil user pertama
\$token = \$user->createToken('api-token')->plainTextToken;
echo 'Token: ' . \$token . PHP_EOL;
"
```

---

## 📝 Cara Menggunakan API Token

### Format Request

Setiap request ke protected endpoint harus menyertakan header:

```
Authorization: Bearer {your_token_here}
```

### Contoh dengan cURL

#### 1. Cari Produk (Public - Tanpa Token)
```bash
curl -X GET http://localhost:8000/api/v1/search/products
```

#### 2. List Semua Produk (Protected - Dengan Token)
```bash
curl -X GET http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123456789"
```

#### 3. Buat Produk Baru (Protected)
```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "New Product",
    "body_html": "<p>Product description</p>",
    "vendor": "My Vendor",
    "product_type": "electronics",
    "status": "active"
  }'
```

#### 4. Import Produk dari Shopify (Protected - Shopify Related)
```bash
curl -X POST http://localhost:8000/api/v1/shopify/import \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "first": 50
  }'
```

#### 5. Export Produk ke Shopify (Protected - Shopify Related)
```bash
curl -X POST http://localhost:8000/api/v1/shopify/export/1 \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123456789"
```

#### 6. Validasi Kredensial Shopify (Protected - Shopify Related)
```bash
curl -X POST http://localhost:8000/api/v1/shopify/sync/validate \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123456789"
```

---

## 🧪 Contoh dengan JavaScript/Fetch

### Public Endpoint
```javascript
// Tidak perlu token
fetch('http://localhost:8000/api/v1/search/products')
  .then(response => response.json())
  .then(data => console.log(data));
```

### Protected Endpoint
```javascript
// Perlu token
const token = '1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123456789';

fetch('http://localhost:8000/api/v1/products', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
})
  .then(response => response.json())
  .then(data => console.log(data));
```

### Shopify Related API
```javascript
// Import dari Shopify
const token = '1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123456789';

fetch('http://localhost:8000/api/v1/shopify/import', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    first: 50
  })
})
  .then(response => response.json())
  .then(data => console.log(data));

// Export ke Shopify
fetch('http://localhost:8000/api/v1/shopify/export/1', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
})
  .then(response => response.json())
  .then(data => console.log(data));
```

---

## 🔧 Testing Authentication

### Test Token Validity
```bash
# Test apakah token valid
curl -X GET http://localhost:8000/user \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ123456789"
```

Jika token valid, akan mengembalikan data user:
```json
{
  "id": 1,
  "name": "API User",
  "email": "api@example.com",
  ...
}
```

Jika token tidak valid:
```json
{
  "message": "Unauthenticated"
}
```

---

---

## 🖼️ Image Upload

### Cara Upload Gambar Produk

Gambar produk di-upload langsung ke Shopify CDN. **Produk harus sudah ter-sync ke Shopify** sebelum bisa upload gambar.

#### Upload via API
```bash
curl -X POST http://localhost:8000/api/v1/products/1/images \
  -H "Authorization: Bearer {token}" \
  -F "image=@/path/to/image.jpg"
```

#### Response Sukses
```json
{
  "success": true,
  "message": "Image uploaded successfully",
  "data": {
    "id": 1,
    "shopify_image_id": 123456789,
    "path": "https://cdn.shopify.com/s/files/...",
    "url": "https://cdn.shopify.com/s/files/..."
  }
}
```

#### Error: Product Not Synced
```json
{
  "success": false,
  "message": "Product must be synced to Shopify first before uploading images."
}
```

### Catatan Penting
- Gambar **tidak disimpan di server lokal**
- URL gambar dari Shopify CDN disimpan di database
- Maksimum ukuran file: 10MB
- Format yang didukung: JPEG, PNG, GIF, WebP

---

## 📊 Token Management

### Lihat Semua Token User
```bash
php artisan tinker --execute="
\$user = \App\Models\User::first();
foreach(\$user->tokens as \$token) {
    echo 'Token ID: ' . \$token->id . PHP_EOL;
    echo 'Token Name: ' . \$token->name . PHP_EOL;
    echo 'Created At: ' . \$token->created_at . PHP_EOL;
    echo '---' . PHP_EOL;
}
"
```

### Hapus Token (Revoke)
```bash
# Hapus semua token user
php artisan tinker --execute="
\$user = \App\Models\User::first();
\$user->tokens()->delete();
echo 'All tokens revoked for user: ' . \$user->email . PHP_EOL;
"

# Hapus token spesifik
php artisan tinker --execute="
\$user = \App\Models\User::first();
\$token = \$user->tokens()->first();
\$token->delete();
echo 'Token revoked' . PHP_EOL;
"
```

---

## 🚨 Error Messages

### 401 Unauthenticated
```json
{
  "message": "Unauthenticated"
}
```
**Penyebab**: Tidak menyertakan token atau token tidak valid

**Solusi**:
1. Pastikan header `Authorization: Bearer {token}` sudah disertakan
2. Pastikan token masih valid (tidak di-revoke)
3. Pastikan token benar (tidak ada typo)

### 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```
**Penyebab**: User tidak memiliki permission yang cukup

### 419 Page Expired (CSRF)
**Penyebab**: Menggunakan session auth instead of token auth

**Solusi**: Pastikan menggunakan API token, bukan session

---

## 🔐 Best Practices

### 1. Environment Variables
Simpan konfigurasi di `.env`:
```env
# Untuk development
SANCTUM_STATEFUL_DOMAINS=localhost
```

### 2. Token Expiration
Token tidak pernah expired secara default. Untuk mengatur expiration:
```php
// Saat membuat token
$token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;
```

### 3. Token Abilities
Batasi kemampuan token:
```php
// Token hanya bisa read products
$token = $user->createToken('read-only', ['products:read'])->plainTextToken;

// Token bisa read dan write products
$token = $user->createToken('full-access', ['products:read', 'products:write'])->plainTextToken;
```

### 4. HTTPS
Selalu gunakan HTTPS di production untuk mengenkripsi token

---

## 📖 Ringkasan

| Endpoint Type | Authentication Required | Token Needed |
|--------------|------------------------|--------------|
| Login | ❌ No | No |
| Health Check | ❌ No | No |
| Search | ❌ No | No |
| Auth (logout, me, refresh) | ✅ Yes | Yes |
| Products CRUD | ✅ Yes | Yes |
| Image Upload | ✅ Yes | Yes |
| Bulk Operations | ✅ Yes | Yes |
| Shopify Sync | ✅ Yes | Yes |
| Variants CRUD | ✅ Yes | Yes |

### Untuk Shopify Related API:
- **TIDAK memerlukan Shopify API Key/Token tambahan**
- Cukup gunakan Laravel Sanctum token
- Shopify credentials sudah dikonfigurasi di `.env`:
  - `SHOPIFY_ACCESS_TOKEN`
  - `SHOPIFY_DOMAIN`
  - `SHOPIFY_API_VERSION`

### Catatan Image Upload:
- Gambar di-upload langsung ke Shopify CDN
- Tidak ada penyimpanan lokal
- Produk harus sudah ter-sync ke Shopify sebelum upload gambar

---

## 🎯 Quick Start

1. Buat user dan token:
```bash
php artisan tinker --execute="
\$user = \App\Models\User::firstOrCreate(
    ['email' => 'api@example.com'],
    ['name' => 'API User', 'password' => bcrypt('password123')]
);
\$token = \$user->createToken('api-token')->plainTextToken;
echo 'Token: ' . \$token . PHP_EOL;
"
```

2. Copy token yang muncul

3. Gunakan di request:
```bash
curl -X GET http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer {paste_token_here}"
```

Done! 🎉
