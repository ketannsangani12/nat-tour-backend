{{--
    Card Reader tab body for the "Secure Payment" modal.
    Shared by tour-booking-add.blade.php and tour-booking-info.blade.php so
    the identity chip / reconnect UI only needs to be maintained in one
    place. Expects $disableCardSave and $disableAffiliateCardSave to already
    be defined by the including view.
--}}
<div id="card-reader" class="tab-pane hidden text-center">

    <!-- Reader identity — icon, name, model, live status. Hidden until a
         reader is known (persisted from a previous connect), then stays
         visible through searching/reconnecting so staff always know which
         physical unit they're using. Filled in by backend-card-reader*.js's
         onStateChange callback via StripeReaderIdentity.applyState(). -->
    <div id="card-reader-identity-chip" class="mb-4 hidden"></div>

    <!-- Generic placeholder — shown only on a workstation that has never
         connected a reader yet. Replaced by the identity chip above as soon
         as one is known. -->
    <div class="mb-6" id="card-reader-generic-placeholder">
        <i class="fas fa-credit-card text-6xl text-gray-400"></i>
        <p class="mt-4 text-lg font-medium text-gray-700">Ready to accept card</p>
        <p class="text-sm text-gray-500">Tap or insert card on the reader</p>
    </div>

    <!-- Live connection state — searching / connecting / reconnecting with
         attempt count, or an error once retries are exhausted. Empty and
         hidden once connected. -->
    <div id="card-reader-state-area" class="hidden mb-4"></div>

    <!-- Processing -->
    <div id="reader-processing" class="hidden">
        <div
            class="inline-block animate-spin rounded-full h-16 w-16 border-8 border-blue-600 border-t-transparent">
        </div>
        <p class="mt-4 text-lg font-medium text-gray-700">Processing...</p>
    </div>

    <!-- Success -->
    <div id="reader-success" class="hidden py-10">
        <p class="text-2xl font-bold text-green-600">Payment Successful!</p>
        <p class="text-lg text-gray-700 mt-2">Booking confirmed</p>
    </div>

    <!-- Connect Buttons -->
    <div class="mb-4">
        @include("/partials/pos/cart/_cardreader_btns")
        @if(!$disableCardSave)
        @if(!$disableAffiliateCardSave)
        <div class="flex items-center gap-2 mt-4 text-gray-700 text-sm cursor-pointer select-none">
            <input type="checkbox" id="save_card_on_file"
                class="w-4 h-4 accent-blue-600 cursor-pointer">
            <label for="save_card_on_file" class="cursor-pointer">
                Save this card for future payments
            </label>
        </div>
        @endif
        @endif
    </div>

    <!-- Error -->
    <div id="reader-error" class="hidden text-red-600 mt-4 text-center"></div>

    <!-- Back Button -->
    <div class="mt-4">
        <button type="button"
            class="w-full border-2 border-gray-300 text-gray-700 font-semibold text-lg py-3 rounded-lg hover:bg-gray-50 transition"
            onclick="document.querySelector('[data-tab=online-payment]').click()">
            Back to Online Payment
        </button>
    </div>
</div>
