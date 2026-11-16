
<?php

ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* =========================================
   JSON RESPONSE
========================================= */

function respond($success, $message = '', $data = [], $status = 200)
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/* =========================================
   DATABASE CONNECTION
========================================= */

try {
    require_once __DIR__ . '/../config/database.php';

    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }

} catch (Throwable $e) {
    error_log('Classroom DB Error: ' . $e->getMessage());

    respond(
        false,
        'Database connection failed. Check XAMPP MySQL.',
        [],
        500
    );
}

/* =========================================
   AUTHENTICATION
========================================= */

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    respond(false, 'Please log in first.', [], 401);
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role']));

if (!in_array($role, ['teacher', 'student'], true)) {
    respond(false, 'Unauthorized access.', [], 403);
}

/* =========================================
   CSRF PROTECTION
========================================= */

if (empty($_SESSION['classroom_csrf'])) {
    $_SESSION['classroom_csrf'] = bin2hex(random_bytes(32));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['classroom_csrf'], $csrf)
    ) {
        respond(false, 'Invalid security token. Refresh the page.', [], 419);
    }
}

/* =========================================
   CLASSROOM HELPERS
========================================= */

function getClassroom(PDO $conn, int $classroomId)
{
    $stmt = $conn->prepare("
        SELECT *
        FROM classrooms
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$classroomId]);

    return $stmt->fetch();
}

