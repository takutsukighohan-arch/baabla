<?php
session_start();
include '../config/database.php';

// STUDENT ACCESS ONLY
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION['role'] !== 'student') {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: ../teacher/dashboard.php");
    } else {
        header("Location: ../login.php");
    }
    exit;
}

$studentId = (int) $_SESSION['user_id'];
$studentName = $_SESSION['username'] ?? 'Student';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getQuestionSql($filter, $ids = [], $studentId = 0) {
    $studentId = (int)$studentId;
    $sql = "
        SELECT
            pq.id,
            pq.lesson_id,
            pq.question,
            pq.option_a,
            pq.option_b,
            pq.option_c,
            pq.option_d,
            pq.correct_answer,
            l.title AS lesson_title
        FROM practice_questions pq
        LEFT JOIN lessons l ON pq.lesson_id = l.id
        WHERE
    ";

    if ($filter === 'general') {
        $sql .= "pq.lesson_id IS NULL";
    } elseif (ctype_digit((string)$filter) && (int)$filter > 0) {
        $sql .= "pq.lesson_id = ?
            AND l.status = 'published'
            AND EXISTS (
                SELECT 1
                FROM public.section_students AS section_student
                INNER JOIN public.sections AS section
                    ON section.id = section_student.section_id
                WHERE section_student.student_id = {$studentId}
                  AND section.teacher_id = l.created_by
                  AND section.is_active IS TRUE
            )";
    } else {
        $sql .= "(pq.lesson_id IS NULL OR (
            l.status = 'published'
            AND EXISTS (
                SELECT 1
                FROM public.section_students AS section_student
                INNER JOIN public.sections AS section
                    ON section.id = section_student.section_id
                WHERE section_student.student_id = {$studentId}
                  AND section.teacher_id = l.created_by
                  AND section.is_active IS TRUE
            )
        ))";
    }

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql .= " AND pq.id IN ($placeholders)";
    }

    $sql .= " ORDER BY pq.id ASC";

    return $sql;
}

