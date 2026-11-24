# api-mhs-graphql — Praktikum GraphQL PHP

API GraphQL untuk data mahasiswa. Satu endpoint (`server/graphql.php`) menggantikan banyak URL REST.  
Dibuat untuk Praktikum Pertemuan 9 — Teknologi Web Service.

---

## Daftar Isi

1. [Kebutuhan](#1-kebutuhan)
2. [Struktur Folder](#2-struktur-folder)
3. [Setup Database](#3-setup-database)
4. [Menjalankan Server](#4-menjalankan-server)
5. [Menjalankan Pengujian Otomatis](#5-menjalankan-pengujian-otomatis)
6. [Contoh Request (curl)](#6-contoh-request-curl)
7. [Operasi yang Tersedia](#7-operasi-yang-tersedia)
8. [Akun Bawaan](#8-akun-bawaan)

---

## 1. Kebutuhan

| Kebutuhan | Versi minimal | Keterangan |
|---|---|---|
| PHP | 8.0 | Sudah termasuk di XAMPP / Laragon |
| MySQL | 5.7 / 8.0 | Sudah termasuk di XAMPP / Laragon |
| curl | — | Untuk uji dari terminal (opsional) |
| Postman | — | Alternatif GUI untuk uji API (opsional) |

> **Tidak butuh Composer.** Library `webonyx/graphql-php` sudah disertakan di folder `server/lib/`.

---

## 2. Struktur Folder

```
api-mhs-graphql/
├── database/
│   └── mhs.sql              ← import ini ke MySQL
├── server/
│   ├── graphql.php          ← endpoint utama (satu-satunya URL)
│   ├── schema.php           ← SDL schema + semua resolver
│   ├── koneksi.php          ← konfigurasi database
│   ├── jwt.php              ← helper token JWT
│   ├── router.php           ← router untuk php -S (dev server)
│   └── lib/                 ← library graphql-php (jangan diubah)
├── client/
│   ├── test_client.php      ← pengujian otomatis (49 pengecekan)
│   └── index.html           ← client web / GraphiQL mini
├── latihan/
│   └── rest_vs_graphql.php  ← demo perbandingan ukuran response
└── README.md
```

---

## 3. Setup Database

### Cara A — XAMPP / Laragon (direkomendasikan)

**Windows:**
1. Buka XAMPP Control Panel → klik **Start** di Apache dan MySQL.
2. Buka browser → `http://localhost/phpmyadmin`
3. Klik tab **Import** → pilih file `database/mhs.sql` → klik **Go**.
4. Pastikan database `kampus_db` sudah muncul dengan tabel `mhs`, `users`, `log_api`.

**Linux:**
```bash
mysql -u root -p kampus_db < database/mhs.sql
# Jika database belum ada:
mysql -u root -p -e "CREATE DATABASE kampus_db;"
mysql -u root -p kampus_db < database/mhs.sql
```

> `mhs.sql` menggunakan `CREATE TABLE IF NOT EXISTS` dan `INSERT IGNORE`,  
> sehingga **aman dijalankan berulang** — data lama tidak terhapus.

### Sesuaikan Koneksi Database

Buka `server/koneksi.php` dan sesuaikan jika perlu:

```php
$host    = 'localhost';
$user    = 'root';        // username MySQL kamu
$pass    = '';            // password MySQL kamu (XAMPP default: kosong)
$nama_db = 'kampus_db';
// $port  = 3306;         // uncomment jika port MySQL bukan 3306
```

---

## 4. Menjalankan Server

Ada dua cara — pilih salah satu.

### Cara A — PHP Built-in Server (tanpa Apache/Nginx)

Ini cara paling cepat, cukup dengan terminal.

**Windows (Command Prompt / PowerShell):**
```bat
cd C:\path\ke\api-mhs-graphql\server
php -S 127.0.0.1:9292 router.php
```

**Linux / macOS:**
```bash
cd /path/ke/api-mhs-graphql/server
php -S 127.0.0.1:9292 router.php
```

Server berjalan di `http://127.0.0.1:9292/graphql.php`.  
Biarkan terminal ini terbuka — buka terminal baru untuk mengirim request.

### Cara B — XAMPP / Laragon (Apache)

1. Salin seluruh folder `api-mhs-graphql` ke:
   - **XAMPP Windows:** `C:\xampp\htdocs\api-mhs-graphql`
   - **Laragon Windows:** `C:\laragon\www\api-mhs-graphql`
   - **Linux XAMPP:** `/opt/lampp/htdocs/api-mhs-graphql`
2. Pastikan Apache sudah **Start**.
3. Endpoint tersedia di: `http://localhost/api-mhs-graphql/server/graphql.php`

---

## 5. Menjalankan Pengujian Otomatis

Buka terminal **baru** (server harus sudah berjalan di terminal lain).

**Jika pakai PHP Built-in Server (port 9292):**

```bash
# Linux / macOS
php client/test_client.php http://127.0.0.1:9292/graphql.php

# Windows
php client\test_client.php http://127.0.0.1:9292/graphql.php
```

**Jika pakai Apache (XAMPP/Laragon):**

```bash
php client/test_client.php http://localhost/api-mhs-graphql/server/graphql.php
```

**Output yang diharapkan:**

```
Menguji server: http://127.0.0.1:9292/graphql.php

  [PASS] Server GraphQL menjawab query { halo }

1. QUERY: client menentukan bentuk datanya
  [PASS] daftarMahasiswa mengembalikan banyak baris
  [PASS] Hanya field yang diminta (nim, name) yang dikirim server
  ...

HASIL: 49 dari 49 pengujian lulus
```

---

## 6. Contoh Request (curl)

Simpan URL ke variabel supaya tidak ketik ulang terus:

**Linux / macOS:**
```bash
BASE="http://127.0.0.1:9292/graphql.php"
```

**Windows (PowerShell):**
```powershell
$BASE = "http://127.0.0.1:9292/graphql.php"
```

**Windows (Command Prompt):**
```bat
set BASE=http://127.0.0.1:9292/graphql.php
```

---

### Query sederhana

```bash
curl -X POST $BASE \
  -H "Content-Type: application/json" \
  -d "{\"query\": \"{ halo jumlahMahasiswa }\"}"
```

Response:
```json
{ "data": { "halo": "Halo dari GraphQL!", "jumlahMahasiswa": 16 } }
```

---

### Daftar mahasiswa (pilih field sendiri)

```bash
curl -X POST $BASE \
  -H "Content-Type: application/json" \
  -d "{\"query\": \"{ daftarMahasiswa { nim name major } }\"}"
```

---

### Filter jurusan + pencarian nama + paginasi

```bash
# Filter jurusan
curl -X POST $BASE -H "Content-Type: application/json" \
  -d "{\"query\": \"{ daftarMahasiswa(jurusan: \\\"Informatika\\\") { nim name } }\"}"

# Cari nama yang mengandung kata tertentu
curl -X POST $BASE -H "Content-Type: application/json" \
  -d "{\"query\": \"{ daftarMahasiswa(cari: \\\"Budi\\\") { nim name } }\"}"

# Paginasi: halaman 1, 3 data per halaman
curl -X POST $BASE -H "Content-Type: application/json" \
  -d "{\"query\": \"{ daftarMahasiswa(page: 1, limit: 3) { nim name } }\"}"
```

---

### Statistik per jurusan

```bash
curl -X POST $BASE -H "Content-Type: application/json" \
  -d "{\"query\": \"{ statistikJurusan { jurusan jumlah } }\"}"
```

---

### Login — Mendapatkan Token

```bash
curl -X POST $BASE -H "Content-Type: application/json" \
  -d "{\"query\": \"mutation { login(username: \\\"admin\\\", password: \\\"admin123\\\") { token role } }\"}"
```

Simpan token dari response, lalu pakai di header request berikutnya:

**Linux / macOS:**
```bash
TOKEN="isi_token_dari_response_di_sini"
```

**Windows PowerShell:**
```powershell
$TOKEN = "isi_token_dari_response_di_sini"
```

---

### Tambah Mahasiswa (perlu token admin)

```bash
curl -X POST $BASE \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"query\": \"mutation { tambahMahasiswa(input: { nim: \\\"24670001\\\", name: \\\"Nama Baru\\\", major: \\\"Informatika\\\" }) { id nim name } }\"}"
```

---

### Ubah Mahasiswa (perlu token admin)

```bash
curl -X POST $BASE \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"query\": \"mutation { ubahMahasiswa(id: 1, input: { nim: \\\"24670001\\\", name: \\\"Nama Diubah\\\", major: \\\"Sistem Informasi\\\" }) { id nim name major } }\"}"
```

---

### Hapus Mahasiswa (perlu token admin)

```bash
curl -X POST $BASE \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"query\": \"mutation { hapusMahasiswa(id: 1) }\"}"
```

Response: `{ "data": { "hapusMahasiswa": true } }`

---

### Introspection — lihat semua type di schema

```bash
curl -X POST $BASE -H "Content-Type: application/json" \
  -d "{\"query\": \"{ __schema { types { name } } }\"}"
```

---

## 7. Operasi yang Tersedia

### Query (membaca data)

| Operasi | Argumen | Akses | Keterangan |
|---|---|---|---|
| `halo` | — | Publik | Tes server berjalan |
| `jumlahMahasiswa` | — | Publik | Total baris di tabel mhs |
| `daftarMahasiswa` | `jurusan`, `cari`, `page`, `limit` | Publik | Semua argumen opsional; `limit` max 100 |
| `mahasiswa` | `id: Int!` | Publik | Satu mahasiswa, null jika tidak ada |
| `statistikJurusan` | — | Publik | Jumlah mahasiswa per jurusan (GROUP BY) |

### Mutation (mengubah data)

| Operasi | Argumen | Akses | Keterangan |
|---|---|---|---|
| `login` | `username`, `password` | Publik | Mengembalikan token JWT |
| `tambahMahasiswa` | `input: MahasiswaInput!` | Admin | Tambah baris baru |
| `ubahMahasiswa` | `id: Int!`, `input: MahasiswaInput!` | Admin | Update; cek NOT_FOUND dan CONFLICT |
| `hapusMahasiswa` | `id: Int!` | Admin | Hapus; mengembalikan `true` |

### Kode Error

| Kode | Artinya |
|---|---|
| `UNAUTHENTICATED` | Tidak ada token / token salah / password salah |
| `FORBIDDEN` | Token sah tapi bukan admin |
| `NOT_FOUND` | ID tidak ada di database |
| `CONFLICT` | NIM sudah dipakai mahasiswa lain |
| `VALIDATION` | Format NIM salah / nama kosong / limit di luar batas |

> Semua response GraphQL selalu HTTP 200.  
> Cek error dari `errors[0].extensions.code` di dalam body JSON.

---

## 8. Akun Bawaan

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | admin — bisa tambah, ubah, hapus |
| `budi` | `budi123` | user — hanya bisa query publik |

> Akun ini hanya untuk keperluan praktikum. Ganti password jika dipakai di lingkungan nyata.

---

## Troubleshooting

**Server tidak bisa connect ke MySQL:**
- Pastikan MySQL sudah berjalan (cek XAMPP Control Panel).
- Periksa `$host`, `$user`, `$pass`, `$nama_db` di `server/koneksi.php`.
- Jika MySQL berjalan di port selain 3306, tambahkan `$host = 'localhost:NOMOR_PORT'`.

**Muncul "Gunakan metode POST":**
- Kamu mengirim request dengan method GET. Ganti ke POST.

**Token ditolak (UNAUTHENTICATED):**
- Token sudah expired (berlaku 1 jam). Login ulang untuk mendapat token baru.
- Pastikan header ditulis `Authorization: Bearer <token>` (ada spasi antara Bearer dan token).

**Test client gagal connect:**
- Pastikan server sudah berjalan sebelum menjalankan `test_client.php`.
- Pastikan URL yang dipakai sesuai (port 9292 jika pakai built-in server, atau path XAMPP yang benar).
