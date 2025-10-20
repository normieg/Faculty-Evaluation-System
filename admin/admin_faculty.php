<?php
require __DIR__ . '/../database.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$PHOTO_DIR = dirname(__DIR__) . '/storage/faculty_profiles/';
if (!is_dir($PHOTO_DIR)) @mkdir($PHOTO_DIR, 0775, true);

/* --- simple photo upload (jpg/png) --- */
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

/* --- redirect helper for PRG --- */
function redirect_with($msg)
{
    $self = strtok($_SERVER['PHP_SELF'], '?'); // current script path
    header("Location: {$self}?msg=" . urlencode($msg));
    exit;
}

/* --- Load programs & sections (for Assign modal) --- */
$programs = [];
$res = mysqli_query($conn, "SELECT id, name FROM programs WHERE is_active=1 ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $programs[(int)$row['id']] = $row;

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
    $yl  = (int)$row['year_level'];
    if (!isset($sections_by_prog_year[$pid])) $sections_by_prog_year[$pid] = [];
    if (!isset($sections_by_prog_year[$pid][$yl])) $sections_by_prog_year[$pid][$yl] = [];
    $sections_by_prog_year[$pid][$yl][] = $row;
}

/* --- Handle actions (PRG: redirect after each) --- */
$msg = $_GET['msg'] ?? '';

if (isset($_POST['add'])) {
    $name = trim($_POST['full_name'] ?? '');
    if ($name !== '') {
        list($file, $err) = simple_upload_photo($_FILES['photo'] ?? [], $PHOTO_DIR, null);
        $file_sql = $file ? "'" . mysqli_real_escape_string($conn, $file) . "'" : "NULL";
        mysqli_query($conn, "INSERT INTO faculty(full_name, photo_url, is_active) VALUES('" . mysqli_real_escape_string($conn, $name) . "', $file_sql, 1)");
        redirect_with($err ?: 'Faculty added.');
    } else {
        redirect_with('Name is required.');
    }
}

if (isset($_POST['save_name'])) {
    $fid = (int)($_POST['faculty_id'] ?? 0);
    $newname = trim($_POST['new_full_name'] ?? '');
    if ($fid && $newname !== '') {
        mysqli_query($conn, "UPDATE faculty SET full_name='" . mysqli_real_escape_string($conn, $newname) . "' WHERE id=$fid");
        redirect_with('Name updated.');
    } else {
        redirect_with('Invalid request.');
    }
}

if (isset($_POST['upload_photo'])) {
    $fid = (int)($_POST['faculty_id'] ?? 0);
    if ($fid) {
        $old = '';
        $r = mysqli_query($conn, "SELECT photo_url FROM faculty WHERE id=$fid");
        if ($r && mysqli_num_rows($r)) $old = mysqli_fetch_assoc($r)['photo_url'] ?? '';
        list($file, $err) = simple_upload_photo($_FILES['photo'] ?? [], $PHOTO_DIR, $old ?: null);
        if ($file) {
            mysqli_query($conn, "UPDATE faculty SET photo_url='" . mysqli_real_escape_string($conn, $file) . "' WHERE id=$fid");
            redirect_with('Photo updated.');
        } else {
            redirect_with($err ?: 'No photo uploaded.');
        }
    } else {
        redirect_with('Invalid request.');
    }
}

if (isset($_POST['toggle_active'])) {
    $fid = (int)($_POST['faculty_id'] ?? 0);
    if ($fid) {
        mysqli_query($conn, "UPDATE faculty SET is_active=1-is_active WHERE id=$fid");
        redirect_with('Status updated.');
    } else {
        redirect_with('Invalid request.');
    }
}

if (isset($_POST['delete_faculty'])) {
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
        redirect_with('Faculty deleted.');
    } else {
        redirect_with('Invalid request.');
    }
}

if (isset($_POST['save_assign_eval'])) {
    $fid = (int)($_POST['faculty_id'] ?? 0);
    $pid = (int)($_POST['program_id'] ?? 0);
    $years = isset($_POST['year']) ? (array)$_POST['year'] : [];
    $sections = isset($_POST['section']) ? (array)$_POST['section'] : [];

    if ($fid && $pid) {
        mysqli_query($conn, "INSERT IGNORE INTO faculty_programs (faculty_id, program_id) VALUES ($fid, $pid)");

        mysqli_query($conn, "DELETE FROM faculty_program_years WHERE faculty_id=$fid AND program_id=$pid");
        foreach ($years as $yl) {
            $yl = (int)$yl;
            if ($yl >= 1 && $yl <= 6) {
                mysqli_query($conn, "INSERT INTO faculty_program_years (faculty_id, program_id, year_level) VALUES ($fid, $pid, $yl)");
            }
        }

        mysqli_query($conn, "DELETE FROM faculty_section_assignments WHERE faculty_id=$fid AND program_id=$pid");
        foreach ($sections as $sid) {
            $sid = (int)$sid;
            $rx = mysqli_query($conn, "SELECT year_level FROM sections WHERE id=$sid AND program_id=$pid");
            if ($rx && mysqli_num_rows($rx)) {
                $ylv = (int)mysqli_fetch_assoc($rx)['year_level'];
                mysqli_query($conn, "INSERT IGNORE INTO faculty_section_assignments (faculty_id, program_id, year_level, section_id) VALUES ($fid, $pid, $ylv, $sid)");
            }
        }
        redirect_with('Assignments saved.');
    } else {
        redirect_with('Invalid request.');
    }
}

