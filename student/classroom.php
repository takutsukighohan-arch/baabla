
<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../config/database.php';

/* =========================================
   AUTHENTICATION
========================================= */

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role']));

if (!in_array($role, ['teacher', 'student'], true)) {
    header("Location: " . ($role === 'admin'
        ? '../admin/admin-dashboard.php'
        : '../login.php'));
    exit;
}

/* =========================================
   USER INFORMATION
========================================= */

$stmt = $conn->prepare("
    SELECT id, username, email, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$userId]);

$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$userName = $currentUser['username'] ?: 'User';

/* =========================================
   CSRF TOKEN
========================================= */

if (empty($_SESSION['classroom_csrf'])) {
    $_SESSION['classroom_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['classroom_csrf'];

/* =========================================
   CURRENT CLASSROOM
========================================= */

$roomId = isset($_GET['room']) ? (int) $_GET['room'] : 0;

$currentRoom = null;
$roomError = '';

if ($roomId > 0) {

    $stmt = $conn->prepare("
        SELECT
            c.*,
            u.username AS teacher_name
        FROM classrooms c
        LEFT JOIN users u ON u.id = c.teacher_id
        WHERE c.id = ?
        LIMIT 1
    ");

    $stmt->execute([$roomId]);
    $currentRoom = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentRoom) {

        $roomError = "The classroom you are trying to access does not exist.";

    } else {

        $isOwner = ((int) $currentRoom['teacher_id'] === $userId);

        if (!$isOwner) {

            $stmt = $conn->prepare("
                SELECT id
                FROM classroom_participants
                WHERE classroom_id = ?
                  AND user_id = ?
                  AND participant_status = 'joined'
                LIMIT 1
            ");

            $stmt->execute([$roomId, $userId]);

            if (!$stmt->fetch()) {
                $roomError = "You are not a participant of this classroom. Please join using the classroom code.";
                $currentRoom = null;
            }
        }
    }
}

/* =========================================
   CLASSROOM STATISTICS
========================================= */

$totalClassrooms = 0;
$liveClassrooms = 0;
$totalParticipants = 0;
$classroomHistory = [];

if ($role === 'teacher') {

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM classrooms
        WHERE teacher_id = ?
    ");
    $stmt->execute([$userId]);

    $totalClassrooms = (int) $stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM classrooms
        WHERE teacher_id = ?
          AND status = 'live'
    ");
    $stmt->execute([$userId]);

    $liveClassrooms = (int) $stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT cp.user_id)
        FROM classroom_participants cp
        INNER JOIN classrooms c ON c.id = cp.classroom_id
        WHERE c.teacher_id = ?
    ");
    $stmt->execute([$userId]);

    $totalParticipants = (int) $stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.classroom_code,
            c.title,
            c.description,
            c.status,
            c.started_at,
            c.ended_at,
            c.created_at,
            (
                SELECT COUNT(*)
                FROM classroom_participants cp
                WHERE cp.classroom_id = c.id
            ) AS participant_count
        FROM classrooms c
        WHERE c.teacher_id = ?
        ORDER BY c.created_at DESC
        LIMIT 10
    ");

    $stmt->execute([$userId]);
    $classroomHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================
   CURRENT PARTICIPANT COUNT
========================================= */

$currentParticipantCount = 0;

if ($currentRoom) {

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM classroom_participants
        WHERE classroom_id = ?
          AND participant_status = 'joined'
    ");

    $stmt->execute([$roomId]);
    $currentParticipantCount = (int) $stmt->fetchColumn();
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$isInRoom = ($currentRoom !== null);
$isTeacher = ($role === 'teacher');

$isRoomOwner = $isInRoom &&
    ((int) $currentRoom['teacher_id'] === $userId);

$dashboardUrl = $isTeacher
    ? '../teacher/teacher-dashboard.php'
    : '../student/dashboard.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Live Classroom | ASLify</title>

    <link rel="stylesheet" href="classroom.css?v=2">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<style>

    @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

:root {
    --primary: #2d68c4;
    --primary-dark: #2055a6;
    --background: #f4f7fc;
    --surface: #ffffff;
    --text: #1d2939;
    --muted: #78869a;
    --border: #e6ebf2;
    --danger: #e5484d;
    --success: #16a36a;
    --radius: 16px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--background);
    color: var(--text);
    min-height: 100vh;
}

button, input, textarea {
    font: inherit;
}

button {
    cursor: pointer;
}

.hidden {
    display: none !important;
}

/* =========================
   MAIN LAYOUT
========================= */

.app-shell {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.topbar {
    min-height: 76px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.brand,
.header-user,
.user-info,
.role-chip {
    display: flex;
    align-items: center;
}

.brand {
    gap: 12px;
}

.brand-icon {
    width: 43px;
    height: 43px;
    border-radius: 13px;
    background: var(--primary);
    color: white;
    display: grid;
    place-items: center;
    font-size: 20px;
    font-weight: 800;
}

.brand h2 {
    font-size: 21px;
    font-weight: 800;
    letter-spacing: -0.7px;
}

.brand span {
    display: block;
    margin-top: 3px;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: var(--muted);
}

.header-user {
    gap: 11px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: #e8efff;
    color: var(--primary);
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 800;
}

.user-info {
    align-items: flex-start;
    flex-direction: column;
    gap: 3px;
}

.user-info strong {
    font-size: 13px;
}

.user-info span {
    font-size: 11px;
    color: var(--muted);
    text-transform: capitalize;
}

.logout-link {
    text-decoration: none;
    color: #69788d;
    font-size: 12px;
    font-weight: 700;
    margin-left: 15px;
    padding: 10px 13px;
    border-radius: 9px;
}

.logout-link:hover {
    background: #f2f5fa;
    color: var(--danger);
}

/* =========================
   MAIN CONTENT
========================= */

.main-content {
    width: min(1450px, 90%);
    margin: 38px auto;
    flex: 1;
}

.welcome-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 27px;
}

.eyebrow {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.7px;
    color: var(--primary);
}

.welcome-section h1 {
    font-size: clamp(27px, 4vw, 36px);
    letter-spacing: -1.2px;
    margin: 8px 0;
}

.welcome-section p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.6;
}

.role-chip {
    gap: 9px;
    padding: 11px 15px;
    background: #eaf2ff;
    border-radius: 30px;
    color: var(--primary);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.status-dot,
.live-dot {
    width: 8px;
    height: 8px;
    background: var(--success);
    border-radius: 50%;
}

/* =========================
   NOTIFICATIONS
========================= */

.notice {
    padding: 13px 16px;
    margin-bottom: 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
}

.notice.success {
    background: #e9f8f0;
    color: #147d50;
}

.notice.error {
    background: #fff0f0;
    color: #c12f35;
}

/* =========================
   DASHBOARD
========================= */

.dashboard-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 330px;
    gap: 22px;
    align-items: start;
}

.main-panel,
.history-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 5px 25px rgba(24, 45, 85, 0.035);
}

.main-panel {
    padding: 30px;
}

.panel-heading,
.history-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.panel-heading h2 {
    font-size: 21px;
    letter-spacing: -0.5px;
    margin-bottom: 7px;
}

.panel-heading p,
.history-heading p {
    color: var(--muted);
    font-size: 12px;
}

.heading-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: #eaf2ff;
    color: var(--primary);
    font-size: 20px;
}

.class-form {
    margin-top: 26px;
    display: flex;
    flex-direction: column;
    gap: 11px;
}

.class-form label {
    font-size: 12px;
    font-weight: 700;
    margin-top: 7px;
}

.class-form input,
.class-form textarea {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 13px 14px;
    background: #fbfcfe;
    color: var(--text);
    outline: none;
    font-size: 13px;
    resize: vertical;
}

.class-form input:focus,
.class-form textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(45, 104, 196, 0.09);
}

