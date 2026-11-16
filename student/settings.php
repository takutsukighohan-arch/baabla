<!DOCTYPE html>
<html lang="en">
<?php
  $today = new DateTime();
  $currentDate = $today->format('D, M j, Y');

  $user = [
    'full_name' => 'Channel Aleli Quiazon',
    'username' => 'channelaleli',
    'password' => 'password123',
    'created_at' => 'January 15, 2024',
    'email' => 'channelaleliquiazon@gmail.com',
    'member_since' => 'August 02, 2007',
    'teacher' => 'Mrs. Maria Santos'
  ];
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ASLify Dashboard - Settings</title>
  <!-- Google Fonts & Font Awesome Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="main.css">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background-color: #f3f6fb;
      color: #333;
      display: flex;
      min-height: 100vh;
      overflow-x: hidden;
    }


    


    /* ================= MAIN CONTENT ================= */
    .main-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    /* Header */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 32px;
    }

    .header-welcome h1 {
      font-size: 20px;
      font-weight: 600;
      color: #2b3674;
    }

    .header-welcome p {
      font-size: 12px;
      color: #a3edee;
      color: #909bb0;
    }

 

    .search-box input::placeholder {
      color: #a3aed0;
    }

    .search-box i {
      color: #2465b5;
      font-size: 14px;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .lang-selector {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 14px;
      font-weight: 600;
      color: #2b3674;
      cursor: pointer;
    }

    .flag-icon {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      object-fit: cover;
    }

    /* Content Area & Settings Card */
    .content-area {
      padding: 0 40px 40px 40px;
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      background: transparent;
    }

    .settings-card {
      width: min(100%, 1220px);
      background: rgba(241, 245, 249, 0.96);
      border-radius: 28px;
      overflow: hidden;
      box-shadow: 0 18px 45px rgba(0, 0, 0, 0.08);
      border: 1px solid rgba(172, 183, 201, 0.3);
    }

    .settings-banner {
      display: none;
    }

    .profile-section {
      padding: 0;
      position: relative;
    }

    .profile-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      background: linear-gradient(90deg, #1b6eb6 0%, #0d5ca4 100%);
      min-height: 130px;
      padding: 26px 34px;
    }

    .profile-main {
      display: flex;
      align-items: center;
      gap: 20px;
      flex: 1;
    }

    .avatar-wrap {
      position: relative;
      width: 78px;
      height: 78px;
      flex-shrink: 0;
    }

    .profile-avatar {
      width: 78px;
      height: 78px;
      border-radius: 50%;
      border: 3px solid #ffffff;
      object-fit: cover;
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
      display: block;
      background-color: #dce9f7;
    }

    .verified-badge {
      position: absolute;
      right: 2px;
      bottom: 4px;
      width: 22px;
      height: 22px;
      background: #2acf78;
      border: 3px solid #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      font-size: 11px;
      box-shadow: 0 4px 10px rgba(42, 207, 120, 0.28);
    }

    .profile-info h2 {
      font-size: 32px;
      font-weight: 600;
      color: #ffffff;
      line-height: 1.2;
      margin: 0;
      letter-spacing: -0.04em;
    }

    .profile-info p {
      font-size: 14px;
      color: rgba(255,255,255,0.9);
      margin-top: 2px;
      font-weight: 500;
    }

    .profile-edit-button {
      border: 1px solid rgba(255,255,255,0.7);
      background: rgba(231, 239, 249, 0.14);
      color: #ffffff;
      border-radius: 12px;
      padding: 11px 18px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      white-space: nowrap;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.2);
    }

    .profile-edit-button i {
      margin-right: 8px;
      font-size: 13px;
    }

    .profile-edit-button:hover {
      background: rgba(255,255,255,0.18);
    }

    .settings-panels {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 28px 26px;
      background: #edf2f7;
      padding: 22px 24px 24px;
    }

    .info-card {
      background: rgba(255,255,255,0.7);
      border: 1px solid #e7edf5;
      border-radius: 18px;
      padding: 22px 26px 18px;
    }

    .info-card h3 {
      font-size: 26px;
      font-weight: 600;
      color: #1d2436;
      margin-bottom: 22px;
      letter-spacing: -0.03em;
    }

    .info-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding: 10px 0;
      font-size: 17px;
      color: #1d2436;
    }

    .info-row span {
      display: inline-block;
      min-width: 170px;
      font-weight: 500;
      color: #1d2436;
    }

    .info-row strong {
      font-weight: 500;
      color: #1d2436;
      text-align: right;
      flex: 1;
    }

    .teacher-pill {
      background: #edf1f5;
      border-radius: 8px;
      padding: 6px 12px;
      display: inline-block;
      color: #2d3a52;
      font-weight: 500;
    }

    .verified-text {
      color: #2cb66b;
      font-weight: 600;
    }

    .password-card {
      grid-column: 1 / -1;
      margin-top: 0;
    }

    .password-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      padding-top: 10px;
    }

    .password-row span {
      min-width: 170px;
      font-weight: 500;
      color: #1d2436;
      font-size: 17px;
    }

    .password-mask {
      font-size: 18px;
      letter-spacing: 3px;
      color: #1d2436;
      flex: 1;
      text-align: left;
      white-space: nowrap;
    }

    .change-password-btn {
      border: 1px solid rgba(61, 117, 180, 0.8);
      background: transparent;
      color: #2f6eb6;
      border-radius: 12px;
      padding: 11px 22px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      min-width: 170px;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.2);
    }

    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(20, 23, 32, 0.35);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 999;
      padding: 20px;
    }

    .modal-backdrop.open {
      display: flex;
    }

    .modal {
      width: min(100%, 520px);
      background-color: #ffffff;
      border-radius: 20px;
      padding: 24px 24px 18px;
      box-shadow: 0 22px 60px rgba(16, 24, 40, 0.18);
      position: relative;
    }

    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }

    .modal-title {
      font-size: 18px;
      font-weight: 700;
      color: #1b2559;
    }

    .close-modal {
      border: none;
      background: transparent;
      color: #7783a1;
      font-size: 30px;
      line-height: 1;
      cursor: pointer;
      padding: 0;
    }

    .modal-question {
      font-size: 15px;
      color: #2b3674;
      margin-bottom: 18px;
      line-height: 1.5;
    }

    .form-field {
      margin-bottom: 18px;
    }

    .form-field label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #2b3674;
      margin-bottom: 8px;
    }

    .form-field select,
    .form-field textarea {
      width: 100%;
      border: 1px solid #dfe7f3;
      border-radius: 12px;
      background-color: #f8fafc;
      color: #1b2559;
      font-size: 14px;
      padding: 12px 14px;
      outline: none;
      font-family: 'Poppins', sans-serif;
    }

    .form-field textarea {
      min-height: 120px;
      resize: vertical;
    }

    .submit-report-btn {
      width: 100%;
      border: none;
      background: linear-gradient(90deg, #2465b5, #2d74c9);
      color: #ffffff;
      border-radius: 12px;
      padding: 13px 18px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 8px;
    }

    .success-modal .modal-title {
      margin-bottom: 10px;
    }

    .success-modal .modal-question {
      margin-bottom: 0;
      font-size: 16px;
      text-align: center;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px 60px;
      max-width: 650px;
    }

    .form-group label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #2b3674;
      margin-bottom: 8px;
    }

    .form-group input {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #dfe7f3;
      border-radius: 12px;
      background-color: #f8fafc;
      color: #1b2559;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-group input[readonly] {
      background-color: #f3f6fb;
      color: #49577d;
      cursor: default;
    }

    .form-group input:focus {
      border-color: #2465b5;
      box-shadow: 0 0 0 3px rgba(36, 101, 181, 0.08);
    }

    .form-group input::placeholder {
      color: #a3aed0;
    }

    .form-group.full-width {
      grid-column: 1 / -1;
    }

    .verification-status {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-top: 10px;
    }

    .verification-option {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #1b2559;
      font-weight: 500;
    }

    .verification-option input[type="radio"] {
      width: 16px;
      height: 16px;
      accent-color: #2eb872;
      margin: 0;
      cursor: default;
    }

    .password-field {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .password-field input {
      flex: 1;
    }

    .edit-password-btn {
      border: none;
      background-color: #eaf4ff;
      color: #2465b5;
      font-weight: 600;
      padding: 10px 14px;
      border-radius: 10px;
      cursor: pointer;
      transition: background-color 0.2s ease;
    }

    .edit-password-btn:hover {
      background-color: #dfeeff;
    }

    .status-item {
      font-size: 13px;
      font-weight: 500;
      color: #1b2559;
    }

    .member-date {
      font-size: 12px;
      color: #a3aed0;
      margin-top: 4px;
    }

    /* ================= RIGHT PANEL ================= */
    .right-panel {
      width: 320px;
      background-color: #b5d5e7;
      padding: 24px 20px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .user-profile-header {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      margin-bottom: 10px;
    }

    .notification-btn {
      width: 36px;
      height: 36px;
      background-color: #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #2465b5;
      position: relative;
      cursor: pointer;
    }

    .notification-btn::after {
      content: '';
      position: absolute;
      top: 8px;
      right: 9px;
      width: 6px;
      height: 6px;
      background-color: #ff4d4f;
      border-radius: 50%;
    }

    .account-badge {
      background-color: #ffffff;
      padding: 6px 14px;
      border-radius: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      font-weight: 500;
      color: #2b3674;
    }

    .account-badge img {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      object-fit: cover;
    }

    /* Cards Inside Right Panel */
    .panel-card {
      background-color: #ffffff;
      border-radius: 16px;
      padding: 16px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .panel-card-title {
      font-size: 14px;
      font-weight: 600;
      color: #2b3674;
      margin-bottom: 14px;
    }

    /* Learning Progress */
    .progress-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
    }

    .circular-progress {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      background: conic-gradient(#2465b5 40%, #e6eff9 0);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .circular-progress::before {
      content: '';
      width: 54px;
      height: 54px;
      border-radius: 50%;
      background-color: #ffffff;
    }

    .progress-value {
      position: absolute;
      font-size: 13px;
      font-weight: 700;
      color: #2465b5;
    }

    .progress-stats h3 {
      font-size: 18px;
      font-weight: 700;
      color: #1b2559;
      text-align: right;
    }

    .progress-stats p {
      font-size: 11px;
      color: #a3aed0;
      text-align: right;
    }

    .divider {
      height: 1px;
      background-color: #f0f4f9;
      margin: 12px 0;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .stat-item .num {
      font-size: 15px;
      font-weight: 700;
    }

    .stat-item .num.blue { color: #2465b5; }
    .stat-item .num.orange { color: #ff9f43; }
    .stat-item .num.red { color: #ff5252; }

    .stat-item .label {
      font-size: 10px;
      color: #a3aed0;
    }

    /* Textbooks List */
    .textbook-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .textbook-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background-color: #ffffff;
      padding: 10px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }

    .tb-info {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .tb-img {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      background-color: #fff0ed;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .tb-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .tb-text h4 {
      font-size: 12px;
      font-weight: 600;
      color: #2b3674;
    }

    .tb-text p {
      font-size: 10px;
      color: #a3aed0;
    }

    .tb-percentage {
      font-size: 10px;
      color: #a3aed0;
      font-weight: 500;
    }

    /* Daily Work */
    .daily-work-title {
      font-size: 14px;
      font-weight: 600;
      color: #2b3674;
      margin-top: 4px;
      margin-bottom: 10px;
    }

    .daily-card {
      background-color: #ffffff;
      border-radius: 16px;
      padding: 14px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .daily-card h4 {
      font-size: 12px;
      font-weight: 600;
      color: #2b3674;
      margin-bottom: 8px;
    }

    .daily-card img {
      width: 45px;
      height: auto;
    }

    .small-progress {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: conic-gradient(#2465b5 60%, #e6eff9 0);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .small-progress::before {
      content: '';
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background-color: #ffffff;
    }

    .small-progress span {
      position: absolute;
      font-size: 10px;
      font-weight: 700;
      color: #2465b5;
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const openEditModalBtn = document.getElementById('openEditModal');
      const editReportModal = document.getElementById('editReportModal');
      const thankYouModal = document.getElementById('thankYouModal');
      const changeRequestForm = document.getElementById('changeRequestForm');

      if (openEditModalBtn && editReportModal) {
        openEditModalBtn.addEventListener('click', function () {
          editReportModal.classList.add('open');
        });
      }

      document.querySelectorAll('[data-close]').forEach(function (button) {
        button.addEventListener('click', function () {
          const targetId = button.getAttribute('data-close');
          const targetModal = document.getElementById(targetId);
          if (targetModal) {
            targetModal.classList.remove('open');
          }
        });
      });

      if (changeRequestForm) {
        changeRequestForm.addEventListener('submit', function (event) {
          event.preventDefault();
          editReportModal.classList.remove('open');
          thankYouModal.classList.add('open');
          changeRequestForm.reset();
        });
      }

      window.addEventListener('click', function (event) {
        if (event.target && event.target.classList.contains('modal-backdrop')) {
          event.target.classList.remove('open');
        }
      });
    });
  </script>
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
                <li class="nav-item">
                    <a href="assignment.php"><i class="fa-regular fa-file-lines"></i> Assignment</a>
                </li>
                <li class="nav-item">
                    <a href="practice.php"><i class="fa-solid fa-bars-progress"></i> Practice</a>
                </li>
                <li class="nav-item active">
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

  <!-- MAIN CONTENT AREA -->
  <div class="main-wrapper">
    <!-- Top Header -->
    <header class="header">
       <div class="search-box">
                    <input type="text" placeholder="Search your textbook...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>

    </header>

    <!-- Content / Settings Card -->
    <main class="content-area">
      <div class="settings-card">
        <div class="profile-section">
          <div class="profile-header">
            <div class="profile-main">
              <div class="avatar-wrap">
                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Channel Aleli Quiazon" class="profile-avatar">
                <span class="verified-badge"><i class="fa-solid fa-check"></i></span>
              </div>
              <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
              </div>
            </div>
            <button type="button" class="profile-edit-button" id="openEditModal"><i class="fa-regular fa-pen-to-square"></i> Request Info Edit</button>
          </div>

          <div class="settings-panels">
            <div class="info-card">
              <h3>Personal Details</h3>

              <div class="info-row">
                <span>Full Name:</span>
                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
              </div>

              <div class="info-row">
                <span>Username:</span>
                <strong>@<?php echo htmlspecialchars($user['username']); ?></strong>
              </div>

              <div class="info-row">
                <span>Assigned Teacher:</span>
                <strong><span class="teacher-pill"><?php echo htmlspecialchars($user['teacher']); ?></span></strong>
              </div>
            </div>

            <div class="info-card">
              <h3>Account Metadata</h3>

              <div class="info-row">
                <span>Verification Status:</span>
                <strong class="verified-text">Verified Member</strong>
              </div>

              <div class="info-row">
                <span>Account Created:</span>
                <strong><?php echo htmlspecialchars($user['created_at']); ?></strong>
              </div>

              <div class="info-row">
                <span>Member Since:</span>
                <strong><?php echo htmlspecialchars($user['member_since']); ?></strong>
              </div>
            </div>

            <div class="info-card password-card">
              <h3>Security &amp; Password</h3>

              <div class="password-row">
                <span>Password:</span>
                <div class="password-mask">••••••••••••••</div>
                <button type="button" class="change-password-btn">Change Password</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal-backdrop" id="editReportModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
      <div class="modal-header">
        <h3 class="modal-title" id="editModalTitle">Edit Request</h3>
        <button type="button" class="close-modal" aria-label="Close" data-close="editReportModal">&times;</button>
      </div>

      <div class="modal-question">Do you request to file a report for changing or your info?</div>

      <form id="changeRequestForm">
        <div class="form-field">
          <label for="changeType">What would you like to change?</label>
          <select id="changeType" name="changeType">
            <option value="Full Name">Full Name</option>
            <option value="Username">Username</option>
            <option value="Email">Email</option>
            <option value="Teacher">Teacher</option>
          </select>
        </div>

        <div class="form-field">
          <label for="reason">Reason of Changing:</label>
          <textarea id="reason" name="reason" placeholder="Please describe the reason..."></textarea>
        </div>

        <button type="submit" class="submit-report-btn">Submit Report</button>
      </form>
    </div>
  </div>

  <div class="modal-backdrop success-modal" id="thankYouModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="thankYouTitle">
      <div class="modal-header">
        <h3 class="modal-title" id="thankYouTitle">Thank You</h3>
        <button type="button" class="close-modal" aria-label="Close" data-close="thankYouModal">&times;</button>
      </div>
      <div class="modal-question">Thank you for contacting us. We will contact you as soon as possible for more further requirements.</div>
    </div>
  </div>

 

</body>
</html>