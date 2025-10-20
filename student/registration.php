<?php
require __DIR__ . '/../database.php';

$err = $ok = '';

// Fetch programs
$programs = [];
$res = mysqli_query($conn, "SELECT id, name FROM programs WHERE is_active=1 ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) {
    $programs[] = $row;
}

// Fetch all sections (we'll filter on the client by program + year)
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

// Handle registration
if (isset($_POST['register'])) {
    $school_id  = trim($_POST['school_id'] ?? '');
    $program_id = intval($_POST['program_id'] ?? 0);
    $year_level = intval($_POST['year_level'] ?? 0);
    $section_id = intval($_POST['section_id'] ?? 0);
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm'] ?? '';

    if ($school_id === '' || !$program_id || !$year_level || !$section_id || $password === '' || $confirm === '') {
        $err = "Please fill out all fields.";
    } elseif ($password !== $confirm) {
        $err = "Passwords do not match.";
    } else {
        // Validate section matches selected program + year
        $sec_ok = false;
        $sec_q = mysqli_query($conn, "SELECT program_id, year_level FROM sections WHERE id='$section_id' LIMIT 1");
        if ($sec_q && mysqli_num_rows($sec_q) === 1) {
            $sec_row = mysqli_fetch_assoc($sec_q);
            if ((int)$sec_row['program_id'] === $program_id && (int)$sec_row['year_level'] === $year_level) {
                $sec_ok = true;
            }
        }
        if (!$sec_ok) {
            $err = "Invalid section for the selected Program and Year.";
        } else {
            // Check duplicate School ID
            $check = mysqli_query($conn, "SELECT id FROM students WHERE school_id='$school_id'");
            if ($check && mysqli_num_rows($check) > 0) {
                $err = "That School ID is already registered.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $query = "
                  INSERT INTO students (school_id, program_id, year_level, section_id, password_hash)
                  VALUES ('$school_id', '$program_id', '$year_level', '$section_id', '$hash')
                ";
                if (mysqli_query($conn, $query)) {
                    $ok = "Registration successful! You can now log in.";
                    // Clear form values
                    $school_id = '';
                    $program_id = 0;
                    $year_level = 0;
                    $section_id = 0;
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
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-4xl">
        <!-- Header / Brand strip -->
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

        <!-- Card Body -->
        <div class="bg-white rounded-b-2xl border-x border-b border-gray-200 p-6">
            <p class="text-md text-gray-700 mb-4">Create your account</p>

            <!-- Alerts -->
            <?php if (!empty($err)): ?>
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-3 text-sm rounded">
                    <?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($ok)): ?>
                <div class="mb-4 bg-green-50 border-l-4 border-green-600 text-green-800 p-3 text-sm rounded">
                    <?= htmlspecialchars($ok) ?> <a href="login.php" class="underline">Log in</a>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="post" action="" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- School ID (full width) -->
                <div class="md:col-span-2">
                    <label for="school_id" class="block text-sm text-gray-700 mb-1">School System ID</label>
                    <div class="relative">
                        <i class='bx bxs-id-card absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <input
                            id="school_id"
                            name="school_id"
                            type="text"
                            required
                            value="<?= isset($school_id) ? htmlspecialchars($school_id) : '' ?>"
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 text-gray-900 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500"
                            placeholder="ex. (3612)">
                    </div>
                </div>

                <!-- Program -->
                <div>
                    <label for="program_id" class="block text-sm text-gray-700 mb-1">Program</label>
                    <div class="relative">
                        <i class='bx bxs-graduation absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <select
                            id="program_id"
                            name="program_id"
                            required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 bg-white text-gray-900 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
                            <option value="" <?= empty($program_id) ? 'selected' : '' ?>> Select Program </option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (!empty($program_id) && (int)$program_id === (int)$p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Year Level -->
                <div>
                    <label for="year_level" class="block text-sm text-gray-700 mb-1">Year Level</label>
                    <div class="relative">
                        <i class='bx bxs-calendar-check absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <select
                            id="year_level"
                            name="year_level"
                            required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 bg-white text-gray-900 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
                            <option value="" <?= empty($year_level) ? 'selected' : '' ?>> Select Year</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>" <?= (!empty($year_level) && (int)$year_level === $i) ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Section -->
                <div>
                    <label for="section_id" class="block text-sm text-gray-700 mb-1">Section</label>
                    <div class="relative">
                        <i class='bx bxs-group absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <select
                            id="section_id"
                            name="section_id"
                            required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 bg-white text-gray-900 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
                            <option value="" disabled selected>Select Section</option>
                            <?php foreach ($sections as $s): ?>
                                <option
                                    value="<?= $s['id'] ?>"
                                    data-program="<?= (int)$s['program_id'] ?>"
                                    data-year="<?= (int)$s['year_level'] ?>"
                                    <?= (!empty($section_id) && (int)$section_id === (int)$s['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['program_name']) ?> • Y<?= (int)$s['year_level'] ?> • <?= htmlspecialchars($s['code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Sections are filtered by Program and Year.</p>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <i class='bx bx-key absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-10 py-2 text-gray-900 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
                        <button type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            onclick="const p=document.getElementById('password'); p.type = p.type==='password'?'text':'password';">
                            <i class='bx bx-show'></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm -->
                <div>
                    <label for="confirm" class="block text-sm text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <i class='bx bx-check-shield absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <input
                            id="confirm"
                            name="confirm"
                            type="password"
                            required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-10 py-2 text-gray-900 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
                        <button type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            onclick="const c=document.getElementById('confirm'); c.type = c.type==='password'?'text':'password';">
                            <i class='bx bx-show'></i>
                        </button>
                    </div>
                </div>

                <!-- Submit (full width) -->
                <div class="md:col-span-2">
                    <button
                        type="submit"
                        name="register"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 rounded-lg">
                        <i class='bx bx-user-plus align-middle mr-1 text-lg'></i> Register
                    </button>
                </div>

                <!-- Links / Note (full width) -->
                <div class="md:col-span-2 flex items-center justify-between text-sm">
                    <a href="login.php" class="text-gray-500 hover:text-gray-700 underline">Back to Login</a>
                    <span class="text-xs text-gray-500">Only your School System ID is stored (no names).</span>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter section options by selected program + year
        (function() {
            const progSel = document.getElementById('program_id');
            const yearSel = document.getElementById('year_level');
            const secSel = document.getElementById('section_id');

            // Cache original options
            const original = Array.from(secSel.options);

            function filterSections() {
                const p = progSel.value;
                const y = yearSel.value;
                // reset list
                secSel.innerHTML = '';
                const head = document.createElement('option');
                head.value = '';
                head.textContent = '-- Select Section --';
                secSel.appendChild(head);

                original.forEach(o => {
                    const dp = o.getAttribute('data-program');
                    const dy = o.getAttribute('data-year');
                    if (!dp) return; // skip the head in the cached list
                    if ((p && dp === p) && (y && dy === y)) {
                        secSel.appendChild(o.cloneNode(true));
                    }
                });

                // clear selection after filtering
                secSel.value = '';
            }

            progSel.addEventListener('change', filterSections);
            yearSel.addEventListener('change', filterSections);

            // Run on load if values exist (e.g., after validation error)
            if (progSel.value && yearSel.value) {
                filterSections();
                // Try to reselect previous section if present
                const current = "<?= isset($section_id) ? (int)$section_id : 0 ?>";
                if (current) {
                    Array.from(secSel.options).forEach(o => {
                        if (o.value === current) secSel.value = current;
                    });
                }
            }
        })();
    </script>
</body>

</html>