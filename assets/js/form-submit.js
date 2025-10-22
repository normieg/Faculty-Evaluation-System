(function () {
  if (!window.addEventListener) return;
  window.addEventListener("DOMContentLoaded", function () {
    const forms = document.querySelectorAll('form[method="post"]');
    for (const f of forms) {
      f.addEventListener("submit", function (e) {
        const active = document.activeElement;
        setTimeout(function () {
          const btns = f.querySelectorAll(
            'button[type="submit"], input[type="submit"]'
          );
          for (const b of btns) {
            try {
              if (
                active &&
                (b === active ||
                  b.contains(active) ||
                  (active === document.body && b === document.activeElement))
              ) {
                // keep the clicked/active button enabled so its name/value is sent
                continue;
              }
            } catch (err) {
              // ignore and disable all if feature detection fails
            }
            b.disabled = true;
            b.classList.add("opacity-60", "cursor-not-allowed");
          }
        }, 50);
      });
    }
  });
})();
