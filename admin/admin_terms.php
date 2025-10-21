<?php
require __DIR__ . '/../database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

/* --- Flash --- */
$msg = $_SESSION['flash_msg'] ?? '';
$msg_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

/* --- Helpers --- */
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function fmt_date($d)
{
    $ts = strtotime($d);
    return $ts ? date('M j, Y', $ts) : $d;
}

/* --- Actions (PRG) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set Active
    if (isset($_POST['set_active'])) {
        $tid = intval($_POST['term_id'] ?? 0);
        $chk = mysqli_query($conn, "SELECT id FROM terms WHERE id='{$tid}'");
        if ($chk && mysqli_num_rows($chk) === 1) {
            mysqli_query($conn, "UPDATE terms SET is_active=0");
            mysqli_query($conn, "UPDATE terms SET is_active=1 WHERE id='{$tid}'");
            $_SESSION['flash_msg'] = "Active term updated.";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = "Selected term not found.";
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: admin_terms.php");
        exit;
    }

    // Delete
    if (isset($_POST['delete_term'])) {
        $tid = intval($_POST['term_id'] ?? 0);
        $chk = mysqli_query($conn, "SELECT id,label,is_active FROM terms WHERE id='{$tid}'");
        if ($chk && mysqli_num_rows($chk) === 1) {
            $t = mysqli_fetch_assoc($chk);
            if ((int)$t['is_active'] === 1) {
                $_SESSION['flash_msg'] = "You cannot delete the active term.";
                $_SESSION['flash_type'] = 'error';
            } else {
                mysqli_query($conn, "DELETE FROM terms WHERE id='{$tid}'");
                $_SESSION['flash_msg'] = "Deleted “" . h($t['label']) . "”.";
                $_SESSION['flash_type'] = 'success';
            }
        } else {
            $_SESSION['flash_msg'] = "Term not found.";
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: admin_terms.php");
        exit;
    }

    // Add (comes from modal)
    if (isset($_POST['add_term'])) {
        $label = trim($_POST['label'] ?? '');
        $start = $_POST['starts_on'] ?? '';
        $end   = $_POST['ends_on'] ?? '';
        if ($label === '' || $start === '' || $end === '') {
            $_SESSION['flash_msg'] = "Please complete all fields.";
            $_SESSION['flash_type'] = 'error';
        } else {
            $tsS = strtotime($start);
            $tsE = strtotime($end);
            if (!$tsS || !$tsE || $tsE <= $tsS) {
                $_SESSION['flash_msg'] = "“Ends On” must be after “Starts On”.";
                $_SESSION['flash_type'] = 'error';
            } else {
                mysqli_query($conn, "INSERT INTO terms(label,starts_on,ends_on,is_active) VALUES('{$label}','{$start}','{$end}',0)");
                $_SESSION['flash_msg'] = "Added “" . h($label) . "”.";
                $_SESSION['flash_type'] = 'success';
            }
        }
        header("Location: admin_terms.php");
        exit;
    }
}

/* --- Data --- */
$terms = [];
$res = mysqli_query($conn, "SELECT id,label,starts_on,ends_on,is_active FROM terms ORDER BY starts_on DESC");
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

        .modal-open {
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-gray-50 lg:pl-64">
    <?php $active = 'terms';
    include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Mobile/Tablet header (UNCHANGED) -->
    <header class="topbar sticky top-0 z-30 bg-white border-b border-gray-200 lg:hidden">
        <div class="flex items-center gap-3 px-3 py-2">
            <button type="button" class="inline-flex items-center justify-center p-2 rounded hover:bg-gray-100"
                aria-label="Open menu" onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <i class='bx bx-menu text-2xl'></i>
            </button>
            <div class="flex items-center gap-2">
                <i class='bx bx-calendar-event text-red-600 text-2xl'></i>
                <h1 class="text-[15px] sm:text-[17px] md:text-[18px] font-semibold text-gray-800">Manage Terms</h1>
            </div>
            <div class="ml-auto text-xs">
                <span class="text-gray-500">Active Term:</span>
                <span class="font-semibold <?= $active_id ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>"><?= h($active_label) ?></span>
            </div>
        </div>
    </header>

    <!-- Desktop header (UNCHANGED) -->
    <div class="hidden lg:flex fixed top-0 left-64 right-0 z-20 bg-white border-b border-gray-200 px-6 py-4 items-center justify-between">
        <div class="flex items-center gap-2">
            <i class='bx bx-calendar-event text-red-600 text-2xl'></i>
            <h1 class="text-2xl font-semibold text-gray-800">Manage Terms</h1>
        </div>
        <div class="text-sm">
            <span class="text-gray-500">Active Term:</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                     <?= $active_id ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>">
                <?= h($active_label) ?>
            </span>
        </div>
    </div>

    <main class="max-w-7xl mx-auto p-4 lg:pt-20">

        <?php if (!empty($msg)): ?>
            <div id="flash" class="rounded border px-3 py-2 text-sm flex items-center gap-2
            <?= $msg_type === 'success' ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800' ?>">
                <i class='bx <?= $msg_type === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?> text-xl'></i>
                <span><?= $msg ?></span>
            </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="mt-2 flex items-center justify-between">
            <div class="text-sm text-gray-600">Manage your school terms. Set which one is active.</div>
            <button type="button" id="openAddTerm"
                class="no-print inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-2 rounded">
                <i class='bx bx-plus'></i> Add New Term
            </button>
        </div>

        <!-- Terms Table (simple) -->
        <section class="mt-4 bg-white border border-gray-200 rounded-lg p-4">
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full text-sm border">
                    <thead class="bg-gray-50">
                        <tr class="text-left">
                            <th class="border px-3 py-2">Term</th>
                            <th class="border px-3 py-2">Start</th>
                            <th class="border px-3 py-2">End</th>
                            <th class="border px-3 py-2">Status</th>
                            <th class="border px-3 py-2 no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($terms as $t): ?>
                            <?php
                            $isActive = (int)$t['is_active'] === 1;
                            $confirmSetActive = $isActive ? 'true' : "confirm('Set " . addslashes($t['label']) . " as the active term?')";
                            $confirmDelete = $isActive
                                ? "alert('You cannot delete the active term.'); return false;"
                                : "return confirm('Delete this term? This cannot be undone.')";
                            ?>
                            <tr class="odd:bg-white even:bg-gray-50">
                                <td class="border px-3 py-2 font-medium text-gray-900">
                                    <?= h($t['label']) ?> <?= $isActive ? '<span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800">Active</span>' : '' ?>
                                </td>
                                <td class="border px-3 py-2"><?= fmt_date($t['starts_on']) ?></td>
                                <td class="border px-3 py-2"><?= fmt_date($t['ends_on']) ?></td>
                                <td class="border px-3 py-2"><?= $isActive ? 'Active' : 'Inactive' ?></td>
                                <td class="border px-3 py-2 no-print">
                                    <form method="post" class="inline" onsubmit="return <?= $confirmSetActive ?>;">
                                        <input type="hidden" name="term_id" value="<?= $t['id'] ?>">
                                        <button type="submit" name="set_active"
                                            class="px-3 py-1.5 rounded text-xs <?= $isActive ? 'bg-gray-200 text-gray-700 cursor-default' : 'bg-red-600 hover:bg-red-700 text-white' ?>">
                                            <?= $isActive ? 'Current' : 'Set Active' ?>
                                        </button>
                                    </form>
                                    <form method="post" class="inline" onsubmit="<?= htmlspecialchars($confirmDelete, ENT_QUOTES) ?>">
                                        <input type="hidden" name="term_id" value="<?= $t['id'] ?>">
                                        <button type="submit" name="delete_term"
                                            class="px-3 py-1.5 rounded text-xs <?= $isActive ? 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed' : 'bg-gray-100 hover:bg-red-100 text-red-700 border border-red-300' ?>"
                                            <?= $isActive ? 'disabled' : '' ?>>
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($terms)): ?>
                            <tr>
                                <td colspan="5" class="border px-3 py-6 text-center text-gray-600">No terms yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Cards on small screens -->
            <div class="md:hidden grid grid-cols-1 gap-3">
                <?php foreach ($terms as $t): ?>
                    <?php
                    $isActive = (int)$t['is_active'] === 1;
                    $confirmSetActive = $isActive ? 'true' : "confirm('Set " . addslashes($t['label']) . " as the active term?')";
                    $confirmDelete = $isActive
                        ? "alert('You cannot delete the active term.'); return false;"
                        : "return confirm('Delete this term? This cannot be undone.')";
                    ?>
                    <div class="border border-gray-200 rounded p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900"><?= h($t['label']) ?></h3>
                            <span class="text-[11px] px-2 py-0.5 rounded-full <?= $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="mt-2 text-sm text-gray-700"><?= fmt_date($t['starts_on']) ?> — <?= fmt_date($t['ends_on']) ?></div>
                        <div class="no-print mt-3 flex gap-2">
                            <form method="post" class="flex-1" onsubmit="return <?= $confirmSetActive ?>;">
                                <input type="hidden" name="term_id" value="<?= $t['id'] ?>">
                                <button type="submit" name="set_active"
                                    class="w-full <?= $isActive ? 'bg-gray-200 text-gray-700 cursor-default' : 'bg-red-600 hover:bg-red-700 text-white' ?> px-3 py-2 rounded text-sm">
                                    <?= $isActive ? 'Currently Active' : 'Set Active' ?>
                                </button>
                            </form>
                            <form method="post" class="flex-1" onsubmit="<?= htmlspecialchars($confirmDelete, ENT_QUOTES) ?>">
                                <input type="hidden" name="term_id" value="<?= $t['id'] ?>">
                                <button type="submit" name="delete_term"
                                    class="w-full px-3 py-2 rounded text-sm <?= $isActive ? 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed' : 'bg-gray-100 hover:bg-red-100 text-red-700 border border-red-300' ?>"
                                    <?= $isActive ? 'disabled' : '' ?>>
                                    <i class='bx bx-trash'></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <!-- Include the modal file -->
    <?php include __DIR__ . '/modals/add_term_modal.php'; ?>

    <script>
        // Flash fade
        const flash = document.getElementById('flash');
        if (flash) {
            setTimeout(() => {
                flash.style.transition = 'opacity .3s';
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 300);
            }, 3500);
        }

        // Modal open/close
        const modal = document.getElementById('addTermModal');
        const openBtn = document.getElementById('openAddTerm');
        const closeBtns = document.querySelectorAll('[data-close-add-term]');
        const body = document.body;

        function openModal() {
            modal.classList.remove('hidden');
            body.classList.add('modal-open');
        }

        function closeModal() {
            modal.classList.add('hidden');
            body.classList.remove('modal-open');
        }

        openBtn?.addEventListener('click', openModal);
        closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    </script>
</body>

</html>