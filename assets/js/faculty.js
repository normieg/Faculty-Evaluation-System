// faculty.js

// --- Simple modal helpers (your modals already use these IDs)
window.openModal = function (id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove("hidden");
};
window.closeModal = function (id) {
  const el = document.getElementById(id);
  if (el) el.classList.add("hidden");
};

// --- Quick search for faculty cards
(function setupFacultySearch() {
  const input = document.getElementById("facultySearch");
  const list = document.getElementById("facultyList");
  if (!input || !list) return;

  const cards = Array.from(list.querySelectorAll(".faculty-card"));
  input.addEventListener("input", function () {
    const q = this.value.trim().toLowerCase();
    cards.forEach((card) => {
      const name = card.getAttribute("data-name") || "";
      card.style.display = name.includes(q) ? "" : "none";
    });
  });
})();

// --- Assign for Evaluation modal (called from buttons)
window.openAssignModal = function (
  fid,
  programs,
  sectionsByProgYear,
  assignedPrograms,
  assignedYears,
  assignedSections
) {
  const modal = document.getElementById("assignFacultyModal");
  if (!modal) return;
  modal.classList.remove("hidden");

  const form = document.getElementById("assignEvalForm");
  if (!form) return;

  // Reset / set faculty id
  form.reset();
  const fidInput = form.querySelector('input[name="faculty_id"]');
  if (fidInput) fidInput.value = fid;

  const progSel = document.getElementById("assign_program_id");
  const yearsWrap = document.getElementById("assign_years_wrap");
  const sectionsWrap = document.getElementById("assign_sections_wrap");
  if (!progSel || !yearsWrap || !sectionsWrap) return;

  // Build program dropdown
  progSel.innerHTML = "";
  const optHead = document.createElement("option");
  optHead.value = "";
  optHead.textContent = "-- Select Program --";
  progSel.appendChild(optHead);
  Object.values(programs || {}).forEach((p) => {
    const o = document.createElement("option");
    o.value = p.id;
    o.textContent = p.name;
    progSel.appendChild(o);
  });

  // If already assigned, select first to show current state
  if (assignedPrograms && assignedPrograms.length > 0) {
    progSel.value = String(assignedPrograms[0]);
  }

  yearsWrap.innerHTML = "";
  sectionsWrap.innerHTML = "";

  progSel.onchange = renderYearAndSections;
  renderYearAndSections.call(progSel);

  function renderYearAndSections() {
    const pid = parseInt(this.value || "0", 10);
    yearsWrap.innerHTML = "";
    sectionsWrap.innerHTML = "";
    if (!pid) return;

    // Years chooser
    const yearsBox = document.createElement("div");
    yearsBox.className = "border border-gray-200 rounded p-2";
    yearsBox.innerHTML = `
      <div class="text-sm font-medium mb-1">Participating Year Levels</div>
      <div class="flex flex-wrap gap-3 text-sm"></div>
    `;
    const flex = yearsBox.querySelector("div.flex");
    for (let yl = 1; yl <= 6; yl++) {
      const label = document.createElement("label");
      label.className = "flex items-center gap-1";
      const cb = document.createElement("input");
      cb.type = "checkbox";
      cb.name = "year[]";
      cb.value = String(yl);
      if (assignedYears && assignedYears[pid] && assignedYears[pid][yl])
        cb.checked = true;
      cb.addEventListener("change", renderSections);
      const span = document.createElement("span");
      span.textContent = "Year " + yl;
      label.appendChild(cb);
      label.appendChild(span);
      flex.appendChild(label);
    }
    yearsWrap.appendChild(yearsBox);

    renderSections();

    function renderSections() {
      sectionsWrap.innerHTML = "";
      const chosenYears = Array.from(
        form.querySelectorAll('input[name="year[]"]:checked')
      ).map((i) => parseInt(i.value, 10));

      if (chosenYears.length === 0) {
        const emptyNote = document.createElement("p");
        emptyNote.className = "text-xs text-gray-600";
        emptyNote.textContent = "Select year levels to see available sections.";
        sectionsWrap.appendChild(emptyNote);
        return;
      }

      chosenYears.forEach((yl) => {
        const holder = document.createElement("div");
        holder.className = "border border-gray-200 rounded p-2 mb-2";

        const h = document.createElement("div");
        h.className = "text-xs text-gray-700 mb-1";
        h.textContent = "Sections • Year " + yl;
        holder.appendChild(h);

        const grid = document.createElement("div");
        grid.className = "grid grid-cols-2 md:grid-cols-3 gap-1 text-sm";

        const list =
          sectionsByProgYear[pid] && sectionsByProgYear[pid][yl]
            ? sectionsByProgYear[pid][yl]
            : [];
        if (!list || list.length === 0) {
          const none = document.createElement("span");
          none.className = "text-xs text-gray-500";
          none.textContent = "No sections defined.";
          grid.appendChild(none);
        } else {
          list.forEach((s) => {
            const lab = document.createElement("label");
            lab.className = "flex items-center gap-1";
            const cb = document.createElement("input");
            cb.type = "checkbox";
            cb.name = "section[]";
            cb.value = s.id;
            if (
              assignedSections &&
              assignedSections[pid] &&
              assignedSections[pid][yl]
            ) {
              if (assignedSections[pid][yl].indexOf(s.id) !== -1)
                cb.checked = true;
            }
            const span = document.createElement("span");
            span.textContent = "Section " + s.code;
            lab.appendChild(cb);
            lab.appendChild(span);
            grid.appendChild(lab);
          });
        }
        holder.appendChild(grid);

        const note = document.createElement("p");
        note.className = "text-xs text-gray-600 mt-1";
        note.textContent =
          "Leave all unchecked to include ALL sections for this year.";
        holder.appendChild(note);

        sectionsWrap.appendChild(holder);
      });
    }
  }
};

