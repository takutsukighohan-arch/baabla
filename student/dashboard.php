<?php
    session_start();
    include '../config/database.php';

    if(!isset($_SESSION["role"]) || $_SESSION["role"] != "student"){
        header("Location: ../index.php");
        exit;
    }

    function e($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    $defaultProfileImage = "https://ui-avatars.com/api/?name=" . urlencode($_SESSION["fullname"] ?? "Student") . "&background=2563eb&color=fff&size=200";
    $studentProfileImage = $defaultProfileImage;

    if (isset($_SESSION["user_id"])) {
        try {
            $profileStmt = $conn->prepare("SELECT profile_image FROM users WHERE id = :id LIMIT 1");
            $profileStmt->execute([':id' => $_SESSION["user_id"]]);
            $profileUser = $profileStmt->fetch();

            if ($profileUser && !empty(trim((string)($profileUser['profile_image'] ?? '')))) {
                $studentProfileImage = $profileUser['profile_image'];
            }
        } catch (Exception $e) {
            $studentProfileImage = $defaultProfileImage;
        }
    }

    $unfinishedTextbooks = [];
    $textbookLoadError = false;

    try {
        $textbookStmt = $conn->prepare("
            SELECT book.id, book.title, book.description, book.category,
                   book.sections -> 0 ->> 'image' AS cover_image,
                   activity.last_opened_at
            FROM public.textbooks AS book
            JOIN LATERAL (
                SELECT MAX(opened_at) AS last_opened_at
                FROM public.textbook_reading_sessions
                WHERE textbook_id = book.id
                  AND student_id = :activity_student_id
            ) AS activity ON activity.last_opened_at IS NOT NULL
            WHERE book.approval_status = 'approved'
              AND book.is_archived = false
              AND NOT EXISTS (
                  SELECT 1
                  FROM public.textbook_reading_sessions AS completed_reading
                  WHERE completed_reading.textbook_id = book.id
                    AND completed_reading.student_id = :completed_student_id
                    AND completed_reading.completed_at IS NOT NULL
              )
            ORDER BY activity.last_opened_at DESC, book.id DESC
        ");
        $textbookStmt->execute([
            ':activity_student_id' => (int)$_SESSION['user_id'],
            ':completed_student_id' => (int)$_SESSION['user_id']
        ]);
        $unfinishedTextbooks = $textbookStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Unable to load unfinished textbooks on student dashboard: ' . $e->getMessage());
        $textbookLoadError = true;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASLify - Dashboard</title>
    <!-- POPPINS & INTER FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FONT AWESOME ICONS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
   
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
                    <a href="classroom.php"><i class="fa-solid fa-border-all"></i> Classroom</a>
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

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        
        <!-- CENTER CONTENT -->
        <main class="center-content">
            
            <!-- TOP BAR -->
            <div class="top-bar">
                <div class="search-box">
                    <input type="text" placeholder="Search your textbook...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
              
            </div>

            <!-- HERO BANNER -->
            <div class="hero-banner">
                <div class="banner-text">
                    <h2>Trending Books<br>This Month</h2>
                    <p>Keep learning and improving your ASL skills!<br>Explore the textbooks below or search for what you want.</p>
                    <button class="btn-view-now">VIEW NOW</button>
                </div>
                <img class="banner-illustration" src="../images/reading.png" alt="3D Reading Character">
            </div>

            <!-- TEXTBOOKS GRID -->
            <section>
                <div class="section-header">
                    <h3>Unfinished Textbooks</h3>
                    <div class="mini-search">
                        <input type="text" placeholder="Search your textbook...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                </div>

                <div class="textbook-table-scroll">
                    <table class="textbooks-table">
                        <tbody>
                            <?php if ($unfinishedTextbooks): ?>
                                <?php foreach (array_chunk($unfinishedTextbooks, 5) as $textbookRow): ?>
                                    <tr>
                                        <?php foreach ($textbookRow as $textbook): ?>
                                            <?php
                                            $coverImage = trim((string)($textbook['cover_image'] ?? ''));
                                            $hasCoverImage = preg_match('/\Auploads\/textbooks\/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z/i', $coverImage) === 1;
                                            ?>
                                            <td class="textbook-card">
                                                <a href="../textbook_content.php?id=<?= (int)$textbook['id']; ?>" style="display:block;color:inherit;text-decoration:none;">
                                                <div class="textbook-img-wrapper">
                                                    <?php if ($hasCoverImage): ?>
                                                        <img
                                                            src="../textbook_image.php?id=<?= (int)$textbook['id']; ?>&amp;section=0"
                                                            alt="<?= e($textbook['title']); ?>"
                                                            onerror="this.hidden=true;this.nextElementSibling.hidden=false;"
                                                        >
                                                        <div hidden style="width:100%;height:100%;padding:30px 12px;background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#1e3a8a;font-size:0.9rem;font-weight:700;line-height:1.35;text-align:center;overflow-wrap:anywhere;">
                                                            <?= e($textbook['title']); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:12px;background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#1e3a8a;font-size:0.9rem;font-weight:700;line-height:1.35;text-align:center;overflow-wrap:anywhere;">
                                                            <?= e($textbook['title']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <p><?= e($textbook['title']); ?></p>
                                                </a>
                                            </td>
                                        <?php endforeach; ?>
                                        <?php for ($emptyCell = count($textbookRow); $emptyCell < 5; $emptyCell++): ?>
                                            <td aria-hidden="true"></td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="textbook-card">
                                        <p>
                                            <?= $textbookLoadError
                                                ? 'Unfinished textbooks could not be loaded right now.'
                                                : 'You have no unfinished textbooks.'; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>  
            </section>

            <!-- STATS COUNTER BAR -->
            <div class="stats-bar">
                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../images/book-icon.png" alt="Book Icon">
                    </div>
                    <div class="stat-info">
                        <h4>20</h4>
                        <p>Read textbooks</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../images/message-icon.png" alt="Message Icon">
                    </div>
                    <div class="stat-info">
                        <h4>20</h4>
                        <p>Message</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../images/assignment-icon.png" alt="Assignment Icon">
                    </div>
                    <div class="stat-info">
                        <h4>03</h4>
                        <p>Assignments</p>
                    </div>
                </div>
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
                <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 12px; color: #1e293b;">Unfinished Textbooks</h4>
                <div class="textbook-list">
                    <?php foreach (array_slice($unfinishedTextbooks, 0, 3) as $textbook): ?>
                        <?php
                        $coverImage = trim((string)($textbook['cover_image'] ?? ''));
                        $hasCoverImage = preg_match('/\Auploads\/textbooks\/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z/i', $coverImage) === 1;
                        ?>
                        <a class="textbook-row"
                           href="../textbook_content.php?id=<?= (int)$textbook['id']; ?>"
                           style="text-decoration:none;color:inherit;">
                            <?php if ($hasCoverImage): ?>
                                <img src="../textbook_image.php?id=<?= (int)$textbook['id']; ?>&amp;section=0"
                                     alt="<?= e($textbook['title']); ?>"
                                     onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                <span hidden style="width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#dbeafe;color:#1e3a8a;font-size:10px;font-weight:700;overflow:hidden;text-align:center;padding:3px;"><?= e($textbook['title']); ?></span>
                            <?php else: ?>
                                <span style="width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#dbeafe;color:#1e3a8a;font-size:10px;font-weight:700;overflow:hidden;text-align:center;padding:3px;"><?= e($textbook['title']); ?></span>
                            <?php endif; ?>
                            <div class="tb-info">
                                <h5><?= e($textbook['title']); ?></h5>
                                <p><?= e($textbook['category'] ?: 'Continue reading'); ?></p>
                            </div>
                            <span class="tb-percent">Continue</span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$unfinishedTextbooks): ?>
                        <p><?= $textbookLoadError ? 'Unfinished textbooks could not be loaded.' : 'You have no unfinished textbooks.'; ?></p>
                    <?php endif; ?>
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

</body>
</html>