-- Timer baca PDF/slides (menit) — jalankan manual jika ALTER otomatis gagal.
ALTER TABLE lessons
ADD COLUMN dwell_minutes INT NOT NULL DEFAULT 1
COMMENT 'Menit wajib baca sebelum tombol selesai (PDF/slides)'
AFTER is_published;
