<?php
/**
 * BitLearn Student API — front controller
 *
 * Base: {BASE_URL}/api/index.php
 * Routes use ?r=path or PATH_INFO / rewrite: /api/{path}
 */

require_once __DIR__ . '/bootstrap.php';

$route = $_GET['r'] ?? '';
if ($route === '' && !empty($_SERVER['PATH_INFO'])) {
    $route = $_SERVER['PATH_INFO'];
}
if ($route === '' && !empty($_SERVER['REQUEST_URI'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#/api(?:/index\.php)?(/.*)$#', $uri, $m)) {
        $route = $m[1];
    }
}

$route = '/' . trim($route, '/');
$method = $_SERVER['REQUEST_METHOD'];
$baseUrl = rtrim(BASE_URL, '/');
// Guard: edge-case BASE_URL detection can wrongly append /api when routed via api/index.php
if (substr($baseUrl, -4) === '/api') {
    $baseUrl = substr($baseUrl, 0, -4);
}

// ---------------------------------------------------------------------------
// GET /
// ---------------------------------------------------------------------------
if ($route === '/' || $route === '') {
    ApiResponse::success([
        'name' => 'BitLearn Student API',
        'version' => '1.0.0',
        'base_url' => $baseUrl . '/api',
    ]);
}

// ---------------------------------------------------------------------------
// POST /auth/login
// ---------------------------------------------------------------------------
if ($route === '/auth/login' && $method === 'POST') {
    $input = api_input();
    $username = trim($input['username'] ?? $input['email'] ?? '');
    $password = $input['password'] ?? '';
    $device = trim($input['device_name'] ?? 'android');

    if ($username === '' || $password === '') {
        ApiResponse::error('Username dan password wajib diisi.', 422);
    }

    $usernameEsc = $conn->real_escape_string($username);
    $result = $conn->query("SELECT id, name, username, email, password, role, profile_pic FROM users WHERE username = '$usernameEsc' LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        ApiResponse::error('Username atau kata sandi salah.', 401);
    }
    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password'])) {
        ApiResponse::error('Username atau kata sandi salah.', 401);
    }
    if (strtolower(trim($user['role'])) !== 'student') {
        ApiResponse::error('Aplikasi ini hanya untuk akun siswa.', 403);
    }

    $token = ApiAuth::createToken($conn, (int) $user['id'], $device);
    ApiResponse::success([
        'token' => $token,
        'token_type' => 'Bearer',
        'user' => ApiAuth::publicUser($user, $baseUrl, $conn),
    ], 'Login berhasil');
}

// ---------------------------------------------------------------------------
// POST /auth/logout
// ---------------------------------------------------------------------------
if ($route === '/auth/logout' && $method === 'POST') {
    $user = ApiAuth::requireStudent($conn);
    $token = ApiAuth::bearerToken();
    if ($token) {
        ApiAuth::revokeToken($conn, $token);
    }
    ApiResponse::success(null, 'Logout berhasil');
}

// ---------------------------------------------------------------------------
// GET /auth/me
// ---------------------------------------------------------------------------
if ($route === '/auth/me' && $method === 'GET') {
    $user = ApiAuth::requireStudent($conn);
    ApiResponse::success(ApiAuth::publicUser($user, $baseUrl, $conn));
}

// ---------------------------------------------------------------------------
// GET /student/dashboard
// ---------------------------------------------------------------------------
if ($route === '/student/dashboard' && $method === 'GET') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];

    $courses = [];
    $courseRes = $conn->query(StudentAccess::accessibleCoursesSql($studentId, $conn));
    if ($courseRes) {
        while ($row = $courseRes->fetch_assoc()) {
            $courses[] = StudentAccess::coursePayload($row, $baseUrl);
        }
    }

    $pending = [];
    $exParts = '';
    $exJoin = '';
    if (CourseStudents::hasExclusionsTable($conn)) {
        $exJoin = "LEFT JOIN course_exclusions cx ON cx.course_id = c.id AND cx.student_id = $studentId";
        $exParts = 'AND cx.id IS NULL';
    }
    $pendingRes = $conn->query("
        SELECT DISTINCT a.id, a.course_id, a.title, a.description, a.due_date, a.file_path, c.title AS course_name
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = $studentId
        LEFT JOIN course_classes cc ON c.id = cc.course_id
        LEFT JOIN class_students cs ON cc.class_id = cs.class_id AND cs.student_id = $studentId
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = $studentId
        $exJoin
        WHERE (e.id IS NOT NULL OR cs.student_id IS NOT NULL)
          $exParts
          AND s.id IS NULL
          AND a.due_date > NOW()
          AND a.is_published = 1
        ORDER BY a.due_date ASC
        LIMIT 10
    ");
    if ($pendingRes) {
        while ($a = $pendingRes->fetch_assoc()) {
            $pending[] = [
                'id' => (int) $a['id'],
                'course_id' => (int) $a['course_id'],
                'title' => $a['title'],
                'description' => $a['description'] ?? '',
                'due_date' => $a['due_date'],
                'course_name' => $a['course_name'],
                'file_url' => StudentAccess::absoluteUploadUrl($a['file_path'] ?? null, $baseUrl),
            ];
        }
    }

    ApiResponse::success([
        'user' => ApiAuth::publicUser($user, $baseUrl, $conn),
        'courses' => $courses,
        'pending_assignments' => $pending,
    ]);
}

