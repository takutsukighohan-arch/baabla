<?php
    session_start();
    include '../config/database.php';

    if(!isset($_SESSION["role"]) || $_SESSION["role"] != "student"){
        header("Location: ../index.php");
        exit;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASLify - My Progress</title>
    <!-- POPPINS FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FONT AWESOME ICONS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: #334155;
            display: flex;
            min-height: 100vh;
        }

        /* --- LEFT SIDEBAR --- */
        .sidebar {
            width: 220px;
            background-color: #ffffff;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid #e2e8f0;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.3rem;
            color: #1e1b4b;
            margin-bottom: 32px;
            padding-left: 10px;
        }

        .brand-logo img {
            height: 28px;
            object-fit: contain;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            text-decoration: none;
            color: #94a3b8;
            font-size: 0.88rem;
            font-weight: 500;
            border-radius: 20px;
            transition: all 0.2s ease;
        }

        .nav-item.active a {
            background-color: #2563eb;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        .nav-item a:hover:not(.active) {
            color: #475569;
            background-color: #f8fafc;
        }

        .sidebar-illustration {
            margin-top: auto;
            text-align: center;
            padding-top: 20px;
        }

        .sidebar-illustration img {
            width: 100%;
            max-width: 130px;
            height: auto;
        }

        /* --- MAIN LAYOUT WRAPPER --- */
        .main-wrapper {
            flex: 1;
            display: flex;
            padding: 20px;
            gap: 20px;
        }

        /* CENTER CONTENT AREA */
        .center-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 18px;
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

        /* --- USER PROFILE SECTION --- */
        .user-profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 4px;
        }

        .user-avatar-large {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            display: block;
            flex-shrink: 0;
        }

        .user-avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dbeafe, #e0e7ff);
            color: #2563eb;
            font-size: 1.6rem;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .profile-name-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-name-row h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
        }

        .badge-gizmo {
            background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .profile-subtext {
            font-size: 0.78rem;
            color: #64748b;
        }

        .profile-followers {
            font-size: 0.78rem;
            font-weight: 600;
            color: #2563eb;
            margin-top: 2px;
        }

        .profile-actions-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }

        .btn-profile-action {
            background-color: #1e293b;
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 18px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-profile-icon {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #e2e8f0;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.82rem;
        }

        /* --- PURE CSS TAB CONTROLS (NO JS) --- */
        .tab-radio {
            display: none; /* Hide standard radio buttons */
        }

        .sub-nav-tabs {
            display: flex;
            gap: 28px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 4px;
            margin-top: 8px;
        }

        .tab-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #94a3b8;
            padding-bottom: 8px;
            cursor: pointer;
            position: relative;
            transition: color 0.2s;
        }

        .tab-content {
            display: none; /* Hide all contents by default */
            flex-direction: column;
            gap: 18px;
        }

        /* Radio Toggle Styles for Active Tab Header */
        #radio-feed:checked ~ .sub-nav-tabs label[for="radio-feed"],
        #radio-stats:checked ~ .sub-nav-tabs label[for="radio-stats"],
        #radio-decks:checked ~ .sub-nav-tabs label[for="radio-decks"],
        #radio-school:checked ~ .sub-nav-tabs label[for="radio-school"] {
            color: #1e293b;
            font-weight: 700;
        }

        #radio-feed:checked ~ .sub-nav-tabs label[for="radio-feed"]::after,
        #radio-stats:checked ~ .sub-nav-tabs label[for="radio-stats"]::after,
        #radio-decks:checked ~ .sub-nav-tabs label[for="radio-decks"]::after,
        #radio-school:checked ~ .sub-nav-tabs label[for="radio-school"]::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #1e293b;
            border-radius: 3px;
        }

        /* Radio Toggle Styles for Showing Corresponding Content */
        #radio-feed:checked ~ #content-feed,
        #radio-stats:checked ~ #content-stats,
        #radio-decks:checked ~ #content-decks,
        #radio-school:checked ~ #content-school {
            display: flex;
        }

        /* --- FEED CONTENT STYLES --- */
        .section-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header-flex h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
        }

        .section-header-flex a {
            font-size: 0.75rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
        }

        .friends-cards-row {
            display: flex;
            gap: 16px;
        }

        .friend-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 16px;
            width: 180px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .friend-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .friend-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
        }

        .friend-info h5 {
            font-size: 0.78rem;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100px;
        }

        .btn-play-friend {
            background-color: #ffffff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.2s;
        }

        .btn-play-friend:hover {
            background-color: #f8fafc;
        }

        .btn-play-friend img {
            width: 18px;
            height: 18px;
        }

        .leaderboard-container {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .leaderboard-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
        }

        .leaderboard-row:last-child {
            border-bottom: none;
        }

        .lb-left-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .lb-icon {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .lb-details h5 {
            font-size: 0.88rem;
            font-weight: 700;
            color: #1e293b;
        }

        .lb-details p {
            font-size: 0.72rem;
            color: #64748b;
        }

        .lb-arrow {
            font-size: 0.9rem;
            color: #1e293b;
        }

        /* --- STATS CONTENT STYLES --- */
        .status-cards-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .status-box {
            background: #ffffff;
            border-radius: 18px;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            font-size: 0.88rem;
            font-weight: 700;
            color: #1e293b;
        }

        .status-box.xp-green {
            background-color: #dcfce7;
            color: #15803d;
            border: none;
        }

        .league-banner {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 40%, #60a5fa 100%);
            border-radius: 20px;
            padding: 16px 24px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        .league-info-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .league-badge-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .league-badge-icon img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .league-details h4 {
            font-size: 0.98rem;
            font-weight: 700;
        }

        .league-time {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 4px;
        }

        .league-arrow {
            font-size: 1.1rem;
            color: #ffffff;
        }

        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
        }

        .collectables-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
        }

        .collectable-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 14px 10px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .collectable-card img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .collectable-card h5 {
            font-size: 0.78rem;
            font-weight: 700;
            color: #1e293b;
        }

        .collectable-card p {
            font-size: 0.68rem;
            color: #64748b;
            margin-top: 2px;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .badge-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .badge-card img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        .badge-card h5 {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1e293b;
        }

        .badge-card p {
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 2px;
        }

        /* --- DECKS CONTENT STYLES --- */
        .decks-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .deck-card {
            background: #ffffff;
            border-radius: 25px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .deck-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 12px;
            background-color: #a5b4fc;
            border-top-left-radius: 25px;
            border-bottom-left-radius: 25px;
        }

        .deck-details {
            padding-left: 8px;
        }

        .deck-details h4 {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.3px;
        }

        .deck-details p {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 2px;
        }

        .deck-badge-icon {
            width: 28px;
            height: 28px;
            background-color: #e0e7ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .deck-badge-icon img {
            width: 16px;
            height: 16px;
            object-fit: contain;
        }

        /* STATS COUNTER BAR */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-top: auto;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .stat-icon img {
            width: 38px;
            height: 38px;
            object-fit: contain;
        }

        .stat-info h4 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }

        .stat-info p {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* --- RIGHT SIDEBAR --- */
        .right-sidebar {
            width: 300px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .profile-card {
            background: #ffffff;
            padding: 10px 14px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .notification-btn {
            background: #f1f5f9;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .user-details {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-details img {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-details span {
            font-size: 0.82rem;
            font-weight: 600;
            color: #1e293b;
        }

        .widget-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .widget-card h4 {
            font-size: 0.92rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .progress-circle-area {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .circular-progress {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: conic-gradient(#2563eb 40%, #e2e8f0 0);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .circular-progress::before {
            content: "";
            position: absolute;
            width: 50px;
            height: 50px;
            background: #ffffff;
            border-radius: 50%;
        }

        .progress-val {
            position: relative;
            font-size: 0.78rem;
            font-weight: 700;
            color: #1e293b;
        }

        .progress-count h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1e293b;
        }

        .progress-count p {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .metric-item h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2563eb;
        }

        .metric-item.accent h5 {
            color: #ef4444;
        }

        .metric-item p {
            font-size: 0.68rem;
            color: #94a3b8;
        }

        .textbook-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .textbook-row {
            background: #ffffff;
            border-radius: 12px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .textbook-row img {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            object-fit: cover;
        }

        .tb-info {
            flex: 1;
        }

        .tb-info h5 {
            font-size: 0.8rem;
            font-weight: 700;
            color: #1e293b;
        }

        .tb-info p {
            font-size: 0.68rem;
            color: #94a3b8;
        }

        .tb-percent {
            font-size: 0.7rem;
            font-weight: 600;
            color: #94a3b8;
        }

        .daily-work-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .dw-text h5 {
            font-size: 0.82rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .dw-text img {
            width: 50px;
            height: auto;
        }

        .dw-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            font-weight: 700;
            color: #1e293b;
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
                <li class="nav-item active">
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
            
            <!-- HIDDEN RADIO INPUTS FOR PURE CSS TAB SWITCHING -->
            <input type="radio" id="radio-feed" name="progress-tabs" class="tab-radio" checked>
            <input type="radio" id="radio-stats" name="progress-tabs" class="tab-radio">
            <input type="radio" id="radio-decks" name="progress-tabs" class="tab-radio">


        

            <!-- USER PROFILE HEADER -->
            <div class="user-profile-header">
                <?php
                    $profileImageUrl = htmlspecialchars($studentProfileImage, ENT_QUOTES, 'UTF-8');
                    echo '<img src="' . $profileImageUrl . '" class="user-avatar-large" alt="Student Profile" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
                    echo '<div class="user-avatar-large user-avatar-fallback" style="display:none;"><i class="fa-solid fa-user"></i></div>';
                ?>
                <div class="profile-info">
                    <div class="profile-name-row">
                        
                        <h2>   <?php
                        $studentName = $_SESSION["fullname"] ?? ($_SESSION["full_name"] ?? "Student");
                        echo "<span>" . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . "</span>";
                    ?></h2>
                        <span class="badge-gizmo"><i class="fa-solid fa-bolt"></i> Enrolled</span>
                    </div>  
                    
                    <div class="profile-followers">
                        1 follower • 1 following
                    </div>
                    <div class="profile-actions-row">
                        <button class="btn-profile-action">
                            <i class="fa-solid fa-user-plus"></i> Find friends
                        </button>
                        <button class="btn-profile-icon"><i class="fa-solid fa-pen"></i></button>
                       
                    </div>
                </div>
            </div>

            <!-- SUB-NAV TABS (PURE CSS LABELS) -->
            <div class="sub-nav-tabs">
                <label for="radio-feed" class="tab-label">Feed</label>
                <label for="radio-stats" class="tab-label">Stats</label>
                <label for="radio-decks" class="tab-label">Decks</label>
            
            </div>

            <!-- ================= TAB 1: FEED CONTENT ================= -->
            <div id="content-feed" class="tab-content">
                <div class="section-header-flex">
                    <h4>Friends (1)</h4>
                    <a href="#">View all</a>
                </div>

                <div class="friends-cards-row">
                    <div class="friend-card">
                        <div class="friend-info">
                            <img src="../images/thankyou.png" class="friend-avatar" alt="Channel Quiazon">
                            <h5>Channel Quiaz...</h5>
                        </div>
                        <button class="btn-play-friend">
                            <i class="fa-regular fa-message" style="color: rgb(0, 0, 0);"></i> Chat
                        </button>
                    </div>
                </div>

                <h4 style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-top: 10px;">Leaderboards</h4>
                
                <div class="leaderboard-container">
                    <div class="leaderboard-row">
                        <div class="lb-left-info">
                            <img src="../images/thankyou.png" class="lb-icon" alt="My Friends">
                            <div class="lb-details">
                                <h5>My friends</h5>
                                <p>#2 position</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right lb-arrow"></i>
                    </div>
    
                    <div class="leaderboard-row">
                        <div class="lb-left-info">
                            <img src="../images/thankyou.png" class="lb-icon" alt="School">
                            <div class="lb-details">
                                <h5>Clark College of Science and Technology</h5>
                                <p>Leaderboard</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right lb-arrow"></i>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 2: STATS CONTENT ================= -->
            <div id="content-stats" class="tab-content">
      

                <div class="league-banner">
                    <div class="league-info-left">
                        <div class="league-badge-icon">
                            <img src="../images/thankyou.png" alt="Mushroom League">
                        </div>
                        <div class="league-details">
                            <h4>Mushroom League</h4>
                            <div class="league-time">
                                <i class="fa-regular fa-clock"></i> 3 days
                            </div>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right league-arrow"></i>
                </div>

                <h4 class="section-title">Top collectables</h4>
                <div class="collectables-grid">
                    <div class="collectable-card">
                        <img src="../images/thankyou.png" alt="Mushroom League">
                        <h5>Wizard</h5>
                        <p>365 days</p>
                    </div>
                    <div class="collectable-card">
                        <img src="../images/thankyou.png" alt="Mushroom League">
                        <h5>Monster</h5>
                        <p>100 days</p>
                    </div>
                    <div class="collectable-card">
                        <img src="../images/thankyou.png" alt="Mushroom League">
                        <h5>Unicorn</h5>
                        <p>75 days</p>
                    </div>
                    <div class="collectable-card">
                        <img src="../images/thankyou.png" alt="Mushroom League">
                        <h5>Lampfish</h5>
                        <p>50 days</p>
                    </div>
                    <div class="collectable-card">
                        <img src="../images/thankyou.png" alt="Mushroom League">
                        <h5>Dragon</h5>
                        <p>30 days</p>
                    </div>
                    <div class="collectable-card">
                        <img src="../images/thankyou.png" alt="Mushroom League">
                        <h5>Wolf</h5>
                        <p>20 days</p>
                    </div>
                </div>

                <h4 class="section-title">Badges</h4>
                <div class="badges-grid">
                    <div class="badge-card">
                        <img src="../images/thankyou.png" alt="Best streak">
                        <h5>Best streak</h5>
                        <p>307 days</p>
                    </div>
                    <div class="badge-card">
                        <img src="../images/thankyou.png" alt="Cards">
                        <h5>Cards</h5>
                        <p>86,833 cards</p>
                    </div>
                    <div class="badge-card">
                        <img src="../images/thankyou.png" alt="Crowns">
                        <h5>Crowns</h5>
                        <p>0 crowns</p>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 3: DECKS CONTENT ================= -->
            <div id="content-decks" class="tab-content">
                <div class="decks-container">
                    <div class="deck-card">
                        <div class="deck-details">
                            <h4>PRELIMS - ACT2</h4>
                            <p>605 cards</p>
                        </div>
                        <div class="deck-badge-icon">
                            <i class="fa-solid fa-book" style="color: rgb(255, 255, 255);"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 4: SCHOOL CONTENT ================= -->
            <div id="content-school" class="tab-content">
                <div class="decks-container">
                    <div class="deck-card">
                        <div class="deck-details">
                            <h4>Clark College of Science and Technology</h4>
                            <p>Degree • Second year</p>
                        </div>
                    </div>
                </div>
            </div>

         
        </main>

   

</body>
</html>