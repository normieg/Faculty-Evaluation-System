<?php
require __DIR__ . '/../database.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

/* -------------- tiny helpers (traditional) -------------- */
function one_int($conn, $sql)
{
    $r = mysqli_query($conn, $sql);
    if ($r && ($row = mysqli_fetch_row($r))) return (int)$row[0];
    return 0;
}
function esc($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* -------------- Active term (id + label) -------------- */
$term_id = 0;
$term_label = 'None';
$tr = mysqli_query($conn, "SELECT id, label FROM terms WHERE is_active=1 LIMIT 1");
if ($tr && mysqli_num_rows($tr)) {
    $trow = mysqli_fetch_assoc($tr);
    $term_id = (int)$trow['id'];
    $term_label = $trow['label'];
}

/* -------------- Stats -------------- */
$total_faculty     = one_int($conn, "SELECT COUNT(*) FROM faculty");
$active_faculty    = one_int($conn, "SELECT COUNT(*) FROM faculty WHERE is_active=1");
$inactive_faculty  = one_int($conn, "SELECT COUNT(*) FROM faculty WHERE is_active=0");

$active_programs   = one_int($conn, "SELECT COUNT(*) FROM programs WHERE is_active=1");
$total_sections    = one_int($conn, "SELECT COUNT(*) FROM sections");

$active_students   = one_int($conn, "SELECT COUNT(*) FROM students WHERE is_active=1");

$evals_this_term   = $term_id ? one_int($conn, "SELECT COUNT(*) FROM evaluations WHERE term_id={$term_id}") : 0;
$evals_today       = $term_id ? one_int($conn, "SELECT COUNT(*) FROM evaluations WHERE term_id={$term_id} AND DATE(submitted_at)=CURDATE()") : 0;

/* -------------- Program snapshot (assigned faculty & sections) -------------- */
$program_rows = [];
$pr = mysqli_query($conn, "SELECT id, name FROM programs WHERE is_active=1 ORDER BY name");
while ($pr && ($row = mysqli_fetch_assoc($pr))) {
    $pid = (int)$row['id'];
    $assigned = one_int($conn, "SELECT COUNT(DISTINCT faculty_id) FROM faculty_programs WHERE program_id={$pid}");
    $sections = one_int($conn, "SELECT COUNT(*) FROM sections WHERE program_id={$pid}");
    $program_rows[] = ['name' => $row['name'], 'assigned' => $assigned, 'sections' => $sections];
}

/* -------------- Needs attention -------------- */
$no_photo = [];
$np = mysqli_query($conn, "
    SELECT id, full_name
    FROM faculty
    WHERE photo_url IS NULL OR photo_url=''
    ORDER BY full_name
    LIMIT 10
");
while ($np && ($r = mysqli_fetch_assoc($np))) $no_photo[] = $r;

$no_assign = [];
$na = mysqli_query($conn, "
    SELECT f.id, f.full_name
    FROM faculty f
    LEFT JOIN faculty_programs fp ON fp.faculty_id=f.id
    WHERE fp.faculty_id IS NULL
    ORDER BY f.full_name
    LIMIT 10
");
while ($na && ($r = mysqli_fetch_assoc($na))) $no_assign[] = $r;

/* -------------- Top faculty this term (simple avg score) -------------- */
$top_faculty = [];
if ($term_id) {
    $tf = mysqli_query($conn, "
        SELECT f.id, f.full_name,
               ROUND(AVG(es.score), 2) AS avg_score,
               COUNT(DISTINCT e.id) AS num_evals
        FROM evaluations e
        JOIN evaluation_scores es ON es.evaluation_id = e.id
        JOIN faculty f ON f.id = e.faculty_id
        WHERE e.term_id = {$term_id}
        GROUP BY f.id, f.full_name
        HAVING num_evals > 0
        ORDER BY avg_score DESC, num_evals DESC, f.full_name ASC
        LIMIT 5
    ");
    while ($tf && ($r = mysqli_fetch_assoc($tf))) $top_faculty[] = $r;
}

/* -------------- Recent feedback (latest 8 submissions with any comment) -------------- */
$recent_feedback = [];
if ($term_id) {
    $rf = mysqli_query($conn, "
        SELECT e.id, f.full_name, e.submitted_at,
               NULLIF(TRIM(e.like_because),'') AS like_because,
               NULLIF(TRIM(e.dislike_because),'') AS dislike_because,
               NULLIF(TRIM(e.suggest_will),'') AS suggest_will
        FROM evaluations e
        JOIN faculty f ON f.id = e.faculty_id
        WHERE e.term_id = {$term_id}
          AND (e.like_because <> '' OR e.dislike_because <> '' OR e.suggest_will <> '')
        ORDER BY e.submitted_at DESC
        LIMIT 8
    ");
    while ($rf && ($r = mysqli_fetch_assoc($rf))) $recent_feedback[] = $r;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

    <?php $active = 'dashboard';
    include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- ✅ Mobile / Tablet Top Bar -->
    <header class="fixed top-0 left-0 right-0 z-30 bg-white border-b border-gray-200 lg:hidden">
        <div class="flex items-center gap-3 px-3 py-2">
            <button type="button"
                class="inline-flex items-center justify-center p-2 rounded hover:bg-gray-100"
                aria-label="Open menu"
                onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <i class='bx bx-menu text-2xl'></i>
            </button>

            <div class="flex items-center gap-2">
                <i class='bx bxs-dashboard text-red-600 text-2xl'></i>
                <h1 class="text-lg font-semibold text-gray-800">Admin Dashboard</h1>
            </div>
        </div>
    </header>

    <!-- ✅ Desktop Header -->
    <header class="hidden lg:flex fixed top-0 left-64 right-0 z-20 bg-white border-b border-gray-200 px-6 py-4 items-center justify-between">
        <div class="flex items-center gap-2">
            <i class='bx bxs-dashboard text-red-600 text-2xl'></i>
            <h1 class="text-xl font-semibold text-gray-800">Admin Dashboard</h1>
        </div>

        <p class="text-sm text-gray-600">
            Logged in as:
            <span class="font-semibold text-gray-800">
                <?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
            </span>
        </p>
    </header>
    
    <!-- Main content -->
    <main class="lg:ml-64 p-4 pt-16">
        <div class="p-4 max-w-7xl mx-auto">

            <!-- Active term -->
            <div class="bg-white border border-gray-300 p-4 rounded mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Active Term</p>
                        <p class="mt-1 text-lg font-semibold text-gray-800">
                            <?= esc($term_label) ?>
                        </p>
                    </div>
                    <a href="admin_terms.php"
                        class="inline-flex items-center gap-2 text-sm bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded">
                        <i class='bx bx-calendar-event text-base'></i>
                        Manage Terms
                    </a>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <div class="bg-white border border-gray-300 p-4 rounded">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Total Faculty</h3>
                        <i class='bx bxs-user-detail text-red-600 text-2xl'></i>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900"><?= $total_faculty ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?= $active_faculty ?> active / <?= $inactive_faculty ?> inactive</p>
                </div>

                <div class="bg-white border border-gray-300 p-4 rounded">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Active Programs</h3>
                        <i class='bx bxs-graduation text-red-600 text-2xl'></i>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900"><?= $active_programs ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?= $total_sections ?> sections total</p>
                </div>

                <div class="bg-white border border-gray-300 p-4 rounded">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Active Students</h3>
                        <i class='bx bx-user-check text-red-600 text-2xl'></i>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900"><?= $active_students ?></p>
                    <p class="text-xs text-gray-500 mt-1">With enabled accounts</p>
                </div>

                <div class="bg-white border border-gray-300 p-4 rounded">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Evaluations (This Term)</h3>
                        <i class='bx bx-bar-chart text-red-600 text-2xl'></i>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900"><?= $evals_this_term ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?= $evals_today ?> submitted today</p>
                </div>
            </div>

            <!-- Quick links -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <a href="admin_faculty.php" class="bg-white border border-gray-300 p-4 rounded block hover:bg-gray-50">
                    <div class="flex items-center gap-2 mb-1">
                        <i class='bx bxs-user-voice text-red-600 text-2xl'></i>
                        <h3 class="font-semibold text-gray-800">Faculty & Programs</h3>
                    </div>
                    <p class="text-sm text-gray-600">Manage faculty and assignments.</p>
                </a>

                <a href="admin_terms.php" class="bg-white border border-gray-300 p-4 rounded block hover:bg-gray-50">
                    <div class="flex items-center gap-2 mb-1">
                        <i class='bx bx-calendar-event text-red-600 text-2xl'></i>
                        <h3 class="font-semibold text-gray-800">Set Active Term</h3>
                    </div>
                    <p class="text-sm text-gray-600">Manage the current term.</p>
                </a>

                <a href="admin_results.php" class="bg-white border border-gray-300 p-4 rounded block hover:bg-gray-50">
                    <div class="flex items-center gap-2 mb-1">
                        <i class='bx bx-bar-chart text-red-600 text-2xl'></i>
                        <h3 class="font-semibold text-gray-800">Evaluation Results</h3>
                    </div>
                    <p class="text-sm text-gray-600">View results for the active term.</p>
                </a>
            </div>

            <!-- 2-column rows -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">

                <!-- Top Faculty (avg score) -->
                <div class="bg-white border border-gray-300 p-4 rounded">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                            <i class='bx bxs-trophy text-red-600 text-xl'></i> Top Faculty (This Term)
                        </h3>
                        <a href="admin_results.php" class="text-sm text-red-600 hover:underline">View Results</a>
                    </div>

                    <?php if (empty($top_faculty)): ?>
                        <p class="text-sm text-gray-500">No evaluation scores yet.</p>
                    <?php else: ?>
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-600 border-b">
                                    <th class="py-2 pr-3">Faculty</th>
                                    <th class="py-2 pr-3">Avg Score</th>
                                    <th class="py-2"># Evals</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-800">
                                <?php foreach ($top_faculty as $tf): ?>
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 pr-3"><?= esc($tf['full_name']) ?></td>
                                        <td class="py-2 pr-3"><?= esc($tf['avg_score']) ?></td>
                                        <td class="py-2"><?= (int)$tf['num_evals'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Programs Snapshot -->
                <div class="bg-white border border-gray-300 p-4 rounded">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                            <i class='bx bx-collection text-red-600 text-xl'></i> Programs Snapshot
                        </h3>
                        <a href="admin_faculty.php" class="text-sm text-red-600 hover:underline">Manage Assignments</a>
                    </div>

                    <?php if (empty($program_rows)): ?>
                        <p class="text-sm text-gray-500">No active programs.</p>
                    <?php else: ?>
                        <div class="overflow-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-600 border-b">
                                        <th class="py-2 pr-3">Program</th>
                                        <th class="py-2 pr-3">Assigned Faculty</th>
                                        <th class="py-2">Sections</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-800">
                                    <?php foreach ($program_rows as $p): ?>
                                        <tr class="border-b last:border-0">
                                            <td class="py-2 pr-3"><?= esc($p['name']) ?></td>
                                            <td class="py-2 pr-3"><?= (int)$p['assigned'] ?></td>
                                            <td class="py-2"><?= (int)$p['sections'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Needs Attention + Recent Feedback -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

                <!-- Needs Attention -->
                <div class="bg-white border border-gray-300 p-4 rounded">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                            <i class='bx bx-error-circle text-red-600 text-xl'></i> Needs Attention
                        </h3>
                        <a href="admin_faculty.php" class="text-sm text-red-600 hover:underline">Go to Faculty</a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">No Photo</p>
                            <?php if (empty($no_photo)): ?>
                                <p class="text-sm text-gray-500">All set ✅</p>
                            <?php else: ?>
                                <ul class="text-sm text-gray-700 list-disc pl-5 space-y-1">
                                    <?php foreach ($no_photo as $row): ?>
                                        <li><?= esc($row['full_name']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">No Program Assignments</p>
                            <?php if (empty($no_assign)): ?>
                                <p class="text-sm text-gray-500">All faculty assigned ✅</p>
                            <?php else: ?>
                                <ul class="text-sm text-gray-700 list-disc pl-5 space-y-1">
                                    <?php foreach ($no_assign as $row): ?>
                                        <li><?= esc($row['full_name']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Feedback -->
                <div class="bg-white border border-gray-300 p-4 rounded">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                            <i class='bx bx-chat text-red-600 text-xl'></i> Recent Feedback
                        </h3>
                        <a href="admin_results.php" class="text-sm text-red-600 hover:underline">See All</a>
                    </div>

                    <?php if (empty($recent_feedback)): ?>
                        <p class="text-sm text-gray-500">No comments submitted yet.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($recent_feedback as $fb): ?>
                                <div class="border border-gray-200 rounded p-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-semibold text-gray-800"><?= esc($fb['full_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= esc($fb['submitted_at']) ?></p>
                                    </div>
                                    <?php if (!empty($fb['like_because'])): ?>
                                        <p class="text-xs text-gray-700 mt-2"><span class="font-semibold">Likes:</span> <?= esc($fb['like_because']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($fb['dislike_because'])): ?>
                                        <p class="text-xs text-gray-700 mt-1"><span class="font-semibold">Dislikes:</span> <?= esc($fb['dislike_because']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($fb['suggest_will'])): ?>
                                        <p class="text-xs text-gray-700 mt-1"><span class="font-semibold">Suggests:</span> <?= esc($fb['suggest_will']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </main>

</body>

</html>
<script src="../assets/js/admin-sidebar.js"></script>
<script src="../assets/js/faculty.js"></script>