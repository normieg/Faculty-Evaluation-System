<?php
if (!isset($active)) $active = '';
function nav_class($name, $active)
{
    $base = 'flex items-center gap-2 px-3 py-2 rounded transition-colors';
    return $name === $active
        ? "$base bg-red-100 text-red-700"
        : "$base text-gray-700 hover:bg-gray-100";
}
?>

<!-- Overlay for mobile -->
<div id="sidebarOverlay"
    class="fixed inset-0 z-40 bg-black/40 hidden lg:hidden"
    aria-hidden="true"></div>

<!-- Sidebar (off-canvas on mobile, static on lg+) -->
<aside id="adminSidebar"
    class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full lg:translate-x-0
              bg-white border-r border-gray-200 p-3 flex flex-col
              transition-transform duration-200 ease-in-out">

    <!-- Sidebar header -->
    <div class="flex items-center gap-2 mb-4 border-b border-gray-200 pb-2">
        <img src="../assets/images/moist_logo.png" class="h-10 w-10" alt="MOIST">
        <div>
            <p class="text-red-600 font-bold text-sm leading-none">MOIST FES</p>
            <p class="text-xs text-red-600 leading-none">Admin Panel</p>
        </div>

        <!-- Close (mobile only) -->
        <button id="closeSidebarBtn"
            class="ml-auto lg:hidden inline-flex items-center justify-center p-1 rounded hover:bg-gray-100"
            aria-label="Close sidebar" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 space-y-1">
        <a href="admin_dashboard.php" class="<?= nav_class('dashboard', $active) ?>">
            <i class='bx bxs-dashboard'></i> <span>Dashboard</span>
        </a>

        <a href="admin_faculty.php" class="<?= nav_class('faculty', $active) ?>">
            <i class='bx bxs-user-voice'></i> <span>Manage & Assign Faculty</span>
        </a>

        <a href="admin_faculty_assignments.php" class="<?= nav_class('faculty_map', $active) ?>">
            <i class='bx bx-map-pin'></i> <span>Faculty Assignments</span>
        </a>

        <a href="admin_terms.php" class="<?= nav_class('terms', $active) ?>">
            <i class='bx bx-calendar-event'></i> <span>Set Active Term</span>
        </a>

        <a href="admin_results.php" class="<?= nav_class('results', $active) ?>">
            <i class='bx bx-bar-chart'></i> <span>Evaluation Results</span>
        </a>
    </nav>

    <!-- Footer / Logout -->
    <div class="border-t border-gray-200 pt-3">
        <a href="../logout_admin.php"
            class="flex items-center justify-center bg-red-600 hover:bg-red-700 text-white py-2 rounded text-sm">
            <i class='bx bx-log-out mr-1'></i> Logout
        </a>
    </div>
</aside>

<!-- Tiny JS to toggle drawer -->
<script>
    (function() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.documentElement.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.documentElement.style.overflow = '';
        }

        window.__openAdminSidebar = openSidebar;
        window.__closeAdminSidebar = closeSidebar;

        const closeBtn = document.getElementById('closeSidebarBtn');
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSidebar();
        });
    })();
</script>