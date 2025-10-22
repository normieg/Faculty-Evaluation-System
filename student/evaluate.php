<?php
require __DIR__ . '/../database.php';

// Ensure student area uses its own session namespace and start session before accessing $_SESSION
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('FES_STUDENT');
    session_start();
}

if (!defined('ANON_SALT')) {
    define('ANON_SALT', 'change-this-to-a-long-random-secret');
}

// Require login
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$err = '';
$ok  = '';

// Get active term
$term_id = 0;
$term_label = 'No Active Term';
$tr = mysqli_query($conn, "SELECT id, label FROM terms WHERE is_active=1 LIMIT 1");
if ($tr && mysqli_num_rows($tr) === 1) {
    $t = mysqli_fetch_assoc($tr);
    $term_id = (int)$t['id'];
    $term_label = $t['label'];
}

// Faculty id from querystring
$fid = isset($_GET['fid']) ? (int)$_GET['fid'] : 0;

// Load faculty
$faculty = null;
if ($fid > 0) {
    $fr = mysqli_query($conn, "SELECT id, first_name, middle_name, last_name, suffix, photo_url, is_active FROM faculty WHERE id='$fid' LIMIT 1");
    if ($fr && mysqli_num_rows($fr) === 1) {
        $faculty = mysqli_fetch_assoc($fr);
        // build full display name
        $first = trim($faculty['first_name'] ?? '');
        $middle = trim($faculty['middle_name'] ?? '');
        $last = trim($faculty['last_name'] ?? '');
        $suffix = trim($faculty['suffix'] ?? '');
        $parts = array_filter([$first, $middle, $last], fn($x) => $x !== '');
        $display = trim(implode(' ', $parts));
        if ($suffix !== '') $display .= ' ' . $suffix;
        $faculty['full_name'] = $display ?: '(No name)';
    }
}

if (!$faculty || !$faculty['is_active']) {
    $err = "Faculty not found or inactive.";
}

// Build rater_hash (only if term exists)
$school_id  = $_SESSION['school_id'] ?? '';
$rater_hash = ($term_id > 0 && $school_id !== '') ? hash('sha256', $school_id . '|' . $term_id . '|' . ANON_SALT) : '';