// ---------------------------------------------------------------------------
// POST /courses/enroll
// ---------------------------------------------------------------------------
if ($route === '/courses/enroll' && $method === 'POST') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];
    $input = api_input();
    $code = trim($input['enrollment_code'] ?? $input['code'] ?? '');

    $result = CourseStudents::enrollByCode($conn, $studentId, $code);
    if (!$result['ok']) {
        ApiResponse::error($result['message'], (int) $result['code']);
    }
    ApiResponse::success(
        StudentAccess::coursePayload($result['course'], $baseUrl),
        $result['message']
    );
}

// ---------------------------------------------------------------------------
// GET /courses/{id}
// ---------------------------------------------------------------------------
if (preg_match('#^/courses/(\d+)$#', $route, $m) && $method === 'GET') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];
    $courseId = (int) $m[1];

    if (!StudentAccess::canAccessCourse($conn, $studentId, $courseId)) {
        ApiResponse::error('Anda tidak memiliki akses ke mata pelajaran ini.', 403);
    }

    $courseRes = $conn->query("SELECT * FROM courses WHERE id = $courseId LIMIT 1");
    if (!$courseRes || $courseRes->num_rows === 0) {
        ApiResponse::error('Mata pelajaran tidak ditemukan.', 404);
    }
    $course = $courseRes->fetch_assoc();

    $modules = [];
    $modulesRes = $conn->query("SELECT * FROM modules WHERE course_id = $courseId AND is_published = 1 ORDER BY order_num ASC, id ASC");
    if ($modulesRes) {
        while ($mod = $modulesRes->fetch_assoc()) {
            $modId = (int) $mod['id'];
            $lessons = [];
            $lessonsRes = $conn->query("SELECT * FROM lessons WHERE module_id = $modId AND is_published = 1 ORDER BY order_num ASC, id ASC");
            if ($lessonsRes) {
                while ($les = $lessonsRes->fetch_assoc()) {
                    $lid = (int) $les['id'];
                    $prog = $conn->query("SELECT id FROM user_progress WHERE student_id = $studentId AND lesson_id = $lid");
                    $lessons[] = [
                        'id' => $lid,
                        'title' => $les['title'],
                        'content_type' => $les['content_type'],
                        'order_num' => (int) $les['order_num'],
                        'is_completed' => ($prog && $prog->num_rows > 0),
                        'prerequisite_lesson_id' => !empty($les['is_prerequisite_of']) ? (int) $les['is_prerequisite_of'] : null,
                    ];
                }
            }
            $modules[] = [
                'id' => $modId,
                'title' => $mod['title'],
                'order_num' => (int) $mod['order_num'],
                'lessons' => $lessons,
            ];
        }
    }

    $assignments = [];
    $asnRes = $conn->query("SELECT * FROM assignments WHERE course_id = $courseId AND is_published = 1 ORDER BY due_date ASC, id ASC");
    if ($asnRes) {
        while ($a = $asnRes->fetch_assoc()) {
            $aid = (int) $a['id'];
            $sub = $conn->query("SELECT id FROM submissions WHERE student_id = $studentId AND assignment_id = $aid");
            $assignments[] = [
                'id' => $aid,
                'title' => $a['title'],
                'due_date' => $a['due_date'],
                'is_submitted' => ($sub && $sub->num_rows > 0),
                'prerequisite_assignment_id' => !empty($a['is_prerequisite_of']) ? (int) $a['is_prerequisite_of'] : null,
            ];
        }
    }

    ApiResponse::success([
        'course' => StudentAccess::coursePayload($course, $baseUrl),
        'modules' => $modules,
        'assignments' => $assignments,
    ]);
}

