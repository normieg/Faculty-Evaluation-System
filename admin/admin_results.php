<?php
require __DIR__ . '/../database.php';
// admin session
if (session_status() === PHP_SESSION_NONE) {
    session_name('FES_ADMIN');
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

/* ---------- Helpers ---------- */
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function build_fullname_row(array $r): string
{
    $first  = trim($r['first_name']  ?? '');
    $middle = trim($r['middle_name'] ?? '');
    $last   = trim($r['last_name']   ?? '');
    $suffix = trim($r['suffix']      ?? '');
    $parts  = array_filter([$first, $middle, $last], fn($x) => $x !== '');
    $name   = trim(implode(' ', $parts));
    if ($suffix !== '') $name .= ' ' . $suffix;
    return $name !== '' ? $name : '(No name)';
}

/* Weighted average per faculty: returns [avg|null, count] */
function faculty_stats($conn, $faculty_id, $term_id)
{
    // avg per evaluation section (criterion belongs to a section)
    $sql = "
        SELECT c.section_id, AVG(es.score) AS avg_score
        FROM evaluations e
        JOIN evaluation_scores es ON es.evaluation_id = e.id
        JOIN eval_criteria c      ON c.id = es.criterion_id
        WHERE e.faculty_id='{$faculty_id}' AND e.term_id='{$term_id}'
        GROUP BY c.section_id
    ";
    $res = mysqli_query($conn, $sql);

    // section weights
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
        $w   = $weights[$sid] ?? 0.0;
        $weighted += ($avg * $w);
        $has = true;
    }
    if ($has) $weighted = $weighted / 100.0;

    // number of submitted evaluations
    $cnt = 0;
    $cres = mysqli_query($conn, "SELECT COUNT(*) AS c FROM evaluations WHERE faculty_id='{$faculty_id}' AND term_id='{$term_id}'");
    if ($cres) $cnt = (int)mysqli_fetch_assoc($cres)['c'];

    return [$has ? round($weighted, 2) : null, $cnt];
}

/* ---------- Active term ---------- */
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

/* ---------- Filters + Programs ---------- */
$filter_program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : 0;
$filter_q = isset($_GET['q']) ? trim($_GET['q']) : '';

$programs = [];
$pr = mysqli_query($conn, "SELECT id, name FROM programs WHERE is_active=1 ORDER BY name");
while ($row = mysqli_fetch_assoc($pr)) {
    $programs[] = $row;
}

/* ---------- Faculty list (optional program + search filter) ---------- */
$faculty = [];
if (!$no_term) {
    if ($filter_program_id > 0) {
        $fq = "
            SELECT DISTINCT f.id, f.first_name, f.middle_name, f.last_name, f.suffix
            FROM faculty f
            JOIN faculty_programs fp ON fp.faculty_id = f.id
            WHERE f.is_active = 1
              AND fp.program_id = {$filter_program_id}
            ORDER BY f.last_name, f.first_name, f.middle_name
        ";
    } else {
        $fq = "
            SELECT id, first_name, middle_name, last_name, suffix
            FROM faculty
            WHERE is_active = 1
            ORDER BY last_name, first_name, middle_name
        ";
    }
    $fr = mysqli_query($conn, $fq);
    while ($row = mysqli_fetch_assoc($fr)) {
        $display = build_fullname_row($row);
        if ($filter_q !== '' && stripos($display, $filter_q) === false) continue;
        $row['display_name'] = $display;
        $faculty[] = $row;
    }
}

