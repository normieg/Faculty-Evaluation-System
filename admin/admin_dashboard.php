<?php
require __DIR__ . '/../database.php';
// Ensure admin session is started with dedicated name
if (session_status() === PHP_SESSION_NONE) {
    session_name('FES_ADMIN');
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

/* ---------- tiny helpers (traditional) ---------- */
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

/* ---------- active term ---------- */
$term_id = 0;
$term_label = 'None';
$tr = mysqli_query($conn, "SELECT id, label FROM terms WHERE is_active = 1 LIMIT 1");
if ($tr && mysqli_num_rows($tr)) {
    $t = mysqli_fetch_assoc($tr);
    $term_id = (int)$t['id'];
    $term_label = $t['label'];
}

/* ---------- minimal stats ---------- */
$total_faculty   = one_int($conn, "SELECT COUNT(*) FROM faculty");
$active_programs = one_int($conn, "SELECT COUNT(*) FROM programs WHERE is_active = 1");
$active_students = one_int($conn, "SELECT COUNT(*) FROM students WHERE is_active = 1");
$evals_this_term = $term_id ? one_int($conn, "SELECT COUNT(*) FROM evaluations WHERE term_id = {$term_id}") : 0;
$evals_today     = $term_id ? one_int($conn, "SELECT COUNT(*) FROM evaluations WHERE term_id = {$term_id} AND DATE(submitted_at)=CURDATE()") : 0;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin Dashboard (Lite)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

    <?php $active = 'dashboard';
    include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Top bar (mobile) -->
    <header class="fixed top-0 left-0 right-0 z-30 bg-white border-b border-gray-200 lg:hidden">
        <div class="flex items-center gap-3 px-3 py-2">
            <button type="button" class="inline-flex items-center justify-center p-2 rounded hover:bg-gray-100"
                aria-label="Open menu" onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <i class='bx bx-menu text-2xl'></i>
            </button>
            <div class="flex items-center gap-2">
                <i class='bx bxs-dashboard text-red-600 text-2xl'></i>
                <h1 class="text-lg font-semibold text-gray-800">Admin Dashboard</h1>
            </div>
        </div>
    </header>

    <!-- Top bar (desktop) -->
    <header class="hidden lg:flex fixed top-0 left-64 right-0 z-20 bg-white border-b border-gray-200 px-6 py-4 items-center justify-between">
        <div class="flex items-center gap-2">
            <i class='bx bxs-dashboard text-red-600 text-2xl'></i>
            <h1 class="text-xl font-semibold text-gray-800">Admin Dashboard</h1>
        </div>
        <p class="text-sm text-gray-600">
            Logged in as:
            <span class="font-semibold text-gray-800"><?= esc($_SESSION['admin_user'] ?? 'Admin') ?></span>
        </p>
    </header>

    <main class="lg:ml-64 pt-20">
        <div class="max-w-7xl  mx-6">

            <!-- Active term -->
            <section class="bg-white border border-gray-200 rounded p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Active Term</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900"><?= esc($term_label) ?></p>
                    </div>
                    <a href="admin_terms.php"
                        class="inline-flex items-center gap-2 text-sm bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded">
                        <i class='bx bx-calendar-event text-base'></i>
                        Manage Terms
                    </a>
                </div>
            </section>

            <!--cards -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-5">
                <div class="bg-white border border-gray-200 rounded p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Faculty</h3>
                        <i class='bx bxs-user-detail text-red-600 text-2xl'></i>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900"><?= $total_faculty ?></p>
                </div>

                <div class="bg-white border border-gray-200 rounded p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Active Programs</h3>
                        <i class='bx bxs-graduation text-red-600 text-2xl'></i>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900"><?= $active_programs ?></p>
                </div>

                <div class="bg-white border border-gray-200 rounded p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Active Students</h3>
                        <i class='bx bx-user-check text-red-600 text-2xl'></i>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900"><?= $active_students ?></p>
                </div>

                <div class="bg-white border border-gray-200 rounded p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Evaluations</h3>
                        <i class='bx bx-bar-chart text-red-600 text-2xl'></i>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900"><?= $evals_this_term ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?= $evals_today ?> today</p>
                </div>
            </section>

            <!-- Quick links -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-5">
                <a href="admin_faculty.php" class="bg-white border border-gray-200 rounded p-4 block hover:bg-gray-50">
                    <div class="flex items-center gap-2 mb-1">
                        <i class='bx bxs-user-voice text-red-600 text-2xl'></i>
                        <h3 class="font-semibold text-gray-800">Faculty & Programs</h3>
                    </div>
                    <p class="text-sm text-gray-600">Manage faculty and assignments.</p>
                </a>

                <a href="admin_terms.php" class="bg-white border border-gray-200 rounded p-4 block hover:bg-gray-50">
                    <div class="flex items-center gap-2 mb-1">
                        <i class='bx bx-calendar-event text-red-600 text-2xl'></i>
                        <h3 class="font-semibold text-gray-800">Set Active Term</h3>
                    </div>
                    <p class="text-sm text-gray-600">Change or add a term.</p>
                </a>

                <a href="admin_results.php" class="bg-white border border-gray-200 rounded p-4 block hover:bg-gray-50">
                    <div class="flex items-center gap-2 mb-1">
                        <i class='bx bx-bar-chart text-red-600 text-2xl'></i>
                        <h3 class="font-semibold text-gray-800">Evaluation Results</h3>
                    </div>
                    <p class="text-sm text-gray-600">See scores and comments.</p>
                </a>
            </section>

        </div>
    </main>

    <script src="../assets/js/admin-sidebar.js"></script>
</body>

</html>