// --- Faculty profile view/edit modal
window.openFacultyProfileModal = function (
  fid,
  fullName,
  photoUrl,
  nameParts
) {
  const modal = document.getElementById("facultyProfileModal");
  if (!modal) return;

  const form = document.getElementById("facultyProfileForm");
  if (form && typeof form.reset === "function") {
    form.reset();
  }

  // Reset file helper text if present
  const photoFileName = document.getElementById("fp_photo_filename");
  if (photoFileName) {
    photoFileName.textContent = "";
  }

  const deleteFlag = document.getElementById("fp_delete_flag");
  if (deleteFlag) {
    deleteFlag.disabled = true;
  }

  // Helper to safely read a part from the payload
  const getPart = (key) => {
    if (!nameParts || typeof nameParts !== "object") return "";
    const raw = nameParts[key];
    return typeof raw === "string" ? raw : "";
  };

  const first = getPart("first_name");
  const middle = getPart("middle_name");
  const last = getPart("last_name");
  const suffix = getPart("suffix");

  const editId = document.getElementById("fp_edit_faculty_id");
  const deleteId = document.getElementById("fp_delete_faculty_id");
  if (editId) editId.value = fid;
  if (deleteId) deleteId.value = fid;

  const firstInput = document.getElementById("fp_first_name");
  const middleInput = document.getElementById("fp_middle_name");
  const lastInput = document.getElementById("fp_last_name");
  const suffixInput = document.getElementById("fp_suffix");

  if (firstInput) firstInput.value = first.trim();
  if (middleInput) middleInput.value = middle.trim();
  if (lastInput) lastInput.value = last.trim();
  if (suffixInput) suffixInput.value = suffix.trim();

  const readValue = (input) => {
    if (!input || typeof input.value !== "string") return "";
    return input.value;
  };

  const buildDisplayName = () => {
    const parts = [readValue(firstInput), readValue(middleInput), readValue(lastInput)]
      .map((part) => (part || "").trim())
      .filter((part) => part !== "");
    const joined = parts.join(" ");
    const suffixVal = readValue(suffixInput).trim();
    return (joined + (suffixVal ? " " + suffixVal : "")).trim();
  };

  const nameView = document.getElementById("fp_view_name");
  const resolvedFullName = buildDisplayName() || (fullName || "").trim();
  if (nameView) {
    nameView.textContent = resolvedFullName || "(No name)";
  }

  const updateViewOnInput = () => {
    if (!nameView) return;
    const current = buildDisplayName();
    nameView.textContent = current || resolvedFullName || "(No name)";
  };

  [firstInput, middleInput, lastInput, suffixInput].forEach((input) => {
    if (!input) return;
    if (input.dataset.fpListenerAttached === "1") return;
    input.addEventListener("input", updateViewOnInput);
    input.dataset.fpListenerAttached = "1";
  });

  const img = document.getElementById("fp_view_photo");
  if (img) {
    if (photoUrl) {
      img.src = photoUrl;
      img.classList.remove("hidden");
    } else {
      img.src = "";
      img.classList.add("hidden");
    }
  }

  modal.classList.remove("hidden");

  setTimeout(() => {
    if (firstInput && typeof firstInput.focus === "function") {
      firstInput.focus();
    }
  }, 0);
};
