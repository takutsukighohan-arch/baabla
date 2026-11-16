<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond($status, $message, array $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge(['message' => $message], $extra));
    exit;
}

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    strtolower(trim((string)$_SESSION['role'])) !== 'student'
) {
    respond(401, 'Please sign in as a student.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, 'Method not allowed.');
}

$csrf = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrf) ||
    empty($_SESSION['textbook_activity_csrf']) ||
    !hash_equals($_SESSION['textbook_activity_csrf'], $csrf)
) {
    respond(403, 'Your session expired. Refresh the textbook and try again.');
}

$sessionId = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
if (!$sessionId || $sessionId < 1 || !in_array($action, ['complete', 'rate'], true)) {
    respond(400, 'Invalid textbook activity request.');
}

try {
    require_once __DIR__ . '/../config/database.php';
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $studentId = (int)$_SESSION['user_id'];
    if ($action === 'complete') {
        $stmt = $conn->prepare("
            UPDATE public.textbook_reading_sessions AS reading
            SET completed_at = COALESCE(reading.completed_at, now())
            FROM public.textbooks AS book
            WHERE reading.id = :session_id
              AND reading.student_id = :student_id
              AND reading.textbook_id = book.id
              AND book.approval_status = 'approved'
              AND book.is_archived = false
            RETURNING reading.completed_at
        ");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':student_id' => $studentId
        ]);
        if ($stmt->fetchColumn() === false) {
            respond(404, 'This reading session is no longer available.');
        }
        respond(200, 'Textbook marked as completed.');
    }

    $rating = $_POST['rating'] ?? '';
    if (!in_array($rating, ['good', 'not_good'], true)) {
        respond(400, 'Choose whether this textbook was good or not good.');
    }

    $stmt = $conn->prepare("
        UPDATE public.textbook_reading_sessions AS reading
        SET is_good = :is_good,
            closed_at = now()
        FROM public.textbooks AS book
        WHERE reading.id = :session_id
          AND reading.student_id = :student_id
          AND reading.closed_at IS NULL
          AND reading.is_good IS NULL
          AND reading.textbook_id = book.id
          AND book.approval_status = 'approved'
          AND book.is_archived = false
        RETURNING reading.id
    ");
    $stmt->execute([
        ':is_good' => $rating === 'good' ? 'true' : 'false',
        ':session_id' => $sessionId,
        ':student_id' => $studentId
    ]);
    if ($stmt->fetchColumn() === false) {
        respond(409, 'This reading has already been rated or is no longer available.');
    }

    respond(200, 'Thank you for rating this textbook.');
} catch (Throwable $e) {
    error_log('Student textbook activity update failed: ' . $e->getMessage());
    respond(500, 'We could not save your update. Please try again.');
}
