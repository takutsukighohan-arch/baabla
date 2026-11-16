<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/textbook_content_helpers.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$role = strtolower(trim((string)$_SESSION['role']));
if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
    header('Location: login.php');
    exit;
}

function textbookPageEscape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Only allow http(s) links for references
function textbookSafeUrl($url) {
    $url = trim((string)($url ?? ''));
    return preg_match('~\Ahttps?://~i', $url) === 1 ? $url : '';
}

// Removes an appended "Reference: <url>" from the description
function textbookCleanDescription($text) {
    return trim(preg_replace('/\s*Reference:\s*https?:\/\/\S+/i', '', (string)($text ?? '')));
}

// Builds a citation line from the reference columns
function textbookReferenceLine(array $b) {
    $parts = [];
    if (!empty($b['ref_author']))    $parts[] = $b['ref_author'];
    $parts[] = '"' . $b['title'] . '"';
    if (!empty($b['ref_publisher'])) $parts[] = $b['ref_publisher'];
    if (!empty($b['ref_year']))      $parts[] = $b['ref_year'];
    if (!empty($b['ref_isbn']))      $parts[] = 'ISBN ' . $b['ref_isbn'];
    return implode(', ', $parts) . '.';
}

$textbookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$textbookId || $textbookId < 1) {
    http_response_code(404);
    exit('Textbook not found.');
}