try {
    // GET PUBLISHED LESSONS
    $lessonStmt = $conn->prepare("
        SELECT id, title
        FROM lessons
        WHERE status = 'published'
          AND EXISTS (
              SELECT 1
              FROM public.section_students AS section_student
              INNER JOIN public.sections AS section
                  ON section.id = section_student.section_id
              WHERE section_student.student_id = " . $studentId . "
                AND section.teacher_id = lessons.created_by
                AND section.is_active IS TRUE
          )
        ORDER BY title ASC
    ");
    $lessonStmt->execute();
    $lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    die("Database error: " . e($ex->getMessage()));
}

$selectedFilter = $_POST['lesson_filter'] ?? $_GET['lesson'] ?? 'all';

if (
    $selectedFilter !== 'all' &&
    $selectedFilter !== 'general' &&
    (!ctype_digit((string)$selectedFilter) || (int)$selectedFilter < 1)
) {
    $selectedFilter = 'all';
}

$questions = [];
$score = null;
$totalQuestions = 0;
$percentage = 0;
$submitted = false;
$errorMessage = '';
$successMessage = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF VALIDATION
    if (
        !isset($_POST['csrf_token'], $_SESSION['practice_csrf']) ||
        !hash_equals($_SESSION['practice_csrf'], $_POST['csrf_token'])
    ) {
        $errorMessage = "Invalid request. Please refresh the page and try again.";
    } else {

        $submittedIds = $_POST['question_ids'] ?? [];
        $answers = $_POST['answers'] ?? [];

        if (!is_array($submittedIds) || !is_array($answers)) {
            $errorMessage = "Invalid quiz submission.";
        } else {

            $submittedIds = array_values(array_unique(array_filter(
                array_map('intval', $submittedIds),
                function ($id) {
                    return $id > 0;
                }
            )));

            if (empty($submittedIds)) {
                $errorMessage = "No questions were submitted.";
            } else {

                try {
                    $sql = getQuestionSql($selectedFilter, $submittedIds, $studentId);
                    $stmt = $conn->prepare($sql);

                    if (
                        $selectedFilter !== 'all' &&
                        $selectedFilter !== 'general'
                    ) {
                        $stmt->bindValue(
                            1,
                            (int)$selectedFilter,
                            PDO::PARAM_INT
                        );

                        foreach ($submittedIds as $index => $id) {
                            $stmt->bindValue(
                                $index + 2,
                                $id,
                                PDO::PARAM_INT
                            );
                        }
                    } else {
                        foreach ($submittedIds as $index => $id) {
                            $stmt->bindValue(
                                $index + 1,
                                $id,
                                PDO::PARAM_INT
                            );
                        }
                    }

                    $stmt->execute();
                    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($questions) !== count($submittedIds)) {
                        $questions = [];
                        $errorMessage = "Some questions are no longer available. Please reload the practice page.";
                    } else {

                        $score = 0;
                        $totalQuestions = count($questions);

                        foreach ($questions as &$question) {
                            $questionId = (int)$question['id'];

                            $studentAnswer = strtoupper(
                                trim((string)($answers[$questionId] ?? ''))
                            );

                            if (!in_array($studentAnswer, ['A', 'B', 'C', 'D'], true)) {
                                $studentAnswer = '';
                            }

                            $question['student_answer'] = $studentAnswer;
                            $question['is_correct'] =
                                ($studentAnswer !== '' &&
                                $studentAnswer === $question['correct_answer']);

                            if ($question['is_correct']) {
                                $score++;
                            }
                        }
                        unset($question);

                        $percentage = $totalQuestions > 0
                            ? round(($score / $totalQuestions) * 100, 2)
                            : 0;

                        // SAVE PRACTICE ATTEMPT
                        $saveStmt = $conn->prepare("
                            INSERT INTO practice_attempts
                            (student_id, score, total_questions, percentage, attempted_at)
                            VALUES
                            (:student_id, :score, :total_questions, :percentage, NOW())
                        ");

                        $saveStmt->execute([
                            ':student_id' => $studentId,
                            ':score' => $score,
                            ':total_questions' => $totalQuestions,
                            ':percentage' => $percentage
                        ]);

                        $submitted = true;
                        $successMessage = "Your practice result has been saved successfully.";
                    }

                } catch (PDOException $ex) {
                    $errorMessage = "Unable to process your practice. Please check your database tables.";
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD QUESTIONS FOR A NEW PRACTICE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    try {
        $sql = getQuestionSql($selectedFilter, [], $studentId);
        $stmt = $conn->prepare($sql);

        if (
            $selectedFilter !== 'all' &&
            $selectedFilter !== 'general'
        ) {
            $stmt->bindValue(1, (int)$selectedFilter, PDO::PARAM_INT);
        }

        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $ex) {
        $errorMessage = "Unable to load practice questions.";
    }
}

// GENERATE CSRF TOKEN
if (empty($_SESSION['practice_csrf'])) {
    $_SESSION['practice_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['practice_csrf'];

$filterLabel = 'All Practice Questions';

if ($selectedFilter === 'general') {
    $filterLabel = 'General Questions';
} elseif ($selectedFilter !== 'all') {
    foreach ($lessons as $lesson) {
        if ((int)$lesson['id'] === (int)$selectedFilter) {
            $filterLabel = $lesson['title'];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practice | ASLify</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
          rel="stylesheet">
    <link rel="stylesheet" href="main.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px 35px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            gap: 15px;
        }

        .topbar h1 {
            font-size: 24px;
            font-weight: 700;
        }

        .topbar p {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 4px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            padding: 9px 15px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .profile i {
            color: #2563eb;
            font-size: 20px;
        }

        .welcome-card {
            background: linear-gradient(120deg, #2563eb, #6658d9);
            padding: 28px;
            border-radius: 16px;
            color: #fff;
            margin-bottom: 25px;
        }

        .welcome-card h2 {
            font-size: 21px;
            margin-bottom: 8px;
        }

        .welcome-card p {
            font-size: 13px;
            opacity: .9;
            max-width: 650px;
            line-height: 1.7;
        }

        .content-card {
            background: #fff;
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,.025);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .section-heading h2 {
            font-size: 17px;
        }

        .section-heading span {
            color: #94a3b8;
            font-size: 12px;
        }

        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        select {
            padding: 11px 14px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            outline: none;
            min-width: 230px;
            color: #334155;
            background: #fff;
        }

        button, .btn {
            border: none;
            background: #2563eb;
            color: #fff;
            padding: 11px 19px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        button:hover, .btn:hover {
            background: #1d4ed8;
        }

        .question-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .question-number {
            color: #2563eb;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .question-text {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .lesson-tag {
            display: inline-block;
            font-size: 10px;
            background: #eff6ff;
            color: #2563eb;
            padding: 5px 9px;
            border-radius: 20px;
            margin-bottom: 12px;
        }

        .option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 9px;
            cursor: pointer;
            font-size: 12px;
            transition: .2s;
        }

        .option:hover {
            background: #eff6ff;
            border-color: #93c5fd;
        }

        .option input {
            margin-top: 3px;
            accent-color: #2563eb;
        }

        .option.correct {
            border-color: #16a34a;
            background: #f0fdf4;
        }

        .option.wrong {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .answer-feedback {
            margin-top: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .correct-text {
            color: #16a34a;
        }

        .wrong-text {
            color: #dc2626;
        }

        .submit-area {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .submit-btn {
            padding: 13px 25px;
            font-size: 13px;
        }

        .result-card {
            text-align: center;
            padding: 30px;
            border-radius: 13px;
            background: #eff6ff;
            margin-bottom: 22px;
        }

        .result-icon {
            font-size: 38px;
            color: #2563eb;
            margin-bottom: 10px;
        }

        .result-card h2 {
            font-size: 22px;
            margin-bottom: 7px;
        }

        .result-score {
            font-size: 35px;
            color: #2563eb;
            font-weight: 700;
            margin: 10px 0;
        }

        .result-card p {
            font-size: 12px;
            color: #64748b;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 12px;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
        }

        .alert-success {
            background: #f0fdf4;
            color: #15803d;
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 40px;
            margin-bottom: 14px;
            color: #cbd5e1;
        }

        .empty-state h3 {
            color: #475569;
            font-size: 16px;
            margin-bottom: 7px;
        }

        .empty-state p {
            font-size: 12px;
        }

        .practice-note {
            color: #94a3b8;
            font-size: 11px;
            margin-top: 12px;
        }

        @media(max-width: 900px) {
            .sidebar {
                width: 210px;
            }

            .main-content {
                margin-left: 210px;
                padding: 22px;
            }
        }

        @media(max-width: 650px) {
            .sidebar {
                width: 65px;
                padding: 20px 8px;
            }

            .brand, .brand-subtitle, .nav-title, .nav-item a span {
                display: none;
            }

            .nav-item a {
                justify-content: center;
                padding: 13px 5px;
            }

            .nav-item i {
                font-size: 17px;
            }

            .main-content {
                margin-left: 65px;
                padding: 18px 12px;
            }

            .topbar h1 {
                font-size: 19px;
            }

            .profile {
                font-size: 11px;
                padding: 8px;
            }

            .content-card {
                padding: 16px;
            }

            select {
                width: 100%;
            }
        }
    </style>
</head>

<body>

 <!-- LEFT SIDEBAR -->
    <aside class="sidebar">
        <div>
            <div class="brand-logo">
                <img src="../images/logo_colored_sign_black_text.svg" alt="ASLify Logo">
               
            </div>

            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a href="textbook.php"><i class="fa-solid fa-border-all"></i> Textbooks</a>
                </li>
                <li class="nav-item">
                    <a href="lessons.php"><i class="fa-solid fa-book-open"></i> Lessons</a>
                </li>
                <li class="nav-item">
                    <a href="progress.php"><i class="fa-regular fa-heart"></i> My Progress</a>
                </li>
                <li class="nav-item">
                    <a href="assignment.php"><i class="fa-regular fa-file-lines"></i> Assignment</a>
                </li>
                <li class="nav-item">
                    <a href="practice.php"><i class="fa-solid fa-bars-progress"></i> Practice</a>
                </li>
                <li class="nav-item">
                    <a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a>
                </li>
                <li class="nav-item">
                    <a href="message.php"><i class="fa-regular fa-comment-dots"></i> Messages</a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</a>
                </li>
            </ul>
        </div>

        <div class="sidebar-illustration">
            <img src="../images/standing-character.svg" alt="Student Illustration">
        </div>
    </aside>


<!-- MAIN CONTENT -->
<main class="main-content">

    <div class="topbar">
        <div>
            <h1>Practice</h1>
            <p>Test your sign language knowledge and improve your skills.</p>
        </div>

        <div class="profile">
            <i class="fa-solid fa-circle-user"></i>
            <?php echo e($studentName); ?>
        </div>
    </div>

    <div class="welcome-card">
        <h2>Ready to Practice? <i class="fa-solid fa-hand"></i></h2>
        <p>
            Answer questions prepared by your teachers. Choose a lesson
            or try general questions to test what you have learned.
        </p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php echo e($errorMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <?php echo e($successMessage); ?>
        </div>
    <?php endif; ?>

    <!-- LESSON FILTER -->
    <section class="content-card">

        <div class="section-heading">
            <h2><i class="fa-solid fa-filter"></i> Choose Practice Category</h2>
            <span>Filter questions by lesson</span>
        </div>

        <form method="GET" action="practice.php" class="filter-form">

            <select name="lesson" required>
                <option value="all" <?php echo $selectedFilter === 'all' ? 'selected' : ''; ?>>
                    All Practice Questions
                </option>

                <option value="general" <?php echo $selectedFilter === 'general' ? 'selected' : ''; ?>>
                    General Questions
                </option>

                <?php foreach ($lessons as $lesson): ?>
                    <option
                        value="<?php echo (int)$lesson['id']; ?>"
                        <?php echo (string)$selectedFilter === (string)$lesson['id'] ? 'selected' : ''; ?>>
                        <?php echo e($lesson['title']); ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <button type="submit">
                <i class="fa-solid fa-magnifying-glass"></i>
                Load Questions
            </button>

        </form>

        <p class="practice-note">
            Only questions from lessons sent by your assigned teachers and general questions are available.
        </p>

    </section>

    <?php if ($submitted): ?>

        <!-- RESULT -->
        <div class="result-card">
            <div class="result-icon">
                <i class="fa-solid fa-trophy"></i>
            </div>

            <h2>Practice Completed!</h2>

            <div class="result-score">
                <?php echo $score; ?> / <?php echo $totalQuestions; ?>
            </div>

            <p>
                Your Score: <strong><?php echo e($percentage); ?>%</strong>
            </p>

            <p style="margin-top:8px;">
                <?php
                if ($percentage >= 80) {
                    echo "Excellent work! Keep it up!";
                } elseif ($percentage >= 50) {
                    echo "Good effort! Keep practicing to improve.";
                } else {
                    echo "Keep learning! Review your lessons and try again.";
                }
                ?>
            </p>

            <div style="margin-top:20px;">
                <a class="btn"
                   href="practice.php?lesson=<?php echo urlencode($selectedFilter); ?>">
                    <i class="fa-solid fa-rotate-right"></i>
                    Try Again
                </a>
            </div>
        </div>

    <?php endif; ?>

    <!-- QUESTIONS -->
    <section class="content-card">

        <div class="section-heading">
            <h2>
                <i class="fa-solid fa-list-check"></i>
                <?php echo e($filterLabel); ?>
            </h2>

            <span>
                <?php echo count($questions); ?> question(s)
            </span>
        </div>

        <?php if (empty($questions)): ?>

            <div class="empty-state">
                <i class="fa-solid fa-clipboard-question"></i>
                <h3>No Practice Questions Available</h3>
                <p>
                    Your teacher has not added questions for this category yet.
                    Please check again later.
                </p>
            </div>

        <?php else: ?>

            <?php if (!$submitted): ?>

                <form method="POST" action="practice.php">

                    <input type="hidden" name="csrf_token"
                           value="<?php echo e($csrfToken); ?>">

                    <input type="hidden" name="lesson_filter"
                           value="<?php echo e($selectedFilter); ?>">

                    <?php foreach ($questions as $index => $question): ?>

                        <input type="hidden"
                               name="question_ids[]"
                               value="<?php echo (int)$question['id']; ?>">

                        <div class="question-card">

                            <div class="question-number">
                                Question <?php echo $index + 1; ?>
                            </div>

                            <?php if (!empty($question['lesson_title'])): ?>
                                <div class="lesson-tag">
                                    <?php echo e($question['lesson_title']); ?>
                                </div>
                            <?php else: ?>
                                <div class="lesson-tag">General Question</div>
                            <?php endif; ?>

                            <div class="question-text">
                                <?php echo e($question['question']); ?>
                            </div>

                            <?php
                            $options = [
                                'A' => $question['option_a'],
                                'B' => $question['option_b'],
                                'C' => $question['option_c'],
                                'D' => $question['option_d']
                            ];
                            ?>

                            <?php foreach ($options as $letter => $option): ?>

                                <label class="option">

                                    <input
                                        type="radio"
                                        name="answers[<?php echo (int)$question['id']; ?>]"
                                        value="<?php echo $letter; ?>"
                                        required
                                    >

                                    <span>
                                        <strong><?php echo $letter; ?>.</strong>
                                        <?php echo e($option); ?>
                                    </span>

                                </label>

                            <?php endforeach; ?>

                        </div>

                    <?php endforeach; ?>

                    <div class="submit-area">
                        <button type="submit" class="submit-btn">
                            <i class="fa-solid fa-paper-plane"></i>
                            Submit Practice
                        </button>
                    </div>

                </form>

            <?php else: ?>

                <!-- ANSWER REVIEW AFTER SUBMISSION -->
                <?php foreach ($questions as $index => $question): ?>

                    <div class="question-card">

                        <div class="question-number">
                            Question <?php echo $index + 1; ?>
                        </div>

                        <div class="question-text">
                            <?php echo e($question['question']); ?>
                        </div>

                        <?php
                        $options = [
                            'A' => $question['option_a'],
                            'B' => $question['option_b'],
                            'C' => $question['option_c'],
                            'D' => $question['option_d']
                        ];

                        foreach ($options as $letter => $option):
                            $class = '';

                            if ($letter === $question['correct_answer']) {
                                $class = 'correct';
                            } elseif (
                                $letter === $question['student_answer'] &&
                                !$question['is_correct']
                            ) {
                                $class = 'wrong';
                            }
                        ?>

                            <div class="option <?php echo $class; ?>">
                                <span>
                                    <strong><?php echo $letter; ?>.</strong>
                                    <?php echo e($option); ?>

                                    <?php if ($letter === $question['correct_answer']): ?>
                                        <i class="fa-solid fa-check correct-text"></i>
                                    <?php elseif (
                                        $letter === $question['student_answer'] &&
                                        !$question['is_correct']
                                    ): ?>
                                        <i class="fa-solid fa-xmark wrong-text"></i>
                                    <?php endif; ?>
                                </span>
                            </div>

                        <?php endforeach; ?>

                        <div class="answer-feedback <?php echo $question['is_correct'] ? 'correct-text' : 'wrong-text'; ?>">
                            <?php if ($question['is_correct']): ?>
                                <i class="fa-solid fa-circle-check"></i> Correct Answer
                            <?php else: ?>
                                <i class="fa-solid fa-circle-xmark"></i>
                                Your answer:
                                <?php echo e($question['student_answer'] ?: 'No answer'); ?>.
                                Correct answer:
                                <?php echo e($question['correct_answer']); ?>.
                            <?php endif; ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