// Load sections and criteria (ordered)
$sections = [];
$criteria = [];
if (!$err) {
    $sr = mysqli_query($conn, "SELECT id, title, weight_pct FROM eval_sections ORDER BY sort_order ASC, id ASC");
    while ($row = mysqli_fetch_assoc($sr)) {
        $sections[(int)$row['id']] = $row;
        $criteria[(int)$row['id']] = [];
    }
    if (!empty($sections)) {
        $cr = mysqli_query($conn, "
            SELECT id, section_id, prompt
            FROM eval_criteria
            ORDER BY section_id ASC, sort_order ASC, id ASC
        ");
        while ($row = mysqli_fetch_assoc($cr)) {
            $sid = (int)$row['section_id'];
            if (isset($criteria[$sid])) $criteria[$sid][] = $row;
        }
    }
}

// Check if already evaluated
$already = false;
if (!$err && $term_id > 0 && $rater_hash) {
    $chk = mysqli_query($conn, "
        SELECT id FROM evaluations
        WHERE faculty_id='$fid' AND term_id='$term_id' AND rater_hash='$rater_hash' LIMIT 1
    ");
    $already = $chk && mysqli_num_rows($chk) > 0;
}

// for optional server-side scroll-point
$first_missing_cid = 0;

// Handle submit
if (!$err && isset($_POST['submit_eval'])) {
    if ($term_id <= 0) {
        $err = "No active term. Please contact admin.";
    } elseif ($already) {
        $err = "You've already submitted an evaluation for this faculty this term.";
    } else {
        $scores = isset($_POST['score']) ? $_POST['score'] : [];
        $like_because    = trim($_POST['like_because'] ?? '');
        $dislike_because = trim($_POST['dislike_because'] ?? '');
        $suggest_will    = trim($_POST['suggest_will'] ?? '');

        // Validate every criterion has 1..5
        $totalCriteria = 0;
        foreach ($criteria as $sid => $list) $totalCriteria += count($list);
        $validCount = 0;

        // Build lookups so we can reference "Test X question #Y"
        $crit_index = [];       // criterion_id => 1-based index inside its section
        $crit_section = [];     // criterion_id => section title
        foreach ($criteria as $sid2 => $list2) {
            foreach ($list2 as $idx2 => $c2) {
                $crit_index[(int)$c2['id']] = $idx2 + 1;
                $crit_section[(int)$c2['id']] = $sections[$sid2]['title'] ?? 'Section';
            }
        }

        foreach ($criteria as $list) {
            foreach ($list as $c) {
                $cid = (int)$c['id'];
                if (isset($scores[$cid]) && in_array((int)$scores[$cid], [1, 2, 3, 4, 5], true)) {
                    $validCount++;
                } else {
                    if ($first_missing_cid === 0) $first_missing_cid = $cid;
                }
            }
        }

        if ($totalCriteria === 0) {
            $err = "Evaluation form is not configured.";
        } elseif ($validCount !== $totalCriteria) {
            if ($first_missing_cid) {
                $secTitle = $crit_section[$first_missing_cid] ?? 'the form';
                $qNum     = $crit_index[$first_missing_cid] ?? '';
                $err = "Please fill out all items in Test 1 and Test 2. First missing: {$secTitle}, question #{$qNum}.";
            } else {
                $err = "Please answer all items before submitting.";
            }
        } else {
            // Save in a transaction
            mysqli_begin_transaction($conn);
            try {
                $ins = mysqli_query($conn, "
                    INSERT INTO evaluations (faculty_id, term_id, rater_hash, like_because, dislike_because, suggest_will)
                    VALUES (
                        '$fid',
                        '$term_id',
                        '$rater_hash',
                        '" . mysqli_real_escape_string($conn, $like_because) . "',
                        '" . mysqli_real_escape_string($conn, $dislike_because) . "',
                        '" . mysqli_real_escape_string($conn, $suggest_will) . "'
                    )
                ");
                if (!$ins) throw new Exception("Failed to save evaluation.");

                $eval_id = (int)mysqli_insert_id($conn);

                foreach ($criteria as $list) {
                    foreach ($list as $c) {
                        $cid = (int)$c['id'];
                        $sc  = (int)$scores[$cid];
                        $okQ = mysqli_query($conn, "
                            INSERT INTO evaluation_scores (evaluation_id, criterion_id, score)
                            VALUES ('$eval_id', '$cid', '$sc')
                        ");
                        if (!$okQ) throw new Exception("Failed to save scores.");
                    }
                }
                mysqli_commit($conn);

                // Redirect to avoid double submit on refresh
                header("Location: " . basename(__FILE__) . "?fid=" . $fid . "&ok=1");
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $err = "Error saving evaluation. Please try again.";
            }
        }
    }
}

if (isset($_GET['ok']) && $_GET['ok'] == '1') {
    $ok = "Thank you! Your evaluation has been submitted.";
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Faculty Evaluation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

    <!-- Top bar -->
    <div class="bg-red-600 text-white px-3 py-2 flex items-center space-x-2">
        <a href="student_dashboard.php" class="bg-red-700 hover:bg-red-800 px-2 py-1 rounded flex items-center">
            <i class='bx bx-left-arrow-alt text-xl'></i>
        </a>
        <span class="font-semibold">Faculty Evaluation</span>
        <span class="ml-auto text-xs opacity-90">Active Term: <?= htmlspecialchars($term_label) ?></span>
    </div>

    <div class="max-w-3xl mx-auto p-3">
        <!-- Card -->
        <div class="bg-white border border-gray-300 rounded p-3">

            <!-- JS-driven top alert (for first missing pointer) -->
            <div id="topAlert"
                class="mb-3 bg-red-100 border border-red-400 text-red-800 p-2 rounded text-sm flex items-center space-x-2 hidden">
                <i class='bx bxs-error-circle'></i>
                <span></span>
            </div>

            <!-- Server-side alerts -->
            <?php if (!empty($err)): ?>
                <div class="mb-3 bg-red-100 border border-red-400 text-red-800 p-2 rounded text-sm flex items-center space-x-2">
                    <i class='bx bxs-error-circle'></i><span><?= htmlspecialchars($err) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($ok)): ?>
                <div class="mb-3 bg-green-100 border border-green-400 text-green-800 p-2 rounded text-sm flex items-center space-x-2">
                    <i class='bx bx-check-circle'></i><span><?= htmlspecialchars($ok) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($faculty && !$already && $term_id > 0): ?>
                <!-- Header info -->
                <div class="flex items-start space-x-3 mb-3">
                    <?php
                    $photo = $faculty['photo_url'] ? "../storage/faculty_profiles/" . $faculty['photo_url'] : null;
                    $exists = $photo && file_exists(dirname(__DIR__) . "/storage/faculty_profiles/" . $faculty['photo_url']);
                    ?>
                    <?php if ($exists): ?>
                        <img src="<?= htmlspecialchars($photo) ?>" class="h-16 w-16 rounded-full object-cover border" alt="Faculty photo">
                    <?php else: ?>
                        <div class="h-16 w-16 rounded-full border flex items-center justify-center text-xs text-gray-500">No photo</div>
                    <?php endif; ?>
                    <div>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($faculty['full_name']) ?></p>
                        <p class="text-xs text-gray-600">Please answer honestly and thoughtfully. Your responses are confidential.</p>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="border border-gray-300 rounded p-3 mb-3">
                    <p class="font-semibold text-sm text-gray-700 mb-2">Instructions:</p>
                    <p class="text-sm text-gray-700">
                        Select a rating for each statement. Do not write your name. Your feedback helps improve teaching quality.
                    </p>
                    <div class="mt-3">
                        <p class="text-sm font-semibold text-gray-700 mb-1">Rating Interpretation:</p>
                        <div class="flex items-center space-x-3 text-xs text-gray-700">
                            <span>1 = Never</span>
                            <span>2 = Once in a while</span>
                            <span>3 = Sometimes</span>
                            <span>4 = Often</span>
                            <span>5 = Always</span>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form method="post" action="" id="evalForm" novalidate>
                    <?php foreach ($sections as $sid => $sec): ?>
                        <div class="border border-gray-300 rounded p-3 mb-3">
                            <p class="font-semibold text-gray-800 mb-2">
                                <?= htmlspecialchars($sec['title']) ?> (<?= (float)$sec['weight_pct'] ?>%)
                            </p>

                            <?php if (empty($criteria[$sid])): ?>
                                <p class="text-sm text-gray-600">No items defined.</p>
                            <?php else: ?>
                                <?php foreach ($criteria[$sid] as $idx => $c): ?>
                                    <div
                                        class="criterion border border-gray-200 rounded p-2 mb-2"
                                        id="crit-<?= (int)$c['id'] ?>"
                                        data-section="<?= htmlspecialchars($sec['title']) ?>"
                                        data-qnum="<?= ($idx + 1) ?>">
                                        <div class="text-sm text-gray-800 mb-2">
                                            <?= ($idx + 1) ?>. <?= htmlspecialchars($c['prompt']) ?>
                                        </div>
                                        <!-- Radios 1..5 -->
                                        <div class="flex items-center space-x-2">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <label class="flex items-center justify-center h-8 w-8 border rounded-full cursor-pointer">
                                                    <input type="radio"
                                                        name="score[<?= (int)$c['id'] ?>]"
                                                        value="<?= $s ?>"
                                                        class="hidden"
                                                        required>
                                                    <span class="text-sm"><?= $s ?></span>
                                                </label>
                                            <?php endfor; ?>
                                            <span class="ml-auto text-xs text-gray-500"></span>
                                        </div>
                                        <!-- Inline error -->
                                        <p class="crit-error mt-1 text-xs text-red-600 hidden">Please answer this question.</p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Additional Feedback -->
                    <div class="border border-gray-300 rounded p-3 mb-3">
                        <p class="font-semibold text-gray-800 mb-2">Test 3: Additional Feedback</p>

                        <div class="mb-3">
                            <label class="block text-sm text-gray-700 mb-1">1. I like my instructor because:</label>
                            <textarea name="like_because" rows="3" class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="Share what you appreciate about your instructor..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="block text-sm text-gray-700 mb-1">2. I do not like my instructor because:</label>
                            <textarea name="dislike_because" rows="3" class="w-full border border-gray-300 rounded p-2 text-sm"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-700 mb-1">3. I suggest that my instructor will:</label>
                            <textarea name="suggest_will" rows="3" class="w-full border border-gray-300 rounded p-2 text-sm"></textarea>
                        </div>
                    </div>

                    <button type="submit" name="submit_eval"
                        class="mx-auto block bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                        SUBMIT EVALUATION
                    </button>
                </form>
            <?php elseif ($already): ?>
                <p class="text-sm text-gray-700">You have already submitted an evaluation for this faculty this term.</p>
            <?php else: ?>
                <p class="text-sm text-gray-700">Unable to load the evaluation form.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Simple visual selection effect: make the chosen circle red text + border
        document.addEventListener('change', function(e) {
            if (e.target && e.target.type === 'radio') {
                const container = e.target.closest('.flex.items-center.space-x-2');
                if (!container) return;
                // Reset
                container.querySelectorAll('label').forEach(l => {
                    l.classList.remove('border-red-600', 'text-red-600');
                    l.classList.add('border-gray-300');
                });
                // Style selected
                const chosen = e.target.closest('label');
                chosen.classList.remove('border-gray-300');
                chosen.classList.add('border-red-600', 'text-red-600');

                // If they fixed a missing one, hide the inline error and red border
                const crit = e.target.closest('.criterion');
                if (crit) {
                    crit.classList.remove('border-red-500', 'ring-1', 'ring-red-400');
                    const err = crit.querySelector('.crit-error');
                    if (err) err.classList.add('hidden');
                }
            }
        });
    </script>

    <!-- Client-side validator: blocks submit, points to first missing, scrolls & highlights -->
    <script>
        (function() {
            const form = document.getElementById('evalForm');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                // Reset any previous visual errors
                document.querySelectorAll('.criterion').forEach(block => {
                    block.classList.remove('border-red-500', 'ring-1', 'ring-red-400');
                    const err = block.querySelector('.crit-error');
                    if (err) err.classList.add('hidden');
                });
                const topAlert = document.getElementById('topAlert');
                if (topAlert) {
                    topAlert.classList.add('hidden');
                    const span = topAlert.querySelector('span');
                    if (span) span.textContent = '';
                }

                // Validate every question has a selected radio
                const missingBlocks = [];
                document.querySelectorAll('.criterion').forEach(block => {
                    const radios = block.querySelectorAll('input[type="radio"]');
                    let checked = false;
                    radios.forEach(r => {
                        if (r.checked) checked = true;
                    });
                    if (!checked) {
                        missingBlocks.push(block);
                        block.classList.add('border-red-500', 'ring-1', 'ring-red-400');
                        const err = block.querySelector('.crit-error');
                        if (err) err.classList.remove('hidden');
                    }
                });

                if (missingBlocks.length > 0) {
                    e.preventDefault();

                    // Build friendly message
                    const first = missingBlocks[0];
                    const section = first.dataset.section || 'the form';
                    const qnum = first.dataset.qnum || '';

                    if (topAlert) {
                        topAlert.classList.remove('hidden');
                        const span = topAlert.querySelector('span');
                        if (span) {
                            span.innerHTML =
                                "Please fill out all items in <b>Test 1</b> and <b>Test 2</b>. " +
                                "First missing answer is in <b>" + section + "</b>, question <b>#" + qnum + "</b>.";
                        }
                    }

                    // Scroll the first missing question into view and briefly pulse it
                    first.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    first.animate(
                        [{
                            backgroundColor: 'transparent'
                        }, {
                            backgroundColor: '#fee2e2'
                        }, {
                            backgroundColor: 'transparent'
                        }], {
                            duration: 900,
                            easing: 'ease-in-out'
                        }
                    );
                }
            });
        })();
    </script>

    <?php if (!empty($err) && isset($first_missing_cid) && $first_missing_cid): ?>
        <!-- If server-side caught a missing item (JS off / tampered), also scroll to it -->
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                const el = document.getElementById('crit-<?= (int)$first_missing_cid ?>');
                if (el) {
                    el.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    el.classList.add('ring-1', 'ring-red-400', 'border-red-500');
                    const p = el.querySelector('.crit-error');
                    if (p) p.classList.remove('hidden');
                }
            });
        </script>
    <?php endif; ?>

    <script src="../assets/js/form-submit.js"></script>
</body>

</html>