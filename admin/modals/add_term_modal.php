<?php // simple modal with add-term form 
?>
<div id="addTermModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40" data-close-add-term></div>

    <!-- Dialog -->
    <div class="relative bg-white rounded-lg shadow-xl w-[95%] max-w-xl mx-auto p-4">
        <div class="flex items-center justify-between border-b pb-2">
            <h3 class="text-lg font-semibold text-gray-800">Add New Term</h3>
            <button type="button" class="p-2 rounded hover:bg-gray-100" aria-label="Close" data-close-add-term>
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <form method="post" class="mt-4 space-y-3">
            <div>
                <label class="block text-sm text-gray-700 mb-1">Term Name</label>
                <input type="text" name="label" placeholder="e.g., AY 2025–2026 • 1st Semester" required
                    class="border border-gray-300 w-full px-3 py-2 rounded">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Starts On</label>
                    <input type="date" name="starts_on" required class="border border-gray-300 w-full px-3 py-2 rounded" id="mtStart">
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Ends On</label>
                    <input type="date" name="ends_on" required class="border border-gray-300 w-full px-3 py-2 rounded" id="mtEnd">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t">
                <button type="button" class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50" data-close-add-term>Cancel</button>
                <button type="submit" name="add_term" class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white">Add Term</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const s = document.getElementById('mtStart');
        const e = document.getElementById('mtEnd');
        if (!s || !e) return;

        function sync() {
            if (s.value) {
                e.min = s.value;
                if (e.value && e.value < s.value) e.value = '';
            }
        }
        s.addEventListener('change', sync);
        sync();
    })();
</script>