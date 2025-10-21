<?php
require __DIR__ . '/../database.php'; // session already started here

$err = '';
$flash_ok = '';
$flash_type = 'success';

/* Pick up flash success after redirect */
if (isset($_SESSION['flash_msg'])) {
    $flash_ok  = $_SESSION['flash_msg'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

/* Fetch programs */
$programs = [];
$res = mysqli_query($conn, "SELECT id, name FROM programs WHERE is_active=1 ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $programs[] = $row;
}

/* Fetch all sections (filter client-side) */
$sections = [];
$sr = mysqli_query($conn, "
  SELECT s.id, s.program_id, s.year_level, s.code, p.name AS program_name
  FROM sections s
  JOIN programs p ON p.id = s.program_id
  WHERE p.is_active = 1
  ORDER BY p.name, s.year_level, s.code
");
while ($row = mysqli_fetch_assoc($sr)) {
    $sections[] = $row;
}

/* Handle registration (PRG) */
if (isset($_POST['register'])) {
    $school_id  = trim($_POST['school_id'] ?? '');
    $program_id = (int)($_POST['program_id'] ?? 0);
    $year_level = (int)($_POST['year_level'] ?? 0);
    $section_id = (int)($_POST['section_id'] ?? 0); // optional
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm'] ?? '';

    if ($school_id === '' || !$program_id || !$year_level || $password === '' || $confirm === '') {
        $err = "Please fill out all required fields.";
    } elseif ($password !== $confirm) {
        $err = "Passwords do not match.";
    } else {
        // Validate section if chosen
        if ($section_id) {
            $sec_ok = false;
            $sec_q = mysqli_query($conn, "SELECT program_id, year_level FROM sections WHERE id='$section_id' LIMIT 1");
            if ($sec_q && mysqli_num_rows($sec_q) === 1) {
                $sec_row = mysqli_fetch_assoc($sec_q);
                if ((int)$sec_row['program_id'] === $program_id && (int)$sec_row['year_level'] === $year_level) {
                    $sec_ok = true;
                }
            }
            if (!$sec_ok) $err = "Invalid section for the selected Program and Year.";
        }

        if ($err === '') {
            // Duplicate school ID?
            $check = mysqli_query($conn, "SELECT id FROM students WHERE school_id='" . mysqli_real_escape_string($conn, $school_id) . "'");
            if ($check && mysqli_num_rows($check) > 0) {
                $err = "That School ID is already registered.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $section_sql = $section_id ? "'" . (int)$section_id . "'" : "NULL";
                $q = "
                    INSERT INTO students (school_id, program_id, year_level, section_id, password_hash)
                    VALUES (
                        '" . mysqli_real_escape_string($conn, $school_id) . "',
                        '$program_id',
                        '$year_level',
                        $section_sql,
                        '$hash'
                    )
                ";
                if (mysqli_query($conn, $q)) {
                    // ✅ Flash + Redirect (PRG)
                    $_SESSION['flash_msg']  = "Registration successful! You can now log in.";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: registration.php");
                    exit;
                } else {
                    $err = "Error registering student: " . mysqli_error($conn);
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        /* Make mobile selects readable and avoid iOS zoom-on-focus */
        @media (max-width: 420px) {

            select,
            input {
                font-size: 16px;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-3xl">
        <!-- Header -->
        <div class="bg-white rounded-t-2xl border border-gray-200">
            <div class="flex items-center gap-3 p-4">
                <img src="../assets/images/moist_logo.png" class="h-14 w-16 object-contain" alt="Logo">
                <div>
                    <h1 class="text-xl font-semibold text-red-600 leading-tight">MOIST</h1>
                    <p class="text-sm font-medium text-red-600">COLLEGE EVALUATION SYSTEM</p>
                </div>
            </div>
            <div class="h-1 bg-red-600"></div>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-b-2xl border-x border-b border-gray-200 p-6">
            <p class="text-md text-gray-700 mb-4">Create your account</p>

            <?php if (!empty($flash_ok)): ?>
                <div class="mb-4 bg-green-50 border-l-4 border-green-600 text-green-800 p-3 text-sm rounded">
                    <?= htmlspecialchars($flash_ok) ?> <a href="login.php" class="underline">Log in</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($err)): ?>
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-3 text-sm rounded">
                    <?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="post" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- School ID -->
                <div class="sm:col-span-2">
                    <label class="block text-sm text-gray-700 mb-1">School System ID</label>
                    <div class="relative">
                        <i class='bx bxs-id-card absolute left-3 top-1/2 -translate-y-1/2 text-lg sm:text-xl text-red-600'></i>
                        <input name="school_id" type="text" required
                            value="<?= htmlspecialchars($school_id ?? '') ?>"
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 text-gray-900 focus:ring-1 focus:ring-red-500 focus:border-red-500"
                            placeholder="ex. 3612">
                    </div>
                </div>

                <!-- Program -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Program</label>
                    <div class="relative">
                        <i class='bx bxs-graduation absolute left-3 top-1/2 -translate-y-1/2 text-lg sm:text-xl text-red-600'></i>
                        <select name="program_id" id="program_id" required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 text-gray-900 bg-white focus:ring-1 focus:ring-red-500">
                            <option value=""> Select Program </option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['id'] ?>"
                                    data-long="<?= htmlspecialchars($p['name']) ?>"
                                    <?= (!empty($program_id) && (int)$program_id === (int)$p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Year Level -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Year Level</label>
                    <div class="relative">
                        <i class='bx bxs-calendar-check absolute left-3 top-1/2 -translate-y-1/2 text-lg sm:text-xl text-red-600'></i>
                        <select name="year_level" id="year_level" required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 text-gray-900 bg-white focus:ring-1 focus:ring-red-500">
                            <option value=""> Select Year </option>
                            <?php
                            $ord = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year', 6 => '6th Year'];
                            foreach ($ord as $i => $txt):
                            ?>
                                <option value="<?= $i ?>"
                                    data-long="<?= $txt ?>"
                                    data-short="Y<?= $i ?>"
                                    <?= (!empty($year_level) && (int)$year_level === $i) ? 'selected' : '' ?>>
                                    <?= $txt ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Section  -->
                <div class="sm:col-span-2">
                    <label class="block text-sm text-gray-700 mb-1">Section (Optional)</label>
                    <div class="relative">
                        <i class='bx bxs-group absolute left-3 top-1/2 -translate-y-1/2 text-lg sm:text-xl text-red-600'></i>
                        <select name="section_id" id="section_id"
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 text-gray-900 bg-white focus:ring-1 focus:ring-red-500">
                            <option value=""> Select Section </option>
                            <?php foreach ($sections as $s):
                                $long  = $s['program_name'] . ' • Y' . (int)$s['year_level'] . ' • ' . $s['code'];
                                $short = $s['code'] . ' (Y' . (int)$s['year_level'] . ')';
                            ?>
                                <option value="<?= $s['id'] ?>"
                                    data-program="<?= (int)$s['program_id'] ?>"
                                    data-year="<?= (int)$s['year_level'] ?>"
                                    data-long="<?= htmlspecialchars($long) ?>"
                                    data-short="<?= htmlspecialchars($short) ?>"
                                    <?= (!empty($section_id) && (int)$section_id === (int)$s['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($long) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Sections are filtered by Program and Year.</p>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <i class='bx bx-key absolute left-3 top-1/2 -translate-y-1/2 text-lg sm:text-xl text-red-600'></i>
                        <input type="password" name="password" id="password" required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-10 py-2 focus:ring-1 focus:ring-red-500 focus:border-red-500">
                        <button type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
                            onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';">
                            <i class='bx bx-show'></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm -->
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <i class='bx bx-check-shield absolute left-3 top-1/2 -translate-y-1/2 text-lg sm:text-xl text-red-600'></i>
                        <input type="password" name="confirm" id="confirm" required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-10 py-2 focus:ring-1 focus:ring-red-500 focus:border-red-500">
                        <button type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
                            onclick="const c=document.getElementById('confirm');c.type=c.type==='password'?'text':'password';">
                            <i class='bx bx-show'></i>
                        </button>
                    </div>
                </div>

                <!-- Submit (full width) -->
                <div class="sm:col-span-2">
                    <button type="submit" name="register"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 rounded-lg">
                        <i class='bx bx-user-plus mr-1 text-lg'></i> Register
                    </button>
                </div>

                <!-- Footer -->
                <div class="sm:col-span-2 flex items-center justify-between text-sm">
                    <a href="login.php" class="text-gray-500 hover:text-gray-700 underline">Back to Login</a>
                    <span class="text-xs text-gray-500">Only your School System ID is stored (no names).</span>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const progSel = document.getElementById('program_id');
            const yearSel = document.getElementById('year_level');
            const secSel = document.getElementById('section_id');

            const secOriginal = Array.from(secSel.options).map(o => o.cloneNode(true));
            const useShort = () => window.matchMedia('(max-width: 420px)').matches;

            function relabel(selectEl) {
                for (const opt of selectEl.options) {
                    const longTxt = opt.getAttribute('data-long') || opt.textContent;
                    const shortTxt = opt.getAttribute('data-short') || longTxt;
                    opt.textContent = useShort() ? shortTxt : longTxt;
                }
            }

            function buildOptionFrom(src) {
                const o = src.cloneNode(false);
                for (const a of src.getAttributeNames()) o.setAttribute(a, src.getAttribute(a));
                const longTxt = src.getAttribute('data-long') || src.textContent;
                const shortTxt = src.getAttribute('data-short') || longTxt;
                o.textContent = useShort() ? shortTxt : longTxt;
                return o;
            }

            function filterSections() {
                const p = progSel.value;
                const y = yearSel.value;

                secSel.innerHTML = '';
                const head = document.createElement('option');
                head.value = '';
                head.textContent = '-- Select Section --';
                secSel.appendChild(head);

                secOriginal.forEach(src => {
                    const dp = src.getAttribute('data-program');
                    const dy = src.getAttribute('data-year');
                    if (!dp || !dy) return; // skip header
                    if ((p && dp === p) && (y && dy === y)) {
                        secSel.appendChild(buildOptionFrom(src));
                    }
                });

                relabel(secSel);
            }

            // Initial label pass
            relabel(yearSel);
            relabel(secSel);

            // Events
            progSel.addEventListener('change', filterSections);
            yearSel.addEventListener('change', filterSections);
            window.addEventListener('resize', () => {
                relabel(yearSel);
                relabel(secSel);
            });

            // If values already chosen (validation fail), filter now
            if (progSel.value && yearSel.value) filterSections();
        })();
    </script>
</body>

</html>