<?php
require __DIR__ . '/../database.php';

// Start session safely with admin-specific session name to avoid collisions
if (session_status() === PHP_SESSION_NONE) {
    session_name('FES_ADMIN');
    session_start();
}

// Require admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// Flash message system
$msg = $_SESSION['flash_msg'] ?? '';
$msg_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Helper for escaping
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Capitalize name parts sensibly (handles spaces and hyphens, preserves separators)
function cap_name($s)
{
    if ($s === null) return '';
    $s = trim((string)$s);
    if ($s === '') return '';

    // Split into parts while keeping separators
    preg_match_all('/[^\s-]+|[\s-]+/', $s, $tokens);
    $out = '';
    foreach ($tokens[0] as $tok) {
        if (preg_match('/^[\s-]+$/', $tok)) {
            $out .= $tok; // separator
        } else {
            if (preg_match('/^(mc|mac)(.+)$/i', $tok, $m)) {
                $combined = $m[1] . $m[2];
                $out .= ucfirst(mb_strtolower($combined, 'UTF-8'));
            } else {
                $out .= ucfirst(mb_strtolower($tok, 'UTF-8'));
            }
        }
    }
    return $out;
}

// Build a nicely formatted full name from parts
function build_fullname($row)
{
    $first  = trim($row['first_name'] ?? '');
    $middle = trim($row['middle_name'] ?? '');
    $last   = trim($row['last_name'] ?? '');
    $suffix = trim($row['suffix'] ?? '');
    $mid_i  = $middle !== '' ? $middle : '';
    $parts  = array_filter([$first, $mid_i, $last], fn($x) => $x !== '');
    $name   = trim(implode(' ', $parts));
    if ($suffix !== '') $name .= ' ' . $suffix;
    return trim($name);
}

// Split a single full name into best-guess parts (fallback for old forms)
function split_fullname($full)
{
    $full = trim(preg_replace('/\s+/', ' ', (string)$full));
    if ($full === '') return ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'suffix' => ''];

    // Common suffixes
    $suffixes = ['Jr', 'Jr.', 'Sr', 'Sr.', 'III', 'IV', 'V'];
    $parts = explode(' ', $full);

    $suffix = '';
    if (count($parts) >= 2) {
        $lastToken = $parts[count($parts) - 1];
        if (in_array($lastToken, $suffixes, true)) {
            $suffix = $lastToken;
            array_pop($parts);
        }
    }

    if (count($parts) === 1) {
        return [
            'first_name'  => $parts[0],
            'middle_name' => '',
            'last_name'   => '',
            'suffix'      => $suffix
        ];
    }

    // Assume last token is last_name, first is first_name, middle rest = middle_name
    $first = array_shift($parts);
    $last  = array_pop($parts);
    $middle = implode(' ', $parts);

    return [
        'first_name'  => $first,
        'middle_name' => $middle,
        'last_name'   => $last,
        'suffix'      => $suffix
    ];
}

// Setup upload directory
$PHOTO_DIR = dirname(__DIR__) . '/storage/faculty_profiles/';
if (!is_dir($PHOTO_DIR)) @mkdir($PHOTO_DIR, 0775, true);

