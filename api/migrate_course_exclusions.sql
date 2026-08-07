-- Opsional: jika CREATE otomatis gagal di hosting, jalankan manual.
CREATE TABLE IF NOT EXISTS course_exclusions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    block_rejoin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_course_student (course_id, student_id),
    KEY idx_course (course_id),
    KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jika tabel sudah ada tanpa kolom block_rejoin:
-- ALTER TABLE course_exclusions ADD COLUMN block_rejoin TINYINT(1) NOT NULL DEFAULT 0 AFTER student_id;
