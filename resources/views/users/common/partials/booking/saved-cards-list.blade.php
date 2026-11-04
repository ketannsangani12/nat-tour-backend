<!-- Saved Cards List Modal -->
<div id="savedCardsModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30 backdrop-blur-sm">

    <div class="bg-white w-[440px] max-w-[95%] rounded-xl shadow-xl p-5 relative">

        <!-- Header -->
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-base font-semibold text-gray-800">
                Saved Cards
            </h3>
            <button id="closeSavedCardsModal"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none">
                &times;
            </button>
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-200 mb-3"></div>

        <!-- Card List -->
        <div id="savedCardsListContainer"
             class="max-h-[260px] overflow-y-auto pr-1 space-y-2">
        </div>

    </div>
</div>