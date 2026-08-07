# BitLearn Student API (Produksi)

**Base URL:** https://bitlearn.mtsn11majalengka.sch.id/api/

Portal web: https://bitlearn.mtsn11majalengka.sch.id/

## Deploy ke hosting

1. Upload seluruh folder project (termasuk folder `api/`) ke document root subdomain BitLearn.
2. Pastikan `core/config.php` berisi kredensial DB hosting (bukan XAMPP).
3. Jalankan SQL di `api/migrate_tokens.sql` via phpMyAdmin **atau** biarkan auto-create saat API pertama dipanggil.
4. Pastikan `mod_rewrite` aktif (umumnya sudah di cPanel).
5. Hapus / ganti nama `install.php` jika masih ada di produksi.
6. Uji di browser:
   - https://bitlearn.mtsn11majalengka.sch.id/api/
   - Harus mengembalikan JSON `success: true`

Jika rewrite gagal, pakai fallback:
`https://bitlearn.mtsn11majalengka.sch.id/api/index.php?r=/auth/login`

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
