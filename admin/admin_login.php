<?php
require __DIR__ . '/../database.php';

// Admin session
if (session_status() === PHP_SESSION_NONE) {
    session_name('FES_ADMIN');
    session_start();
}

$err = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username === '' || $password === '') {
        $err = "Enter username and password.";
    } else {
        $res = mysqli_query($conn, "SELECT * FROM admins WHERE username='$username' AND is_active=1 LIMIT 1");
        if ($res && mysqli_num_rows($res) === 1) {
            $row = mysqli_fetch_assoc($res);
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_user'] = $row['username'];
                header("Location: admin_dashboard.php");
                exit;
            } else {
                $err = "Invalid password.";
            }
        } else {
            $err = "Admin account not found or inactive.";
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <!-- Logo -->
            <div class="flex justify-center">
                <img src="../assets/images/moist_logo.png" class="h-32 w-40" alt="MOIST Logo">
            </div>

            <!-- Header -->
            <div class="text-center mt-4">
                <h1 class="text-3xl font-semibold  text-red-600">MOIST</h1>
                <p class="text-2xl font-semibold text-red-600">COLLEGE EVALUATION</p>
                <p class="text-2xl font-semibold  text-red-600">ADMIN PANEL</p>
            </div>

            <hr class="my-6 border-gray-200">

            <p class="text-center text-md text-gray-600">Enter admin credentials.</p>

            <!-- Error message -->
            <?php if (!empty($err)): ?>
                <div class="mt-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-3 text-sm rounded">
                    <?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>

            <!-- Login form -->
            <form method="post" action="" class="mt-6 space-y-4">
                <!-- Username -->
                <div>
                    <label class="sr-only" for="username">Username</label>
                    <div class="relative">
                        <i class='bx bxs-user-circle absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            required
                            class="w-full rounded-lg border border-gray-300 pl-11 pr-3 py-2 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500"
                            placeholder="Admin username">
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
                        <button type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            onclick="const p=document.getElementById('password'); p.type = p.type==='password'?'text':'password';">
                            <i class='bx bx-show'></i>
                        </button>
                    </div>
                </div>

                <!-- Submit button -->
                <button
                    type="submit"
                    name="login"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 rounded-lg shadow-sm transition">
                    <i class='bx bx-log-in align-middle mr-1 text-lg'></i> Log in
                </button>
            </form>
        </div>
    </div>
</body>

</html>
<script src="../assets/js/admin-sidebar.js"></script>
<script src="../assets/js/faculty.js"></script>