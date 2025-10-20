<!-- Faculty Profile Modal -->
<div id="facultyProfileModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-2xl rounded border border-gray-200">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <i class='bx bx-id-card text-red-600 text-2xl'></i>
                <h3 class="text-lg font-semibold text-gray-800">Faculty Profile</h3>
            </div>
            <button type="button" onclick="closeModal('facultyProfileModal')" class="text-gray-500 text-2xl leading-none">
                <i class='bx bx-x'></i>
            </button>
        </div>

        <!-- Body -->
        <div class="px-4 py-4 space-y-4">
            <!-- Overview -->
            <div class="border border-gray-200 rounded p-3">
                <div class="flex items-center gap-4">
                    <img id="fp_view_photo" src="" class="h-24 w-24 object-cover border rounded" alt="Photo">
                    <div class="min-w-0">
                        <p id="fp_view_name" class="text-2xl font-semibold text-gray-800 truncate"></p>
                        <p class="text-sm text-gray-600 mt-1">This name and photo are shown to students during evaluation.</p>
                    </div>
                </div>
            </div>

            <!-- Edit Name -->
            <div class="border border-gray-200 rounded">
                <div class="px-3 py-2 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
                    <i class='bx bx-edit-alt text-red-600'></i>
                    <p class="font-medium text-gray-800">Edit Name</p>
                </div>
                <form method="post" class="p-3 space-y-2">
                    <input type="hidden" name="faculty_id" id="fp_edit_faculty_id" value="">
                    <div>
                        <label class="block text-sm mb-1">Full Name</label>
                        <input type="text" name="new_full_name" id="fp_edit_name"
                            class="w-full border border-gray-300 px-3 py-2 rounded" required>
                    </div>
                    <div>
                        <button name="save_name" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm rounded flex items-center gap-1">
                            <i class='bx bx-save'></i><span>Save Name</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Replace Photo -->
            <div class="border border-gray-200 rounded">
                <div class="px-3 py-2 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
                    <i class='bx bx-image-add text-red-600'></i>
                    <p class="font-medium text-gray-800">Replace Photo</p>
                </div>
                <form method="post" enctype="multipart/form-data" class="p-3 flex items-center gap-2">
                    <input type="hidden" name="faculty_id" value="" id="fp_edit_faculty_id_dup">
                    <input type="file" name="photo" class="border border-gray-300 px-3 py-2 rounded text-sm">
                    <button name="upload_photo" class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 text-sm rounded flex items-center gap-1">
                        <i class='bx bx-upload'></i><span>Upload</span>
                    </button>
                </form>
                <script>
                    // keep duplicate hidden id in sync for photo form
                    (function() {
                        const a = document.getElementById('fp_edit_faculty_id');
                        const b = document.getElementById('fp_edit_faculty_id_dup');
                        const sync = () => {
                            b.value = a.value;
                        };
                        a.addEventListener('input', sync);
                        sync();
                    })();
                </script>
            </div>

            <!-- Danger Zone -->
            <div class="border border-red-300 rounded">
                <div class="px-3 py-2 border-b border-red-200 flex items-center gap-2 bg-red-50">
                    <i class='bx bx-error-circle text-red-600'></i>
                    <p class="font-medium text-red-700">Danger Zone</p>
                </div>
                <div class="p-3">
                    <form method="post" onsubmit="return confirm('Delete this faculty? This cannot be undone.');" class="flex items-center gap-2">
                        <input type="hidden" name="faculty_id" id="fp_delete_faculty_id" value="">
                        <button name="delete_faculty" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-sm rounded flex items-center gap-1">
                            <i class='bx bx-trash'></i><span>Delete Faculty</span>
                        </button>
                    </form>
                    <p class="text-xs text-gray-600 mt-2">Deleting will also remove related assignments and, if your foreign keys cascade, dependent records.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-gray-200">
            <button type="button" onclick="closeModal('facultyProfileModal')" class="px-3 py-2 border border-gray-300 rounded text-gray-700">
                Close
            </button>
        </div>
    </div>
</div>