try {
    require_once __DIR__ . '/config/database.php';
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $stmt = $conn->prepare("
        SELECT id, title, description, category, file_path, created_by,
               approval_status, is_archived, sections, practice_method, practice_data,
               ref_author, ref_publisher, ref_year, ref_isbn, ref_url
        FROM public.textbooks
        WHERE id = :id
    ");
    $stmt->execute([':id' => $textbookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Unable to load textbook content: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load this textbook right now.');
}

if (!$book ||
    ($role === 'student' && ($book['approval_status'] !== 'approved' || textbookDatabaseBoolean($book['is_archived']))) ||
    ($role === 'teacher' && (int)$book['created_by'] !== (int)$_SESSION['user_id'])) {
    http_response_code(404);
    exit('Textbook not found.');
}

$sections = textbookJsonArray($book['sections'] ?? []);
$practiceData = textbookJsonArray($book['practice_data'] ?? []);
$readingSessionId = null;
$activityCsrf = '';
if ($role === 'student') {
    try {
        if (empty($_SESSION['textbook_activity_csrf'])) {
            $_SESSION['textbook_activity_csrf'] = bin2hex(random_bytes(32));
        }
        $activityCsrf = $_SESSION['textbook_activity_csrf'];
        $readingStmt = $conn->prepare("
            INSERT INTO public.textbook_reading_sessions (student_id, textbook_id)
            VALUES (:student_id, :textbook_id)
            RETURNING id
        ");
        $readingStmt->execute([
            ':student_id' => (int)$_SESSION['user_id'],
            ':textbook_id' => (int)$book['id']
        ]);
        $readingSessionId = (int)$readingStmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Unable to start student textbook reading session: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to start this reading session. Please try again.');
    }
}
$practiceMethod = in_array($book['practice_method'] ?? '', ['quiz', 'camera'], true)
    ? $book['practice_method']
    : 'quiz';
$questions = is_array($practiceData['questions'] ?? null) ? $practiceData['questions'] : [];
$targetSigns = is_array($practiceData['target_signs'] ?? null) ? $practiceData['target_signs'] : [];
$hasPdf = is_string($book['file_path'] ?? null) &&
    preg_match('/\Auploads\/textbooks\/[a-f0-9]{32}\.pdf\z/i', $book['file_path']);
$backHref = $role === 'teacher' ? 'teacher/textbooks.php' : ($role === 'admin' ? 'admin/lesson_management.php' : 'student/textbook.php');
$bookDescription = textbookCleanDescription($book['description'] ?? '');
$bookRefUrl = textbookSafeUrl($book['ref_url'] ?? '');
$hasBookReference = !empty($book['ref_author']) || $bookRefUrl !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= textbookPageEscape($book['title']); ?> | ASLify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --blue:#1735b5;
            --blue-deep:#102a9d;
            --blue-light:#d9edff;
            --ink:#263247;
            --muted:#68758a;
            --paper:#ffffff;
        }
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{
            margin:0;
            min-height:100vh;
            background:var(--blue);
            color:var(--ink);
            font-family:Poppins,Arial,sans-serif;
            padding:1px 0 48px;
        }
        .page{max-width:860px;margin:0 auto;padding:30px 28px 64px;display:grid;grid-template-columns:minmax(0,1fr);gap:0;align-items:stretch}
        .back,.close-textbook{grid-column:1/-1}
        header,.practice,.completion-control,.rating-overlay{grid-column:1/-1}
        .back,.close-textbook{
            display:inline-flex;align-items:center;gap:8px;
            color:#fff;font:600 13px Poppins,Arial,sans-serif;
            text-decoration:none;letter-spacing:.2px;
        }
        .back:hover,.close-textbook:hover{color:#cfe6ff;text-decoration:underline}
        .close-textbook{border:0;background:transparent;cursor:pointer;padding:0}
        header{
            position:relative;isolation:isolate;overflow:hidden;
            margin:22px 0 30px;padding:30px 34px 27px;
            border-radius:4px;background:transparent;color:#fff;
            text-align:center;box-shadow:none;
        }
        header:before,header:after{
            content:"";position:absolute;z-index:-1;border:1px solid #ffffff30;
            width:190px;height:190px;border-radius:50%;
        }
        header:before{left:-115px;top:-135px}
        header:after{right:-115px;bottom:-150px}
        header h1{margin:0 auto 10px;max-width:850px;font-size:clamp(26px,4vw,42px);line-height:1.18;font-weight:700;letter-spacing:-1.1px}
        header p{max-width:760px;margin:0 auto;color:#e4edff;font-size:14px;line-height:1.75}
        .meta{display:flex;justify-content:center;flex-wrap:wrap;gap:9px;margin-top:17px;font-size:11px}
        .pill{padding:6px 12px;border:1px solid #ffffff50;border-radius:2px;background:#ffffff16;color:#fff;letter-spacing:.3px}
        .header-reference{max-width:760px;margin:17px auto 0;padding-top:12px;border-top:1px solid #ffffff45;font-size:12px;line-height:1.7;overflow-wrap:anywhere;color:#e8efff}
        .header-reference a{color:#fff;font-weight:600;text-decoration:underline;text-underline-offset:3px}
        .pdf-link,.button{
            display:inline-flex;align-items:center;justify-content:center;gap:8px;
            margin-top:14px;padding:11px 17px;border:0;border-radius:3px;
            background:var(--blue);color:#fff;text-decoration:none;
            font:600 13px Poppins,Arial,sans-serif;cursor:pointer;
            transition:background .2s ease,transform .2s ease;
        }
        .pdf-link:hover,.button:hover{background:#254bd5;transform:translateY(-1px)}
        /* Each textbook section is styled like a printed magazine page. */
        .section,.practice{
            position:relative;isolation:isolate;overflow:hidden;
            background:var(--paper);border:1px solid #e8edf5;border-radius:2px;
            padding:30px 30px 34px;margin:0 0 20px;
            box-shadow:0 8px 22px #071b6940;
        }
        .section:before{
            content:"";position:absolute;z-index:-1;right:-54px;top:-78px;
            width:125px;height:125px;border:12px solid #dcefff;border-radius:50%;opacity:.9;
        }
        .section:after{
            content:"";position:absolute;z-index:-1;left:-74px;bottom:-104px;
            width:150px;height:150px;border:1px solid #d8eaff;border-radius:50%;
        }
        .section h2,.practice h2{
            margin:0 0 19px;padding-bottom:12px;border-bottom:1px solid #e7edf5;
            color:#1d315c;font-size:clamp(18px,2vw,22px);line-height:1.35;font-weight:600;
        }
        .section-content{font-size:13px;line-height:1.85;color:#485467;overflow-wrap:anywhere}
        .section-content p{margin:0 0 13px}
        .section-image{display:block;width:auto;max-width:100%;max-height:390px;margin:0 auto 22px;border:1px solid #d7e0eb;padding:4px;background:#fff;object-fit:contain}
        .section-references{list-style:none;margin:20px 0 0;padding:15px 0 0;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b}
        .section-references li{margin-bottom:11px;line-height:1.6;overflow-wrap:anywhere}
        .section-references .ref-title{color:#244db8;font-weight:600;text-decoration:none}
        .section-references .ref-title:hover{text-decoration:underline}
        .section-references .ref-source{display:block;color:#56836b;font-size:11px}
        .section-references .ref-author{display:block;color:#64748b}
        .practice{margin-top:26px;padding:28px 30px}
        .practice>p{color:#64748b;font-size:13px;line-height:1.7}
        .question{margin:0;border:0;border-top:1px solid #e5eaf2;padding:17px 0}
        .question:first-child{border-top:0}
        .question p{font-weight:600;font-size:14px;line-height:1.6;margin:0 0 10px;color:#263957}
        .answer{display:flex;align-items:flex-start;gap:9px;padding:8px 10px;margin:5px 0;border:1px solid #edf1f6;border-radius:3px;font-size:13px;line-height:1.55;cursor:pointer}
        .answer:hover{background:#f4f8ff;border-color:#cbdcf8}
        .answer input{margin-top:4px;accent-color:var(--blue)}
        .result{font-weight:700;margin-top:14px;color:#1d4caa;font-size:14px}
        .targets{display:flex;flex-wrap:wrap;gap:9px;margin:0 0 18px}
        .target{padding:8px 13px;border:1px solid #d7e7fb;border-radius:2px;background:#edf6ff;color:#2454b5;font-size:12px;font-weight:600}
        .camera-area{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(200px,1fr);gap:20px;align-items:start}
        video{width:100%;min-height:250px;background:#101b36;border:5px solid #edf3fb;border-radius:2px;object-fit:cover}
        .camera-note{font-size:12px;line-height:1.7;color:#68758a}
        .completion-control{margin-top:24px;padding:20px 24px;border:1px solid #ffffff45;background:#ffffff12;color:#fff}
        .completion-control p{font-size:13px;color:#e1eaff}
        .completion-control .button{background:#fff;color:#1735b5}
        #completionStatus{display:inline-block;margin-left:10px;font-size:12px;color:#e8f1ff}
        .rating-overlay{display:none;position:fixed;inset:0;z-index:20;background:#081747b8;padding:20px;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
        .rating-overlay.show{display:flex}
        .rating-dialog{width:min(100%,440px);padding:28px;border:1px solid #e8edf5;border-radius:4px;background:#fff;box-shadow:0 20px 60px #071b6960}
        .rating-dialog h2{margin:0 0 8px;font-size:21px;color:#1d315c}
        .rating-dialog p{color:#68758a;font-size:13px;line-height:1.7}
        .rating-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
        .rating-actions .button{margin-top:0}
        .rating-actions .button.secondary{background:#e8edf5;color:#334155}
        .rating-actions .button.secondary:hover{background:#dbe4f0}
        .rating-error{min-height:20px;color:#b91c1c;font-size:13px}
        @media(min-width:900px){
            .page{max-width:860px}
            main.page>header+article.section,
            main.page>article.section{display:block}
            .section{min-height:360px}
        }
        @media(max-width:650px){
            body{padding-bottom:24px}
            .page{padding:20px 14px 36px;grid-template-columns:minmax(0,1fr);gap:0}
            .back,.close-textbook,header,.practice,.completion-control,.rating-overlay{grid-column:1}
            header{margin:18px 0 22px;padding:25px 10px}
            header h1{font-size:27px}
            .section,.practice{padding:22px 19px;margin-bottom:15px}
            .section-content{font-size:12px;line-height:1.8}
            .section-image{max-height:300px}
            .camera-area{grid-template-columns:1fr}
            video{min-height:210px}
            .completion-control{padding:17px}
            .rating-dialog{padding:22px}
        }
    
        /* OPEN-BOOK LAYOUT: two paper pages side by side on desktop */
        @media (min-width: 900px){
            html body{
                padding-left:20px;
                padding-right:20px;
            }
            html body main.page{
                display:grid !important;
                grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
                align-items:stretch;
                column-gap:18px;
                row-gap:0;
                width:100%;
                max-width:1180px !important;
                padding-left:24px !important;
                padding-right:24px !important;
            }
            html body main.page > .back,
            html body main.page > .close-textbook,
            html body main.page > header,
            html body main.page > .practice,
            html body main.page > .completion-control,
            html body main.page > .rating-overlay{
                grid-column:1 / -1 !important;
            }
            html body main.page > article.section{
                grid-column:auto !important;
                width:100% !important;
                min-height:560px;
                margin:0 0 18px;
                padding:30px 28px 36px;
                box-shadow:0 10px 28px #071b6945;
            }
            /* Magazine-like text columns inside each paper page */
            html body main.page .section-content{
                display:block;
                column-count:2;
                column-gap:22px;
                column-rule:1px solid #e8edf5;
                column-fill:balance;
                font-size:12px;
                line-height:1.75;
            }
            html body main.page .section-content p{
                break-inside:avoid;
                page-break-inside:avoid;
            }
            html body main.page .section-image{
                max-width:100%;
                max-height:300px;
            }
            html body main.page .camera-area{
                display:grid !important;
                grid-template-columns:minmax(0,1.5fr) minmax(200px,1fr) !important;
            }
        }
        /* On smaller screens, show one readable page at a time */
        @media (max-width: 899px){
            html body main.page{
                display:grid !important;
                grid-template-columns:minmax(0,1fr) !important;
                width:100%;
                max-width:760px !important;
                padding-left:18px !important;
                padding-right:18px !important;
                gap:0;
            }
            html body main.page > *{
                grid-column:1 !important;
                width:100% !important;
                max-width:100% !important;
            }
            html body main.page .section-content{
                column-count:1 !important;
                column-width:auto !important;
            }
            html body main.page .camera-area{
                grid-template-columns:minmax(0,1fr) !important;
            }
        }

    </style>
</head>
<body>
<main class="page">
    <?php if ($role === 'student'): ?>
        <button class="close-textbook" type="button" id="closeTextbook">&larr; Close textbook</button>
    <?php else: ?>
        <a class="back" href="<?= textbookPageEscape($backHref); ?>">&larr; Back to textbooks</a>
    <?php endif; ?>
    <header>
        <h1><?= textbookPageEscape($book['title']); ?></h1>
        <p><?= textbookPageEscape($bookDescription ?: 'Explore the sections and practice activity in this textbook.'); ?></p>
        <div class="meta">
            <span class="pill"><?= textbookPageEscape($book['category'] ?: 'General'); ?></span>
            <span class="pill"><?= $practiceMethod === 'quiz' ? 'Interactive Quiz' : 'Camera Practice'; ?></span>
        </div>
        <?php if ($hasBookReference): ?>
            <div class="header-reference">
                <strong>Reference:</strong> <?= textbookPageEscape(textbookReferenceLine($book)); ?>
                <?php if ($bookRefUrl !== ''): ?>
                    <a href="<?= textbookPageEscape($bookRefUrl); ?>" target="_blank" rel="noopener noreferrer">View source</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($hasPdf): ?>
            <a class="pdf-link" href="textbook_file.php?id=<?= (int)$book['id']; ?>" target="_blank" rel="noopener">Open attached PDF</a>
        <?php endif; ?>
    </header>

    <?php if ($sections): ?>
        <?php foreach ($sections as $index => $section): ?>
            <?php
                $sectionTitle = trim((string)($section['title'] ?? ''));
                $sectionRefs = is_array($section['references'] ?? null) ? $section['references'] : [];
            ?>
            <article class="section">
                <h2><?= $sectionTitle !== '' ? textbookPageEscape($sectionTitle) : 'Section ' . ((int)$index + 1); ?></h2>
                <?php if (!empty($section['image'])): ?>
                    <img class="section-image"
                         src="textbook_image.php?id=<?= (int)$book['id']; ?>&amp;section=<?= (int)$index; ?>"
                         alt="<?= textbookPageEscape($book['title']); ?> section <?= (int)$index + 1; ?>">
                <?php endif; ?>
                <?php if (!empty($section['content'])): ?>
                    <div class="section-content"><?= textbookSafeHtml($section['content']); ?></div>
                <?php endif; ?>
                <?php if ($sectionRefs): ?>
                    <ul class="section-references" aria-label="References for this section">
                        <?php foreach ($sectionRefs as $ref): ?>
                            <?php
                                if (!is_array($ref)) continue;
                                $refUrl = textbookSafeUrl($ref['url'] ?? '');
                                $refTitle = (string)($ref['title'] ?? '');
                                if ($refTitle === '') continue;
                            ?>
                            <li>
                                <?php if ($refUrl !== ''): ?>
                                    <a class="ref-title" href="<?= textbookPageEscape($refUrl); ?>" target="_blank" rel="noopener noreferrer"><?= textbookPageEscape($refTitle); ?></a>
                                <?php else: ?>
                                    <span class="ref-title"><?= textbookPageEscape($refTitle); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ref['author'])): ?>
                                    <span class="ref-author"><?= textbookPageEscape($ref['author']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ref['source'])): ?>
                                    <span class="ref-source"><?= textbookPageEscape($ref['source']); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <article class="section">
            <h2>Textbook Content</h2>
            <p>This textbook does not have structured sections yet. Use the attached PDF if available.</p>
        </article>
    <?php endif; ?>

    <?php if ($practiceMethod === 'quiz'): ?>
        <section class="practice">
            <h2>Knowledge Test</h2>
            <?php if ($questions): ?>
                <form id="textbookQuiz">
                    <?php foreach ($questions as $questionIndex => $question): ?>
                        <?php $options = is_array($question['options'] ?? null) ? $question['options'] : []; ?>
                        <fieldset class="question" data-correct="<?= textbookPageEscape($question['correct_answer'] ?? ''); ?>">
                            <legend style="display:none;">Question <?= (int)$questionIndex + 1; ?></legend>
                            <p><?= (int)$questionIndex + 1; ?>. <?= textbookPageEscape($question['question'] ?? ''); ?></p>
                            <?php foreach (['a', 'b', 'c', 'd'] as $optionKey): ?>
                                <?php if (isset($options[$optionKey])): ?>
                                    <label class="answer">
                                        <input type="radio" name="question_<?= (int)$questionIndex; ?>" value="<?= textbookPageEscape($optionKey); ?>" required>
                                        <?= strtoupper($optionKey); ?>. <?= textbookPageEscape($options[$optionKey]); ?>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    <button class="button" type="submit">Check Answers</button>
                    <p class="result" id="quizResult" aria-live="polite"></p>
                </form>
            <?php else: ?>
                <p>No quiz questions have been added to this textbook yet.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="practice">
            <h2>Camera Sign Practice</h2>
            <?php if ($targetSigns): ?>
                <p>Practice the target signs listed below while viewing your camera.</p>
                <div class="targets">
                    <?php foreach ($targetSigns as $sign): ?>
                        <span class="target"><?= textbookPageEscape($sign); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No target signs have been added to this activity.</p>
            <?php endif; ?>
            <div class="camera-area">
                <video id="practiceVideo" autoplay muted playsinline></video>
                <div>
                    <button class="button" id="startCamera" type="button">Start Camera</button>
                    <button class="button" id="stopCamera" type="button" hidden>Stop Camera</button>
                    <p class="camera-note" id="cameraStatus" aria-live="polite">Allow camera access to practice. The camera preview does not automatically recognize or grade signs.</p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($role === 'student'): ?>
        <div class="completion-control">
            <p>Finished reading this textbook?</p>
            <button type="button" class="button" id="markCompleted">Mark as completed</button>
            <span id="completionStatus" role="status"></span>
        </div>
        <div class="rating-overlay" id="ratingOverlay" role="dialog" aria-modal="true" aria-labelledby="ratingTitle">
            <div class="rating-dialog">
                <h2 id="ratingTitle">Rate this textbook</h2>
                <p>Was “<?= textbookPageEscape($book['title']); ?>” good? Your feedback helps other students find useful books.</p>
                <div class="rating-actions">
                    <button class="button" type="button" data-rating="good">Yes, it was good</button>
                    <button class="button secondary" type="button" data-rating="not_good">No, not really</button>
                    <button class="button secondary" type="button" id="continueReading">Keep reading</button>
                </div>
                <p class="rating-error" id="ratingError" role="alert"></p>
            </div>
        </div>
    <?php endif; ?>
</main>
<script>
<?php if ($role === 'student'): ?>
const readingSessionId = <?= (int)$readingSessionId; ?>;
const activityCsrfToken = <?= json_encode($activityCsrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const activityEndpoint = 'student/textbook_activity.php';
const ratingOverlay = document.getElementById('ratingOverlay');
let closingForRating = false;
let completed = false;

async function submitActivity(data) {
    const body = new URLSearchParams({
        csrf_token: activityCsrfToken,
        session_id: String(readingSessionId),
        ...data
    });
    const response = await fetch(activityEndpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
        body: body.toString(),
        credentials: 'same-origin'
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'The update could not be saved.');
    return result;
}

function openRatingPrompt() {
    closingForRating = true;
    ratingOverlay.classList.add('show');
    document.querySelector('[data-rating="good"]').focus();
}

document.getElementById('closeTextbook').addEventListener('click', openRatingPrompt);
document.getElementById('continueReading').addEventListener('click', function() {
    closingForRating = false;
    ratingOverlay.classList.remove('show');
    document.getElementById('ratingError').textContent = '';
});

document.querySelectorAll('[data-rating]').forEach(function(button) {
    button.addEventListener('click', async function() {
        const errorMessage = document.getElementById('ratingError');
        errorMessage.textContent = '';
        document.querySelectorAll('[data-rating], #continueReading').forEach(function(action) {
            action.disabled = true;
        });
        try {
            await submitActivity({action: 'rate', rating: button.dataset.rating});
            window.location.href = <?= json_encode($backHref, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        } catch (error) {
            errorMessage.textContent = error.message;
            document.querySelectorAll('[data-rating], #continueReading').forEach(function(action) {
                action.disabled = false;
            });
        }
    });
});

document.getElementById('markCompleted').addEventListener('click', async function() {
    const button = this;
    const status = document.getElementById('completionStatus');
    button.disabled = true;
    status.textContent = 'Saving...';
    try {
        const result = await submitActivity({action: 'complete'});
        completed = true;
        button.textContent = 'Completed';
        status.textContent = result.message;
    } catch (error) {
        button.disabled = false;
        status.textContent = error.message;
    }
});

history.pushState({textbookReading: true}, '', location.href);
window.addEventListener('popstate', function() {
    if (!closingForRating) {
        history.pushState({textbookReading: true}, '', location.href);
        openRatingPrompt();
    }
});
<?php endif; ?>

const quizForm = document.getElementById('textbookQuiz');
if (quizForm) {
    quizForm.addEventListener('submit', function(event) {
        event.preventDefault();
        let score = 0;
        const questions = quizForm.querySelectorAll('.question');
        questions.forEach(function(question) {
            const selected = question.querySelector('input:checked');
            if (selected && selected.value === question.dataset.correct) score++;
        });
        document.getElementById('quizResult').textContent =
            'You scored ' + score + ' out of ' + questions.length + '.';
    });
}

const video = document.getElementById('practiceVideo');
if (video) {
    let cameraStream = null;
    const startButton = document.getElementById('startCamera');
    const stopButton = document.getElementById('stopCamera');
    const cameraStatus = document.getElementById('cameraStatus');
    startButton.addEventListener('click', async function() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraStatus.textContent = 'Camera access is not supported by this browser. Use HTTPS or localhost and try a supported browser.';
            return;
        }
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({video: true, audio: false});
            video.srcObject = cameraStream;
            startButton.hidden = true;
            stopButton.hidden = false;
            cameraStatus.textContent = 'Camera is on. Follow the target signs shown above to practice.';
        } catch (error) {
            cameraStatus.textContent = error.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access in your browser settings to practice.'
                : 'The camera could not be started. Check that it is connected and not being used by another app.';
        }
    });
    stopButton.addEventListener('click', stopCamera);
    function stopCamera() {
        if (cameraStream) cameraStream.getTracks().forEach(function(track) { track.stop(); });
        cameraStream = null;
        video.srcObject = null;
        startButton.hidden = false;
        stopButton.hidden = true;
        cameraStatus.textContent = 'Camera is off.';
    }
    window.addEventListener('pagehide', stopCamera);
}
</script>
</body>
</html>