.primary-btn {
    border: none;
    border-radius: 10px;
    padding: 14px 18px;
    background: var(--primary);
    color: white;
    font-weight: 700;
    font-size: 13px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 9px;
    margin-top: 10px;
    transition: 0.2s;
}

.primary-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.feature-grid {
    margin-top: 32px;
    padding-top: 25px;
    border-top: 1px solid var(--border);
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.feature-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.feature-icon {
    width: 39px;
    height: 39px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    margin-bottom: 4px;
}

.feature-icon.blue {
    color: #2d68c4;
    background: #eaf2ff;
}

.feature-icon.purple {
    color: #8056cf;
    background: #f1eaff;
}

.feature-icon.green {
    color: #169e6a;
    background: #e8f8f0;
}

.feature-card strong {
    font-size: 12px;
}

.feature-card span {
    font-size: 10px;
    color: var(--muted);
    line-height: 1.5;
}

/* =========================
   HISTORY
========================= */

.history-panel {
    padding: 23px;
}

.history-heading {
    padding-bottom: 19px;
    border-bottom: 1px solid var(--border);
}

.history-heading h3 {
    font-size: 15px;
    margin-bottom: 5px;
}

.history-heading > i {
    color: var(--primary);
    font-size: 18px;
}

.empty-history {
    min-height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 9px;
    text-align: center;
    color: var(--muted);
}

.empty-history i {
    font-size: 30px;
    color: #b4c1d4;
    margin-bottom: 5px;
}

.empty-history strong {
    font-size: 13px;
    color: var(--text);
}

.empty-history span {
    font-size: 11px;
    line-height: 1.5;
}

.history-list {
    display: flex;
    flex-direction: column;
}

.history-item {
    display: flex;
    gap: 12px;
    padding: 15px 0;
    border-bottom: 1px solid var(--border);
}

.history-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    color: var(--primary);
    background: #edf3ff;
    border-radius: 10px;
}

.history-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
}

.history-details strong {
    font-size: 12px;
    overflow-wrap: anywhere;
}

.history-details span,
.history-details small {
    font-size: 10px;
    color: var(--muted);
}

/* =========================
   LIVE ROOM
========================= */

.live-room {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 25px;
    box-shadow: 0 5px 25px rgba(24, 45, 85, 0.035);
}

.room-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--border);
}

.live-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
    color: var(--danger);
    margin-bottom: 9px;
}

.live-label .live-dot {
    background: var(--danger);
    animation: pulse 1.4s infinite;
}

@keyframes pulse {
    50% { opacity: 0.35; }
}

.room-header h2 {
    font-size: 22px;
    margin-bottom: 7px;
}

.room-header p {
    color: var(--muted);
    font-size: 12px;
}

.room-header p strong {
    color: var(--primary);
    letter-spacing: 1px;
}

.copy-btn {
    border: none;
    background: #edf3ff;
    color: var(--primary);
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    margin-left: 5px;
}

.secondary-btn {
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: white;
    color: var(--text);
    font-size: 11px;
    font-weight: 700;
}

.room-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 20px;
    margin-top: 22px;
}

.video-section {
    min-width: 0;
}

.video-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.video-card {
    position: relative;
    min-height: 210px;
    aspect-ratio: 16 / 10;
    border-radius: 12px;
    overflow: hidden;
    background: #182235;
}

.video-card video {
    width: 100%;
    height: 100%;
    position: absolute;
    inset: 0;
    object-fit: cover;
}

.video-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    color: white;
    background: linear-gradient(transparent, rgba(0,0,0,0.75));
    font-size: 11px;
    font-weight: 700;
}

.video-status {
    padding: 4px 7px;
    background: rgba(255,255,255,0.18);
    border-radius: 5px;
    font-size: 9px;
}

.video-controls {
    margin-top: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 15px;
    background: #f6f8fc;
    border-radius: 12px;
}

.control-btn {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 11px 13px;
    min-width: 75px;
    background: white;
    color: #44536a;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 7px;
    font-size: 10px;
    font-weight: 700;
}

.control-btn i {
    font-size: 16px;
}

.control-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.control-btn.active {
    background: #eaf2ff;
    border-color: #c7d9fb;
    color: var(--primary);
}

.control-btn.danger {
    background: var(--danger);
    color: white;
    border-color: var(--danger);
}

.control-btn.danger:hover {
    background: #c92e35;
}

/* =========================
   ROOM SIDEBAR
========================= */

.room-sidebar {
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    min-height: 400px;
    display: flex;
    flex-direction: column;
}

.sidebar-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: #f7f9fc;
    border-bottom: 1px solid var(--border);
}

.tab-btn {
    border: none;
    background: transparent;
    padding: 15px 8px;
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
    border-bottom: 2px solid transparent;
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background: white;
}

.tab-content {
    min-height: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.sidebar-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid var(--border);
    font-size: 11px;
}

.sidebar-title span {
    background: #edf3ff;
    color: var(--primary);
    padding: 4px 8px;
    border-radius: 20px;
    font-weight: 800;
}

.participant-list {
    padding: 10px 15px;
}

.participant-item {
    padding: 11px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #f0f2f6;
    font-size: 11px;
}

.participant-avatar {
    width: 30px;
    height: 30px;
    background: #eaf2ff;
    color: var(--primary);
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 800;
}

.participant-role {
    margin-left: auto;
    color: var(--muted);
    font-size: 9px;
}

.chat-messages {
    height: 315px;
    overflow-y: auto;
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 13px;
}