/* --- Load faculty list --- */
$faculty = [];
$res = mysqli_query($conn, "SELECT id, full_name, photo_url, is_active FROM faculty ORDER BY full_name");
while ($row = mysqli_fetch_assoc($res)) $faculty[] = $row;
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

    <!-- Top bar -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-300 px-4 py-3 flex items-center justify-between lg:ml-64">
        <div class="flex items-center gap-3">
            <button type="button"
                class="lg:hidden inline-flex items-center justify-center rounded-md p-2 border border-gray-300"
                aria-label="Open sidebar"
                onclick="window.__openAdminSidebar && window.__openAdminSidebar()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <h1 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                <i class='bx bxs-user-voice text-red-600 text-2xl'></i>
                <span>Manage Faculty</span>
            </h1>
        </div>
    </div>

    <!-- Main -->
    <main class="lg:ml-64 p-4">
        <!-- Actions row -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <div class="relative">
                <input id="facultySearch" type="text"
                    class="w-full md:w-80 rounded border border-gray-300 px-3 py-2 pr-9 text-sm"
                    placeholder="Search faculty by name...">
                <i class='bx bx-search absolute right-3 top-2.5 text-gray-500 text-xl'></i>
            </div>

            <button onclick="openModal('addFacultyModal')"
                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded">
                <i class='bx bx-plus-circle text-lg'></i>
                <span>Add Faculty</span>
            </button>
        </div>

        <?php if (!empty($msg)): ?>
            <p class="bg-green-50 border border-green-400 text-green-800 p-3 mb-4 text-sm rounded flex items-center gap-2">
                <i class='bx bx-check-circle'></i>
                <span><?= htmlspecialchars($msg) ?></span>
            </p>
        <?php endif; ?>

        <?php if (empty($faculty)): ?>
            <div class="bg-white border border-gray-200 rounded p-6 text-center text-gray-600">
                <p class="mb-2">No faculty yet.</p>
                <button onclick="openModal('addFacultyModal')"
                    class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">
                    <i class='bx bx-plus-circle text-lg'></i> Add your first Faculty
                </button>
            </div>
        <?php else: ?>
            <div id="facultyList" class="space-y-3">
                <?php foreach ($faculty as $f):
                    $fid = (int)$f['id'];

                    // simple lookups for this faculty
                    $prog_ids = [];
                    $r = mysqli_query($conn, "SELECT program_id FROM faculty_programs WHERE faculty_id=$fid");
                    while ($x = mysqli_fetch_assoc($r)) $prog_ids[] = (int)$x['program_id'];

                    $years_map = []; // [program_id][year_level] = true
                    $r = mysqli_query($conn, "SELECT program_id, year_level FROM faculty_program_years WHERE faculty_id=$fid");
                    while ($x = mysqli_fetch_assoc($r)) {
                        $pid = (int)$x['program_id'];
                        $yl  = (int)$x['year_level'];
                        if (!isset($years_map[$pid])) $years_map[$pid] = [];
                        $years_map[$pid][$yl] = true;
                    }

                    $sections_map = []; // [program_id][year_level][] = section_id
                    $r = mysqli_query($conn, "SELECT program_id, year_level, section_id FROM faculty_section_assignments WHERE faculty_id=$fid");
                    while ($x = mysqli_fetch_assoc($r)) {
                        $pid = (int)$x['program_id'];
                        $yl  = (int)$x['year_level'];
                        $sid = (int)$x['section_id'];
                        if (!isset($sections_map[$pid])) $sections_map[$pid] = [];
                        if (!isset($sections_map[$pid][$yl])) $sections_map[$pid][$yl] = [];
                        $sections_map[$pid][$yl][] = $sid;
                    }

                    $photoRel = $f['photo_url'] ? "../storage/faculty_profiles/" . $f['photo_url'] : "";
                    $photoExists = $f['photo_url'] && file_exists($PHOTO_DIR . $f['photo_url']);
                    $isActive = (int)$f['is_active'] === 1;
                ?>
                    <div class="bg-white border border-gray-200 rounded p-4 faculty-card" data-name="<?= htmlspecialchars(mb_strtolower($f['full_name'])) ?>">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <?php if ($photoExists): ?>
                                    <img src="<?= $photoRel ?>" class="h-20 w-20 object-cover border rounded" alt="Faculty photo">
                                <?php else: ?>
                                    <div class="h-20 w-20 border rounded flex items-center justify-center text-[11px] text-gray-500">No photo</div>
                                <?php endif; ?>

                                <div>
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <span class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($f['full_name']) ?></span>
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
                            <button
                                class="bg-white border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded text-sm flex items-center gap-1"
                                onclick='openFacultyProfileModal(
                                    <?= $fid ?>,
                                    <?= json_encode($f['full_name']) ?>,
                                    <?= json_encode($photoExists ? $photoRel : "") ?>
                                )'
                                title="View or edit details">
                                <i class='bx bx-show'></i>
                                <span>View / Edit</span>
                            </button>

                            <button
                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm flex items-center gap-1"
                                onclick='openAssignModal(
                                    <?= $fid ?>,
                                    <?= json_encode($programs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                                    <?= json_encode($sections_by_prog_year, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                                    <?= json_encode($prog_ids, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                                    <?= json_encode($years_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                                    <?= json_encode($sections_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
                                )'
                                title="Assign to program/year/section for evaluation">
                                <i class='bx bx-task'></i>
                                <span>Assign for Evaluation</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/modals/add_faculty_modal.php'; ?>
    <?php include __DIR__ . '/modals/assign_faculty_modal.php'; ?>
    <?php include __DIR__ . '/modals/faculty_profile_modal.php'; ?>

</body>

</html>
<script src="../assets/js/admin-sidebar.js"></script>
<script src="../assets/js/faculty.js"></script>