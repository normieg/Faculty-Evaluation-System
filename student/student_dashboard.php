<?php
require __DIR__ . '/../database.php';

// Ensure student area uses its own session namespace and start session before accessing $_SESSION
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('FES_STUDENT');
    session_start();
}

if (!defined('ANON_SALT')) {
    define('ANON_SALT', 'change-this-to-a-long-random-secret');
}

// --- Require login ---
if (!isset($_SESSION['student_id'])) {
    header("Location: " . basename(__DIR__) . "/login.php");
    exit;
}

// Basic student context
$program_id = (int)($_SESSION['program_id'] ?? 0);
$year_level = (int)($_SESSION['year_level'] ?? 0);
$section_id = isset($_SESSION['section_id']) ? (int)$_SESSION['section_id'] : 0;
$school_id  = $_SESSION['school_id'] ?? '';

// --- Get program code ---
$program_code = '';
$progRes = mysqli_query($conn, "SELECT code FROM programs WHERE id='$program_id' LIMIT 1");
if ($progRes && mysqli_num_rows($progRes) > 0) {
    $program_code = mysqli_fetch_assoc($progRes)['code'];
}

// --- Active term ---
$term_id = 0;
$term_label = "No Active Term";
$termRes = mysqli_query($conn, "SELECT id, label FROM terms WHERE is_active=1 LIMIT 1");
if ($termRes && mysqli_num_rows($termRes) > 0) {
    $term = mysqli_fetch_assoc($termRes);
    $term_id = (int)$term['id'];
    $term_label = $term['label'];
}

// Rater hash for “once per term per faculty”
$rater_hash = ($term_id > 0 && $school_id !== '')
    ? hash('sha256', $school_id . '|' . $term_id . '|' . ANON_SALT)
    : '';

// --- Faculty list, filtered by program + year + (optional) section ---
$faculty = [];

$pid = $program_id;
$yl  = $year_level;
$sec = $section_id;

if ($sec > 0) {
    $sectionClause = "
        (
            NOT EXISTS (
                SELECT 1 FROM faculty_section_assignments s
                WHERE s.faculty_id = f.id
                  AND s.program_id = {$pid}
                  AND s.year_level = {$yl}
            )
            OR EXISTS (
                SELECT 1 FROM faculty_section_assignments s2
                WHERE s2.faculty_id = f.id
                  AND s2.program_id = {$pid}
                  AND s2.year_level = {$yl}
                  AND s2.section_id = {$sec}
            )
        )
    ";
} else {
    $sectionClause = "
        NOT EXISTS (
            SELECT 1 FROM faculty_section_assignments s
            WHERE s.faculty_id = f.id
              AND s.program_id = {$pid}
              AND s.year_level = {$yl}
        )
    ";
}

$q = "
SELECT DISTINCT f.id, f.first_name, f.middle_name, f.last_name, f.suffix, f.photo_url
FROM faculty f
JOIN faculty_programs fp
    ON fp.faculty_id = f.id
 AND fp.program_id = {$pid}
LEFT JOIN faculty_program_years fpy
    ON fpy.faculty_id = f.id
 AND fpy.program_id = {$pid}
WHERE f.is_active = 1
    AND (
                fpy.year_level IS NULL
                OR fpy.year_level = {$yl}
            )
    AND {$sectionClause}
ORDER BY f.last_name, f.first_name, f.middle_name
";
$res = mysqli_query($conn, $q);
while ($row = mysqli_fetch_assoc($res)) {
    // Build display name from parts
    $first = trim($row['first_name'] ?? '');
    $middle = trim($row['middle_name'] ?? '');
    $last = trim($row['last_name'] ?? '');
    $suffix = trim($row['suffix'] ?? '');
    $parts = array_filter([$first, $middle, $last], fn($x) => $x !== '');
    $display = trim(implode(' ', $parts));
    if ($suffix !== '') $display .= ' ' . $suffix;
    $row['display_name'] = $display ?: '(No name)';
    $faculty[] = $row;
}

