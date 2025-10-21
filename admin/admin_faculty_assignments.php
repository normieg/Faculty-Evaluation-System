<?php
require __DIR__ . '/../database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// --- Active term ---
$active_term_id = 0;
$t = mysqli_query($conn, "SELECT id, label FROM terms WHERE is_active=1 LIMIT 1");
if ($t && mysqli_num_rows($t) === 1) {
    $row = mysqli_fetch_assoc($t);
    $active_term_id = (int)$row['id'];
}

// --- Helpers ---
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $in = '';
    foreach ($parts as $p) {
        if ($p !== '') $in .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($in) >= 2) break;
    }
    return $in ?: 'F';
}

// --- Program selector ---
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$programs = [];
$pr = mysqli_query($conn, "SELECT id, code, name FROM programs WHERE is_active=1 ORDER BY code");
while ($r = mysqli_fetch_assoc($pr)) $programs[] = $r;

// --- WHERE clause ---
$where = ["1=1"];
if ($program_id > 0) {
    $where[] = "(EXISTS (SELECT 1 FROM faculty_programs fp WHERE fp.faculty_id=f.id AND fp.program_id={$program_id})
                 OR EXISTS (SELECT 1 FROM faculty_program_years fpy WHERE fpy.faculty_id=f.id AND fpy.program_id={$program_id})
                 OR EXISTS (SELECT 1 FROM faculty_section_assignments fsa WHERE fsa.faculty_id=f.id AND fsa.program_id={$program_id}))";
}
$where_sql = implode(" AND ", $where);

// --- Submitted count subquery ---
$submittedSub = $active_term_id > 0
    ? "(SELECT COUNT(*) FROM evaluations e WHERE e.faculty_id=f.id AND e.term_id={$active_term_id})"
    : "0";

// --- Query faculty ---
$sql = "
SELECT
    f.id,
    f.full_name,
    f.photo_url,
    f.is_active,
    COALESCE((
        SELECT GROUP_CONCAT(DISTINCT p.code ORDER BY p.code SEPARATOR ', ')
        FROM faculty_programs fp
        JOIN programs p ON p.id=fp.program_id
        WHERE fp.faculty_id=f.id
    ), '') AS programs_list,
    {$submittedSub} AS submitted_count
FROM faculty f
WHERE {$where_sql}
ORDER BY
    (SELECT MIN(p2.code) FROM faculty_programs fp2 JOIN programs p2 ON p2.id=fp2.program_id WHERE fp2.faculty_id=f.id) ASC,
    f.full_name ASC
";
$rows = [];
$rs = mysqli_query($conn, $sql);
if ($rs) while ($r = mysqli_fetch_assoc($rs)) $rows[] = $r;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Faculty Submitted Counts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        thead th {
            position: sticky;
            top: 0;
            background: #f9fafb;
            z-index: 10;
        }
    </style>
</head>

<body class="bg-gray-50 lg:pl-64">
    <?php $active = 'faculty_map';
    include __DIR__ . '/partials/sidebar.php'; ?>


    <!-- ✅ Mobile / Tablet top bar -->
    <header class="topbar sticky top-0 z-30 bg-white border-b border-gray-200 lg:hidden">
        <div class="flex items-center gap-3 px-3 py-2">
            <button type="button"
                class="inline-flex items-center justify-center p-2 rounded hover:bg-gray-100"
                aria-label="Open menu"
                onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <i class='bx bx-menu text-2xl'></i>
            </button>
            <div class="flex items-center gap-2">
                <i class='bx bx-bar-chart-alt-2 text-red-600 text-2xl'></i>
                <h1 class="text-lg font-semibold text-gray-800">Faculty Submitted Counts</h1>
    </header>

    <header class="hidden lg:flex fixed top-0 left-64 right-0 z-20 bg-white border-b border-gray-200 px-6 py-4 items-center justify-between">
        <div class="flex items-center gap-2">
            <i class='bx bx-bar-chart-alt-2 text-red-600 text-2xl'></i>
            <h1 class="text-[13px] sm:text-[17px] md:text-[18px] font-semibold text-gray-800">
                Faculty assignment & submitted count
            </h1>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-4 lg:pt-20">

        <!-- Filter -->
        <form method="get"
            class="bg-white border border-gray-200 rounded-lg p-3 mb-4 flex flex-col sm:flex-row sm:items-center gap-2 shadow-sm">

            <label class="text-sm text-gray-700 font-medium whitespace-nowrap ">
                Filter by Program:
            </label>

            <select
                name="program_id"
                class="border border-gray-300 px-2 py-1 text-xs sm:text-sm w-32 sm:w-44 md:w-52 lg:w-60 rounded focus:ring-2 focus:ring-red-500 focus:outline-none">
                <option value="0">All Programs</option>
                <?php foreach ($programs as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $program_id === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= h($p['code']) ?> — <?= h($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>


            <button type="submit"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm flex items-center justify-center gap-1 w-full sm:w-auto">
                <i class='bx bx-filter-alt'></i>
                <span>Apply</span>
            </button>
        </form>


        <!-- Table -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-gray-800">
                    <thead>
                        <tr class="text-left text-gray-700 uppercase text-xs border-b">
                            <th class="pl-20 py-3 font-bold ml- ">Faculty</th>
                            <th class="px-4 py-3  font-bold text-center">Programs</th>
                            <th class="px-4 py-3  text-center font-bold">Submitted</th>
                            <th class="px-4 py-3  font-bold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): $isActive = (int)$r['is_active'] === 1; ?>
                            <tr class="hover:bg-gray-50 transition text-center">
                                <td class="px-4 py-3 border-t">
                                    <div class="flex items-center gap-3 text-center rounded-full w-40 sm:w-40 md:w-60 lg:w-72 ml-10">
                                        <div class="avatar ">
                                            <?php if (!empty($r['photo_url'])): ?>
                                                <img src="../storage/faculty_profiles/<?= h($r['photo_url']) ?>" alt="<?= h($r['full_name']) ?>">
                                            <?php else: ?>
                                                <div class="avatar-fallback bg-gray-200 text-gray-700"><?= h(initials($r['full_name'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900"><?= h($r['full_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 border-t"><?= $r['programs_list'] ? h($r['programs_list']) : '<span class="text-gray-400">—</span>' ?></td>
                                <td class="px-4 py-3 border-t font-semibold text-center text-gray-900"><?= (int)$r['submitted_count'] ?></td>
                                <td class="px-4 py-3 border-t">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?= $isActive ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-gray-100 text-gray-700 border border-gray-200' ?>">
                                        <i class='bx <?= $isActive ? 'bx-check-circle' : 'bx-pause-circle' ?> text-sm'></i>
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-gray-500 text-sm">
                                    No faculty found for this filter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($active_term_id === 0): ?>
            <p class="mt-3 text-xs text-gray-500 flex items-center gap-1">
                <i class='bx bx-info-circle'></i>
                No active term set — “Submitted” counts will remain 0 until one is active.
            </p>
        <?php endif; ?>
    </main>

</body>

</html>