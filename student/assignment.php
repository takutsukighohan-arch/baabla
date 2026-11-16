<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    strtolower(trim((string)$_SESSION['role'])) !== 'student'
) {
    header('Location: ../login.php');
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$defaultProfileImage = "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['fullname'] ?? "Student") . "&background=2563eb&color=fff&size=200";
$profileStmt = $conn->prepare("SELECT profile_image FROM users WHERE id = :id AND role = 'student' LIMIT 1");
$profileStmt->execute(['id' => $studentId]);
$profileUser = $profileStmt->fetch(PDO::FETCH_ASSOC);
$studentProfileImage = !empty(trim((string)($profileUser['profile_image'] ?? '')))
    ? $profileUser['profile_image']
    : $defaultProfileImage;

if (empty($_SESSION['student_assignments_csrf'])) {
    $_SESSION['student_assignments_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['student_assignments_csrf'];
$successMessage = '';
$errorMessage = '';

function assignmentPageEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errorMessage = 'Your session expired. Refresh the page and try again.';
    } else {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $submission = trim((string)($_POST['submission_text'] ?? ''));

        if ($assignmentId <= 0 || $submission === '' || mb_strlen($submission) > 10000) {
            $errorMessage = 'Enter a response of 1 to 10,000 characters.';
        } else {
            try {
                $submit = $conn->prepare("
                    UPDATE public.teacher_assignment_students AS recipient
                    SET submission_text = :submission_text,
                        submitted_at = NOW()
                    WHERE recipient.assignment_id = :assignment_id
                      AND recipient.student_id = :student_id
                      AND recipient.reviewed_at IS NULL
                      AND EXISTS (
                          SELECT 1
                          FROM public.teacher_assignments AS assignment
                          WHERE assignment.id = recipient.assignment_id
                      )
                ");
                $submit->execute([
                    'submission_text' => $submission,
                    'assignment_id' => $assignmentId,
                    'student_id' => $studentId,
                ]);

                if ($submit->rowCount() !== 1) {
                    $errorMessage = 'Assignment not found or your response has already been reviewed.';
                } else {
                    $successMessage = 'Your response was submitted.';
                }
            } catch (PDOException $exception) {
                error_log('Student assignment submission failed: ' . $exception->getMessage());
                $errorMessage = 'Your response could not be submitted. Confirm the assignment migration has been applied.';
            }
        }
    }
}

$assignments = [];
try {
    $assignmentsQuery = $conn->prepare("
        SELECT
            assignment.id,
            assignment.title,
            assignment.instructions,
            assignment.due_date,
            assignment.created_at,
            teacher.username AS teacher_name,
            recipient.submission_text,
            recipient.submitted_at,
            recipient.feedback,
            recipient.reviewed_at
        FROM public.teacher_assignment_students AS recipient
        INNER JOIN public.teacher_assignments AS assignment
            ON assignment.id = recipient.assignment_id
        INNER JOIN public.users AS teacher
            ON teacher.id = assignment.teacher_id
        WHERE recipient.student_id = :student_id
        ORDER BY recipient.submitted_at NULLS FIRST,
                 assignment.due_date ASC NULLS LAST,
                 assignment.created_at DESC
    ");
    $assignmentsQuery->execute(['student_id' => $studentId]);
    $assignments = $assignmentsQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Unable to load student assignments: ' . $exception->getMessage());
    if ($errorMessage === '') {
        $errorMessage = 'Assignments are unavailable. Apply the teacher assignments migration in Supabase.';
    }
}

foreach ($assignments as &$assignment) {
    $hasSubmission = $assignment['submitted_at'] !== null;
    if ($assignment['reviewed_at'] !== null) {
        $assignment['status'] = 'reviewed';
    } elseif ($hasSubmission) {
        $assignment['status'] = 'submitted';
    } elseif (
        $assignment['due_date'] !== null &&
        $assignment['due_date'] < date('Y-m-d')
    ) {
        $assignment['status'] = 'overdue';
    } else {
        $assignment['status'] = 'pending';
    }
}
unset($assignment);

$assignmentCounts = array_count_values(array_column($assignments, 'status'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASLify - Assignments</title>
    <!-- POPPINS FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FONT AWESOME ICONS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        /* CENTER CONTENT AREA */
        .center-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* TOP BAR */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .search-box {
            flex: 1;
            background: #ffffff;
            display: flex;
            align-items: center;
            padding: 10px 18px;
            border-radius: 25px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .search-box input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.88rem;
            color: #64748b;
        }

        .search-box i {
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .lang-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .lang-selector img {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* PAGE TITLE */
        .page-header h2 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1e293b;
        }

        .page-header p {
            font-size: 0.78rem;
            color: #94a3b8;
        }

        /* ASSIGNMENTS FILTER PILLS */
        .filter-container {
            background: #ffffff;
            border-radius: 25px;
            padding: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .filter-pill {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
            background: transparent;
            transition: all 0.2s ease;
        }

        .filter-pill.active {
            background-color: #1e3a8a;
            color: #ffffff;
        }

        .pill-badge {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 0.7rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ASSIGNMENT CARDS LIST */
        .assignments-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .assignment-card {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 40%, #60a5fa 100%);
            border-radius: 24px;
            padding: 22px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.18);
            position: relative;
        }

        .assignment-main {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .assignment-icon-box {
            width: 55px;
            height: 55px;
            background-color: #475569;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .assignment-icon-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .assignment-details {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .assignment-details h4 {
            font-size: 1.05rem;
            font-weight: 700;
            color: white;
        }

        .due-date {
            font-size: 0.72rem;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        /* STATUS BADGES */
        .status-badge {
            padding: 6px 16px;
            border-radius: 14px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.7);
        }

        .status-badge.pending {
            color: #f97316;
            background-color: #ffedd5;
        }

        .status-badge.completed {
            color: #16a34a;
            background-color: #dcfce7;
        }

        .status-badge.not-started {
            color: #64748b;
            background-color: #f1f5f9;
        }

        .status-badge.submitted {
            color: #1d4ed8;
            background-color: #dbeafe;
        }

        .status-badge.reviewed {
            color: #15803d;
            background-color: #dcfce7;
        }

        .status-badge.overdue {
            color: #b91c1c;
            background-color: #fee2e2;
        }

        .assignment-instructions {
            max-width: 650px;
            color: #eff6ff;
            font-size: 0.8rem;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .assignment-meta {
            color: #e2e8f0;
            font-size: 0.75rem;
        }

        .submission-form {
            display: grid;
            gap: 8px;
            width: min(100%, 700px);
            margin-top: 12px;
        }

        .submission-form textarea {
            min-height: 80px;
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, .6);
            border-radius: 8px;
            resize: vertical;
            font: inherit;
        }

        .submission-form button {
            justify-self: start;
            padding: 8px 14px;
            border: 0;
            border-radius: 8px;
            background: #fff;
            color: #1d4ed8;
            font-weight: 700;
            cursor: pointer;
        }

        .submission-form button:disabled {
            opacity: .7;
            cursor: not-allowed;
        }

        .assignment-notice {
            padding: 12px 16px;
            border-radius: 10px;
            background: #fff;
            font-size: .9rem;
        }

        .assignment-notice.error {
            color: #991b1b;
        }

        .assignment-notice.success {
            color: #166534;
        }

        .assignment-empty {
            padding: 24px;
            border-radius: 14px;
            background: #fff;
            color: #64748b;
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
                <li class="nav-item">
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
                <li class="nav-item active">
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

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        
        <!-- CENTER CONTENT -->
        <main class="center-content">
            
            <!-- TOP BAR -->
            <div class="top-bar">
                <div class="search-box">
                    <input type="search" id="searchAssignments" placeholder="Search assignments..." aria-label="Search assignments">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
             
            </div>

            <!-- PAGE TITLE HEADER -->
            <div class="page-header">
                <h2>Assignments</h2>
                <p>Check your teacher's assignments</p>
            </div>

            <!-- FILTER PILLS -->
            <div class="filter-container" aria-label="Assignment filters">
                <button type="button" class="filter-pill active" data-filter="all">All</button>
                <button type="button" class="filter-pill" data-filter="pending">Pending <span class="pill-badge"><?= (int)($assignmentCounts['pending'] ?? 0) ?></span></button>
                <button type="button" class="filter-pill" data-filter="submitted">Submitted <span class="pill-badge"><?= (int)($assignmentCounts['submitted'] ?? 0) ?></span></button>
                <button type="button" class="filter-pill" data-filter="reviewed">Reviewed <span class="pill-badge"><?= (int)($assignmentCounts['reviewed'] ?? 0) ?></span></button>
                <button type="button" class="filter-pill" data-filter="overdue">Overdue <span class="pill-badge"><?= (int)($assignmentCounts['overdue'] ?? 0) ?></span></button>
            </div>

            <!-- ASSIGNMENTS CARDS LIST -->
            <div class="assignments-list">
                <?php if ($successMessage !== ''): ?>
                    <div class="assignment-notice success" role="status"><?= assignmentPageEscape($successMessage) ?></div>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <div class="assignment-notice error" role="alert"><?= assignmentPageEscape($errorMessage) ?></div>
                <?php endif; ?>
                <?php if (!$assignments): ?>
                    <div class="assignment-empty">You don’t have any assignments yet.</div>
                <?php endif; ?>
                <?php foreach ($assignments as $assignment): ?>
                    <?php
                        $statusLabels = [
                            'pending' => 'Pending',
                            'submitted' => 'Submitted',
                            'reviewed' => 'Reviewed',
                            'overdue' => 'Overdue',
                        ];
                        $isReviewed = $assignment['status'] === 'reviewed';
                    ?>
                    <article class="assignment-card" data-status="<?= assignmentPageEscape($assignment['status']) ?>">
                        <div class="assignment-main">
                            <div class="assignment-icon-box">
                                <img src="../images/thankyou.png" alt="">
                            </div>
                            <div class="assignment-details">
                                <h4><?= assignmentPageEscape($assignment['title']) ?></h4>
                                <p class="assignment-instructions"><?= assignmentPageEscape($assignment['instructions']) ?></p>
                                <p class="assignment-meta">From <?= assignmentPageEscape($assignment['teacher_name']) ?></p>
                                <div class="due-date">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= $assignment['due_date'] ? 'Due: ' . assignmentPageEscape(date('F j, Y', strtotime($assignment['due_date']))) : 'No due date' ?>
                                </div>
                                <?php if ($isReviewed && $assignment['feedback'] !== null && $assignment['feedback'] !== ''): ?>
                                    <p class="assignment-instructions"><strong>Teacher feedback:</strong> <?= assignmentPageEscape($assignment['feedback']) ?></p>
                                <?php endif; ?>
                                <?php if (!$isReviewed): ?>
                                    <form class="submission-form" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= assignmentPageEscape($csrfToken) ?>">
                                        <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                                        <textarea name="submission_text" maxlength="10000" required aria-label="Your written response" placeholder="Write your response..."><?= assignmentPageEscape($assignment['submission_text']) ?></textarea>
                                        <button type="submit"><?= $assignment['submitted_at'] !== null ? 'Resubmit response' : 'Submit response' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge <?= assignmentPageEscape($assignment['status']) ?>">
                            <?= assignmentPageEscape($statusLabels[$assignment['status']]) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>


        </main>

       <!-- RIGHT SIDEBAR -->
        <aside class="right-sidebar">
            
            <!-- PROFILE HEADER -->
            <div class="profile-card">
                <div class="notification-btn">
                    <i class="fa-regular fa-bell"></i>
                </div>
                <div class="user-details">
                    <div class="user-avatar">
                        <?php
                            $profileImageUrl = htmlspecialchars($studentProfileImage, ENT_QUOTES, 'UTF-8');
                            echo '<img src="' . $profileImageUrl . '" alt="Student Profile" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
                            echo '<div class="user-avatar-fallback" style="display:none;"><i class="fa-solid fa-user"></i></div>';
                        ?>
                    </div>
                    <?php
                        $studentName = $_SESSION["fullname"] ?? ($_SESSION["full_name"] ?? "Student");
                        echo "<span>" . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . "</span>";
                    ?>
                </div>
            </div>


            <!-- LEARNING PROGRESS WIDGET -->
            <div class="widget-card">
                <h4>My Learning Progress</h4>
                
                <div class="progress-circle-area">
                    <div class="circular-progress">
                        <span class="progress-val">40%</span>
                    </div>
                    <div class="progress-count">
                        <h3>4 of 10</h3>
                        <p>Textbooks completed</p>
                    </div>
                </div>

                <div class="metrics-grid">
                    <div class="metric-item">
                        <h5>4</h5>
                        <p>Completed</p>
                    </div>
                    <div class="metric-item accent">
                        <h5>12</h5>
                        <p>Quizzes taken</p>
                    </div>
                    <div class="metric-item">
                        <h5>85%</h5>
                        <p>Average score</p>
                    </div>
                    <div class="metric-item accent">
                        <h5>7 days</h5>
                        <p>Learning streak</p>
                    </div>
                </div>
            </div>

            <!-- TEXTBOOKS LIST WIDGET -->
            <div>
                <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 12px; color: #1e293b;">Textbooks</h4>
                <div class="textbook-list">
                    <div class="textbook-row">
                        <img src="../images/thankyou.png" alt="ASL Alphabet">
                        <div class="tb-info">
                            <h5>ASL Alphabet</h5>
                            <p>Learn letters A-Z</p>
                        </div>
                        <span class="tb-percent">40%</span>
                    </div>

                    <div class="textbook-row">
                        <img src="../images/thankyou.png" alt="Finger Spelling">
                        <div class="tb-info">
                            <h5>Finger Spelling</h5>
                            <p>Practice spelling words</p>
                        </div>
                        <span class="tb-percent">60%</span>
                    </div>

                    <div class="textbook-row">
                        <img src="../images/thankyou.png" alt="Basic ASL Sign">
                        <div class="tb-info">
                            <h5>Basic ASL Sign</h5>
                            <p>Learn everyday expressions</p>
                        </div>
                        <span class="tb-percent">20%</span>
                    </div>
                </div>
            </div>

            <!-- DAILY WORK WIDGET -->
            <div>
                <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 12px; color: #1e293b;">Daily Work</h4>
                <div class="daily-work-card">
                    <div class="dw-text">
                        <h5>Books this week</h5>
                        <img src="../images/stack-of-books.png" alt="Books Stack">
                    </div>
                    <div class="dw-circle">
                        3/5
                    </div>
                </div>
            </div>

        </aside>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterButtons = document.querySelectorAll('.filter-pill');
            const cards = document.querySelectorAll('.assignment-card');
            const searchInput = document.getElementById('searchAssignments');
            let selectedFilter = 'all';

            filterButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    selectedFilter = this.dataset.filter;

                    filterButtons.forEach(function (item) {
                        item.classList.toggle('active', item === button);
                    });

                    filterCards();
                });
            });

            function filterCards() {
                const searchValue = searchInput ? searchInput.value.trim().toLowerCase() : '';

                cards.forEach(function (card) {
                    const statusMatches = selectedFilter === 'all' || card.dataset.status === selectedFilter;
                    const searchMatches = card.textContent.toLowerCase().includes(searchValue);
                    card.style.display = statusMatches && searchMatches ? 'flex' : 'none';
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterCards);
            }
        });
    </script>

</body>
</html>