function already_evaluated($conn, $fid, $term_id, $rater_hash)
{
    if ($term_id <= 0 || $rater_hash === '') return false;
    $fid = (int)$fid;
    $sql = "SELECT id FROM evaluations
            WHERE faculty_id='$fid' AND term_id='$term_id' AND rater_hash='$rater_hash'
            LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="min-h-screen bg-gray-100">
    <!-- Top bar (kept) -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="../assets/images/moist_logo.png" class="h-10 w-12 object-contain" alt="Logo">
                <div>
                    <h1 class="text-lg font-semibold text-red-600 leading-tight">MOIST</h1>
                    <p class="text-xs font-medium text-red-600">Faculty Evaluation</p>
                </div>
            </div>

            <a href="../logout_student.php"
                class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">
                <i class='bx bx-log-out'></i> Logout
            </a>
        </div>
        <div class="h-1 bg-red-600"></div>
    </header>

    <!-- Page -->
    <main class="max-w-5xl mx-auto p-4">
        <!-- Heading + summary -->
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                <i class='bx bxs-dashboard text-red-600'></i> Student Dashboard
            </h2>
        </div>

        <!-- Info cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">Active Term</p>
                <p class="mt-1 font-semibold text-gray-800">
                    <?= htmlspecialchars($term_label) ?>
                </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">Program Code</p>
                <p class="mt-1 font-semibold text-gray-800">
                    <?= htmlspecialchars($program_code ?: '—') ?>
                </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <p class="text-sm text-gray-500">Year Level<?= $section_id ? ' / Section' : '' ?></p>
                <p class="mt-1 font-semibold text-gray-800">
                    <?= $year_level ?><?= $section_id ? ' / ' . htmlspecialchars((string)$section_id) : '' ?>
                </p>
            </div>
        </div>

        <!-- Faculty cards -->
        <div class="bg-white border border-gray-200 rounded-xl">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center gap-2">
                <i class='bx bxs-user-voice text-red-600'></i>
                <h3 class="font-semibold text-gray-800">Faculty you can evaluate</h3>
            </div>

            <?php if (empty($faculty)): ?>
                <div class="p-6 text-center text-sm text-gray-600">
                    No eligible faculty at the moment for your program/year<?= $section_id ? '/section' : '' ?>.
                </div>
            <?php else: ?>
                <div class="p-4">
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($faculty as $f):
                            $fid  = (int)$f['id'];
                            $done = already_evaluated($conn, $fid, $term_id, $rater_hash);

                            $photoRel = '';
                            if (!empty($f['photo_url']) && file_exists(dirname(__DIR__) . "/storage/faculty_profiles/" . $f['photo_url'])) {
                                $photoRel = "../storage/faculty_profiles/" . $f['photo_url'];
                            } else {
                                $photoRel = "../assets/images/moist_logo.png";
                            }
                        ?>
                            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 flex flex-col items-center text-center">
                                <img src="<?= htmlspecialchars($photoRel) ?>" alt="Faculty Photo"
                                    class="h-28 w-28 rounded-full object-cover border mb-3">
                                <p class="font-semibold text-gray-800 text-sm mb-3 leading-snug px-2">
                                    <?= htmlspecialchars($f['display_name']) ?>
                                </p>

                                <?php if ($done): ?>
                                    <span class="inline-flex items-center gap-1 bg-green-600 text-white text-xs font-medium px-3 py-2 rounded">
                                        <i class='bx bxs-check-circle'></i> Evaluated
                                    </span>
                                <?php elseif ($term_id > 0): ?>
                                    <a href="evaluate.php?fid=<?= $fid ?>"
                                        class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">
                                        Start Evaluation
                                    </a>
                                <?php else: ?>
                                    <span class="w-full bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded">
                                        No Active Term
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="../assets/js/form-submit.js"></script>
</body>

</html>