/* ---------- Export CSV ---------- */
if (!$no_term && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=results_' . $term_id . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['#', 'Faculty', 'Weighted Avg (1–5)', 'Evaluations']);
    $rownum = 1;
    foreach ($faculty as $f) {
        [$avg, $cnt] = faculty_stats($conn, (int)$f['id'], $term_id);
        fputcsv($output, [$rownum++, $f['display_name'], ($avg === null ? '' : $avg), $cnt]);
    }
    fclose($output);
    exit;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Evaluation Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 lg:pl-64">
    <?php $active = 'results';
    include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Mobile header -->
    <header class="topbar sticky top-0 z-30 bg-white border-b border-gray-200 lg:hidden">
        <div class="flex items-center gap-3 px-3 py-2">
            <button type="button" class="inline-flex items-center justify-center p-2 rounded hover:bg-gray-100"
                aria-label="Open menu" onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <i class='bx bx-menu text-2xl'></i>
            </button>
            <div class="flex items-center gap-2">
                <i class='bx bx-bar-chart text-red-600 text-2xl'></i>
                <h1 class="text-[16px] sm:text-[18px] md:text-[18px] font-semibold text-gray-800">Evaluation Results</h1>
            </div>
        </div>
    </header>

    <!-- Desktop header -->
    <div class="hidden lg:flex fixed top-0 left-64 right-0 z-20 bg-white border-b border-gray-200 px-6 py-4 items-center justify-between">
        <div class="flex items-center gap-2">
            <i class='bx bx-bar-chart text-red-600 text-2xl'></i>
            <h1 class="text-2xl font-semibold text-gray-800">Evaluation Results</h1>
        </div>
        <div class="text-sm">
            <span class="text-gray-500">Active Term:</span>
            <span class="font-semibold"><?= h($term_label) ?></span>
        </div>
    </div>

    <main class="max-w-7xl mx-auto p-4 lg:pt-20">
        <?php if ($no_term): ?>
            <div class="bg-yellow-50 border border-yellow-300 text-yellow-900 p-4 rounded">
                <div class="flex items-start gap-3">
                    <i class='bx bx-error-circle text-2xl'></i>
                    <div>
                        <p class="font-medium">No active term is set.</p>
                        <p class="text-sm">Go to <a href="admin_terms.php" class="underline">Manage Terms</a> to activate a term.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <!-- Filter bar  -->
            <form method="get" action="" class="bg-white border border-gray-200 rounded p-3 mb-3">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                    <div class="md:col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">Program</label>
                        <select name="program_id" class="w-full border border-gray-300 px-2 py-2 rounded" onchange="this.form.submit()">
                            <option value="0">All Programs</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filter_program_id == $p['id'] ? 'selected' : '' ?>>
                                    <?= h($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">Search Faculty Name</label>
                        <input type="text" name="q" value="<?= h($filter_q) ?>" placeholder="Type a name"
                            class="w-full border border-gray-300 px-3 py-2 rounded">
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="bg-gray-800 text-white px-3 py-2 rounded text-sm" type="submit">
                            <i class='bx bx-search'></i> Apply
                        </button>
                        <a href="?program_id=<?= $filter_program_id ?>&q=<?= urlencode($filter_q) ?>&export=csv"
                            class="bg-white border border-gray-300 px-3 py-2 rounded text-sm hover:bg-gray-50 no-print">
                            <i class='bx bx-download'></i> Export Record
                        </a>
                    </div>
                </div>
            </form>

            <!-- HOW TO READ SCORES -->
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
                        <span class="inline-block w-3 h-3 rounded bg-red-200"></span> Needs Attention (less than 2.5)
                    </span>
                </div>
            </div>

            <?php if (empty($faculty)): ?>
                <div class="bg-white border border-gray-200 rounded p-8 text-center text-gray-600">
                    <i class='bx bx-search text-3xl mb-2'></i>
                    <p class="font-medium">No faculty found for the selected filters.</p>
                    <p class="text-sm">Try clearing the search or selecting another program.</p>
                </div>
            <?php else: ?>
                <div class="bg-white border border-gray-200 rounded p-3">
                    <div class="overflow-x-auto">
                        <table class="min-w-full border text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left">
                                    <th class="border px-3 py-2">#</th>
                                    <th class="border px-3 py-2">Faculty</th>
                                    <th class="border px-3 py-2">Weighted Avg (1–5)</th>
                                    <th class="border px-3 py-2">Evaluations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rownum = 1;
                                foreach ($faculty as $f):
                                    [$avg, $cnt] = faculty_stats($conn, (int)$f['id'], $term_id);

                                    // Optional: color band per “How to read scores”
                                    $band = 'bg-red-50 text-red-800';
                                    if ($avg === null) $band = '';
                                    else if ($avg >= 4.5) $band = 'bg-green-50 text-green-800';
                                    else if ($avg >= 3.5) $band = 'bg-blue-50 text-blue-800';
                                    else if ($avg >= 2.5) $band = 'bg-yellow-50 text-yellow-800';
                                ?>
                                    <tr class="odd:bg-white even:bg-gray-50">
                                        <td class="border px-3 py-2"><?= $rownum++ ?></td>
                                        <td class="border px-3 py-2 font-medium text-gray-800"><?= h($f['display_name']) ?></td>
                                        <td class="border px-3 py-2">
                                            <?php if ($avg === null): ?>
                                                —
                                            <?php else: ?>
                                                <span class="inline-block px-2 py-0.5 rounded <?= $band ?>"><?= $avg ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="border px-3 py-2"><?= (int)$cnt ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>

</html>