// ---------------------------------------------------------------------------
// GET /lessons/{id}
// ---------------------------------------------------------------------------
if (preg_match('#^/lessons/(\d+)$#', $route, $m) && $method === 'GET') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];
    $lessonId = (int) $m[1];

    $lesRes = $conn->query("
        SELECT lessons.*, modules.course_id, courses.title AS course_title
        FROM lessons
        JOIN modules ON lessons.module_id = modules.id
        JOIN courses ON modules.course_id = courses.id
        WHERE lessons.id = $lessonId AND lessons.is_published = 1
        LIMIT 1
    ");
    if (!$lesRes || $lesRes->num_rows === 0) {
        ApiResponse::error('Materi tidak ditemukan.', 404);
    }
    $lesson = $lesRes->fetch_assoc();
    $courseId = (int) $lesson['course_id'];

    if (!StudentAccess::canAccessCourse($conn, $studentId, $courseId)) {
        ApiResponse::error('Anda tidak memiliki akses ke materi ini.', 403);
    }

    $locked = false;
    $missingPrereq = null;
    if (!empty($lesson['is_prerequisite_of'])) {
        $reqId = (int) $lesson['is_prerequisite_of'];
        $reqCheck = $conn->query("SELECT id FROM user_progress WHERE student_id = $studentId AND lesson_id = $reqId");
        if (!$reqCheck || $reqCheck->num_rows === 0) {
            $locked = true;
            $titleRes = $conn->query("SELECT title FROM lessons WHERE id = $reqId");
            $missingPrereq = ($titleRes && $titleRes->num_rows > 0) ? $titleRes->fetch_assoc()['title'] : 'Materi sebelumnya';
        }
    }

    $prog = $conn->query("SELECT id FROM user_progress WHERE student_id = $studentId AND lesson_id = $lessonId");
    $isCompleted = ($prog && $prog->num_rows > 0);

    $payload = [
        'id' => $lessonId,
        'course_id' => $courseId,
        'course_title' => $lesson['course_title'],
        'title' => $lesson['title'],
        'description' => $lesson['description'] ?? '',
        'content_type' => $lesson['content_type'],
        'is_locked' => $locked,
        'missing_prerequisite_title' => $missingPrereq,
        'is_completed' => $isCompleted,
        'url_embed' => null,
        'document_url' => null,
        'viewer_url' => null,
        'media_kind' => 'text',
        'file_extension' => null,
    ];

    if (!$locked) {
        $media = StudentAccess::buildLessonMedia($lesson, $baseUrl);
        $payload = array_merge($payload, $media);
    } else {
        $payload['completion'] = [
            'mode' => 'none',
            'required_seconds' => 0,
            'youtube_id' => null,
            'pause_aware' => false,
            'label' => 'Materi terkunci',
        ];
    }

    ApiResponse::success($payload);
}

