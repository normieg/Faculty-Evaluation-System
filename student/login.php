<?php
require __DIR__ . '/../database.php';

// student session namespace
if (session_status() === PHP_SESSION_NONE) {
    session_name('FES_STUDENT');
    session_start();
}

$err = '';

if (isset($_POST['login'])) {
    $school_id = trim($_POST['school_id']);
    $password  = $_POST['password'];

    if (empty($school_id) || empty($password)) {
        $err = "Please enter both School ID and Password.";
    } else {
        $result = mysqli_query($conn, "SELECT * FROM students WHERE school_id='$school_id' AND is_active=1 LIMIT 1");
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['student_id'] = $row['id'];
                $_SESSION['school_id']  = $row['school_id'];
                $_SESSION['program_id'] = $row['program_id'];
                $_SESSION['year_level'] = $row['year_level'];

                header("Location: student_dashboard.php");
                exit;
            } else {
                $err = "Incorrect password.";
            }
        } else {
            $err = "No account found with that School ID.";
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <!-- Logo -->
            <div class="flex justify-center">
                <img src="../assets/images/moist_logo.png" class="h-32 w-40">
            </div>

            <div class="text-center mt-4">
                <p class="text-2xl font-semibold  text-red-600">MOIST</p>
                <p class="text-xl font-semibold  text-red-600">COLLEGE EVALUATION</p>
                <p class="text-xl font-semibold  text-red-600">SYSTEM</p>
            </div>

            <hr class="my-6 border-gray-200">

            <p class="text-center text-sm text-gray-500">ENTER YOUR CREDENTIALS TO LOGIN</p>

            <!-- Error -->
            <?php if (!empty($err)): ?>
                <div class="mt-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-3 text-sm rounded">
                    <?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="post" action="" class="mt-6 space-y-4">
                <!-- School ID -->
                <div>
                    <label class="sr-only" for="school_id">School System ID</label>
                    <div class="relative">
                        <i class='bx bxs-user absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <input
                            id="school_id"
                            name="school_id"
                            type="text"
                            required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500"
                            placeholder="School System ID">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="sr-only" for="password">Password</label>
                    <div class="relative">
                        <i class='bx bx-key absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-10 py-2 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500"
                            placeholder="Password">
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            onclick="const p=document.getElementById('password'); p.type = p.type==='password'?'text':'password';">
                            <i class='bx bx-show'></i>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    name="login"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 rounded-lg shadow-sm transition">
                    <i class='bx bx-log-in-circle align-middle mr-1 text-lg'></i> Log in
                </button>
                <div class="flex items-center justify-between text-sm pt-1">
                    <a href="registration.php" class="text-gray-500 hover:text-gray-700 hover:underline">Create Account</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>