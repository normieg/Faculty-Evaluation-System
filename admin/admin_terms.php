<?php
require __DIR__ . '/../database.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$msg = '';
$msg_type = 'success'; // 'success' | 'error'

// --- Helpers ---
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function fmt_date($d)
{
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('M j, Y', $ts) : $d;
}

// --- Actions ---
// Set active term
if (isset($_POST['set_active'])) {
    $tid = intval($_POST['term_id']);
    // Safety: make sure term exists
    $chk = mysqli_query($conn, "SELECT id FROM terms WHERE id='{$tid}'");
    if ($chk && mysqli_num_rows($chk) === 1) {
        mysqli_query($conn, "UPDATE terms SET is_active=0");
        mysqli_query($conn, "UPDATE terms SET is_active=1 WHERE id='{$tid}'");
        $msg = "Active term updated.";
        $msg_type = 'success';
    } else {
        $msg = "Selected term not found.";
        $msg_type = 'error';
    }
}
// Delete term
if (isset($_POST['delete_term'])) {
    $tid = intval($_POST['term_id']);
    // Check if exists
    $chk = mysqli_query($conn, "SELECT id, label, is_active FROM terms WHERE id='{$tid}'");
    if ($chk && mysqli_num_rows($chk) === 1) {
        $term = mysqli_fetch_assoc($chk);
        if ((int)$term['is_active'] === 1) {
            $msg = "You cannot delete the active term.";
            $msg_type = 'error';
        } else {
            mysqli_query($conn, "DELETE FROM terms WHERE id='{$tid}'");
            $msg = "Term “" . h($term['label']) . "” has been deleted.";
            $msg_type = 'success';
        }
    } else {
        $msg = "Term not found.";
        $msg_type = 'error';
    }
}


// Add new term
if (isset($_POST['add_term'])) {
    $label = trim($_POST['label'] ?? '');
    $start = $_POST['starts_on'] ?? '';
    $end   = $_POST['ends_on'] ?? '';
    if ($label === '' || $start === '' || $end === '') {
        $msg = "Please complete all fields.";
        $msg_type = 'error';
    } else {
        // Basic date validation
        $tsStart = strtotime($start);
        $tsEnd   = strtotime($end);
        if (!$tsStart || !$tsEnd || $tsEnd <= $tsStart) {
            $msg = "“Ends On” must be after “Starts On”.";
            $msg_type = 'error';
        } else {
            mysqli_query($conn, "INSERT INTO terms(label, starts_on, ends_on, is_active) VALUES('{$label}', '{$start}', '{$end}', 0)");
            $msg = "Term “" . h($label) . "” added.";
            $msg_type = 'success';
        }
    }
}

