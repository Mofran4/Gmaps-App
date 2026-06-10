## Deskripsi Proyek

Aplikasi mapping berbasis web yang memungkinkan pengguna menambahkan, mengelola, dan memvisualisasikan lokasi pada peta. Dibangun menggunakan **PHP + SQLite** untuk backend dan **jQuery + Mapbox GL JS** untuk frontend.

---

## Fitur yang Diimplementasikan

| No | Requirement | Status |
|----|-------------|--------|
| 1 | User dapat menambahkan data record (alamat, judul, detail kegiatan) | ✅ |
| 2 | Setiap record muncul pada peta dengan marker khusus per kategori | ✅ |
| 3 | Peta digeser akan menampilkan record berdasarkan area yang terlihat | ✅ |
| 4 | Klik record di tabel akan memunculkan infobox detail | ✅ |
| 5 | Filter data berdasarkan kategori | ✅ |

---

## Teknologi

- **Backend:** PHP + PDO SQLite
- **Frontend:** jQuery + Mapbox
- **Database:** SQLite
- **Font:** Syne + DM Sans (Google Fonts)
- **Icons:** Font Awesome 6.5

---

## Cara Instalasi & Menjalankan

### Prerequisites
- PHP dengan ekstensi `pdo_sqlite`
- Web server (Apache / Nginx) atau PHP built-in server

### Langkah Instalasi

**1. Clone atau ekstrak project**

**2. Konfigurasi Mapbox Token**

Buka file `assets/js/app.js`, cari baris:
```javascript
mapboxgl.accessToken = 'pk.eyJ1Ijo...';
```
Token berbeda jadi ganti dengan token dari akun Mapbox di https://account.mapbox.com/

**3. Jalankan dengan PHP built-in server**
```bash
php -S localhost:8080
```

**4. Buka di browser**
```
http://localhost:8080
```
---
## Database Schema

```sql
CREATE TABLE records (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT NOT NULL,       -- Judul kegiatan/tempat
    address     TEXT NOT NULL,       -- Alamat lengkap
    detail      TEXT NOT NULL,       -- Detail deskripsi
    category    TEXT NOT NULL,       -- Kategori (Kuliner, Pendidikan, dst)
    latitude    REAL NOT NULL,       -- Koordinat latitude
    longitude   REAL NOT NULL,       -- Koordinat longitude
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
```
---
## API Endpoints

Base URL: `api/records.php`

| Method | Action | Deskripsi |
|--------|--------|-----------|
| GET | `?action=list` | Ambil semua record |
| GET | `?action=list&category=Kuliner` | Filter by kategori |
| GET | `?action=list&bounds=sw_lat,sw_lng,ne_lat,ne_lng` | Filter by area peta |
| GET | `?action=categories` | Ambil daftar kategori + jumlah |
| POST | `?action=create` | Buat record baru |
| POST | `?action=update` | Update record (sertakan `id`) |
| POST | `?action=delete` | Hapus record (sertakan `id`) |

---
## Catatan Teknis

- Database SQLite dibuat otomatis saat pertama kali diakses
- Data sample (8 lokasi di Bandung) di-seed otomatis jika database kosong
- Semua input di-sanitasi menggunakan prepared statements (SQL injection safe)
- Mapbox token perlu diganti dengan token valid dari akun masing-masing