// ---------------------------------------------------------------------------
// POST /lessons/{id}/complete
// ---------------------------------------------------------------------------
if (preg_match('#^/lessons/(\d+)/complete$#', $route, $m) && $method === 'POST') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];
    $lessonId = (int) $m[1];
    $input = api_input();

    $lesRes = $conn->query("
        SELECT lessons.*
        FROM lessons
        JOIN modules ON lessons.module_id = modules.id
        WHERE lessons.id = $lessonId AND lessons.is_published = 1
        LIMIT 1
    ");
    if (!$lesRes || $lesRes->num_rows === 0) {
        ApiResponse::error('Materi tidak ditemukan.', 404);
    }
    $lesson = $lesRes->fetch_assoc();
    $courseId = StudentAccess::getCourseIdForLesson($conn, $lessonId);
    if ($courseId === null) {
        ApiResponse::error('Materi tidak ditemukan.', 404);
    }
    if (!StudentAccess::canAccessCourse($conn, $studentId, $courseId)) {
        ApiResponse::error('Akses ditolak.', 403);
    }

    $check = $conn->query("SELECT id FROM user_progress WHERE student_id = $studentId AND lesson_id = $lessonId");
    if ($check && $check->num_rows > 0) {
        ApiResponse::success(['lesson_id' => $lessonId, 'is_completed' => true], 'Materi sudah selesai');
    }

    $media = StudentAccess::buildLessonMedia($lesson, $baseUrl);
    $rule = $media['completion'];
    $mode = $rule['mode'] ?? 'none';
    $proof = strtolower(trim((string) ($input['proof'] ?? '')));
    $elapsed = (int) ($input['elapsed_seconds'] ?? 0);
    $required = (int) ($rule['required_seconds'] ?? 0);

    if ($mode === 'quiz') {
        ApiResponse::error('Materi kuis diselesaikan dengan mengerjakan kuis.', 422);
    }

    if ($mode === 'video_ended') {
        if ($proof !== 'video_ended') {
            ApiResponse::error('Tonton video sampai selesai sebelum menandai materi.', 422);
        }
    } elseif ($mode === 'download') {
        if ($proof !== 'download') {
            ApiResponse::error('Buka/unduh berkas lampiran terlebih dahulu.', 422);
        }
    } elseif ($mode === 'dwell') {
        // Toleransi 5% (timer Android pause-aware)
        $minNeeded = (int) max(0, floor($required * 0.95));
        if ($elapsed < $minNeeded) {
            ApiResponse::error(
                'Waktu belajar belum cukup. Minimal ' . $required . ' detik (tercatat: ' . $elapsed . ' detik).',
                422
            );
        }
    }

    $conn->query("INSERT INTO user_progress (student_id, lesson_id) VALUES ($studentId, $lessonId)");

    ApiResponse::success([
        'lesson_id' => $lessonId,
        'is_completed' => true,
        'proof' => $proof !== '' ? $proof : $mode,
        'elapsed_seconds' => $elapsed,
    ], 'Materi ditandai selesai');
}

// ---------------------------------------------------------------------------
// GET /quizzes/{lesson_id}
// ---------------------------------------------------------------------------
if (preg_match('#^/quizzes/(\d+)$#', $route, $m) && $method === 'GET') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];
    $lessonId = (int) $m[1];

    $lesRes = $conn->query("
        SELECT lessons.title, lessons.content_type, courses.id AS course_id, courses.title AS course_title
        FROM lessons
        JOIN modules ON lessons.module_id = modules.id
        JOIN courses ON modules.course_id = courses.id
        WHERE lessons.id = $lessonId AND lessons.is_published = 1
        LIMIT 1
    ");
    if (!$lesRes || $lesRes->num_rows === 0) {
        ApiResponse::error('Kuis tidak ditemukan.', 404);
    }
    $lesson = $lesRes->fetch_assoc();
    $courseId = (int) $lesson['course_id'];

    if (!StudentAccess::canAccessCourse($conn, $studentId, $courseId)) {
        ApiResponse::error('Akses ditolak.', 403);
    }

    $attemptRes = $conn->query("SELECT * FROM quiz_attempts WHERE student_id = $studentId AND lesson_id = $lessonId LIMIT 1");
    $alreadyTaken = ($attemptRes && $attemptRes->num_rows > 0);
    $attempt = $alreadyTaken ? $attemptRes->fetch_assoc() : null;

    $questions = [];
    if (!$alreadyTaken) {
        $qRes = $conn->query("SELECT * FROM quiz_questions WHERE lesson_id = $lessonId ORDER BY id ASC");
        if ($qRes) {
            while ($q = $qRes->fetch_assoc()) {
                $qid = (int) $q['id'];
                $options = [];
                $optRes = $conn->query("SELECT id, option_text FROM quiz_options WHERE question_id = $qid ORDER BY RAND()");
                if ($optRes) {
                    while ($opt = $optRes->fetch_assoc()) {
                        $options[] = [
                            'id' => (int) $opt['id'],
                            'option_text' => $opt['option_text'],
                        ];
                    }
                }
                $questions[] = [
                    'id' => $qid,
                    'question_text' => $q['question_text'],
                    'options' => $options,
                ];
            }
        }
    }

    ApiResponse::success([
        'lesson_id' => $lessonId,
        'course_id' => $courseId,
        'title' => $lesson['title'],
        'course_title' => $lesson['course_title'],
        'already_taken' => $alreadyTaken,
        'score' => $alreadyTaken ? (int) $attempt['score'] : null,
        'total_questions' => count($questions),
        'questions' => $questions,
    ]);
}