.chat-message {
    font-size: 11px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.chat-message strong {
    display: block;
    color: var(--primary);
    margin-bottom: 4px;
}

.chat-message p {
    background: #f2f5fa;
    padding: 9px 11px;
    border-radius: 9px;
    color: var(--text);
}

.chat-form {
    margin-top: auto;
    padding: 12px;
    display: flex;
    gap: 8px;
    border-top: 1px solid var(--border);
}

.chat-form input {
    flex: 1;
    min-width: 0;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
    font-size: 11px;
}

.chat-form button {
    width: 37px;
    border: none;
    background: var(--primary);
    color: white;
    border-radius: 8px;
}

/* =========================
   FOOTER
========================= */

.footer {
    padding: 20px 5%;
    background: white;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    gap: 10px;
    color: var(--muted);
    font-size: 10px;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {
    .dashboard-layout {
        grid-template-columns: 1fr;
    }

    .room-layout {
        grid-template-columns: 1fr;
    }

    .room-sidebar {
        min-height: 350px;
    }
}

@media (max-width: 650px) {
    .topbar {
        padding: 0 5%;
    }

    .user-info,
    .logout-link {
        display: none;
    }

    .main-content {
        width: 92%;
        margin: 25px auto;
    }

    .welcome-section {
        align-items: flex-start;
        flex-direction: column;
    }

    .main-panel,
    .live-room {
        padding: 18px;
    }

    .feature-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .video-grid {
        grid-template-columns: 1fr;
    }

    .video-card {
        min-height: 220px;
    }

    .room-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .footer {
        flex-direction: column;
        text-align: center;
    }

    .control-btn {
        flex: 1 1 75px;
    }
}

/* =========================================
   LIVE ASL TRANSLATOR
========================================= */

.asl-translator-panel {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px;
    margin-top: 20px;
}

.asl-translator-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.asl-translator-header h3 {
    color: #252b48;
    font-size: 17px;
    margin: 0 0 5px;
}

.asl-translator-header p {
    color: #7b8194;
    font-size: 12px;
    margin: 0;
}

.asl-status {
    background: #f1f5f9;
    color: #64748b;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.asl-status.active {
    background: #dcfce7;
    color: #15803d;
}

.asl-result {
    background: #f5f7ff;
    border: 1px solid #e4e7ff;
    border-radius: 12px;
    padding: 22px;
    text-align: center;
}

.asl-result > span {
    color: #777e94;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
}

.asl-result h2 {
    color: #6658d9;
    font-size: 36px;
    margin: 10px 0;
}

.asl-result p {
    color: #7b8194;
    font-size: 12px;
    margin: 0;
}

.asl-detection-info {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #64748b;
    font-size: 13px;
    margin-top: 15px;
}

.asl-detection-info i {
    color: #6658d9;
}

@media (max-width: 600px) {
    .asl-translator-header {
        align-items: flex-start;
        flex-direction: column;
    }
}

</style>
<body>

<div class="app-shell">

    <!-- TOP BAR -->

    <header class="topbar">

        <div class="brand">

            <div class="brand-icon">
                <i class="fa-solid fa-hands-asl-interpreting"></i>
            </div>

            <div>
                <h2>ASLify</h2>
                <span>Sign Language System</span>
            </div>

        </div>

        <div class="header-user">

            <div class="user-info">
                <span class="user-name"><?= e($userName) ?></span>
                <span class="user-role"><?= e(ucfirst($role)) ?></span>
            </div>

            <div class="user-avatar">
                <?= e(strtoupper(substr($userName, 0, 1))) ?>
            </div>

        </div>

    </header>


    <main class="main-content">

        <?php if (!$isInRoom): ?>

            <!-- CLASSROOM LANDING PAGE -->

            <div class="welcome-section">

                <div>

                    <div class="role-chip">
                        <i class="fa-solid fa-video"></i>
                        LIVE LEARNING
                    </div>

                    <h1>Live Classroom</h1>

                    <p>
                        Connect with your class in real time through video,
                        audio, and classroom discussions.
                    </p>

                </div>

                <a href="<?= e($dashboardUrl) ?>" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Dashboard
                </a>

            </div>


            <?php if ($roomError): ?>

                <div class="notice notice-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= e($roomError) ?>
                </div>

            <?php endif; ?>


            <?php if ($isTeacher): ?>

                <!-- TEACHER STATISTICS -->

                <section class="feature-grid">

                    <div class="feature-card">

                        <div class="feature-icon">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>

                        <div>
                            <span>Total Classrooms</span>
                            <h3><?= number_format($totalClassrooms) ?></h3>
                        </div>

                    </div>

                    <div class="feature-card">

                        <div class="feature-icon">
                            <i class="fa-solid fa-tower-broadcast"></i>
                        </div>

                        <div>
                            <span>Live Classrooms</span>
                            <h3><?= number_format($liveClassrooms) ?></h3>
                        </div>

                    </div>

                    <div class="feature-card">

                        <div class="feature-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>

                        <div>
                            <span>Total Participants</span>
                            <h3><?= number_format($totalParticipants) ?></h3>
                        </div>

                    </div>

                </section>


                <!-- CREATE AND JOIN -->

                <section class="dashboard-layout">

                    <div class="main-panel">

                        <div class="panel-heading">

                            <div>
                                <h2>Create a Live Classroom</h2>
                                <p>Start a live session with your students.</p>
                            </div>

                            <div class="panel-heading-icon">
                                <i class="fa-solid fa-video"></i>
                            </div>

                        </div>


                        <form id="createClassroomForm" class="class-form">

                            <div class="form-group">

                                <label for="classroomTitle">
                                    Classroom Title
                                </label>

                                <input
                                    type="text"
                                    id="classroomTitle"
                                    name="title"
                                    placeholder="Example: Basic Sign Language - Lesson 1"
                                    maxlength="150"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="classroomDescription">
                                    Description
                                    <span class="optional-label">Optional</span>
                                </label>

                                <textarea
                                    id="classroomDescription"
                                    name="description"
                                    rows="4"
                                    maxlength="5000"
                                    placeholder="Enter classroom details or instructions..."
                                ></textarea>

                            </div>


                            <div id="createMessage" class="form-message"></div>


                            <button type="submit" class="primary-btn" id="createRoomBtn">

                                <i class="fa-solid fa-video"></i>
                                Create Live Classroom

                            </button>

                        </form>

                    </div>


                    <aside class="history-panel">

                        <div class="panel-heading">

                            <div>
                                <h2>Join a Classroom</h2>
                                <p>Enter a classroom code to join a session.</p>
                            </div>

                            <div class="panel-heading-icon">
                                <i class="fa-solid fa-key"></i>
                            </div>

                        </div>


                        <form id="joinClassroomForm" class="class-form">

                            <div class="form-group">

                                <label for="joinCode">
                                    Classroom Code
                                </label>

                                <input
                                    type="text"
                                    id="joinCode"
                                    name="classroom_code"
                                    placeholder="Enter classroom code"
                                    maxlength="8"
                                    required
                                >

                            </div>

                            <div id="joinMessage" class="form-message"></div>

                            <button type="submit" class="primary-btn">

                                <i class="fa-solid fa-right-to-bracket"></i>
                                Join Classroom

                            </button>

                        </form>

                    </aside>

                </section>


                <!-- CLASSROOM FEATURES -->

                <section class="feature-section">

                    <div class="section-heading">
                        <h2>Classroom Features</h2>
                        <p>Tools available during your live classroom sessions.</p>
                    </div>

                    <div class="feature-grid">

                        <div class="feature-card">

                            <div class="feature-icon">
                                <i class="fa-solid fa-video"></i>
                            </div>

                            <div>
                                <h3>Live Video</h3>
                                <span>Interact through your camera.</span>
                            </div>

                        </div>

                        <div class="feature-card">

                            <div class="feature-icon">
                                <i class="fa-solid fa-microphone"></i>
                            </div>

                            <div>
                                <h3>Audio Controls</h3>
                                <span>Manage your microphone.</span>
                            </div>

                        </div>

                        <div class="feature-card">

                            <div class="feature-icon">
                                <i class="fa-solid fa-comments"></i>
                            </div>

                            <div>
                                <h3>Classroom Chat</h3>
                                <span>Send messages to participants.</span>
                            </div>

                        </div>

                        <div class="feature-card">

                            <div class="feature-icon">
                                <i class="fa-solid fa-desktop"></i>
                            </div>

                            <div>
                                <h3>Screen Sharing</h3>
                                <span>Share your screen with the class.</span>
                            </div>

                        </div>

                    </div>

                </section>


                <!-- CLASSROOM HISTORY -->

                <section class="history-section">

                    <div class="section-heading">
                        <div>
                            <h2>Classroom History</h2>
                            <p>Recently created classrooms.</p>
                        </div>
                    </div>


                    <?php if (!empty($classroomHistory)): ?>

                        <div class="table-responsive">

                            <table class="classroom-table">

                                <thead>
                                    <tr>
                                        <th>Classroom</th>
                                        <th>Code</th>
                                        <th>Participants</th>
                                        <th>Status</th>
                                        <th>Date Created</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                <?php foreach ($classroomHistory as $history): ?>

                                    <tr>

                                        <td>
                                            <strong><?= e($history['title']) ?></strong>
                                        </td>

                                        <td>
                                            <span class="class-code">
                                                <?= e($history['classroom_code']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= (int) $history['participant_count'] ?>
                                        </td>

                                        <td>
                                            <span class="status-badge status-<?= e($history['status']) ?>">
                                                <?= e(ucfirst($history['status'])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= e(date('M d, Y', strtotime($history['created_at']))) ?>
                                        </td>

                                        <td>

                                            <?php if ($history['status'] === 'live'): ?>

                                                <a
                                                    href="classroom.php?room=<?= (int) $history['id'] ?>"
                                                    class="table-action"
                                                >
                                                    <i class="fa-solid fa-arrow-right"></i>
                                                    Open
                                                </a>

                                            <?php else: ?>

                                                <span class="muted-text">Ended</span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="empty-history">

                            <div class="empty-icon">
                                <i class="fa-solid fa-video-slash"></i>
                            </div>

                            <h3>No Classroom History</h3>

                            <p>
                                You haven't created any classrooms yet.
                                Create your first live classroom to get started.
                            </p>

                        </div>

                    <?php endif; ?>

                </section>


            <?php else: ?>

                <!-- STUDENT JOIN PAGE -->

                <section class="dashboard-layout">

                    <div class="main-panel">

                        <div class="panel-heading">

                            <div>
                                <h2>Join Your Live Classroom</h2>
                                <p>Enter the classroom code provided by your teacher.</p>
                            </div>

                            <div class="panel-heading-icon">
                                <i class="fa-solid fa-users"></i>
                            </div>

                        </div>


                        <form id="joinClassroomForm" class="class-form">

                            <div class="form-group">

                                <label for="joinCode">
                                    Classroom Code
                                </label>

                                <input
                                    type="text"
                                    id="joinCode"
                                    name="classroom_code"
                                    placeholder="Enter your classroom code"
                                    maxlength="8"
                                    required
                                >

                            </div>

                            <div id="joinMessage" class="form-message"></div>

                            <button type="submit" class="primary-btn">

                                <i class="fa-solid fa-right-to-bracket"></i>
                                Join Classroom

                            </button>

                        </form>

                    </div>


                    <aside class="history-panel">

                        <div class="panel-heading">

                            <div>
                                <h2>Before You Join</h2>
                                <p>Prepare your device for the session.</p>
                            </div>

                            <div class="panel-heading-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>

                        </div>


                        <div class="student-instructions">

                            <p>
                                <i class="fa-solid fa-video"></i>
                                Allow camera access when prompted.
                            </p>

                            <p>
                                <i class="fa-solid fa-microphone"></i>
                                Allow microphone access when prompted.
                            </p>

                            <p>
                                <i class="fa-solid fa-wifi"></i>
                                Make sure you have a stable internet connection.
                            </p>

                            <p>
                                <i class="fa-solid fa-key"></i>
                                Use the correct classroom code from your teacher.
                            </p>

                        </div>

                    </aside>

                </section>

            <?php endif; ?>


        <?php else: ?>

            <!-- LIVE CLASSROOM -->

            <section class="live-room">

                <div class="room-header">

                    <div class="room-title">

                        <a href="classroom.php" class="back-link">
                            <i class="fa-solid fa-arrow-left"></i>
                            Back
                        </a>

                        <div>
                            <h1><?= e($currentRoom['title']) ?></h1>

                            <p>
                                <i class="fa-solid fa-user-tie"></i>
                                <?= e($currentRoom['teacher_name'] ?? 'Teacher') ?>
                            </p>
                        </div>

                    </div>


                    <div class="room-status-area">

                        <span class="status-badge status-<?= e($currentRoom['status']) ?>">
                            <span class="status-dot"></span>
                            <?= e(strtoupper($currentRoom['status'])) ?>
                        </span>

                        <span class="participant-counter">
                            <i class="fa-solid fa-users"></i>

                            <span id="participantCount">
                                <?= $currentParticipantCount + ($isRoomOwner ? 1 : 0) ?>
                            </span>

                            Participants
                        </span>

                    </div>

                </div>


                <?php if ($currentRoom['status'] !== 'live'): ?>

                    <div class="notice notice-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        This classroom has already ended.
                    </div>

                <?php else: ?>

                    <div class="room-layout">

                        <!-- VIDEO AREA -->

                        <div class="main-panel room-main-panel">

                            <div class="video-grid" id="videoGrid">

                                <div class="video-card local-video-card" id="localVideoCard">

                                    <video
                                        id="localVideo"
                                        autoplay
                                        muted
                                        playsinline
                                    ></video>

                                    <div class="video-label">
                                        <i class="fa-solid fa-user"></i>
                                        You (<?= e($userName) ?>)
                                    </div>

                                    <div class="video-placeholder" id="localVideoPlaceholder">
                                        <i class="fa-solid fa-video-slash"></i>
                                        <span>Camera is off</span>
                                    </div>

                                </div>

                            </div>


                            <!-- VIDEO CONTROLS -->

                            <div class="video-controls">

                                <button type="button" class="control-btn" id="cameraBtn">
                                    <i class="fa-solid fa-video-slash"></i>
                                    <span>Camera Off</span>
                                </button>

                                <button type="button" class="control-btn" id="micBtn">
                                    <i class="fa-solid fa-microphone-slash"></i>
                                    <span>Microphone Off</span>
                                </button>

                                <button type="button" class="control-btn" id="screenShareBtn">
                                    <i class="fa-solid fa-desktop"></i>
                                    <span>Share Screen</span>
                                </button>

                                <button type="button" class="control-btn leave-btn" id="leaveRoomBtn">
                                    <i class="fa-solid fa-phone-slash"></i>
                                    <span>Leave</span>
                                </button>

                                <?php if ($isRoomOwner): ?>

                                    <button type="button" class="control-btn end-btn" id="endRoomBtn">
                                        <i class="fa-solid fa-circle-stop"></i>
                                        <span>End Class</span>
                                    </button>

                                <?php endif; ?>

                            </div>

                            <div id="roomNotice" class="form-message"></div>

                        </div>


                        <!-- SIDEBAR -->

                        <aside class="room-sidebar">

                            <div class="sidebar-tabs">

                                <button
                                    type="button"
                                    class="tab-btn active"
                                    data-tab="participants"
                                >
                                    <i class="fa-solid fa-users"></i>
                                    Participants
                                </button>

                                <button
                                    type="button"
                                    class="tab-btn"
                                    data-tab="chat"
                                >
                                    <i class="fa-solid fa-comments"></i>
                                    Chat
                                </button>

                            </div>


                            <!-- PARTICIPANTS -->

                            <div class="tab-content active" id="participantsTab">

                                <div class="sidebar-content-heading">
                                    <h3>In this classroom</h3>

                                    <span id="participantCountSide">
                                        <?= $currentParticipantCount + ($isRoomOwner ? 1 : 0) ?>
                                    </span>
                                </div>

                                <div class="participant-list" id="participantList">

                                    <div class="participant-item">

                                        <div class="participant-avatar">
                                            <?= e(strtoupper(substr($userName, 0, 1))) ?>
                                        </div>

                                        <div class="participant-details">
                                            <strong><?= e($userName) ?></strong>
                                            <span>You</span>
                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- CHAT -->

                            <div class="tab-content" id="chatTab">

                                <div class="chat-messages" id="chatMessages">

                                    <div class="chat-empty">
                                        <i class="fa-regular fa-comments"></i>
                                        <p>No messages yet.</p>
                                        <span>Start the conversation.</span>
                                    </div>

                                </div>


                                <form id="chatForm" class="chat-form">

                                    <input
                                        type="text"
                                        id="chatInput"
                                        placeholder="Type a message..."
                                        maxlength="2000"
                                        autocomplete="off"
                                        required
                                    >

                                    <button type="submit" aria-label="Send message">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>

                                </form>

                            </div>

                        </aside>

                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

    </main>


    <footer class="footer">
        <p>© <?= date('Y') ?> ASLify. All rights reserved.</p>
        <span>Sign Language System</span>
    </footer>

</div>


<script>

/* =========================================
   CONFIGURATION
========================================= */

const API_URL = new URL(
    "classroom-api.php",
    window.location.href
).href;

const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

const CURRENT_USER_ID = Number(<?= json_encode($userId) ?>);
const CURRENT_USER_NAME = <?= json_encode($userName) ?>;

const ROOM_ID = Number(<?= json_encode($isInRoom ? $roomId : 0) ?>);

const IS_ROOM_OWNER = <?= json_encode($isRoomOwner) ?>;

const RTC_CONFIG = {
    iceServers: [
        {
            urls: "stun:stun.l.google.com:19302"
        }
    ]
};


/* =========================================
   API RESPONSE HANDLER
========================================= */

async function parseApiResponse(response) {

    const raw = await response.text();

    console.log("Classroom API URL:", response.url);
    console.log("HTTP Status:", response.status);

    let result;

    try {

        result = JSON.parse(raw);

    } catch (error) {

        console.error("Invalid API response:");
        console.error(raw);

        throw new Error(
            "The server returned HTML instead of JSON. Check the classroom-api.php response in the browser console."
        );

    }

    if (!response.ok || !result.success) {

        throw new Error(
            result.message || "The classroom request failed."
        );

    }

    return result;
}


/* =========================================
   POST REQUEST
========================================= */

async function apiPost(action, values = {}) {

    const formData = new FormData();

    formData.append("action", action);

    Object.entries(values).forEach(([key, value]) => {

        formData.append(
            key,
            typeof value === "object" && value !== null
                ? JSON.stringify(value)
                : value
        );

    });

    const response = await fetch(API_URL, {

        method: "POST",

        headers: {
            "X-CSRF-TOKEN": CSRF_TOKEN,
            "Accept": "application/json"
        },

        body: formData,

        cache: "no-store"

    });

    return await parseApiResponse(response);
}


/* =========================================
   GET REQUEST
========================================= */

async function apiGet(action, values = {}) {

    const params = new URLSearchParams();

    params.append("action", action);

    Object.entries(values).forEach(([key, value]) => {

        params.append(key, value);

    });

    const response = await fetch(
        API_URL + "?" + params.toString(),
        {
            method: "GET",

            headers: {
                "Accept": "application/json"
            },

            cache: "no-store"
        }
    );

    return await parseApiResponse(response);
}


/* =========================================
   MESSAGE HELPER
========================================= */

function showMessage(element, message, type = "") {

    if (!element) return;

    element.textContent = message;
    element.className = "form-message " + type;

}


/* =========================================
   CREATE CLASSROOM
========================================= */

const createForm = document.getElementById("createClassroomForm");

if (createForm) {

    createForm.addEventListener("submit", async function(event) {

        event.preventDefault();

        const title = document.getElementById("classroomTitle").value.trim();

        const description =
            document.getElementById("classroomDescription").value.trim();

        const message = document.getElementById("createMessage");

        const button = document.getElementById("createRoomBtn");

        if (!title) {

            showMessage(message, "Please enter a classroom title.", "error");
            return;

        }

        button.disabled = true;

        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';

        try {

            const result = await apiPost("create", {
                title,
                description
            });

            const roomId = Number(result.data?.classroom_id);

            if (!roomId) {

                throw new Error("The classroom ID was not returned.");

            }

            window.location.href = "classroom.php?room=" + roomId;

        } catch (error) {

            showMessage(message, error.message, "error");

            button.disabled = false;

            button.innerHTML =
                '<i class="fa-solid fa-video"></i> Create Live Classroom';

        }

    });

}


/* =========================================
   JOIN CLASSROOM
========================================= */

const joinForm = document.getElementById("joinClassroomForm");

if (joinForm) {

    joinForm.addEventListener("submit", async function(event) {

        event.preventDefault();

        const code = document.getElementById("joinCode").value.trim().toUpperCase();

        const message = document.getElementById("joinMessage");

        const button = joinForm.querySelector("button[type='submit']");

        if (!/^[A-Z0-9]{8}$/.test(code)) {

            showMessage(
                message,
                "Please enter a valid 8-character classroom code.",
                "error"
            );

            return;

        }

        button.disabled = true;

        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Joining...';

        try {

            const result = await apiPost("join", {
                code: code
            });

            const joinedRoomId = Number(result.data?.classroom_id);

            if (!joinedRoomId) {

                throw new Error("The classroom ID was not returned.");

            }

            window.location.href = "classroom.php?room=" + joinedRoomId;

        } catch (error) {

            showMessage(message, error.message, "error");

            button.disabled = false;

            button.innerHTML =
                '<i class="fa-solid fa-right-to-bracket"></i> Join Classroom';

        }

    });

}


/* =========================================
   WEBRTC CLASSROOM
========================================= */

if (ROOM_ID > 0) {

    let localStream = new MediaStream();

    let cameraTrack = null;
    let microphoneTrack = null;
    let screenTrack = null;

    let cameraEnabled = false;
    let micEnabled = false;
    let screenSharing = false;

    let lastMessageId = 0;
    let lastSignalId = 0;

    let participants = new Map();
    let peerConnections = new Map();
    let remoteStreams = new Map();

    let isLeaving = false;

    const videoGrid = document.getElementById("videoGrid");

    const localVideo = document.getElementById("localVideo");

    const localPlaceholder =
        document.getElementById("localVideoPlaceholder");

    const cameraBtn = document.getElementById("cameraBtn");
    const micBtn = document.getElementById("micBtn");
    const screenShareBtn = document.getElementById("screenShareBtn");

    const roomNotice = document.getElementById("roomNotice");


    /* =====================================
       ROOM NOTIFICATIONS
    ===================================== */

    function roomMessage(message, type = "") {

        showMessage(roomNotice, message, type);

    }


    /* =====================================
       MEDIA BUTTONS
    ===================================== */

    function updateMediaButtons() {

        if (cameraBtn) {

            cameraBtn.classList.toggle("active", cameraEnabled);

            cameraBtn.innerHTML = cameraEnabled
                ? '<i class="fa-solid fa-video"></i><span>Camera On</span>'
                : '<i class="fa-solid fa-video-slash"></i><span>Camera Off</span>';

        }

        if (micBtn) {

            micBtn.classList.toggle("active", micEnabled);

            micBtn.innerHTML = micEnabled
                ? '<i class="fa-solid fa-microphone"></i><span>Microphone On</span>'
                : '<i class="fa-solid fa-microphone-slash"></i><span>Microphone Off</span>';

        }

        if (localPlaceholder) {

            localPlaceholder.style.display =
                cameraEnabled && !screenSharing ? "none" : "flex";

        }

    }


    /* =====================================
       PEER CONNECTION
    ===================================== */

    function createPeerConnection(peerId) {

        peerId = Number(peerId);

        if (peerId === CURRENT_USER_ID) return null;

        if (peerConnections.has(peerId)) {

            return peerConnections.get(peerId);

        }

        const pc = new RTCPeerConnection(RTC_CONFIG);

        const stream = new MediaStream();

        remoteStreams.set(peerId, stream);

        const audioTransceiver = pc.addTransceiver("audio", {
            direction: "sendrecv"
        });

        const videoTransceiver = pc.addTransceiver("video", {
            direction: "sendrecv"
        });

        pc._audioTransceiver = audioTransceiver;
        pc._videoTransceiver = videoTransceiver;

        if (microphoneTrack) {

            audioTransceiver.sender.replaceTrack(microphoneTrack);

        }

        if (cameraTrack) {

            videoTransceiver.sender.replaceTrack(cameraTrack);

        }

        pc.onicecandidate = async function(event) {

            if (!event.candidate) return;

            try {

                await apiPost("signal_send", {
                    classroom_id: ROOM_ID,
                    receiver_id: peerId,
                    signal_type: "candidate",
                    signal_data: JSON.stringify(event.candidate)
                });

            } catch (error) {

                console.error("ICE candidate error:", error);

            }

        };

        pc.ontrack = function(event) {

            if (event.streams && event.streams[0]) {

                event.streams[0].getTracks().forEach(track => {

                    if (!stream.getTracks().some(t => t.id === track.id)) {

                        stream.addTrack(track);

                    }

                });

            } else {

                if (!stream.getTracks().some(t => t.id === event.track.id)) {

                    stream.addTrack(event.track);

                }

            }

            renderRemoteVideo(peerId);

        };

        pc.onconnectionstatechange = function() {

            if (pc.connectionState === "failed") {

                console.warn("Peer connection failed:", peerId);

            }

            if (
                pc.connectionState === "closed" ||
                pc.connectionState === "failed"
            ) {

                removeRemoteVideo(peerId);

            }

        };

        peerConnections.set(peerId, pc);

        return pc;

    }


    /* =====================================
       REMOTE VIDEO
    ===================================== */

    function renderRemoteVideo(peerId) {

        const participant = participants.get(Number(peerId));

        if (!participant || !videoGrid) return;

        const cardId = "remoteVideo_" + peerId;

        let card = document.getElementById(cardId);

        if (!card) {

            card = document.createElement("div");

            card.className = "video-card remote-video-card";
            card.id = cardId;

            const video = document.createElement("video");

            video.autoplay = true;
            video.playsInline = true;
            video.dataset.peerId = peerId;

            const label = document.createElement("div");

            label.className = "video-label";

            const icon = document.createElement("i");
            icon.className = "fa-solid fa-user";

            label.appendChild(icon);

            label.appendChild(
                document.createTextNode(" " + participant.username)
            );

            const placeholder = document.createElement("div");

            placeholder.className = "video-placeholder";

            const placeholderIcon = document.createElement("i");
            placeholderIcon.className = "fa-solid fa-user";

            const placeholderText = document.createElement("span");
            placeholderText.textContent = participant.username;

            placeholder.appendChild(placeholderIcon);
            placeholder.appendChild(placeholderText);

            card.appendChild(video);
            card.appendChild(label);
            card.appendChild(placeholder);

            videoGrid.appendChild(card);

        }

        const video = card.querySelector("video");

        const stream = remoteStreams.get(Number(peerId));

        if (video && stream) {

            video.srcObject = stream;

            video.play().catch(() => {});

        }

    }


    function removeRemoteVideo(peerId) {

        const card = document.getElementById("remoteVideo_" + peerId);

        if (card) {

            card.remove();

        }

    }


    /* =====================================
       SEND WEBRTC OFFER
    ===================================== */

    async function sendOffer(peerId) {

        const pc = createPeerConnection(peerId);

        if (!pc || pc.signalingState !== "stable") return;

        try {

            const offer = await pc.createOffer();

            await pc.setLocalDescription(offer);

            await apiPost("signal_send", {
                classroom_id: ROOM_ID,
                receiver_id: peerId,
                signal_type: "offer",
                signal_data: JSON.stringify(pc.localDescription)
            });

        } catch (error) {

            console.error("Offer error:", error);

        }

    }


    /* =====================================
       HANDLE SIGNALS
    ===================================== */

    async function handleSignal(signal) {

        const senderId = Number(signal.sender_id);

        if (!participants.has(senderId)) return;

        const pc = createPeerConnection(senderId);

        if (!pc) return;

        try {

            const data = signal.signal_data;

            if (signal.signal_type === "offer") {

                await pc.setRemoteDescription(
                    new RTCSessionDescription(data)
                );

                const answer = await pc.createAnswer();

                await pc.setLocalDescription(answer);

                await apiPost("signal_send", {
                    classroom_id: ROOM_ID,
                    receiver_id: senderId,
                    signal_type: "answer",
                    signal_data: JSON.stringify(pc.localDescription)
                });

            }

            else if (signal.signal_type === "answer") {

                if (pc.signalingState === "have-local-offer") {

                    await pc.setRemoteDescription(
                        new RTCSessionDescription(data)
                    );

                }

            }

            else if (signal.signal_type === "candidate") {

                if (pc.remoteDescription) {

                    await pc.addIceCandidate(
                        new RTCIceCandidate(data)
                    );

                } else {

                    if (!pc._pendingCandidates) {

                        pc._pendingCandidates = [];

                    }

                    pc._pendingCandidates.push(data);

                }

            }

            if (
                pc.remoteDescription &&
                pc._pendingCandidates &&
                pc._pendingCandidates.length
            ) {

                for (const candidate of pc._pendingCandidates) {

                    await pc.addIceCandidate(
                        new RTCIceCandidate(candidate)
                    );

                }

                pc._pendingCandidates = [];

            }

        } catch (error) {

            console.error("Signal handling error:", error);

        }

    }


    /* =====================================
       POLL SIGNALS
    ===================================== */

    async function pollSignals() {

        try {

            const result = await apiGet("signal_poll", {
                classroom_id: ROOM_ID,
                after_id: lastSignalId
            });

            const signals = result.data || [];

            for (const signal of signals) {

                lastSignalId = Math.max(
                    lastSignalId,
                    Number(signal.id)
                );

                await handleSignal(signal);

            }

        } catch (error) {

            console.error("Signal polling error:", error);

        }

    }


    /* =====================================
       LOAD PARTICIPANTS
    ===================================== */

    async function loadParticipants() {

        try {

            const result = await apiGet("participants", {
                classroom_id: ROOM_ID
            });

            const list = result.data || [];

            if (!Array.isArray(list)) return;

            const newParticipants = new Map();

            list.forEach(participant => {

                const id = Number(participant.id);

                newParticipants.set(id, {
                    id,
                    username: participant.username || "Participant",
                    role: participant.role || "student"
                });

            });

            participants = newParticipants;

            // Remove disconnected users.

            for (const [peerId, pc] of peerConnections.entries()) {

                if (!participants.has(peerId)) {

                    pc.close();

                    peerConnections.delete(peerId);
                    remoteStreams.delete(peerId);

                    removeRemoteVideo(peerId);

                }

            }

            // Build participant list.

            const participantList =
                document.getElementById("participantList");

            if (participantList) {

                participantList.innerHTML = "";

                list.forEach(participant => {

                    const id = Number(participant.id);

                    const name = participant.username || "Participant";

                    const item = document.createElement("div");

                    item.className = "participant-item";

                    const avatar = document.createElement("div");

                    avatar.className = "participant-avatar";

                    avatar.textContent = name.charAt(0).toUpperCase();

                    const details = document.createElement("div");

                    details.className = "participant-details";

                    const strong = document.createElement("strong");

                    strong.textContent = name;

                    const small = document.createElement("span");

                    small.textContent = id === CURRENT_USER_ID
                        ? "You"
                        : (participant.role || "Participant");

                    details.appendChild(strong);
                    details.appendChild(small);

                    item.appendChild(avatar);
                    item.appendChild(details);

                    participantList.appendChild(item);

                });

            }

            const count = list.length;

            const headerCount = document.getElementById("participantCount");

            const sideCount = document.getElementById("participantCountSide");

            if (headerCount) headerCount.textContent = count;

            if (sideCount) sideCount.textContent = count;

            // Create peer connections.
            // Only the lower user ID initiates an offer.

            for (const participant of list) {

                const peerId = Number(participant.id);

                if (peerId === CURRENT_USER_ID) continue;

                createPeerConnection(peerId);

                if (CURRENT_USER_ID < peerId) {

                    const pc = peerConnections.get(peerId);

                    if (
                        pc &&
                        pc.signalingState === "stable" &&
                        !pc._offerCreated
                    ) {

                        pc._offerCreated = true;

                        await sendOffer(peerId);

                    }

                }

            }

        } catch (error) {

            console.error("Participant loading error:", error);

        }

    }


    /* =====================================
       CAMERA
    ===================================== */

    async function toggleCamera() {

        try {

            if (!cameraTrack) {

                const stream = await navigator.mediaDevices.getUserMedia({
                    video: true
                });

                cameraTrack = stream.getVideoTracks()[0];

                cameraTrack.enabled = true;

                localStream.addTrack(cameraTrack);

                if (localVideo) {

                    localVideo.srcObject = localStream;

                }

                for (const pc of peerConnections.values()) {

                    await pc._videoTransceiver.sender.replaceTrack(
                        cameraTrack
                    );

                }

                cameraEnabled = true;

            } else {

                cameraEnabled = !cameraEnabled;

                cameraTrack.enabled = cameraEnabled;

            }

            updateMediaButtons();

        } catch (error) {

            roomMessage(
                "Unable to access your camera. Check your browser permissions.",
                "error"
            );

            console.error(error);

        }

    }

    if (cameraBtn) {

        cameraBtn.addEventListener("click", toggleCamera);

    }


    /* =====================================
       MICROPHONE
    ===================================== */

    async function toggleMicrophone() {

        try {

            if (!microphoneTrack) {

                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: true
                });

                microphoneTrack = stream.getAudioTracks()[0];

                microphoneTrack.enabled = true;

                localStream.addTrack(microphoneTrack);

                for (const pc of peerConnections.values()) {

                    await pc._audioTransceiver.sender.replaceTrack(
                        microphoneTrack
                    );

                }

                micEnabled = true;

            } else {

                micEnabled = !micEnabled;

                microphoneTrack.enabled = micEnabled;

            }

            updateMediaButtons();

        } catch (error) {

            roomMessage(
                "Unable to access your microphone. Check your browser permissions.",
                "error"
            );

            console.error(error);

        }

    }

    if (micBtn) {

        micBtn.addEventListener("click", toggleMicrophone);

    }


    /* =====================================
       SCREEN SHARING
    ===================================== */

    async function toggleScreenShare() {

        try {

            if (!screenSharing) {

                const stream = await navigator.mediaDevices.getDisplayMedia({
                    video: true
                });

                screenTrack = stream.getVideoTracks()[0];

                if (!screenTrack) return;

                screenSharing = true;

                if (localVideo) {

                    localVideo.srcObject = stream;

                }

                for (const pc of peerConnections.values()) {

                    await pc._videoTransceiver.sender.replaceTrack(
                        screenTrack
                    );

                }

                screenTrack.addEventListener("ended", stopScreenShare);

                screenShareBtn.classList.add("active");

                screenShareBtn.innerHTML =
                    '<i class="fa-solid fa-stop"></i><span>Stop Sharing</span>';

                if (localPlaceholder) {

                    localPlaceholder.style.display = "none";

                }

            } else {

                await stopScreenShare();

            }

        } catch (error) {

            console.error(error);

            roomMessage(
                "Screen sharing was cancelled or is not available.",
                "error"
            );

        }

    }


    async function stopScreenShare() {

        if (screenTrack) {

            screenTrack.stop();

            screenTrack = null;

        }

        screenSharing = false;

        const replacementTrack =
            cameraEnabled ? cameraTrack : null;

        if (localVideo) {

            localVideo.srcObject = localStream;

        }

        for (const pc of peerConnections.values()) {

            await pc._videoTransceiver.sender.replaceTrack(
                replacementTrack
            );

        }

        screenShareBtn.classList.remove("active");

        screenShareBtn.innerHTML =
            '<i class="fa-solid fa-desktop"></i><span>Share Screen</span>';

        updateMediaButtons();

    }

    if (screenShareBtn) {

        screenShareBtn.addEventListener("click", toggleScreenShare);

    }


    /* =====================================
       CHAT
    ===================================== */

    const chatForm = document.getElementById("chatForm");

    const chatInput = document.getElementById("chatInput");

    const chatMessages = document.getElementById("chatMessages");


    async function loadMessages() {

        try {

            const result = await apiGet("get_messages", {
                classroom_id: ROOM_ID,
                after_id: lastMessageId
            });

            const messages = result.data || [];

            if (!Array.isArray(messages)) return;

            if (!chatMessages) return;

            if (messages.length === 0) return;

            const empty = chatMessages.querySelector(".chat-empty");

            if (empty) {

                empty.remove();

            }

            messages.forEach(message => {

                const id = Number(message.id);

                if (id <= lastMessageId) return;

                lastMessageId = Math.max(lastMessageId, id);

                const item = document.createElement("div");

                item.className = "chat-message";

                const sender = document.createElement("strong");

                sender.className = "chat-sender";

                sender.textContent = message.username || "User";

                const content = document.createElement("p");

                content.textContent = message.message || "";

                const time = document.createElement("span");

                time.className = "chat-time";

                time.textContent = message.created_at || "";

                item.appendChild(sender);
                item.appendChild(content);
                item.appendChild(time);

                chatMessages.appendChild(item);

            });

            chatMessages.scrollTop = chatMessages.scrollHeight;

        } catch (error) {

            console.error("Chat loading error:", error);

        }

    }


    if (chatForm) {

        chatForm.addEventListener("submit", async function(event) {

            event.preventDefault();

            const message = chatInput.value.trim();

            if (!message) return;

            chatInput.disabled = true;

            try {

                await apiPost("send_message", {
                    classroom_id: ROOM_ID,
                    message
                });

                chatInput.value = "";

                await loadMessages();

            } catch (error) {

                roomMessage(error.message, "error");

            }

            chatInput.disabled = false;

            chatInput.focus();

        });

    }


    /* =====================================
       ROOM TABS
    ===================================== */

    document.querySelectorAll(".tab-btn").forEach(button => {

        button.addEventListener("click", function() {

            const tabName = this.dataset.tab;

            document.querySelectorAll(".tab-btn").forEach(tab => {

                tab.classList.remove("active");

            });

            document.querySelectorAll(".tab-content").forEach(tab => {

                tab.classList.remove("active");

            });

            this.classList.add("active");

            const target = document.getElementById(tabName + "Tab");

            if (target) {

                target.classList.add("active");

            }

        });

    });


    /* =====================================
       LEAVE CLASSROOM
    ===================================== */

    async function stopAllMedia() {

        if (screenTrack) {

            screenTrack.stop();

        }

        if (localStream) {

            localStream.getTracks().forEach(track => {

                track.stop();

            });

        }

        for (const pc of peerConnections.values()) {

            pc.close();

        }

        peerConnections.clear();

        remoteStreams.clear();

    }


    const leaveBtn = document.getElementById("leaveRoomBtn");

    if (leaveBtn) {

        leaveBtn.addEventListener("click", async function() {

            if (!confirm("Are you sure you want to leave this classroom?")) {

                return;

            }

            if (isLeaving) return;

            isLeaving = true;

            leaveBtn.disabled = true;

            try {

                if (!IS_ROOM_OWNER) {

                    await apiPost("leave", {
                        classroom_id: ROOM_ID
                    });

                }

            } catch (error) {

                console.error(error);

            }

            await stopAllMedia();

            window.location.href = "classroom.php";

        });

    }


    /* =====================================
       END CLASSROOM
    ===================================== */

    const endBtn = document.getElementById("endRoomBtn");

    if (endBtn) {

        endBtn.addEventListener("click", async function() {

            if (!confirm("End this classroom for all participants?")) {

                return;

            }

            endBtn.disabled = true;

            try {

                await apiPost("end", {
                    classroom_id: ROOM_ID
                });

                await stopAllMedia();

                window.location.href = "classroom.php";

            } catch (error) {

                roomMessage(error.message, "error");

                endBtn.disabled = false;

            }

        });

    }


    /* =====================================
       CLASSROOM INITIALIZATION
    ===================================== */

    async function initializeClassroom() {

        if (!navigator.mediaDevices) {

            roomMessage(
                "Your browser does not support camera and microphone access.",
                "error"
            );

        }

        await loadParticipants();

        await loadMessages();

        await pollSignals();

        setInterval(loadParticipants, 5000);

        setInterval(loadMessages, 2000);

        setInterval(pollSignals, 1000);

        updateMediaButtons();

    }

    initializeClassroom();

}

</script>

</body>
</html>
