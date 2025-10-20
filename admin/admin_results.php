<?php
require __DIR__ . '/../database.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

/* -----------------------------
   Active term (keep layout if none)
------------------------------ */
$no_term = false;
$term = mysqli_query($conn, "SELECT id, label FROM terms WHERE is_active=1 LIMIT 1");
if (!$term || mysqli_num_rows($term) === 0) {
    $no_term = true;
    $term_id = 0;
    $term_label = 'None';
} else {
    $term = mysqli_fetch_assoc($term);
    $term_id = (int)$term['id'];
    $term_label = $term['label'];
}

/* -----------------------------
   Filters
------------------------------ */
$filter_program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
$filter_q = isset($_GET['q']) ? trim($_GET['q']) : '';

/* Programs for filter dropdown */
$programs = [];
$pr = mysqli_query($conn, "SELECT id, name FROM programs WHERE is_active=1 ORDER BY name");
while ($row = mysqli_fetch_assoc($pr)) {
    $programs[] = $row;
}

/* -----------------------------
   Faculty list (optionally filtered by program)
------------------------------ */
$faculty = [];
if (!$no_term) {
    if ($filter_program_id > 0) {
        $fq = "
            SELECT DISTINCT f.id, f.full_name
            FROM faculty f
            JOIN faculty_programs fp ON fp.faculty_id=f.id
            WHERE f.is_active=1 AND fp.program_id='{$filter_program_id}'
            ORDER BY f.full_name
        ";
    } else {
        $fq = "SELECT id, full_name FROM faculty WHERE is_active=1 ORDER BY full_name";
    }
    $fr = mysqli_query($conn, $fq);
    while ($row = mysqli_fetch_assoc($fr)) {
        if ($filter_q !== '' && stripos($row['full_name'], $filter_q) === false) continue;
        $faculty[] = $row;
    }
}

/* -----------------------------
   Helper functions
------------------------------ */
function faculty_stats($conn, $faculty_id, $term_id)
{
    $sql = "
        SELECT c.section_id, AVG(es.score) AS avg_score
        FROM evaluations e
        JOIN evaluation_scores es ON es.evaluation_id = e.id
        JOIN eval_criteria c ON c.id = es.criterion_id
        WHERE e.faculty_id='{$faculty_id}' AND e.term_id='{$term_id}'
        GROUP BY c.section_id
    ";
    $res = mysqli_query($conn, $sql);

    $weights = [];
    $wres = mysqli_query($conn, "SELECT id, weight_pct FROM eval_sections");
    while ($wrow = mysqli_fetch_assoc($wres)) {
        $weights[(int)$wrow['id']] = (float)$wrow['weight_pct'];
    }

    $weighted = 0.0;
    $has = false;
    while ($row = mysqli_fetch_assoc($res)) {
        $sid = (int)$row['section_id'];
        $avg = (float)$row['avg_score'];
        $w   = isset($weights[$sid]) ? $weights[$sid] : 0.0;
        $weighted += ($avg * $w);
        $has = true;
    }
    if ($has) $weighted = $weighted / 100.0;

    $cnt = 0;
    $cres = mysqli_query($conn, "SELECT COUNT(*) AS c FROM evaluations WHERE faculty_id='{$faculty_id}' AND term_id='{$term_id}'");
    if ($cres) $cnt = (int)mysqli_fetch_assoc($cres)['c'];

    return [$has ? round($weighted, 2) : null, $cnt];
}

function rating_badge_classes($avg)
{
    if ($avg === null) return "bg-gray-100 text-gray-600";
    if ($avg >= 4.5) return "bg-green-100 text-green-800";
    if ($avg >= 3.5) return "bg-blue-100 text-blue-800";
    if ($avg >= 2.5) return "bg-yellow-100 text-yellow-800";
    return "bg-red-100 text-red-800";
}

function rating_label($avg)
{
    if ($avg === null) return "No data yet";
    if ($avg >= 4.5) return "Excellent";
    if ($avg >= 3.5) return "Good";
    if ($avg >= 2.5) return "Fair";
    return "Needs Attention";
}