// ---------------------------------------------------------------------------
// POST /quizzes/{lesson_id}/submit
// ---------------------------------------------------------------------------
if (preg_match('#^/quizzes/(\d+)/submit$#', $route, $m) && $method === 'POST') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];
    $lessonId = (int) $m[1];
    $input = api_input();
    $answers = $input['answers'] ?? [];

    $courseId = StudentAccess::getCourseIdForLesson($conn, $lessonId);
    if ($courseId === null) {
        ApiResponse::error('Kuis tidak ditemukan.', 404);
    }
    if (!StudentAccess::canAccessCourse($conn, $studentId, $courseId)) {
        ApiResponse::error('Akses ditolak.', 403);
    }

    $check = $conn->query("SELECT id, score FROM quiz_attempts WHERE lesson_id = $lessonId AND student_id = $studentId LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $existing = $check->fetch_assoc();
        ApiResponse::error('Anda sudah mengerjakan kuis ini.', 409, ['score' => (int) $existing['score']]);
    }

    $totalRes = $conn->query("SELECT COUNT(id) AS c FROM quiz_questions WHERE lesson_id = $lessonId");
    $totalQ = ($totalRes && $totalRes->num_rows > 0) ? (int) $totalRes->fetch_assoc()['c'] : 0;
    $correctCount = 0;

    if ($totalQ > 0 && is_array($answers)) {
        foreach ($answers as $qid => $optId) {
            $o = (int) $optId;
            $res = $conn->query("SELECT is_correct FROM quiz_options WHERE id = $o LIMIT 1");
            if ($res && $res->num_rows > 0 && (int) $res->fetch_assoc()['is_correct'] === 1) {
                $correctCount++;
            }
        }
        $score = (int) round(($correctCount / $totalQ) * 100);
    } else {
        $score = 0;
    }

    $conn->query("INSERT INTO quiz_attempts (student_id, lesson_id, score) VALUES ($studentId, $lessonId, $score)");
    $progCheck = $conn->query("SELECT id FROM user_progress WHERE student_id = $studentId AND lesson_id = $lessonId");
    if (!$progCheck || $progCheck->num_rows === 0) {
        $conn->query("INSERT INTO user_progress (student_id, lesson_id) VALUES ($studentId, $lessonId)");
    }

    ApiResponse::success([
        'lesson_id' => $lessonId,
        'score' => $score,
        'correct_count' => $correctCount,
        'total_questions' => $totalQ,
    ], 'Kuis berhasil dikumpulkan');
}

// ---------------------------------------------------------------------------
// GET /assignments/{id}
// ---------------------------------------------------------------------------
if (preg_match('#^/assignments/(\d+)$#', $route, $m) && $method === 'GET') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];
    $assignmentId = (int) $m[1];

    $asnRes = $conn->query("
        SELECT a.*, c.title AS course_title
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE a.id = $assignmentId AND a.is_published = 1
        LIMIT 1
    ");
    if (!$asnRes || $asnRes->num_rows === 0) {
        ApiResponse::error('Tugas tidak ditemukan.', 404);
    }
    $asn = $asnRes->fetch_assoc();
    $courseId = (int) $asn['course_id'];

    if (!StudentAccess::canAccessCourse($conn, $studentId, $courseId)) {
        ApiResponse::error('Akses ditolak.', 403);
    }

    $locked = false;
    $missingPrereq = null;
    if (!empty($asn['is_prerequisite_of'])) {
        $reqId = (int) $asn['is_prerequisite_of'];
        $reqCheck = $conn->query("SELECT id FROM submissions WHERE student_id = $studentId AND assignment_id = $reqId");
        if (!$reqCheck || $reqCheck->num_rows === 0) {
            $locked = true;
            $titleRes = $conn->query("SELECT title FROM assignments WHERE id = $reqId");
            $missingPrereq = ($titleRes && $titleRes->num_rows > 0) ? $titleRes->fetch_assoc()['title'] : 'Tugas sebelumnya';
        }
    }

    $submission = null;
    $subRes = $conn->query("SELECT * FROM submissions WHERE student_id = $studentId AND assignment_id = $assignmentId LIMIT 1");
    if ($subRes && $subRes->num_rows > 0) {
        $s = $subRes->fetch_assoc();
        $submission = [
            'id' => (int) $s['id'],
            'file_url' => StudentAccess::absoluteUploadUrl($s['file_path'] ?? null, $baseUrl),
            'grade' => $s['grade'] !== null ? (float) $s['grade'] : null,
            'feedback' => $s['feedback'],
            'created_at' => $s['created_at'],
        ];
    }

    $dueTs = strtotime((string) ($asn['due_date'] ?? ''));
    $dueOpen = $conn->query("SELECT id FROM assignments WHERE id = $assignmentId AND due_date > NOW() LIMIT 1");
    $isClosed = !($dueOpen && $dueOpen->num_rows > 0);
    if (!$isClosed && $dueTs !== false && $dueTs < time()) {
        $isClosed = true;
    }
    $canSubmit = !$locked && $submission === null && !$isClosed;

    ApiResponse::success([
        'id' => $assignmentId,
        'course_id' => $courseId,
        'course_title' => $asn['course_title'],
        'title' => $asn['title'],
        'description' => $asn['description'] ?? '',
        'due_date' => $asn['due_date'],
        'file_url' => StudentAccess::absoluteUploadUrl($asn['file_path'] ?? null, $baseUrl),
        'is_locked' => $locked,
        'is_closed' => $isClosed,
        'can_submit' => $canSubmit,
        'missing_prerequisite_title' => $missingPrereq,
        'submission' => $submission,
    ]);
}

