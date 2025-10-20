// admin-sidebar.js
(function () {
  const sidebar = document.getElementById("adminSidebar");
  const overlay = document.getElementById("sidebarOverlay");

  if (!sidebar || !overlay) return; // Sidebar not present on this page

  function openSidebar() {
    sidebar.classList.remove("-translate-x-full");
    overlay.classList.remove("hidden");
    document.documentElement.style.overflow = "hidden"; // lock scroll
  }

  function closeSidebar() {
    sidebar.classList.add("-translate-x-full");
    overlay.classList.add("hidden");
    document.documentElement.style.overflow = "";
  }

  // Expose global helpers so buttons can call them
  window.__openAdminSidebar = openSidebar;
  window.__closeAdminSidebar = closeSidebar;

  // Close button inside sidebar (optional)
  const closeBtn = document.getElementById("closeSidebarBtn");
  if (closeBtn) closeBtn.addEventListener("click", closeSidebar);

  // Clicking the overlay closes the sidebar
  overlay.addEventListener("click", closeSidebar);

  // Esc key closes
  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeSidebar();
  });
})();
