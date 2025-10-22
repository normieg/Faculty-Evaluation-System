<!-- Faculty Profile Modal (single-submit) -->
<div id="facultyProfileModal"
    class="hidden fixed inset-0 z-50"
    role="dialog" aria-modal="true" aria-labelledby="facultyProfileTitle">

    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40" data-backdrop></div>

    <!-- Modal wrapper -->
    <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <!-- Panel -->
            <div class="w-full max-w-full sm:max-w-lg md:max-w-xl lg:max-w-2xl bg-white rounded-lg border border-gray-200 shadow-lg
                  max-h-[85vh] flex flex-col">

                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <i class='bx bx-id-card text-red-600 text-2xl'></i>
                        <h3 id="facultyProfileTitle" class="text-lg font-semibold text-gray-800">Faculty Profile</h3>
                    </div>
                    <button type="button" onclick="closeModal('facultyProfileModal')" class="text-gray-500 text-2xl leading-none">
                        <i class='bx bx-x'></i>
                    </button>
                </div>

                <!-- ONE form handles names + photo -->
                <form method="post" id="facultyProfileForm" enctype="multipart/form-data"
                    class="flex-1 flex flex-col overflow-hidden">
                    <input type="hidden" name="faculty_id" id="fp_edit_faculty_id" value="">
                    <!-- body -->
                    <div class="px-4 py-4 space-y-4 overflow-y-auto">

                        <!-- Overview -->
                        <div class="border border-gray-200 rounded p-3">
                            <div class="flex items-center gap-4">
                                <img id="fp_view_photo"
                                    src=""
                                    class="h-16 w-16 sm:h-24 sm:w-24 object-cover border rounded"
                                    alt="Photo">
                                <div class="min-w-0">
                                    <p id="fp_view_name" class="text-xl sm:text-2xl font-semibold text-gray-800 truncate">(No name)</p>
                                    <p class="text-xs sm:text-sm text-gray-600 mt-1">Shown to students during evaluation.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Replace Photo -->
                        <div class="border border-gray-200 rounded">
                            <div class="px-3 py-2 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
                                <i class='bx bx-image-add text-red-600'></i>
                                <p class="font-medium text-gray-800">Replace Photo</p>
                            </div>
                            <div class="p-3 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                <input type="file" name="photo" id="fp_photo_input" accept=".jpg,.jpeg,.png"
                                    class="border border-gray-300 px-3 py-2 rounded text-sm w-full sm:w-auto">
                                <span id="fp_photo_filename" class="text-xs text-gray-600"></span>
                            </div>
                        </div>

                        <!-- Edit Name -->
                        <div class="border border-gray-200 rounded">
                            <div class="px-3 py-2 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
                                <i class='bx bx-edit-alt text-red-600'></i>
                                <p class="font-medium text-gray-800">Edit Name</p>
                            </div>
                            <div class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm mb-1">First Name</label>
                                    <input type="text" name="new_first_name" id="fp_first_name"
                                        class="w-full border border-gray-300 px-3 py-2 rounded">
                                </div>
                                <div>
                                    <label class="block text-sm mb-1">Middle Name</label>
                                    <input type="text" name="new_middle_name" id="fp_middle_name"
                                        class="w-full border border-gray-300 px-3 py-2 rounded">
                                </div>
                                <div>
                                    <label class="block text-sm mb-1">Last Name</label>
                                    <input type="text" name="new_last_name" id="fp_last_name"
                                        class="w-full border border-gray-300 px-3 py-2 rounded">
                                </div>
                                <div>
                                    <label class="block text-sm mb-1">Suffix (Jr., III, etc.)</label>
                                    <input type="text" name="new_suffix" id="fp_suffix"
                                        class="w-full border border-gray-300 px-3 py-2 rounded">
                                </div>
                            </div>
                        </div>
                        <!-- End of form body -->
                    </div>
                    <!-- Danger Zone (no nested form) -->
                    <div class="px-4 pb-4">
                        <div class="border border-red-300 rounded">
                            <div class="px-3 py-2 border-b border-red-200 flex items-center gap-2 bg-red-50">
                                <i class='bx bx-error-circle text-red-600'></i>
                                <p class="font-medium text-red-700">Danger Zone</p>
                            </div>
                            <div class="p-3 flex items-center gap-2">
                                <input type="hidden" name="faculty_id" id="fp_delete_faculty_id" value="">
                                <input type="hidden" name="delete_faculty" id="fp_delete_flag" value="1" disabled>
                                <button
                                    type="button"
                                    id="fp_delete_button"
                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm rounded inline-flex items-center gap-1">
                                    <i class='bx bx-trash'></i><span>Delete Faculty</span>
                                </button>
                            </div>
                            <p class="text-xs text-gray-600 mt-2 px-3 pb-2">Deleting also removes related assignments.</p>
                        </div>
                    </div>


                    <!-- Footer  -->
                    <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-gray-200">
                        <button type="button" onclick="closeModal('facultyProfileModal')"
                            class="px-3 py-2 border border-gray-300 rounded text-gray-700">
                            Cancel
                        </button>
                        <button name="save_profile" value="1"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm rounded inline-flex items-center gap-1">
                            <i class='bx bx-save'></i><span>Save Changes</span>
                        </button>
                    </div>
                </form>


            </div>
        </div>
    </div>
</div>

<script>
    // Backdrop click + Esc to close
    (function() {
        const modal = document.getElementById('facultyProfileModal');
        modal?.addEventListener('click', (e) => {
            if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-backdrop')) {
                closeModal('facultyProfileModal');
            }
        });
        document.addEventListener('keydown', (e) => {
            if (!modal.classList.contains('hidden') && e.key === 'Escape') {
                closeModal('facultyProfileModal');
            }
        });
    })();

    // Keep delete form's hidden id in sync
    (function() {
        const src = document.getElementById('fp_edit_faculty_id');
        const del = document.getElementById('fp_delete_faculty_id');
        const sync = () => {
            if (src && del) del.value = src.value;
        };
        src && src.addEventListener('input', sync);
        sync();
    })();

    // Photo input: show filename + live preview in header image
    (function() {
        const input = document.getElementById('fp_photo_input');
        const nameEl = document.getElementById('fp_photo_filename');
        const img = document.getElementById('fp_view_photo');
        if (!input) return;
        input.addEventListener('change', () => {
            const f = input.files && input.files[0];
            nameEl.textContent = f ? f.name : '';
            if (f) {
                const url = URL.createObjectURL(f);
                img.src = url;
            }
        });
    })();

    (function() {
        const form = document.getElementById('facultyProfileForm');
        const deleteBtn = document.getElementById('fp_delete_button');
        const deleteFlag = document.getElementById('fp_delete_flag');
        if (!form || !deleteBtn || !deleteFlag) return;

        deleteBtn.addEventListener('click', () => {
            if (!confirm('Delete this faculty? This cannot be undone.')) {
                return;
            }

            deleteFlag.disabled = false;
            form.submit();
        });
    })();
</script>
