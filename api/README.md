# BitLearn Student API (Produksi)

**Base URL:** https://bitlearn.mtsn11majalengka.sch.id/api/

Portal web: https://bitlearn.mtsn11majalengka.sch.id/

## Deploy ke hosting

1. Upload folder `api/` (semua file, terutama `bootstrap.php` + `lib/Auth.php`).
2. Opsional di phpMyAdmin: jalankan `api/migrate_tokens.sql`.
3. Uji di browser: https://bitlearn.mtsn11majalengka.sch.id/api/
   Harus JSON `success: true`. Jika masih 500, cek error message JSON yang baru.

### Catatan perbaikan login 500

PHP 8.1+ melempar exception saat `CREATE TABLE ... FOREIGN KEY` gagal di shared hosting.
Versi API sekarang membuat `api_tokens` **tanpa foreign key** dan mengembalikan JSON error yang terbaca.

## Auth

```
Authorization: Bearer {token}
```

## Endpoints

| Method | Path | Auth | Keterangan |
|--------|------|------|------------|
| GET | `/` | - | Info API |
| POST | `/auth/login` | - | Login siswa |
| POST | `/auth/logout` | ✓ | Hapus token |
| GET | `/auth/me` | ✓ | User saat ini |
| GET | `/student/dashboard` | ✓ | Kursus + tugas pending |
| POST | `/courses/enroll` | ✓ | Gabung via kode |
| GET | `/courses/{id}` | ✓ | Kurikulum |
| GET | `/lessons/{id}` | ✓ | Detail materi |
| POST | `/lessons/{id}/complete` | ✓ | Tandai selesai |
| GET | `/quizzes/{lessonId}` | ✓ | Soal (tanpa kunci) |
| POST | `/quizzes/{lessonId}/submit` | ✓ | Submit jawaban |
| GET | `/assignments/{id}` | ✓ | Detail tugas |
| POST | `/assignments/{id}/submit` | ✓ | Upload file (`assignment_file`) |
| GET | `/profile` | ✓ | Profil |
| PUT/POST | `/profile` | ✓ | Update profil |

### Login

```http
POST https://bitlearn.mtsn11majalengka.sch.id/api/auth/login
Content-Type: application/json

{"username":"NISN123","password":"rahasia","device_name":"android"}
```

## Response

```json
{"success": true, "message": "OK", "data": {}}
```
