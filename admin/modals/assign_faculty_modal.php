<!-- Assign for Evaluation Modal -->
<div id="assignFacultyModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-2xl rounded border border-gray-200">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <i class='bx bx-task text-red-600 text-2xl'></i>
                <h3 class="text-lg font-semibold text-gray-800">Assign for Evaluation</h3>
            </div>
            <button type="button" onclick="closeModal('assignFacultyModal')" class="text-gray-500 text-2xl leading-none">
                <i class='bx bx-x'></i>
            </button>
        </div>

        <!-- Body -->
        <form method="post" id="assignEvalForm" class="px-4 py-4 space-y-4">
            <input type="hidden" name="faculty_id" value="">
            <input type="hidden" name="save_assign_eval" value="1">

            <!-- Step 1: Program -->
            <div class="border border-gray-200 rounded">
                <div class="px-3 py-2 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-red-600 text-white text-xs">1</span>
                    <p class="font-medium text-gray-800">Choose Program</p>
                </div>
                <div class="p-3">
                    <div class="relative">
                        <i class='bx bxs-graduation absolute left-3 top-1/2 -translate-y-1/2 text-xl text-red-600'></i>
                        <select id="assign_program_id" name="program_id" required
                            class="w-full border border-gray-300 pl-11 pr-3 py-2 rounded bg-white">
                            <!-- options are filled by JS -->
                        </select>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">Pick the program first. The next steps will load based on this selection.</p>
                </div>
            </div>

            <!-- Step 2: Year Levels (appears after program) -->
            <div class="border border-gray-200 rounded">
                <div class="px-3 py-2 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-red-600 text-white text-xs">2</span>
                    <p class="font-medium text-gray-800">Select Year Levels</p>
                </div>
                <div id="assign_years_wrap" class="p-3">
                    <p class="text-sm text-gray-500">Select a program to see year levels.</p>
                </div>
            </div>

            <!-- Step 3: Sections (appears after year selection) -->
            <div class="border border-gray-200 rounded">
                <div class="px-3 py-2 border-b border-gray-200 flex items-center gap-2 bg-gray-50">
                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-red-600 text-white text-xs">3</span>
                    <p class="font-medium text-gray-800">Limit to Sections (optional)</p>
                </div>
                <div id="assign_sections_wrap" class="p-3">
                    <p class="text-sm text-gray-500">Choose year levels first. If you leave all sections unchecked for a year, all sections of that year will be included.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-200">
                <button type="button" onclick="closeModal('assignFacultyModal')" class="px-3 py-2 border border-gray-300 rounded text-gray-700">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded flex items-center gap-1">
                    <i class='bx bx-save'></i>
                    <span>Save Assignments</span>
                </button>
            </div>
        </form>
    </div>
</div>