function isClassroomMember(PDO $conn, int $classroomId, int $userId)
{
    $classroom = getClassroom($conn, $classroomId);

    if (!$classroom) {
        return false;
    }

    if ((int) $classroom['teacher_id'] === $userId) {
        return true;
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM classroom_participants
        WHERE classroom_id = ?
          AND user_id = ?
          AND participant_status = 'joined'
        LIMIT 1
    ");

    $stmt->execute([$classroomId, $userId]);

    return (bool) $stmt->fetch();
}

function requireMembership(PDO $conn, int $classroomId, int $userId)
{
    if ($classroomId <= 0) {
        respond(false, 'Invalid classroom ID.', [], 422);
    }

    if (!isClassroomMember($conn, $classroomId, $userId)) {
        respond(false, 'You are not a member of this classroom.', [], 403);
    }
}

/* =========================================
   CLASSROOM ACTIONS
========================================= */

try {

    if ($action === '') {
        respond(false, 'No action specified.', [], 400);
    }

    /* =====================================
       CREATE CLASSROOM
    ===================================== */

    if ($action === 'create') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(false, 'Invalid request method.', [], 405);
        }

        if ($role !== 'teacher') {
            respond(false, 'Only teachers can create classrooms.', [], 403);
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || mb_strlen($title) > 150) {
            respond(false, 'Please enter a valid classroom title.', [], 422);
        }

        if (mb_strlen($description) > 5000) {
            respond(false, 'Description is too long.', [], 422);
        }

        // Generate a unique classroom code.
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));

            $check = $conn->prepare("
                SELECT id
                FROM classrooms
                WHERE classroom_code = ?
                LIMIT 1
            ");

            $check->execute([$code]);

        } while ($check->fetch());

        $stmt = $conn->prepare("
            INSERT INTO classrooms (
                teacher_id,
                classroom_code,
                title,
                description,
                status,
                started_at
            )
            VALUES (?, ?, ?, ?, 'live', NOW())
            RETURNING id
        ");

        $stmt->execute([
            $userId,
            $code,
            $title,
            $description
        ]);

        $classroomId = (int) $stmt->fetchColumn();

        respond(true, 'Classroom created successfully.', [
            'classroom_id' => $classroomId,
            'classroom_code' => $code,
            'title' => $title
        ]);
    }

    /* =====================================
       JOIN CLASSROOM
    ===================================== */

    if ($action === 'join') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(false, 'Invalid request method.', [], 405);
        }

        if ($role !== 'student') {
            respond(false, 'Only students can join classrooms using a code.', [], 403);
        }

        $code = strtoupper(trim($_POST['code'] ?? ''));

        if (!preg_match('/^[A-Z0-9]{8}$/', $code)) {
            respond(false, 'Invalid classroom code.', [], 422);
        }

        $stmt = $conn->prepare("
            SELECT id, teacher_id, title, classroom_code, status
            FROM classrooms
            WHERE classroom_code = ?
            LIMIT 1
        ");

        $stmt->execute([$code]);
        $classroom = $stmt->fetch();

        if (!$classroom || strtolower(trim((string) $classroom['status'])) !== 'live') {
            respond(false, 'Classroom not found or no longer live.', [], 404);
        }

        $stmt = $conn->prepare("
            INSERT INTO classroom_participants (
                classroom_id,
                user_id,
                joined_at,
                left_at,
                participant_status
            )
            VALUES (?, ?, NOW(), NULL, 'joined')
            ON CONFLICT (classroom_id, user_id) DO UPDATE
            SET joined_at = NOW(),
                left_at = NULL,
                participant_status = 'joined'
        ");

        $stmt->execute([
            (int) $classroom['id'],
            $userId
        ]);

        respond(true, 'You joined the classroom successfully.', [
            'classroom_id' => (int) $classroom['id'],
            'classroom_code' => $classroom['classroom_code'],
            'title' => $classroom['title']
        ]);
    }

    /* =====================================
       LEAVE CLASSROOM
    ===================================== */

    if ($action === 'leave') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(false, 'Invalid request method.', [], 405);
        }

        $classroomId = (int) ($_POST['classroom_id'] ?? 0);

        requireMembership($conn, $classroomId, $userId);

        $classroom = getClassroom($conn, $classroomId);

        if ($classroom['status'] !== 'live') {
            respond(false, 'Classroom is no longer live.', [], 409);
        }

        // The teacher ends the room using the end action.
        if ((int) $classroom['teacher_id'] !== $userId) {

            $stmt = $conn->prepare("
                UPDATE classroom_participants
                SET participant_status = 'left',
                    left_at = NOW()
                WHERE classroom_id = ?
                  AND user_id = ?
            ");

            $stmt->execute([$classroomId, $userId]);
        }

        respond(true, 'You left the classroom.');
    }

    /* =====================================
       END CLASSROOM
    ===================================== */

    if ($action === 'end') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(false, 'Invalid request method.', [], 405);
        }

        if ($role !== 'teacher') {
            respond(false, 'Only teachers can end classrooms.', [], 403);
        }

        $classroomId = (int) ($_POST['classroom_id'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE classrooms
            SET status = 'ended',
                ended_at = NOW()
            WHERE id = ?
              AND teacher_id = ?
              AND status = 'live'
        ");

        $stmt->execute([$classroomId, $userId]);

        if ($stmt->rowCount() === 0) {
            respond(false, 'Classroom not found or already ended.', [], 404);
        }

        $stmt = $conn->prepare("
            UPDATE classroom_participants
            SET participant_status = 'left',
                left_at = NOW()
            WHERE classroom_id = ?
              AND participant_status = 'joined'
        ");

        $stmt->execute([$classroomId]);

        respond(true, 'Classroom ended successfully.');
    }

    /* =====================================
       SEND MESSAGE
    ===================================== */

    if ($action === 'send_message') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(false, 'Invalid request method.', [], 405);
        }

        $classroomId = (int) ($_POST['classroom_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        requireMembership($conn, $classroomId, $userId);

        if ($message === '' || mb_strlen($message) > 2000) {
            respond(false, 'Message must be between 1 and 2000 characters.', [], 422);
        }

        $classroom = getClassroom($conn, $classroomId);

        if ($classroom['status'] !== 'live') {
            respond(false, 'Classroom is no longer live.', [], 409);
        }

        $stmt = $conn->prepare("
            INSERT INTO classroom_messages (
                classroom_id,
                sender_id,
                message
            )
            VALUES (?, ?, ?)
            RETURNING id
        ");

        $stmt->execute([
            $classroomId,
            $userId,
            $message
        ]);

        respond(true, 'Message sent successfully.', [
            'message_id' => (int) $stmt->fetchColumn()
        ]);
    }

    /* =====================================
       GET MESSAGES
    ===================================== */

    if ($action === 'get_messages') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            respond(false, 'Invalid request method.', [], 405);
        }

        $classroomId = (int) ($_GET['classroom_id'] ?? 0);
        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));

        requireMembership($conn, $classroomId, $userId);

        $stmt = $conn->prepare("
            SELECT
                m.id,
                m.sender_id,
                u.username,
                m.message,
                m.created_at
            FROM classroom_messages m
            INNER JOIN users u ON u.id = m.sender_id
            WHERE m.classroom_id = ?
              AND m.id > ?
            ORDER BY m.id ASC
            LIMIT 100
        ");

        $stmt->execute([$classroomId, $afterId]);

        respond(true, 'Messages loaded successfully.', $stmt->fetchAll());
    }

    /* =====================================
       SEND WEBRTC SIGNAL
    ===================================== */

    if ($action === 'signal_send') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(false, 'Invalid request method.', [], 405);
        }

        $classroomId = (int) ($_POST['classroom_id'] ?? 0);
        $receiverId = (int) ($_POST['receiver_id'] ?? 0);
        $signalType = trim($_POST['signal_type'] ?? '');
        $signalData = $_POST['signal_data'] ?? '';

        requireMembership($conn, $classroomId, $userId);

        if (!in_array($signalType, ['offer', 'answer', 'candidate'], true)) {
            respond(false, 'Invalid signal type.', [], 422);
        }

        if (
            $receiverId <= 0 ||
            $receiverId === $userId ||
            !is_string($signalData) ||
            strlen($signalData) > 100000
        ) {
            respond(false, 'Invalid signal data.', [], 422);
        }

        $classroom = getClassroom($conn, $classroomId);

        if ($classroom['status'] !== 'live') {
            respond(false, 'Classroom is no longer live.', [], 409);
        }

        if (!isClassroomMember($conn, $classroomId, $receiverId)) {
            respond(false, 'Invalid signal recipient.', [], 403);
        }

        $decoded = json_decode($signalData, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            respond(false, 'Signal data must be valid JSON.', [], 422);
        }

        $stmt = $conn->prepare("
            INSERT INTO classroom_signals (
                classroom_id,
                sender_id,
                receiver_id,
                signal_type,
                signal_data
            )
            VALUES (?, ?, ?, ?, ?)
            RETURNING id
        ");

        $stmt->execute([
            $classroomId,
            $userId,
            $receiverId,
            $signalType,
            $signalData
        ]);

        respond(true, 'Signal sent successfully.', [
            'signal_id' => (int) $stmt->fetchColumn()
        ]);
    }

    /* =====================================
       POLL WEBRTC SIGNALS
    ===================================== */

    if ($action === 'signal_poll') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            respond(false, 'Invalid request method.', [], 405);
        }

        $classroomId = (int) ($_GET['classroom_id'] ?? 0);
        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));

        requireMembership($conn, $classroomId, $userId);

        $stmt = $conn->prepare("
            SELECT
                id,
                sender_id,
                signal_type,
                signal_data,
                created_at
            FROM classroom_signals
            WHERE classroom_id = ?
              AND receiver_id = ?
              AND id > ?
            ORDER BY id ASC
            LIMIT 100
        ");

        $stmt->execute([
            $classroomId,
            $userId,
            $afterId
        ]);

        $signals = $stmt->fetchAll();

        foreach ($signals as &$signal) {
            $signal['signal_data'] = json_decode($signal['signal_data'], true);
        }

        unset($signal);

        respond(true, 'Signals loaded successfully.', $signals);
    }

    /* =====================================
       GET PARTICIPANTS
    ===================================== */

    if ($action === 'participants') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            respond(false, 'Invalid request method.', [], 405);
        }

        $classroomId = (int) ($_GET['classroom_id'] ?? 0);

        requireMembership($conn, $classroomId, $userId);

        $stmt = $conn->prepare("
            SELECT
                u.id,
                u.username,
                u.role,
                'joined' AS participant_status
            FROM classrooms c
            INNER JOIN users u ON u.id = c.teacher_id
            WHERE c.id = ?

            UNION ALL

            SELECT
                u.id,
                u.username,
                u.role,
                p.participant_status
            FROM classroom_participants p
            INNER JOIN users u ON u.id = p.user_id
            WHERE p.classroom_id = ?
              AND p.participant_status = 'joined'
        ");

        $stmt->execute([
            $classroomId,
            $classroomId
        ]);

        respond(true, 'Participants loaded successfully.', $stmt->fetchAll());
    }

    /* =====================================
       UNKNOWN ACTION
    ===================================== */

    respond(false, 'Unknown classroom action.', [], 404);

} catch (PDOException $e) {

    error_log('Classroom Database Error: ' . $e->getMessage());

    respond(
        false,
        'A database error occurred. Check your classroom database tables.',
        [],
        500
    );

} catch (Throwable $e) {

    error_log('Classroom PHP Error: ' . $e->getMessage());

    respond(
        false,
        'An unexpected server error occurred. Check the PHP error log.',
        [],
        500
    );

}