// ---------------------------------------------------------------------------
// POST /assignments/{id}/submit  (multipart: assignment_file)
// ---------------------------------------------------------------------------
if (preg_match('#^/assignments/(\d+)/submit$#', $route, $m) && $method === 'POST') {
    $user = ApiAuth::requireStudent($conn);
    $studentId = (int) $user['id'];
    $assignmentId = (int) $m[1];

    $asnRes = $conn->query("SELECT * FROM assignments WHERE id = $assignmentId AND is_published = 1 LIMIT 1");
    if (!$asnRes || $asnRes->num_rows === 0) {
        ApiResponse::error('Tugas tidak ditemukan.', 404);
    }
    $asn = $asnRes->fetch_assoc();
    $courseId = (int) $asn['course_id'];

    if (!StudentAccess::canAccessCourse($conn, $studentId, $courseId)) {
        ApiResponse::error('Akses ditolak.', 403);
    }

    $dueTs = strtotime((string) ($asn['due_date'] ?? ''));
    if ($dueTs !== false && $dueTs < time()) {
        ApiResponse::error('Batas pengumpulan tugas sudah berakhir.', 422);
    }
    $stillOpen = $conn->query("SELECT id FROM assignments WHERE id = $assignmentId AND due_date > NOW() LIMIT 1");
    if (!$stillOpen || $stillOpen->num_rows === 0) {
        ApiResponse::error('Batas pengumpulan tugas sudah berakhir.', 422);
    }

    $existing = $conn->query("SELECT id FROM submissions WHERE student_id = $studentId AND assignment_id = $assignmentId");
    if ($existing && $existing->num_rows > 0) {
        ApiResponse::error('Anda sudah mengumpulkan tugas ini.', 409);
    }

    if (!isset($_FILES['assignment_file'])) {
        ApiResponse::error('File tugas wajib diunggah (field: assignment_file).', 422);
    }
    $file = $_FILES['assignment_file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        ApiResponse::error(StudentAccess::uploadErrorMessage((int) $file['error']), 422);
    }

    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
    $ext = StudentAccess::resolveUploadExtension($file);
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        $hint = $file['name'] ?? '';
        $mime = $file['type'] ?? '';
        ApiResponse::error(
            'Format file tidak diizinkan. Gunakan PDF/DOC/PPT/XLS/ZIP/gambar. (nama: ' . $hint . ', tipe: ' . $mime . ')',
            422
        );
    }

    $newFilename = 'ans_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFilename)) {
        ApiResponse::error('Gagal menyimpan file di server. Periksa permission folder uploads/.', 500);
    }

    $docEsc = $conn->real_escape_string($newFilename);
    if (!$conn->query("INSERT INTO submissions (assignment_id, student_id, file_path) VALUES ($assignmentId, $studentId, '$docEsc')")) {
        ApiResponse::error('Gagal merekam pengumpulan: ' . $conn->error, 500);
    }

    ApiResponse::success([
        'assignment_id' => $assignmentId,
        'file_url' => StudentAccess::absoluteUploadUrl($newFilename, $baseUrl),
    ], 'Berkas tugas berhasil diunggah');
}

