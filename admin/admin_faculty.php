<?php
require __DIR__ . '/../database.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
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
        $name = trim($_POST['full_name'] ?? '');
        if ($name !== '') {
            list($file, $err) = simple_upload_photo($_FILES['photo'] ?? [], $PHOTO_DIR, null);
            $file_sql = $file ? "'" . mysqli_real_escape_string($conn, $file) . "'" : "NULL";
            mysqli_query($conn, "INSERT INTO faculty(full_name, photo_url, is_active)
                                VALUES('" . mysqli_real_escape_string($conn, $name) . "', $file_sql, 1)");
            $_SESSION['flash_msg'] = $err ?: 'Faculty added successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'Name is required.';
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: admin_faculty.php");
        exit;
    }

    // Rename
    if (isset($_POST['save_name'])) {
        $fid = (int)($_POST['faculty_id'] ?? 0);
        $newname = trim($_POST['new_full_name'] ?? '');
        if ($fid && $newname !== '') {
            mysqli_query($conn, "UPDATE faculty SET full_name='" . mysqli_real_escape_string($conn, $newname) . "' WHERE id=$fid");
            $_SESSION['flash_msg'] = 'Name updated successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'Invalid request.';
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: admin_faculty.php");
        exit;
    }

    // Upload photo
    if (isset($_POST['upload_photo'])) {
        $fid = (int)($_POST['faculty_id'] ?? 0);
        if ($fid) {
            $r = mysqli_query($conn, "SELECT photo_url FROM faculty WHERE id=$fid");
            $old = ($r && mysqli_num_rows($r)) ? mysqli_fetch_assoc($r)['photo_url'] ?? '' : '';
            list($file, $err) = simple_upload_photo($_FILES['photo'] ?? [], $PHOTO_DIR, $old ?: null);
            if ($file) {
                mysqli_query($conn, "UPDATE faculty SET photo_url='" . mysqli_real_escape_string($conn, $file) . "' WHERE id=$fid");
                $_SESSION['flash_msg'] = 'Photo updated.';
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_msg'] = $err ?: 'No photo uploaded.';
                $_SESSION['flash_type'] = 'error';
            }
        }
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

    // Delete
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

// Load faculty
$faculty = [];
$res = mysqli_query($conn, "SELECT id, full_name, photo_url, is_active FROM faculty $filter ORDER BY full_name ASC");
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

    <!-- ✅ Mobile/Tablet Top Bar -->
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

    <!-- ✅ Desktop Header -->
    <header class="hidden lg:flex fixed top-0 left-64 right-0 z-20 bg-white border-b border-gray-200 px-6 py-4 items-center justify-between">
        <div class="flex items-center gap-2">
            <i class='bx bxs-user-voice text-red-600 text-2xl'></i>
            <h1 class="text-xl font-semibold text-gray-800">Manage Faculty</h1>
        </div>
    </header>

    <main class="lg:ml-64 p-4 pt-16">

        <!-- Flash Message -->
        <?php if (!empty($msg)): ?>
            <div class="rounded border px-3 py-2 mb-4 text-sm flex items-center gap-2
            <?= $msg_type === 'success' ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800' ?>">
                <i class='bx <?= $msg_type === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?> text-xl'></i>
                <span><?= h($msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Search + Sort + Add -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 pt-3">
            <div class="flex items-center gap-2">
                <div class="relative">
                    <input id="facultySearch" type="text"
                        class="w-full md:w-80 rounded border border-gray-300 px-3 py-2 pr-9 text-sm"
                        placeholder="Search faculty by name...">
                    <i class='bx bx-search absolute right-3 top-2.5 text-gray-500 text-xl'></i>
                </div>

                <form method="get">
                    <select name="sort" onchange="this.form.submit()"
                        class="border border-gray-300 rounded px-2 py-2 text-sm">
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
                <button onclick="openModal('addFacultyModal')"
                    class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">
                    <i class='bx bx-plus-circle text-lg'></i> Add Faculty
                </button>
            </div>
        <?php else: ?>
            <div id="facultyList" class="space-y-3">
                <?php foreach ($faculty as $f):
                    $fid = (int)$f['id'];
                    $photoRel = $f['photo_url'] ? "../storage/faculty_profiles/" . $f['photo_url'] : "";
                    $photoExists = $f['photo_url'] && file_exists($PHOTO_DIR . $f['photo_url']);
                    $isActive = (int)$f['is_active'] === 1;
                ?>
                    <div class="bg-white border border-gray-200 rounded p-4 faculty-card"
                        data-name="<?= h(mb_strtolower($f['full_name'])) ?>">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <?php if ($photoExists): ?>
                                    <img src="<?= $photoRel ?>" class="h-20 w-20 object-cover border rounded" alt="Faculty photo">
                                <?php else: ?>
                                    <div class="h-20 w-20 border rounded flex items-center justify-center text-[11px] text-gray-500">No photo</div>
                                <?php endif; ?>

                                <div>
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <span class="text-xl font-semibold text-gray-900"><?= h($f['full_name']) ?></span>
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
                            <?= json_encode($f["full_name"]) ?>,
                            <?= json_encode($photoExists ? $photoRel : "") ?>
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
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/modals/add_faculty_modal.php'; ?>
    <?php include __DIR__ . '/modals/assign_faculty_modal.php'; ?>
    <?php include __DIR__ . '/modals/faculty_profile_modal.php'; ?>

    <script src="../assets/js/admin-sidebar.js"></script>
    <script src="../assets/js/faculty.js"></script>
</body>

</html>