function stars_html($avg)
{
    if ($avg === null) return '<span class="text-xs text-gray-500">—</span>';
    $full = floor($avg + 0.0001);
    $half = ($avg - $full) >= 0.5 ? 1 : 0;
    $out  = 5 - $full - $half;
    $html = '<div class="flex items-center space-x-0.5 text-yellow-500">';
    for ($i = 0; $i < $full; $i++) $html .= "<i class='bx bxs-star'></i>";
    if ($half) $html .= "<i class='bx bxs-star-half'></i>";
    for ($i = 0; $i < $out; $i++) $html .= "<i class='bx bx-star'></i>";
    $html .= "</div>";
    return $html;
}

/* -----------------------------
   Optional: Export CSV
------------------------------ */
if (!$no_term && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=results_' . $term_id . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['#', 'Faculty', 'Weighted Avg (1–5)', 'Evaluations']);
    $rownum = 1;

    foreach ($faculty as $f) {
        list($avg, $cnt) = faculty_stats($conn, (int)$f['id'], $term_id);
        fputcsv($output, [$rownum++, $f['full_name'], ($avg === null ? '' : $avg), $cnt]);
    }
    fclose($output);
    exit;
}

/* -----------------------------
   Quick overall stats (simple + fast)
------------------------------ */
$total_faculty = count($faculty);
$total_evals = 0;
$weighted_sum = 0.0;
foreach ($faculty as $f) {
    list($avg, $cnt) = faculty_stats($conn, (int)$f['id'], $term_id);
    $total_evals += $cnt;
    if ($avg !== null && $cnt > 0) {
        $weighted_sum += ($avg * $cnt);
    }
}
$overall_avg = ($total_evals > 0) ? round($weighted_sum / $total_evals, 2) : null;

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Evaluation Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        @media print {

            /* Hide sidebar, topbar, buttons when printing */
            #adminSidebar,
            #sidebarOverlay,
            .topbar,
            .no-print {
                display: none !important;
            }

            html,
            body {
                background: #fff;
            }

            main {
                padding: 0 !important;
                margin: 0 !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100 lg:pl-64">
    <?php $active = 'results';
    include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Top bar (mobile + tablet). Hidden on lg because sidebar is visible there -->
    <header class="topbar sticky top-0 z-30 bg-white border-b border-gray-200 lg:hidden">
        <div class="flex items-center gap-3 px-3 py-2">
            <button type="button"
                class="inline-flex items-center justify-center p-2 rounded hover:bg-gray-100"
                aria-label="Open menu"
                onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <i class='bx bx-menu text-2xl'></i>
            </button>
            <div class="flex items-center gap-2">
                <i class='bx bx-bar-chart text-red-600 text-2xl'></i>
                <h1 class="text-lg font-semibold text-gray-800">Evaluation Results</h1>
            </div>
            <div class="ml-auto text-xs">
                <span class="text-gray-500">Active Term:</span>
                <span class="font-semibold"><?= htmlspecialchars($term_label) ?></span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
        <!-- Page header (desktop) -->
        <div class="hidden lg:flex items-center justify-between mb-4">
            <div class="flex items-center space-x-2">
                <i class='bx bx-bar-chart text-red-600 text-2xl'></i>
                <h1 class="text-2xl font-semibold text-gray-800">Evaluation Results</h1>
            </div>
            <div class="text-sm">
                <span class="text-gray-500">Active Term:</span>
                <span class="font-semibold"><?= htmlspecialchars($term_label) ?></span>
            </div>
        </div>

        <?php if ($no_term): ?>
            <div class="bg-yellow-50 border border-yellow-300 text-yellow-900 p-4 rounded">
                <div class="flex items-start space-x-3">
                    <i class='bx bx-error-circle text-2xl'></i>
                    <div>
                        <p class="font-medium">No active term is set.</p>
                        <p class="text-sm">Go to <a href="admin_terms.php" class="underline">Manage Terms</a> to activate a term.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <div class="bg-white border border-gray-200 rounded p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Term</p>
                    <p class="text-lg font-semibold"><?= htmlspecialchars($term_label) ?></p>
                </div>
                <div class="bg-white border border-gray-200 rounded p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Overall Average</p>
                    <div class="flex items-center space-x-2">
                        <p class="text-2xl font-bold"><?= $overall_avg === null ? '—' : $overall_avg ?></p>
                        <?= $overall_avg === null ? '' : stars_html($overall_avg) ?>
                    </div>
                    <p class="text-xs mt-1">
                        <span class="inline-block px-2 py-0.5 rounded <?= rating_badge_classes($overall_avg) ?>">
                            <?= htmlspecialchars(rating_label($overall_avg)) ?>
                        </span>
                    </p>
                </div>
                <div class="bg-white border border-gray-200 rounded p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Faculty in View</p>
                    <p class="text-2xl font-bold"><?= $total_faculty ?></p>
                </div>
                <div class="bg-white border border-gray-200 rounded p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Total Evaluations</p>
                    <p class="text-2xl font-bold"><?= $total_evals ?></p>
                </div>
            </div>

            <!-- Filter bar -->
            <form method="get" action="" class="bg-white border border-gray-200 rounded p-3 mb-3">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                    <div class="md:col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">Program</label>
                        <select name="program_id" class="w-full border border-gray-300 px-2 py-2 rounded" onchange="this.form.submit()">
                            <option value="0">All Programs</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filter_program_id == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">Search Faculty Name</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>"
                            placeholder="ex. Normie Bagongon"
                            class="w-full border border-gray-300 px-3 py-2 rounded">
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="bg-gray-800 text-white px-3 py-2 rounded text-sm" type="submit">
                            <i class='bx bx-search'></i> Apply
                        </button>
                        <a href="?program_id=<?= $filter_program_id ?>&q=<?= urlencode($filter_q) ?>&export=csv"
                            class="bg-white border border-gray-300 px-3 py-2 rounded text-sm hover:bg-gray-50 no-print">
                            <i class='bx bx-download'></i> Export CSV
                        </a>
                        <button type="button" onclick="window.print()"
                            class="bg-white border border-gray-300 px-3 py-2 rounded text-sm hover:bg-gray-50 no-print">
                            <i class='bx bx-printer'></i> Print
                        </button>
                    </div>
                </div>
                <noscript>
                    <div class="mt-2"><button class="bg-gray-800 text-white px-3 py-2 rounded text-sm">Apply</button></div>
                </noscript>
            </form>

            <!-- Legend -->
            <div class="bg-white border border-gray-200 rounded p-3 mb-3">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="text-gray-700 font-medium">How to read scores:</span>
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded bg-green-200"></span> Excellent (4.5–5.0)
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded bg-blue-200"></span> Good (3.5–4.49)
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded bg-yellow-200"></span> Fair (2.5–3.49)
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded bg-red-200"></span> Needs Attention (&lt;2.5)
                    </span>
                </div>
            </div>

            <?php if (empty($faculty)): ?>
                <div class="bg-white border border-gray-200 rounded p-8 text-center text-gray-600">
                    <i class='bx bx-search text-3xl mb-2'></i>
                    <p class="font-medium">No faculty found for the selected filters.</p>
                    <p class="text-sm">Try clearing the search or picking another program.</p>
                </div>
            <?php else: ?>

                <!-- Table on md+ screens -->
                <div class="hidden md:block bg-white border border-gray-200 rounded p-3">
                    <div class="overflow-x-auto">
                        <table class="min-w-full border text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr class="text-left">
                                    <th class="border px-3 py-2">#</th>
                                    <th class="border px-3 py-2">Faculty</th>
                                    <th class="border px-3 py-2">Weighted Avg</th>
                                    <th class="border px-3 py-2">Rating</th>
                                    <th class="border px-3 py-2">Evaluations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rownum = 1;
                                foreach ($faculty as $f):
                                    list($avg, $cnt) = faculty_stats($conn, (int)$f['id'], $term_id);
                                    $badge = rating_badge_classes($avg);
                                    $label = rating_label($avg);
                                ?>
                                    <tr class="odd:bg-white even:bg-gray-50 align-middle">
                                        <td class="border px-3 py-2"><?= $rownum++ ?></td>
                                        <td class="border px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($f['full_name']) ?></td>
                                        <td class="border px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-base font-semibold"><?= $avg === null ? '—' : $avg ?></span>
                                                <?= stars_html($avg) ?>
                                            </div>
                                        </td>
                                        <td class="border px-3 py-2">
                                            <span class="inline-block text-xs px-2 py-1 rounded <?= $badge ?>">
                                                <?= htmlspecialchars($label) ?>
                                            </span>
                                        </td>
                                        <td class="border px-3 py-2"><?= (int)$cnt ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Weighted average is based on your evaluation section weights.</p>
                </div>

                <!-- Card list on small screens -->
                <div class="md:hidden grid grid-cols-1 gap-3">
                    <?php
                    $rownum = 1;
                    foreach ($faculty as $f):
                        list($avg, $cnt) = faculty_stats($conn, (int)$f['id'], $term_id);
                        $badge = rating_badge_classes($avg);
                        $label = rating_label($avg);
                    ?>
                        <div class="bg-white border border-gray-200 rounded p-4">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500">#<?= $rownum++ ?></div>
                                <span class="inline-block text-[10px] px-2 py-1 rounded <?= $badge ?>"><?= htmlspecialchars($label) ?></span>
                            </div>
                            <h3 class="mt-1 font-semibold text-gray-800"><?= htmlspecialchars($f['full_name']) ?></h3>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-sm text-gray-600">Weighted Avg:</span>
                                <span class="text-base font-semibold"><?= $avg === null ? '—' : $avg ?></span>
                                <?= stars_html($avg) ?>
                            </div>
                            <div class="mt-1 text-sm text-gray-600">
                                Evaluations: <span class="font-medium"><?= (int)$cnt ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recent comments -->
                <div class="mt-4">
                    <h2 class="font-semibold text-gray-800 mb-2 flex items-center space-x-2">
                        <i class='bx bx-chat text-red-600'></i>
                        <span>Recent Comments (last 20)</span>
                    </h2>
                    <?php
                    $comments_sql = "
                        SELECT f.full_name, e.like_because, e.dislike_because, e.suggest_will, e.submitted_at
                        FROM evaluations e
                        JOIN faculty f ON f.id = e.faculty_id
                        WHERE e.term_id='{$term_id}'
                        ORDER BY e.submitted_at DESC
                        LIMIT 20
                    ";
                    $cr = mysqli_query($conn, $comments_sql);
                    ?>
                    <?php if ($cr && mysqli_num_rows($cr) > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php while ($c = mysqli_fetch_assoc($cr)): ?>
                                <div class="bg-white border border-gray-200 p-4 rounded">
                                    <div class="text-xs text-gray-600 mb-2">
                                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($c['full_name']) ?></span>
                                        <span class="mx-1">·</span>
                                        <span><?= htmlspecialchars($c['submitted_at']) ?></span>
                                    </div>
                                    <?php if (trim((string)$c['like_because']) !== ''): ?>
                                        <div class="text-sm">
                                            <span class="font-semibold text-green-700">What students liked:</span>
                                            <div class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars($c['like_because'])) ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (trim((string)$c['dislike_because']) !== ''): ?>
                                        <div class="text-sm mt-2">
                                            <span class="font-semibold text-red-700">What needs improvement:</span>
                                            <div class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars($c['dislike_because'])) ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (trim((string)$c['suggest_will']) !== ''): ?>
                                        <div class="text-sm mt-2">
                                            <span class="font-semibold text-blue-700">Student suggestions:</span>
                                            <div class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars($c['suggest_will'])) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white border border-gray-200 rounded p-8 text-center text-gray-600">
                            <i class='bx bx-message-rounded text-3xl mb-2'></i>
                            <p class="font-medium">No comments yet.</p>
                            <p class="text-sm">New comments will appear here as students submit evaluations.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</body>

</html>