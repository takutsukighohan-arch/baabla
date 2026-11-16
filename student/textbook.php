<?php
    session_start();
    include '../config/database.php';
    require_once '../textbook_content_helpers.php';

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

    $availableTextbooks = [];
    $textbookLoadError = false;
    try {
        $textbookStmt = $conn->prepare("
            SELECT book.id, book.title, book.description, book.category,
                   book.sections -> 0 ->> 'image' AS cover_image,
                   COALESCE(book.reviewed_at, book.created_at) AS added_at,
                   COALESCE(activity.recent_reads, 0) AS recent_reads,
                   COALESCE(activity.recent_ratings, 0) AS recent_ratings,
                   COALESCE(activity.recent_good_ratings, 0) AS recent_good_ratings,
                   COALESCE(activity.total_ratings, 0) AS total_ratings,
                   COALESCE(activity.total_good_ratings, 0) AS total_good_ratings,
                   COALESCE(progress.has_opened, false) AS has_opened,
                   COALESCE(progress.has_completed, false) AS has_completed
            FROM public.textbooks AS book
            LEFT JOIN LATERAL (
                SELECT
                    COUNT(*) FILTER (WHERE opened_at >= date_trunc('month', now())) AS recent_reads,
                    COUNT(*) FILTER (WHERE opened_at >= date_trunc('month', now()) AND is_good IS NOT NULL) AS recent_ratings,
                    COUNT(*) FILTER (WHERE opened_at >= date_trunc('month', now()) AND is_good IS TRUE) AS recent_good_ratings,
                    COUNT(*) FILTER (WHERE is_good IS NOT NULL) AS total_ratings,
                    COUNT(*) FILTER (WHERE is_good IS TRUE) AS total_good_ratings
                FROM public.textbook_reading_sessions
                WHERE textbook_id = book.id
            ) AS activity ON true
            LEFT JOIN LATERAL (
                SELECT
                    COUNT(*) > 0 AS has_opened,
                    COALESCE(BOOL_OR(completed_at IS NOT NULL), false) AS has_completed
                FROM public.textbook_reading_sessions
                WHERE textbook_id = book.id AND student_id = :student_id
            ) AS progress ON true
            WHERE book.approval_status = 'approved'
              AND book.is_archived = false
            ORDER BY book.title ASC, book.id DESC
        ");
        $textbookStmt->execute([':student_id' => (int)$_SESSION['user_id']]);
        $availableTextbooks = $textbookStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($availableTextbooks as &$textbook) {
            $reads = (int)$textbook['recent_reads'];
            $ratings = (int)$textbook['recent_ratings'];
            $goodRatings = (int)$textbook['recent_good_ratings'];
            $positiveRatingRatio = $ratings > 0
                ? ($goodRatings + 1) / ($ratings + 2)
                : 0.5;
            $textbook['trending_score'] = $reads > 0
                ? $positiveRatingRatio * log(1 + $reads)
                : 0;
        }
        unset($textbook);

    } catch (PDOException $e) {
        error_log('Unable to load textbooks for student library: ' . $e->getMessage());
        $textbookLoadError = true;
    }

    $trendingTextbooks = array_values(array_filter(
        $availableTextbooks,
        static function ($textbook) {
            return (int)$textbook['recent_reads'] > 0;
        }
    ));
    usort($trendingTextbooks, static function ($left, $right) {
        return $right['trending_score'] <=> $left['trending_score'];
    });

    $recentTextbooks = $availableTextbooks;
    usort($recentTextbooks, static function ($left, $right) {
        $dateOrder = strtotime((string)$right['added_at']) <=> strtotime((string)$left['added_at']);
        return $dateOrder !== 0 ? $dateOrder : ((int)$right['id'] <=> (int)$left['id']);
    });

    $unfinishedTextbooks = array_values(array_filter(
        $availableTextbooks,
        static function ($textbook) {
            return textbookDatabaseBoolean($textbook['has_opened']) &&
                !textbookDatabaseBoolean($textbook['has_completed']);
        }
    ));

    $textbookSections = [
        ['title' => 'Trending Books', 'icon' => 'fa-fire', 'books' => $trendingTextbooks, 'empty' => 'No textbooks have reading activity this month yet.'],
        ['title' => 'Recently Added', 'icon' => 'fa-clock', 'books' => $recentTextbooks, 'empty' => 'No approved textbooks have been added yet.'],
        ['title' => 'All Textbooks', 'icon' => 'fa-book-open', 'books' => $availableTextbooks, 'empty' => 'No approved textbooks are available yet.'],
        ['title' => 'Unfinished Books', 'icon' => 'fa-bookmark', 'books' => $unfinishedTextbooks, 'empty' => 'You do not have any unfinished textbooks.']
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASLify - Textbooks Study Options</title>
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

        /* TOP BAR WITH LANG SELECTOR */
        .top-bar-right {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .lang-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            padding: 6px 14px;
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

        /* HERO BANNER */
        .hero-banner {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 20px;
            padding: 28px 36px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .banner-text h2 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .banner-text p {
            font-size: 0.82rem;
            opacity: 0.9;
            margin-bottom: 20px;
            max-width: 320px;
            line-height: 1.4;
        }

        .btn-view-now {
            background-color: #1e1b4b;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            border-radius: 18px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-view-now:hover {
            background-color: #0f172a;
        }


        /* BACK TO DASHBOARD LINK */
        .back-link {
            font-size: 0.78rem;
            font-weight: 700;
            color: #1e293b;
            text-decoration: none;
            display: inline-block;
            margin-top: 4px;
        }

        /* TEXTBOOKS HEADER + MINI SEARCH */
        .section-header {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .section-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }

        /* SELECTED TEXTBOOK DETAIL CARD */
        .textbook-detail-container {
            display: flex;
            align-items: flex-start;
            gap: 24px;
            margin-top: 4px;
        }

        .tb-single-card {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tb-image-box {
            background: #ffffff;
            border-radius: 14px;
            padding: 12px;
            width: 110px;
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .tb-image-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .tb-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: #1e293b;
            margin-top: 8px;
        }

        .tb-description {
            flex: 1;
            font-size: 14px;
            color: #64748b;
            line-height: 1.7;
            padding-top: 6px;
            text-align: justify;
            padding-right: 20px ;
        }

        /* STUDY OPTIONS SECTION */
        .study-options-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: #1e293b;
            margin-top: 6px;
        }

        .study-cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .study-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
        }

        .study-card-icon {
            width: 56px;
            height: 56px;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .study-card-icon svg {
            width: 32px;
            height: 32px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .study-card-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            flex: 1;
        }

        .study-card-content h5 {
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
        }

        .btn-action {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 0.2s;
        }

        .btn-action:hover {
            background-color: #1d4ed8;
        }

        .textbook-shelf { margin-top: 8px; }
        .textbook-shelf[hidden], .catalog-book[hidden] { display: none; }
        .textbook-shelf-heading { display: flex; align-items: center; gap: 9px; margin-bottom: 12px; }
        .textbook-shelf-heading h3 { margin: 0; color: #172554; font-size: 1.05rem; font-weight: 700; }
        .textbook-shelf-heading i { color: #2563eb; }
        .textbook-shelf-scroll {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding: 2px 3px 14px;
            scroll-snap-type: x mandatory;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 #e2e8f0;
        }

        .textbook-shelf-scroll::-webkit-scrollbar { height: 8px; }
        .textbook-shelf-scroll::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 8px; }
        .textbook-shelf-scroll::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 8px; }

        .catalog-book {
            display: flex;
            flex-direction: column;
            flex: 0 0 205px;
            width: 205px;
            min-width: 0;
            padding: 14px;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
            text-decoration: none;
            color: inherit;
            scroll-snap-align: start;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .catalog-book:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(15,23,42,.1); }
        .catalog-cover {
            height: 150px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            border-radius: 10px;
            background: linear-gradient(135deg,#eff6ff,#dbeafe);
            color: #1e3a8a;
            font-weight: 700;
            text-align: center;
            overflow-wrap: anywhere;
            padding: 12px;
        }

        .catalog-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .catalog-book h4 { margin: 12px 0 5px; font-size: .9rem; }
        .catalog-book p { margin: 0; color: #64748b; font-size: .75rem; line-height: 1.5; }
        .catalog-category { margin-top: auto; padding-top: 10px; color: #2563eb; font-size: .7rem; font-weight: 700; }
        .catalog-activity { margin-top: 10px !important; color: #64748b !important; font-size: .7rem !important; }
        .catalog-progress { margin-top: 7px; color: #2563eb; font-size: .68rem; font-weight: 700; }
        .catalog-empty { flex: 0 0 100%; padding: 18px; border-radius: 12px; background: #fff; color: #64748b; }
        .section-header { width: 100%; }
        .section-header h3 { flex: 0 0 auto; white-space: nowrap; }
        .section-header .mini-search {
            flex: 1 1 auto;
            width: auto;
            min-width: 0;
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
        }
        .section-header .mini-search input { min-width: 0; font-size: .85rem; }

        @media (max-width: 560px) {
            .section-header { align-items: stretch; flex-direction: column; gap: 10px; }
            .section-header .mini-search { width: 100%; }
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
                <li class="nav-item ">
                    <a href="dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
                </li>
                <li class="nav-item active">
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

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        
        <!-- CENTER CONTENT -->
        <main class="center-content">



          

            <div class="section-header">
                <h3>ASL Textbook Library</h3>
                <div class="mini-search">
                    <input type="text" id="textbookLibrarySearch" placeholder="Search your textbook...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
            </div>

            <?php foreach ($textbookSections as $section): ?>
                <section class="textbook-shelf">
                    <div class="textbook-shelf-heading">
                        <i class="fa-solid <?= e($section['icon']); ?>" aria-hidden="true"></i>
                        <h3><?= e($section['title']); ?></h3>
                    </div>
                    <div class="textbook-shelf-scroll">
                        <?php if ($textbookLoadError): ?>
                            <p class="catalog-empty">Textbooks could not be loaded right now.</p>
                        <?php elseif ($section['books']): ?>
                            <?php foreach ($section['books'] as $textbook): ?>
                                <?php $coverImage = preg_match('/\Auploads\/textbooks\/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z/i', (string)($textbook['cover_image'] ?? '')) === 1; ?>
                                <a class="catalog-book"
                                   href="../textbook_content.php?id=<?= (int)$textbook['id']; ?>"
                                   data-search="<?= e($textbook['title'] . ' ' . ($textbook['description'] ?? '') . ' ' . ($textbook['category'] ?? '')); ?>">
                                    <div class="catalog-cover">
                                        <?php if ($coverImage): ?>
                                            <img src="../textbook_image.php?id=<?= (int)$textbook['id']; ?>&amp;section=0" alt="<?= e($textbook['title']); ?>" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                            <span hidden><?= e($textbook['title']); ?></span>
                                        <?php else: ?>
                                            <?= e($textbook['title']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <h4><?= e($textbook['title']); ?></h4>
                                    <p><?= e($textbook['description'] ?: 'Open this textbook to view its sections and practice activity.'); ?></p>
                                    <p class="catalog-activity">
                                        <?= number_format((int)$textbook['recent_reads']); ?> reads this month
                                        ·
                                        <?php if ((int)$textbook['total_ratings'] > 0): ?>
                                            <?= number_format(100 * (int)$textbook['total_good_ratings'] / (int)$textbook['total_ratings'], 0); ?>% good (<?= number_format((int)$textbook['total_ratings']); ?> ratings)
                                        <?php else: ?>
                                            Not rated yet
                                        <?php endif; ?>
                                    </p>
                                    <?php if (textbookDatabaseBoolean($textbook['has_completed'])): ?>
                                        <span class="catalog-progress">Completed</span>
                                    <?php elseif (textbookDatabaseBoolean($textbook['has_opened'])): ?>
                                        <span class="catalog-progress">Unfinished · Continue reading</span>
                                    <?php endif; ?>
                                    <span class="catalog-category"><?= e($textbook['category'] ?: 'General'); ?> · Open textbook</span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="catalog-empty"><?= e($section['empty']); ?></p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <!-- STATS COUNTER BAR -->
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
                <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 12px; color: #1e293b;">Textbooks</h4>
                <div class="textbook-list">
                    <?php foreach (array_slice($availableTextbooks, 0, 3) as $textbook): ?>
                        <a class="textbook-row" href="../textbook_content.php?id=<?= (int)$textbook['id']; ?>" style="text-decoration:none;color:inherit;">
                            <?php if (preg_match('/\Auploads\/textbooks\/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z/i', (string)($textbook['cover_image'] ?? '')) === 1): ?>
                                <img src="../textbook_image.php?id=<?= (int)$textbook['id']; ?>&amp;section=0" alt="<?= e($textbook['title']); ?>">
                            <?php else: ?>
                                <span style="width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#dbeafe;color:#1e3a8a;font-size:10px;font-weight:700;overflow:hidden;text-align:center;padding:3px;"><?= e($textbook['title']); ?></span>
                            <?php endif; ?>
                            <div class="tb-info">
                                <h5><?= e($textbook['title']); ?></h5>
                                <p><?= e($textbook['category'] ?: 'Learning material'); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$availableTextbooks): ?>
                        <p><?= $textbookLoadError ? 'Textbooks could not be loaded.' : 'No approved textbooks yet.'; ?></p>
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


<script>
const librarySearch = document.getElementById('textbookLibrarySearch');
if (librarySearch) {
    librarySearch.addEventListener('input', function() {
        const search = this.value.trim().toLocaleLowerCase();
        document.querySelectorAll('.textbook-shelf').forEach(function(shelf) {
            let visibleBooks = 0;
            shelf.querySelectorAll('.catalog-book').forEach(function(book) {
                book.hidden = !book.dataset.search.toLocaleLowerCase().includes(search);
                if (!book.hidden) visibleBooks++;
            });
            shelf.hidden = search !== '' && visibleBooks === 0;
        });
    });
}
</script>
</body>
</html>