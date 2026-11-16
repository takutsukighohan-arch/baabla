<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASLify - Messages & Live Call</title>
    <!-- POPPINS FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FONT AWESOME ICONS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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

        /* VIDEO STREAM GRID */
        .video-streams-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .video-box {
            background-color: #94a3b8;
            border-radius: 16px;
            height: 220px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-box img.video-feed {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-tag {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            background-color: #22c55e;
            border-radius: 50%;
        }

        /* CALL TOOLBAR CONTROLS */
        .call-toolbar {
            background-color: #2563eb;
            border-radius: 25px;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .control-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .btn-start-call {
            background-color: #22c55e;
            color: #ffffff;
            border: none;
            padding: 6px 16px;
            border-radius: 16px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-end-call {
            background-color: #ef4444;
            color: #ffffff;
        }

        .btn-end-call:hover {
            background-color: #dc2626;
        }

        /* CHAT PANEL */
        .chat-panel {
            background-color: #cbd5e1;
            border-radius: 16px;
            flex: 1;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .chat-header {
            padding: 12px 18px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #1e293b;
            border-bottom: 2px solid #94a3b8;
        }

        .chat-body {
            flex: 1;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .chat-avatar-placeholder {
            width: 24px;
            height: 24px;
            background-color: #e2e8f0;
            border-radius: 50%;
        }

        .chat-right-actions {
            align-self: flex-end;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .chat-action-btn {
            color: #475569;
            font-size: 1rem;
            cursor: pointer;
        }

        .chat-blue-dot {
            width: 18px;
            height: 18px;
            background-color: #2563eb;
            border-radius: 50%;
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

        /* PROGRESS CONTAINER */
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

        /* TEXTBOOKS LIST WIDGET */
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

        /* DAILY WORK WIDGET */
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
                <img src="images/logo.svg" alt="ASLify Logo">
                <span>ASLify</span>
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
                <li class="nav-item">
                    <a href="assignment.php"><i class="fa-regular fa-file-lines"></i> Assignment</a>
                </li>
                <li class="nav-item">
                    <a href="practice.php"><i class="fa-solid fa-bars-progress"></i> Practice</a>
                </li>
                <li class="nav-item">
                    <a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a>
                </li>
                <li class="nav-item active">
                    <a href="message.php"><i class="fa-regular fa-comment-dots"></i> Messages</a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</a>
                </li>
            </ul>
        </div>

        <div class="sidebar-illustration">
            <img src="images/standing-character.svg" alt="Student Illustration">
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
                <div class="lang-selector">
                    <span>EN</span>
                    <img src="images/uk-flag.png" alt="UK Flag">
                </div>
            </div>

            <!-- VIDEO STREAMS GRID -->
            <div class="video-streams-grid">
                <!-- Instructor Video Feed -->
                <div class="video-box">
                    <div class="user-tag">
                        <span class="status-dot"></span> Instructor
                    </div>
                    <img src="images/instructor-stream.jpg" class="video-feed" alt="Instructor Video Feed">
                </div>

                <!-- User Video Feed -->
                <div class="video-box">
                    <div class="user-tag">
                        <span class="status-dot"></span> You
                    </div>
                    <img src="images/user-stream.jpg" class="video-feed" alt="User Video Feed">
                </div>
            </div>

            <!-- CALL TOOLBAR CONTROLS -->
            <div class="call-toolbar">
                <button class="control-btn"><i class="fa-solid fa-microphone"></i></button>
                <button class="control-btn"><i class="fa-solid fa-video"></i></button>
                <button class="control-btn"><i class="fa-solid fa-desktop"></i></button>
                <button class="control-btn"><i class="fa-regular fa-comment"></i></button>
                <button class="control-btn"><i class="fa-regular fa-face-smile"></i></button>
                <button class="btn-start-call">
                    <i class="fa-solid fa-video"></i> START
                </button>
                <button class="control-btn"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                <button class="control-btn"><i class="fa-regular fa-bookmark"></i></button>
                <button class="control-btn"><i class="fa-solid fa-share-nodes"></i></button>
                <button class="control-btn btn-end-call"><i class="fa-solid fa-phone-slash"></i></button>
            </div>

            <!-- CHAT PANEL -->
            <div class="chat-panel">
                <div class="chat-header">
                    Chat
                </div>
                <div class="chat-body">
                    <div class="chat-avatar-placeholder">
                        <img src="images/chat-user-avatar.png" alt="Chat User" style="width:100%; height:100%; border-radius:50%;">
                    </div>
                    <div class="chat-right-actions">
                        <i class="fa-regular fa-user chat-action-btn"></i>
                        <div class="chat-blue-dot"></div>
                    </div>
                </div>
            </div>

            <!-- STATS COUNTER BAR -->
            <div class="stats-bar">
                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="images/book-icon.png" alt="Book Icon">
                    </div>
                    <div class="stat-info">
                        <h4>20</h4>
                        <p>Read textbooks</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="images/message-icon.png" alt="Message Icon">
                    </div>
                    <div class="stat-info">
                        <h4>20</h4>
                        <p>Message</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="images/assignment-icon.png" alt="Assignment Icon">
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
                    <img src="images/student-avatar.png" alt="Student Profile">
                    <span>Student Account</span>
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
                        <img src="images/asl-alphabet-thumb.png" alt="ASL Alphabet">
                        <div class="tb-info">
                            <h5>ASL Alphabet</h5>
                            <p>Learn letters A-Z</p>
                        </div>
                        <span class="tb-percent">40%</span>
                    </div>

                    <div class="textbook-row">
                        <img src="images/finger-spelling-thumb.png" alt="Finger Spelling">
                        <div class="tb-info">
                            <h5>Finger Spelling</h5>
                            <p>Practice spelling words</p>
                        </div>
                        <span class="tb-percent">60%</span>
                    </div>

                    <div class="textbook-row">
                        <img src="images/basic-asl-thumb.png" alt="Basic ASL Sign">
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
                        <img src="images/stack-of-books.png" alt="Books Stack">
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