// ---------------------------------------------------------------------------
// GET /profile
// ---------------------------------------------------------------------------
if ($route === '/profile' && $method === 'GET') {
    $user = ApiAuth::requireStudent($conn);
    ApiResponse::success(ApiAuth::publicUser($user, $baseUrl, $conn));
}

// ---------------------------------------------------------------------------
// PUT|POST /profile
// ---------------------------------------------------------------------------
if ($route === '/profile' && ($method === 'PUT' || $method === 'POST')) {
    $user = ApiAuth::requireStudent($conn);
    $userId = (int) $user['id'];
    $input = api_input();

    // Profil siswa: nama & NISN tidak diubah dari app (read-only).
    // Hanya ganti foto dan/atau password.
    $name = $user['name'];
    $username = $user['username'];
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    $fields = [];

    if ($newPassword !== '') {
        if ($currentPassword === '') {
            ApiResponse::error('Masukkan kata sandi saat ini untuk mengganti password.', 422);
        }
        $row = $conn->query("SELECT password FROM users WHERE id = $userId LIMIT 1")->fetch_assoc();
        if (!$row || !password_verify($currentPassword, $row['password'])) {
            ApiResponse::error('Kata sandi saat ini salah.', 422);
        }
        if (strlen($newPassword) < 6) {
            ApiResponse::error('Kata sandi baru minimal 6 karakter.', 422);
        }
        if ($newPassword !== $confirmPassword) {
            ApiResponse::error('Konfirmasi kata sandi baru tidak sama.', 422);
        }
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $fields[] = "password = '" . $conn->real_escape_string($hashed) . "'";
    }

    if (isset($_FILES['profile_pic'])) {
        $uploadErr = (int) ($_FILES['profile_pic']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $uploadMsg = [
                UPLOAD_ERR_INI_SIZE => 'Ukuran foto melebihi batas server.',
                UPLOAD_ERR_FORM_SIZE => 'Ukuran foto terlalu besar.',
                UPLOAD_ERR_PARTIAL => 'Unggahan foto terputus. Coba lagi.',
                UPLOAD_ERR_NO_FILE => 'Tidak ada file foto yang dikirim.',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak tersedia.',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file foto di server.',
                UPLOAD_ERR_EXTENSION => 'Unggahan foto diblokir ekstensi PHP.',
            ];
            ApiResponse::error($uploadMsg[$uploadErr] ?? 'Gagal mengunggah foto profil.', 422);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo((string) $_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            $mime = strtolower((string) ($_FILES['profile_pic']['type'] ?? ''));
            $mimeMap = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/pjpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            $ext = $mimeMap[$mime] ?? '';
        }
        if (!in_array($ext, $allowed, true)) {
            ApiResponse::error('Format foto harus JPG atau PNG.', 422);
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if ($_FILES['profile_pic']['size'] > 2000000) {
            ApiResponse::error('Ukuran foto maksimal 2MB.', 422);
        }
        $newFilename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $dest = __DIR__ . '/../uploads/' . $newFilename;
        if (!is_dir(__DIR__ . '/../uploads/')) {
            mkdir(__DIR__ . '/../uploads/', 0777, true);
        }
        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest)) {
            ApiResponse::error('Gagal mengunggah foto profil.', 500);
        }
        $old = $conn->query("SELECT profile_pic FROM users WHERE id = $userId")->fetch_assoc();
        if (!empty($old['profile_pic'])) {
            $oldPath = __DIR__ . '/../uploads/' . $old['profile_pic'];
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
        $fields[] = "profile_pic = '" . $conn->real_escape_string($newFilename) . "'";
    }

    if (empty($fields)) {
        ApiResponse::error('Tidak ada perubahan yang dikirim. Pastikan foto JPG/PNG terpilih.', 422);
    }

    $sql = 'UPDATE users SET ' . implode(', ', $fields) . " WHERE id = $userId";
    if (!$conn->query($sql)) {
        ApiResponse::error('Gagal memperbarui profil: ' . $conn->error, 500);
    }

    $fresh = $conn->query("SELECT id, name, username, email, role, profile_pic FROM users WHERE id = $userId")->fetch_assoc();
    ApiResponse::success(ApiAuth::publicUser($fresh, $baseUrl, $conn), 'Profil berhasil diperbarui');
}

ApiResponse::error('Endpoint tidak ditemukan: ' . $method . ' ' . $route, 404);
