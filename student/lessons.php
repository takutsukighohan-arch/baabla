<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../textbook_content_helpers.php';

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    strtolower(trim((string)$_SESSION['role'])) !== 'student'
) {
    header('Location: ../login.php');
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$lessons = [];
$loadError = false;

function studentLessonEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function studentLessonImagePath($path): ?string
{
    $path = trim((string)$path);
    if (
        $path === '' ||
        strpos($path, '..') !== false ||
        strpos($path, '\\') !== false ||
        preg_match('/\A(?:images|uploads\/lessons)\/[A-Za-z0-9._\/-]+\.(?:png|jpe?g|gif|webp)\z/i', $path) !== 1
    ) {
        return null;
    }

    return $path;
}

try {
    $lessonQuery = $conn->prepare("
        SELECT lesson.id, lesson.title, lesson.description, lesson.category,
               lesson.difficulty, lesson.content, lesson.image, lesson.sections,
               lesson.created_at, teacher.username AS teacher_name
        FROM public.lessons AS lesson
        INNER JOIN public.users AS teacher
            ON teacher.id = lesson.created_by
        WHERE lesson.status = 'published'
          AND EXISTS (
              SELECT 1
              FROM public.section_students AS section_student
              INNER JOIN public.sections AS section
                  ON section.id = section_student.section_id
              WHERE section_student.student_id = :student_id
                AND section.teacher_id = lesson.created_by
                AND section.is_active IS TRUE
          )
        ORDER BY lesson.created_at DESC, lesson.id DESC
    ");
    $lessonQuery->execute(['student_id' => $studentId]);
    $lessons = $lessonQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Unable to load student lessons: ' . $exception->getMessage());
    $loadError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons | ASLify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        .lesson-library { flex: 1; min-width: 0; max-width: 1050px; margin: 0 auto; padding: 28px; }
        .lesson-library > h1 { margin-bottom: 8px; color: #1e293b; }
        .lesson-intro { margin-bottom: 22px; color: #64748b; }
        .lesson-library-list { display: grid; gap: 18px; }
        .lesson-card { padding: 22px; border: 1px solid #e8eaf1; border-radius: 16px; background: #fff; }
        .lesson-card h2 { margin: 0 0 6px; color: #1e293b; }
        .lesson-meta { margin-bottom: 14px; color: #64748b; font-size: 13px; }
        .lesson-section { padding-top: 16px; margin-top: 16px; border-top: 1px solid #e8eaf1; }
        .lesson-section h3 { margin-bottom: 8px; color: #334155; }
        .lesson-section-content { white-space: pre-wrap; overflow-wrap: anywhere; color: #475569; line-height: 1.7; }
        .lesson-section img { display: block; max-width: 100%; max-height: 480px; margin-top: 12px; border-radius: 10px; object-fit: contain; }
        .lesson-empty, .lesson-error { padding: 20px; border-radius: 12px; background: #fff; color: #64748b; }
        .lesson-error { background: #fff0f0; color: #b42318; }
        @media (max-width: 650px) { .lesson-library { padding: 18px 12px; } .lesson-card { padding: 16px; } }
    </style>
</head>
<body>
<aside class="sidebar">
    <div>
        <div class="brand-logo">
            <img src="../images/logo_colored_sign_black_text.svg" alt="ASLify Logo">
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-house"></i> Home</a></li>
            <li class="nav-item"><a href="textbook.php"><i class="fa-solid fa-border-all"></i> Textbooks</a></li>
            <li class="nav-item active"><a href="lessons.php"><i class="fa-solid fa-book-open"></i> Lessons</a></li>
            <li class="nav-item"><a href="classroom.php"><i class="fa-solid fa-border-all"></i> Classroom</a></li>
            <li class="nav-item"><a href="progress.php"><i class="fa-regular fa-heart"></i> My Progress</a></li>
            <li class="nav-item"><a href="assignment.php"><i class="fa-regular fa-file-lines"></i> Assignment</a></li>
            <li class="nav-item"><a href="practice.php"><i class="fa-solid fa-bars-progress"></i> Practice</a></li>
            <li class="nav-item"><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
            <li class="nav-item"><a href="message.php"><i class="fa-regular fa-comment-dots"></i> Messages</a></li>
            <li class="nav-item"><a href="../logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</a></li>
        </ul>
    </div>
</aside>
<main class="lesson-library">
    <h1>Lessons from Your Teachers</h1>
    <p class="lesson-intro">Study the materials your teacher has sent to your active assigned section.</p>

    <?php if ($loadError): ?>
        <div class="lesson-error" role="alert">Lessons are unavailable. Apply the lesson sections migration in Supabase, then try again.</div>
    <?php elseif (!$lessons): ?>
        <div class="lesson-empty">Your teachers haven’t sent any lessons yet.</div>
    <?php else: ?>
        <div class="lesson-library-list">
            <?php foreach ($lessons as $lesson): ?>
                <?php
                $sections = textbookJsonArray($lesson['sections'] ?? []);
                if (!$sections && (!empty($lesson['content']) || !empty($lesson['image']))) {
                    $sections = [[
                        'title' => 'Lesson',
                        'content' => $lesson['content'] ?? '',
                        'image' => $lesson['image'] ?? ''
                    ]];
                }
                ?>
                <article class="lesson-card">
                    <h2><?= studentLessonEscape($lesson['title']) ?></h2>
                    <p class="lesson-meta">
                        <?= studentLessonEscape($lesson['teacher_name']) ?>
                        <?php if (!empty($lesson['category'])): ?>
                            &middot; <?= studentLessonEscape($lesson['category']) ?>
                        <?php endif; ?>
                        <?php if (!empty($lesson['difficulty'])): ?>
                            &middot; <?= studentLessonEscape($lesson['difficulty']) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($lesson['description'])): ?>
                        <p><?= nl2br(studentLessonEscape($lesson['description'])) ?></p>
                    <?php endif; ?>
                    <?php foreach ($sections as $index => $section): ?>
                        <?php if (!is_array($section)) { continue; } ?>
                        <section class="lesson-section">
                            <h3><?= studentLessonEscape($section['title'] ?? ('Section ' . ($index + 1))) ?></h3>
                            <?php if (!empty($section['content'])): ?>
                                <div class="lesson-section-content"><?= textbookSafeHtml((string)$section['content']) ?></div>
                            <?php endif; ?>
                            <?php $imagePath = studentLessonImagePath($section['image'] ?? ''); ?>
                            <?php if ($imagePath !== null): ?>
                                <img src="../<?= studentLessonEscape($imagePath) ?>" alt="">
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