// Upload helper
function simple_upload_photo($file, $dir, $old = null)
{
    if (!isset($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, 'Upload error'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) return [null, 'Only JPG/PNG allowed'];
    $new = 'fac_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $new)) return [null, 'Failed to move file'];
    if ($old && file_exists($dir . $old)) @unlink($dir . $old);
    return [$new, null];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add
    if (isset($_POST['add'])) {
        // New preferred fields
        $first  = trim($_POST['first_name'] ?? '');
        $middle = trim($_POST['middle_name'] ?? '');
        $last   = trim($_POST['last_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');

        // Backward compat: if still posting full_name, split it
        if ($first === '' && $last === '' && isset($_POST['full_name'])) {
            $parts = split_fullname($_POST['full_name']);
            $first  = $parts['first_name'];
            $middle = $parts['middle_name'];
            $last   = $parts['last_name'];
            $suffix = $parts['suffix'];
        }

        if ($first !== '' && $last !== '') {
            list($file, $err) = simple_upload_photo($_FILES['photo'] ?? [], $PHOTO_DIR, null);
            $file_sql = $file ? "'" . mysqli_real_escape_string($conn, $file) . "'" : "NULL";

            $q = sprintf(
                "INSERT INTO faculty(first_name, middle_name, last_name, suffix, photo_url, is_active)
                 VALUES('%s','%s','%s','%s', %s, 1)",
                mysqli_real_escape_string($conn, $first),
                mysqli_real_escape_string($conn, $middle),
                mysqli_real_escape_string($conn, $last),
                mysqli_real_escape_string($conn, $suffix),
                $file_sql
            );
            mysqli_query($conn, $q);

            $_SESSION['flash_msg'] = $err ?: 'Faculty added successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'First name and Last name are required.';
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: admin_faculty.php");
        exit;
    }

    // Save (names + optional photo) from the single-submit modal
    if (isset($_POST['save_profile'])) {
        // Log incoming POST for debugging if something's wrong
        error_log('save_profile POST: ' . print_r($_POST, true));

        $fid = isset($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : 0;
        if ($fid <= 0) {
            $_SESSION['flash_msg']  = 'Invalid faculty ID.';
            $_SESSION['flash_type'] = 'error';
            header("Location: admin_faculty.php");
            exit;
        }

        // Load current values
        $res = mysqli_query($conn, "SELECT first_name, middle_name, last_name, suffix, photo_url FROM faculty WHERE id={$fid}");
        if (!$res || !mysqli_num_rows($res)) {
            $_SESSION['flash_msg']  = 'Faculty not found.';
            $_SESSION['flash_type'] = 'error';
            header("Location: admin_faculty.php");
            exit;
        }
        $cur = mysqli_fetch_assoc($res);

        $updates = [];

        // Use isset to detect which fields were submitted; inputs exist in modal so they will be set.
        if (array_key_exists('new_first_name', $_POST)) {
            $v = trim($_POST['new_first_name']);
            if ($v === '') {
                // Do not allow clearing first name
                // If admin left it blank accidentally, keep current value
            } elseif ($v !== $cur['first_name']) {
                $updates[] = "first_name='" . mysqli_real_escape_string($conn, cap_name($v)) . "'";
            }
        }

        if (array_key_exists('new_middle_name', $_POST)) {
            $v = trim($_POST['new_middle_name']);
            // Only update middle name when a non-empty value is provided and differs from current
            if ($v !== '' && $v !== $cur['middle_name']) {
                $updates[] = "middle_name='" . mysqli_real_escape_string($conn, cap_name($v)) . "'";
            }
            // If admin clears the field (empty), we intentionally leave current value unchanged to avoid accidental data loss.
        }

        if (array_key_exists('new_last_name', $_POST)) {
            $v = trim($_POST['new_last_name']);
            if ($v === '') {
                // Do not allow clearing last name
            } elseif ($v !== $cur['last_name']) {
                $updates[] = "last_name='" . mysqli_real_escape_string($conn, cap_name($v)) . "'";
            }
        }

        if (array_key_exists('new_suffix', $_POST)) {
            $v = trim($_POST['new_suffix']);
            // Only update suffix when a non-empty value is provided and differs from current
            if ($v !== '' && $v !== $cur['suffix']) {
                $updates[] = "suffix='" . mysqli_real_escape_string($conn, $v) . "'";
            }
            // If admin clears the suffix field, leave it unchanged to avoid accidental removal.
        }

        // Photo handling (optional)
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $old = $cur['photo_url'] ?? '';
            list($file, $err) = simple_upload_photo($_FILES['photo'], $PHOTO_DIR, $old ?: null);
            if ($file) {
                $updates[] = "photo_url='" . mysqli_real_escape_string($conn, $file) . "'";
            } else {
                // If there are other updates, apply them and show photo error; otherwise show photo error
                if (!empty($updates)) {
                    mysqli_query($conn, "UPDATE faculty SET " . implode(', ', $updates) . " WHERE id={$fid}");
                    $_SESSION['flash_msg']  = 'Saved, but photo upload failed: ' . $err;
                    $_SESSION['flash_type'] = 'error';
                    header("Location: admin_faculty.php");
                    exit;
                }
                $_SESSION['flash_msg']  = 'Photo upload failed: ' . ($err ?: 'Unknown error');
                $_SESSION['flash_type'] = 'error';
                header("Location: admin_faculty.php");
                exit;
            }
        }

        if (!empty($updates)) {
            mysqli_query($conn, "UPDATE faculty SET " . implode(', ', $updates) . " WHERE id={$fid}");
            $_SESSION['flash_msg']  = 'Profile saved.';
            $_SESSION['flash_type'] = 'success';
        } else {
            // No fields changed; still allow this as a successful no-op
            $_SESSION['flash_msg']  = 'No changes to save.';
            $_SESSION['flash_type'] = 'info';
        }

        header("Location: admin_faculty.php");
        exit;
    }

    // Save assignments from Assign for Evaluation modal
    if (isset($_POST['save_assign_eval'])) {
        // Debug incoming POST to help diagnose missing section assignments
        error_log('save_assign_eval POST: ' . print_r($_POST, true));
        $fid = (int)($_POST['faculty_id'] ?? 0);
        $pid = (int)($_POST['program_id'] ?? 0);

        if ($fid <= 0 || $pid <= 0) {
            $_SESSION['flash_msg'] = 'Invalid faculty or program.';
            $_SESSION['flash_type'] = 'error';
            header("Location: admin_faculty.php");
            exit;
        }

        // Normalize inputs
        $years = isset($_POST['year']) && is_array($_POST['year']) ? array_map('intval', $_POST['year']) : [];
        $sections = isset($_POST['section']) && is_array($_POST['section']) ? array_map('intval', $_POST['section']) : [];

        // Remove previous mappings for this faculty+program to simplify re-insert
        mysqli_query($conn, "DELETE FROM faculty_programs WHERE faculty_id={$fid} AND program_id={$pid}");
        mysqli_query($conn, "DELETE FROM faculty_program_years WHERE faculty_id={$fid} AND program_id={$pid}");
        mysqli_query($conn, "DELETE FROM faculty_section_assignments WHERE faculty_id={$fid} AND program_id={$pid}");

        // Always add a faculty_programs row to indicate faculty belongs to the program
        mysqli_query($conn, "INSERT INTO faculty_programs (faculty_id, program_id) VALUES ({$fid}, {$pid})");

        // If year levels were selected, insert them (restricts to those years)
        if (!empty($years)) {
            $years = array_unique($years);
            foreach ($years as $yl) {
                $yl = (int)$yl;
                if ($yl <= 0) continue;
                mysqli_query($conn, "INSERT INTO faculty_program_years (faculty_id, program_id, year_level) VALUES ({$fid}, {$pid}, {$yl})");
            }
        }

        // If sections were selected, insert section-level assignments (these restrict which sections a faculty covers)
        if (!empty($sections)) {
            $sections = array_unique($sections);
            // Fetch year levels for selected sections (defensive)
            $in = implode(',', array_map('intval', $sections));
            $secRes = mysqli_query($conn, "SELECT id, year_level FROM sections WHERE id IN ({$in})");
            if ($secRes) {
                while ($r = mysqli_fetch_assoc($secRes)) {
                    $sid = (int)$r['id'];
                    $yl = (int)$r['year_level'];
                    $q = "INSERT INTO faculty_section_assignments (faculty_id, program_id, year_level, section_id) VALUES ({$fid}, {$pid}, {$yl}, {$sid})";
                    mysqli_query($conn, $q);
                    if (mysqli_errno($conn)) {
                        error_log('save_assign_eval - insert failed: ' . mysqli_error($conn) . ' -- SQL: ' . $q);
                    } else {
                        error_log('save_assign_eval - inserted: ' . $q);
                    }
                }
            } else {
                error_log('save_assign_eval - no sections found for IN: ' . $in);
            }
        } else {
            error_log('save_assign_eval - no sections posted');
        }

        $_SESSION['flash_msg'] = 'Assignments saved.';
        $_SESSION['flash_type'] = 'success';
        header("Location: admin_faculty.php");
        exit;
    }


    // Toggle active
    if (isset($_POST['toggle_active'])) {
        $fid = (int)($_POST['faculty_id'] ?? 0);
        if ($fid) {
            mysqli_query($conn, "UPDATE faculty SET is_active=1-is_active WHERE id=$fid");
            $_SESSION['flash_msg'] = 'Status updated.';
            $_SESSION['flash_type'] = 'success';
        }
        header("Location: admin_faculty.php");
        exit;
    }

    // Delete (supports either traditional delete_faculty POST or new do_delete flag from modal)
    if (isset($_POST['delete_faculty']) || (isset($_POST['do_delete']) && $_POST['do_delete'] == '1')) {
        $fid = (int)($_POST['faculty_id'] ?? 0);
        if ($fid) {
            $r = mysqli_query($conn, "SELECT photo_url FROM faculty WHERE id=$fid");
            if ($r && mysqli_num_rows($r)) {
                $old = mysqli_fetch_assoc($r)['photo_url'] ?? '';
                if ($old && file_exists($PHOTO_DIR . $old)) @unlink($PHOTO_DIR . $old);
            }
            mysqli_query($conn, "DELETE FROM faculty WHERE id=$fid");
            mysqli_query($conn, "DELETE FROM faculty_programs WHERE faculty_id=$fid");
            mysqli_query($conn, "DELETE FROM faculty_program_years WHERE faculty_id=$fid");
            mysqli_query($conn, "DELETE FROM faculty_section_assignments WHERE faculty_id=$fid");
            $_SESSION['flash_msg'] = 'Faculty deleted.';
            $_SESSION['flash_type'] = 'success';
        }
        header("Location: admin_faculty.php");
        exit;
    }
}

// Sorting filter
$sort = $_GET['sort'] ?? 'all';
switch ($sort) {
    case 'active':
        $filter = "WHERE is_active=1";
        break;
    case 'inactive':
        $filter = "WHERE is_active=0";
        break;
    default:
        $filter = "";
        break;
}

// Load programs & sections for modals
$programs = [];
$res = mysqli_query($conn, "SELECT id, name FROM programs WHERE is_active=1 ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $programs[$row['id']] = $row;

$sections_by_prog_year = [];
$res = mysqli_query($conn, "
    SELECT s.id, s.program_id, s.year_level, s.code, p.name AS program_name
    FROM sections s
    JOIN programs p ON p.id = s.program_id
    WHERE p.is_active=1
    ORDER BY p.name, s.year_level, s.code
");
while ($row = mysqli_fetch_assoc($res)) {
    $pid = (int)$row['program_id'];
    $yl = (int)$row['year_level'];
    $sections_by_prog_year[$pid][$yl][] = $row;
}

// Load faculty (order by last, first, middle)
$faculty = [];
$res = mysqli_query($conn, "
    SELECT id, first_name, middle_name, last_name, suffix, photo_url, is_active
    FROM faculty
    $filter
    ORDER BY last_name ASC, first_name ASC, middle_name ASC
");
while ($row = mysqli_fetch_assoc($res)) {
    // Store the full name for display + search
    $row['full_name'] = build_fullname($row);

    // Store individual name parts for the edit modal
    $row['name_parts'] = [
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'],
        'last_name' => $row['last_name'],
        'suffix' => $row['suffix']
    ];
    $faculty[] = $row;
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Manage Faculty</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <?php $active = 'faculty';
    include __DIR__ . '/partials/sidebar.php'; ?>

    <!--  Mobile/Tablet Top Bar -->
    <header class="fixed top-0 left-0 right-0 z-30 bg-white border-b border-gray-200 lg:hidden">
        <div class="flex items-center gap-3 px-3 py-2">
            <button type="button"
                class="inline-flex items-center justify-center p-2 rounded hover:bg-gray-100"
                aria-label="Open menu"
                onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <i class='bx bx-menu text-2xl'></i>
            </button>
            <div class="flex items-center gap-2">
                <i class='bx bxs-user-voice text-red-600 text-2xl'></i>
                <h1 class="text-lg font-semibold text-gray-800">Manage Faculty</h1>
            </div>
        </div>
    </header>

    <!-- Desktop Header -->
    <header class="hidden lg:flex fixed top-0 left-64 right-0 z-20 bg-white border-b border-gray-200 px-6 py-4 items-center justify-between">
        <div class="flex items-center gap-2">
            <i class='bx bxs-user-voice text-red-600 text-2xl'></i>
            <h1 class="text-xl font-semibold text-gray-800">Manage Faculty</h1>
        </div>
    </header>

    <main class="lg:ml-64 p-4 pt-16">

        <div class="pt-2">
            <!-- Flash Message -->
            <?php if (!empty($msg)): ?>
                <div class="rounded border px-3 py-2 text-sm flex items-center gap-2
            <?= $msg_type === 'success' ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800' ?>">
                    <i class='bx <?= $msg_type === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?> text-xl'></i>
                    <span><?= h($msg) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <!-- Search + Sort + Add -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 pt-2">
            <div class="flex items-center gap-2">
                <div class="relative">
                    <input id="facultySearch" type="text"
                        class="w-full md:w-80 rounded border border-gray-300 px-3 py-2 pr-9 text-sm"
                        placeholder="Search faculty by name...">
                    <i class='bx bx-search absolute right-3 top-2.5 text-gray-500 text-xl'></i>
                </div>

                <form method="get">
                    <select name="sort" onchange="this.form.submit()
                        " class="border border-gray-300 rounded px-2 py-2 text-sm">
                        <option value="all" <?= $sort === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="active" <?= $sort === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $sort === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </form>
            </div>

            <div class="pt-5 lg:pt-0">
                <button onclick="openModal('addFacultyModal')"
                    class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded">
                    <i class='bx bx-plus-circle text-lg'></i>
                    <span>Add Faculty</span>
                </button>
            </div>
        </div>

        <?php if (empty($faculty)): ?>
            <div class="bg-white border rounded p-6 text-center text-gray-600">
                <p class="mb-2">No faculty found.</p>
            </div>
        <?php else: ?>
            <div id="facultyList" class="space-y-3">
                <?php foreach ($faculty as $f):
                    $fid = (int)$f['id'];
                    $photoRel = $f['photo_url'] ? "../storage/faculty_profiles/" . $f['photo_url'] : "";
                    $photoExists = $f['photo_url'] && file_exists($PHOTO_DIR . $f['photo_url']);
                    $isActive = (int)$f['is_active'] === 1;
                    $full = $f['full_name'];
                    $nameParts = [
                        'first_name'  => $f['first_name'] ?? '',
                        'middle_name' => $f['middle_name'] ?? '',
                        'last_name'   => $f['last_name'] ?? '',
                        'suffix'      => $f['suffix'] ?? ''
                    ];
                    $searchKey = mb_strtolower(trim(implode(' ', array_filter([
                        $f['first_name'] ?? '',
                        $f['middle_name'] ?? '',
                        $f['last_name'] ?? '',
                        $f['suffix'] ?? ''
                    ], fn($x) => $x !== ''))));
                ?>
                    <div class="bg-white border border-gray-200 rounded p-4 faculty-card"
                        data-name="<?= h($searchKey) ?>">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <?php if ($photoExists): ?>
                                    <img src="<?= $photoRel ?>" class="h-20 w-20 object-cover border rounded" alt="Faculty photo">
                                <?php else: ?>
                                    <div class="h-20 w-20 border rounded flex items-center justify-center text-[11px] text-gray-500">No photo</div>
                                <?php endif; ?>

                                <div>
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <span class="text-xl font-semibold text-gray-900"><?= h($full) ?></span>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs
                                            <?= $isActive ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-gray-100 text-gray-700 border border-gray-200' ?>">
                                            <i class='bx <?= $isActive ? 'bx-check-circle' : 'bx-pause-circle' ?> text-sm'></i>
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <form method="post" class="shrink-0">
                                <input type="hidden" name="faculty_id" value="<?= $fid ?>">
                                <button name="toggle_active"
                                    class="<?= $isActive ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-600 hover:bg-gray-700' ?> text-white px-3 py-2 rounded flex items-center gap-1 text-sm"
                                    title="Toggle active status">
                                    <i class='<?= $isActive ? 'bx bx-check-circle' : 'bx bx-pause-circle' ?>'></i>
                                    <span><?= $isActive ? 'Set Inactive' : 'Set Active' ?></span>
                                </button>
                            </form>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <!-- View/Edit -->
                            <button
                                class="bg-white border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded text-sm flex items-center gap-1"
                                onclick='openFacultyProfileModal(
                                    <?= $fid ?>,
                                    <?= json_encode($full) ?>,
                                    <?= json_encode($photoExists ? $photoRel : "") ?>,
                                    <?= json_encode($nameParts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
                                )'
                                title="View or edit details">
                                <i class="bx bx-show"></i>
                                <span>View / Edit</span>
                            </button>

                            <!-- Assign -->
                            <button
                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm flex items-center gap-1"
                                onclick='openAssignModal(
                                    <?= $fid ?>,
                                    <?= json_encode($programs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                                    <?= json_encode($sections_by_prog_year, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                                    [],
                                    [],
                                    []
                                )'
                                title="Assign for Evaluation">
                                <i class="bx bx-task"></i>
                                <span>Assign for Evaluation</span>
                            </button>
                        </div>
                    </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </main>

    <?php include __DIR__ . '/modals/add_faculty_modal.php'; ?>
    <?php include __DIR__ . '/modals/assign_faculty_modal.php'; ?>
    <?php include __DIR__ . '/modals/faculty_profile_modal.php'; ?>

    <script src="../assets/js/admin-sidebar.js"></script>
    <script src="../assets/js/faculty.js"></script>

    <script>
        (function() {
            const q = document.getElementById('facultySearch');
            if (!q) return;
            q.addEventListener('input', function() {
                const term = (q.value || '').trim().toLowerCase();
                document.querySelectorAll('.faculty-card').forEach(card => {
                    const name = (card.getAttribute('data-name') || '').toLowerCase();
                    card.style.display = name.includes(term) ? '' : 'none';
                });
            });
        })();
    </script>
</body>

</html>