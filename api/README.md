# BitLearn Student API

JSON REST API untuk aplikasi Android siswa. Web guru tetap memakai session PHP seperti biasa.

## Base URL

```
http://{host}/{path-ke-bitlearn}/api
```

Contoh XAMPP lokal:
```
http://10.0.2.2/bitlearn/api
```
(`10.0.2.2` = localhost dari emulator Android)

## Auth

Login mengembalikan Bearer token. Kirim di setiap request terproteksi:

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
| PUT/POST | `/profile` | ✓ | Update profil (+ opsional `profile_pic`) |

### Contoh login

```http
POST /api/auth/login
Content-Type: application/json

{"username":"NISN123","password":"rahasia","device_name":"android"}
```

Alternatif tanpa rewrite Apache: `POST /api/index.php?r=/auth/login`

## Response format

```json
{"success": true, "message": "OK", "data": {}}
```

Error:
```json
{"success": false, "message": "..."}
```

## Migrasi DB

Tabel `api_tokens` otomatis dibuat saat API pertama kali dipanggil. Juga ada di `db_setup.sql` untuk instalasi baru.