// Fetch terms (latest first)
$terms = [];
$res = mysqli_query($conn, "SELECT id, label, starts_on, ends_on, is_active FROM terms ORDER BY starts_on DESC");
$active_label = 'None';
$active_id = 0;
while ($row = mysqli_fetch_assoc($res)) {
    if ((int)$row['is_active'] === 1) {
        $active_label = $row['label'];
        $active_id = (int)$row['id'];
    }
    $terms[] = $row;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Manage Terms</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        @media print {

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

<body class="bg-gray-50 lg:pl-64">
    <?php $active = 'terms';
    include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Mobile/Tablet top bar -->
    <header class="topbar sticky top-0 z-30 bg-white border-b border-gray-200 lg:hidden">
        <div class="flex items-center gap-3 px-3 py-2">
            <button type="button"
                class="inline-flex items-center justify-center p-2 rounded hover:bg-gray-100"
                aria-label="Open menu"
                onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <i class='bx bx-menu text-2xl'></i>
            </button>
            <div class="flex items-center gap-2">
                <i class='bx bx-calendar-event text-red-600 text-2xl'></i>
                <h1 class="text-lg font-semibold text-gray-800">Manage Terms</h1>
            </div>
            <div class="ml-auto text-xs">
                <span class="text-gray-500">Active:</span>
                <span class="font-semibold"><?= h($active_label) ?></span>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto p-4 space-y-4">
        <!-- Desktop header -->
        <div class="hidden lg:flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class='bx bx-calendar-event text-red-600 text-2xl'></i>
                <h1 class="text-2xl font-semibold text-gray-800">Manage Terms</h1>
            </div>
            <div class="text-sm">
                <span class="text-gray-500">Current Active Term:</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                             <?= $active_id ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>">
                    <?= h($active_label) ?>
                </span>
            </div>
        </div>

        <!-- Flash -->
        <?php if (!empty($msg)): ?>
            <div class="rounded border px-3 py-2 text-sm flex items-center gap-2
                        <?= $msg_type === 'success' ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800' ?>">
                <i class='bx <?= $msg_type === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?> text-xl'></i>
                <span><?= $msg ?></span>
            </div>
        <?php endif; ?>

        <!-- Quick actions: Set active + Add term -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Set Active Term -->
            <section class="bg-white border border-gray-200 rounded-lg p-4">
                <h2 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class='bx bx-check-circle text-red-600'></i>
                    <span>Set Active Term</span>
                </h2>
                <form method="post" class="space-y-2">
                    <label class="text-sm text-gray-600">Choose the term that students will evaluate in.</label>
                    <select name="term_id" required class="w-full border border-gray-300 px-3 py-2 rounded">
                        <?php foreach ($terms as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['is_active'] ? 'selected' : '' ?>>
                                <?= h($t['label']) ?> (<?= h($t['starts_on']) ?> → <?= h($t['ends_on']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="set_active"
                        class="no-print w-full bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm flex items-center justify-center gap-2">
                        <i class='bx bx-check'></i><span>Make Selected Term Active</span>
                    </button>
                </form>
                <p class="mt-2 text-xs text-gray-500">
                    Tip: Only one term can be active at a time.
                </p>
            </section>

            <!-- Add New Term -->
            <section class="bg-white border border-gray-200 rounded-lg p-4 lg:col-span-2">
                <h2 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class='bx bx-plus-circle text-red-600'></i>
                    <span>Add New Term</span>
                </h2>
                <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-3">
                        <label class="block text-sm text-gray-700 mb-1">Term Name</label>
                        <input type="text" name="label" placeholder="ex. (AY 2025–2026  2nd Semester)" required
                            class="border border-gray-300 w-full px-3 py-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Starts On</label>
                        <input type="date" name="starts_on" required
                            class="border border-gray-300 w-full px-3 py-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Ends On</label>
                        <input type="date" name="ends_on" required
                            class="border border-gray-300 w-full px-3 py-2 rounded">
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" name="add_term"
                            class="no-print bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm inline-flex items-center gap-2">
                            <i class='bx bx-save'></i><span>Add Term</span>
                        </button>
                    </div>
                </form>
                <ul class="mt-3 text-xs text-gray-500 list-disc pl-5 space-y-1">
                    <li>Use a clear name (e.g., “AY 2025–2026 • 1st Sem”).</li>
                    <li>Dates should cover the evaluation period.</li>
                </ul>
            </section>
        </div>

        <!-- All Terms (readable for non-IT users) -->
        <section class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class='bx bx-list-ul text-red-600'></i>
                    <span>All Terms</span>
                </h2>
                <button type="button" onclick="window.print()"
                    class="no-print bg-white border border-gray-300 px-3 py-2 rounded text-sm hover:bg-gray-50 inline-flex items-center gap-2">
                    <i class='bx bx-printer'></i> Print
                </button>
            </div>

            <!-- Cards on small screens; table on md+ -->
            <div class="md:hidden grid grid-cols-1 gap-3">
                <?php foreach ($terms as $t): ?>
                    <?php
                    $isActive = (int)$t['is_active'] === 1;
                    ?>
                    <div class="border border-gray-200 rounded p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900"><?= h($t['label']) ?></h3>
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="mt-2 text-sm text-gray-700">
                            <div class="flex items-center gap-2">
                                <i class='bx bx-calendar'></i>
                                <span><?= fmt_date($t['starts_on']) ?> — <?= fmt_date($t['ends_on']) ?></span>
                            </div>
                        </div>
                        <form method="post" class="no-print mt-3">
                            <input type="hidden" name="term_id" value="<?= $t['id'] ?>">
                            <button type="submit" name="set_active"
                                class="w-full <?= $isActive ? 'bg-gray-200 text-gray-700 cursor-default' : 'bg-red-600 hover:bg-red-700 text-white' ?> px-3 py-2 rounded text-sm">
                                <?= $isActive ? 'Currently Active' : 'Make Active' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full text-sm border">
                    <thead class="bg-gray-50">
                        <tr class="text-left">
                            <th class="border px-3 py-2">Term</th>
                            <th class="border px-3 py-2">Start</th>
                            <th class="border px-3 py-2">End</th>
                            <th class="border px-3 py-2">Status</th>
                            <th class="border px-3 py-2 no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($terms as $t): ?>
                            <?php $isActive = (int)$t['is_active'] === 1; ?>
                            <tr class="odd:bg-white even:bg-gray-50">
                                <td class="border px-3 py-2 font-medium text-gray-900"><?= h($t['label']) ?></td>
                                <td class="border px-3 py-2"><?= fmt_date($t['starts_on']) ?></td>
                                <td class="border px-3 py-2"><?= fmt_date($t['ends_on']) ?></td>
                                <td class="border px-3 py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="border px-3 py-2 no-print">
                                    <form method="post" class="inline">
                                        <input type="hidden" name="term_id" value="<?= $t['id'] ?>">
                                        <button type="submit" name="set_active"
                                            class="px-3 py-1.5 rounded text-xs <?= $isActive ? 'bg-gray-200 text-gray-700 cursor-default' : 'bg-red-600 hover:bg-red-700 text-white' ?>">
                                            <?= $isActive ? 'Current' : 'Make Active' ?>
                                        </button>
                                    </form>
                                    <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this term?');">
                                        <input type="hidden" name="term_id" value="<?= $t['id'] ?>">
                                        <button type="submit" name="delete_term"
                                            class="px-3 py-1.5 rounded text-xs bg-gray-100 hover:bg-red-100 text-red-700 border border-red-300">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($terms)): ?>
                            <tr>
                                <td colspan="5" class="border px-3 py-6 text-center text-gray-600">
                                    No terms yet. Add your first term above.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>

</html>