<div id="addFacultyModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center">
    <div class="bg-white w-full max-w-md p-4 rounded border border-gray-300">
        <div class="flex justify-between items-center border-b pb-2 mb-3">
            <h3 class="font-semibold text-gray-800 text-lg flex items-center space-x-1">
                <i class='bx bx-plus-circle text-red-600'></i>
                <span>Add Faculty</span>
            </h3>
            <button type="button" onclick="closeModal('addFacultyModal')" class="text-gray-500 text-xl">
                <i class='bx bx-x'></i>
            </button>
        </div>

        <form method="post" enctype="multipart/form-data" class="space-y-3">
            <div>
                <label class="block text-sm mb-1">Full name</label>
                <input type="text" name="full_name" required class="border border-gray-300 w-full px-2 py-1 rounded">
            </div>
            <div>
                <label class="block text-sm mb-1">Photo</label>
                <input type="file" name="photo" accept="image/*" class="border border-gray-300 w-full px-2 py-1 rounded">
            </div>
            <div class="flex justify-end space-x-2 pt-2">
                <button type="button" onclick="closeModal('addFacultyModal')" class="px-3 py-1 border border-gray-300 rounded">Cancel</button>
                <button type="submit" name="add" class="px-4 py-1 bg-red-600 text-white rounded flex items-center space-x-1">
                    <i class='bx bx-check'></i>
                    <span>Add</span>
                </button>
            </div>
        </form>
    </div>
</div>