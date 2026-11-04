<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#ffffff">
    <title>Tip Payment</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            background-color: white;
            color: #1f2937;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Prevent text selection and zoom */
        .no-select {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        input[type="text"],
        input[type="number"] {
            font-size: 30px;
        }

        /* Status Bar */
        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 24px;
            font-size: 14px;
            color: #374151;
        }

        .time {
            font-weight: 600;
        }

        .status-icons {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .signal-bars {
            display: flex;
            gap: 2px;
        }

        .bar {
            width: 4px;
            height: 4px;
            background-color: #9ca3af;
            border-radius: 50%;
        }

        .bar.active {
            background-color: #374151;
        }

        .battery {
            width: 24px;
            height: 12px;
            border: 1px solid #374151;
            border-radius: 2px;
            position: relative;
        }

        .battery-level {
            width: 16px;
            height: 100%;
            background-color: #374151;
            border-radius: 1px;
        }

        /* Profile Section */
        .profile-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 0;
        }

        .profile-avatar {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .avatar-inner {
            width: 112px;
            height: 112px;
            border-radius: 50%;
            background-color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-center {
            width: 48px;
            height: 48px;
            background-color: #1e40af;
            border-radius: 50%;
        }

        .avatar-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.1), transparent);
        }

        .profile-name {
            font-size: 2.25rem;
            font-weight: 500;
            color: #1f2937;
        }

        /* Amount Section */
        .amount-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 32px;
        }

        .amount-container {
            margin-bottom: 32px;
            cursor: pointer;
        }

        .amount-input {
            font-size: 5rem;
            font-weight: 300;
            color: #059669;
            background: transparent;
            border: none;
            outline: none;
            text-align: center;
            cursor: pointer;
            caret-color: transparent;
            width: 100%;
            min-width: 300px;
        }

        /* Keypad */
        .keypad {
            width: 100%;
            max-width: 384px;
            padding: 0 24px;
            margin-bottom: 24px;
        }

        .keypad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .keypad-btn {
            height: 64px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .keypad-btn:hover {
            background-color: #f3f4f6;
        }

        .keypad-btn:active {
            transform: scale(0.95);
            background-color: #e5e7eb;
        }

        .keypad-empty {
            height: 64px;
        }

        .delete-btn {
            color: #6b7280;
        }

        .delete-btn svg {
            width: 24px;
            height: 24px;
        }

        /* Fees */
        .fees-text {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 24px;
        }

        /* Action Section */
        .action-section {
            padding: 24px;
        }

        .pay-button {
            width: 100%;
            background-color: #2563eb;
            color: white;
            padding: 16px;
            border-radius: 12px;
            font-size: 1.125rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
        }

        .pay-button:hover {
            background-color: #1d4ed8;
        }

        .pay-button:active {
            transform: scale(0.95);
        }

        /* Safe area */
        .safe-area {
            padding-bottom: env(safe-area-inset-bottom);
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {
            .amount-input {
                font-size: 4rem;
                min-width: 250px;
            }

            .profile-name {
                margin-top: 20px;
                font-size: 1.5rem;
            }

            .keypad {
                padding: 0 16px;
            }

            .keypad-btn {
                height: 56px;
                font-size: 1.25rem;
            }
        }
    </style>
</head>
@php
$currencySettings = [
'code' => currencyFormation(true, false),
'symbol' => currencyFormation(false, true),
'separator' => ',',
'decimal' => '.',
'precision' => 2,
];
@endphp

<body class="no-select">
    <!-- Profile Section -->
    <div class="profile-section">
        <div style="margin: 0 auto; width: 100px; height:100px; background-color:#cccccc; border-radius:50px; overflow:hidden; display:flex; align-items:center; justify-content:center;">
        @if ($guides->profile_photo_path != '' && !Storage::disk('s3')->missing('images/tour-operator/' . $tourOperator->tenant_id . '/users/'. $guides->profile_photo_path))
        <img
            src="{{ Storage::disk('s3')->url('images/tour-operator/' . $tourOperator->tenant_id . '/users/'. $guides->profile_photo_path) }}"
            alt="{{ $guides->first_name }} {{ $guides->last_name }}"
            style="width: 100%; height: 100%; object-fit: cover; object-position: center;">
        @else
       <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
<circle cx="50" cy="50" r="50" fill="white"/>
<path d="M49.6842 49.0564C52.794 49.0564 55.8339 48.1343 58.4196 46.4066C61.0053 44.6789 63.0206 42.2232 64.2106 39.3502C65.4007 36.4771 65.712 33.3157 65.1054 30.2657C64.4987 27.2157 63.0012 24.414 60.8022 22.2151C58.6033 20.0162 55.8017 18.5187 52.7517 17.912C49.7016 17.3053 46.5402 17.6167 43.6672 18.8067C40.7941 19.9968 38.3385 22.0121 36.6108 24.5978C34.8831 27.1834 33.9609 30.2234 33.9609 33.3331C33.9651 37.5019 35.623 41.4988 38.5708 44.4466C41.5186 47.3944 45.5154 49.0523 49.6842 49.0564ZM49.6842 22.851C51.7574 22.851 53.784 23.4657 55.5078 24.6175C57.2316 25.7693 58.5751 27.4064 59.3685 29.3218C60.1619 31.2372 60.3694 33.3448 59.965 35.3781C59.5605 37.4115 58.5622 39.2792 57.0962 40.7452C55.6303 42.2111 53.7625 43.2095 51.7292 43.6139C49.6958 44.0184 47.5882 43.8108 45.6729 43.0174C43.7575 42.224 42.1204 40.8805 40.9686 39.1567C39.8168 37.4329 39.202 35.4063 39.202 33.3331C39.202 30.5531 40.3064 27.8869 42.2722 25.9211C44.238 23.9553 46.9042 22.851 49.6842 22.851Z" fill="#C2C2C2"/>
<path d="M49.6865 54.2979C43.4335 54.3048 37.4386 56.7918 33.0171 61.2134C28.5956 65.6349 26.1085 71.6298 26.1016 77.8828C26.1016 78.5778 26.3777 79.2443 26.8691 79.7358C27.3605 80.2272 28.0271 80.5033 28.7221 80.5033C29.4171 80.5033 30.0837 80.2272 30.5751 79.7358C31.0666 79.2443 31.3427 78.5778 31.3427 77.8828C31.3427 73.0177 33.2753 68.3519 36.7154 64.9117C40.1556 61.4716 44.8214 59.5389 49.6865 59.5389C54.5516 59.5389 59.2174 61.4716 62.6575 64.9117C66.0976 68.3519 68.0303 73.0177 68.0303 77.8828C68.0303 78.5778 68.3064 79.2443 68.7978 79.7358C69.2893 80.2272 69.9558 80.5033 70.6508 80.5033C71.3458 80.5033 72.0124 80.2272 72.5038 79.7358C72.9953 79.2443 73.2714 78.5778 73.2714 77.8828C73.2644 71.6298 70.7774 65.6349 66.3559 61.2134C61.9343 56.7918 55.9394 54.3048 49.6865 54.2979Z" fill="#C2C2C2"/>
</svg>

        @endif
        </div>
        <h3 class="profile-name">{{ $guides->first_name }} {{ $guides->last_name }}</h3>
    </div>

    <!-- Amount Display -->
    <form id="tipForm" class="p-4 shadow-sm rounded bg-white text-center" method="post" action="{{ route('customer.create-tip-payment-link') }}">
        @csrf
        <div class="amount-section">
            <div class="amount-container" id="amountContainer">
                <input type="text" id="amountInput" value="{{$currencySettings['symbol']}}0.00" readonly class="amount-input">
            </div>

            <!-- Custom Keypad -->
            <div class="keypad" id="keypad" style="display: none;">
                <div class="keypad-grid">
                    <button type="button" class="keypad-btn" data-number="1">1</button>
                    <button type="button" class="keypad-btn" data-number="2">2</button>
                    <button type="button" class="keypad-btn" data-number="3">3</button>
                    <button type="button" class="keypad-btn" data-number="4">4</button>
                    <button type="button" class="keypad-btn" data-number="5">5</button>
                    <button type="button" class="keypad-btn" data-number="6">6</button>
                    <button type="button" class="keypad-btn" data-number="7">7</button>
                    <button type="button" class="keypad-btn" data-number="8">8</button>
                    <button type="button" class="keypad-btn" data-number="9">9</button>
                    <div class="keypad-empty"></div>
                    <button type="button" class="keypad-btn" data-number="0">0</button>
                    <button type="button" class="keypad-btn delete-btn" id="deleteBtn">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path>
                            <line x1="18" y1="9" x2="12" y2="15"></line>
                            <line x1="12" y1="9" x2="18" y2="15"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Fees -->
            <div class="fees-text">
                Fees: {{$currencySettings['symbol']}}<span id="feesAmount">0.00</span>
            </div>
        </div>

        <!-- Bottom Action Button -->
        <div class="action-section">
            <input type="hidden" name="booking_id" id="bookingId" value="{{ $bookingId }}">
            <input type="hidden" name="customer_id" id="customerId" value="{{ $customerId }}">
            <input type="hidden" name="tip_amount" id="tipAmount" value="0">
            <input type="hidden" name="tip_fees" id="tipFees" value="0">
            <button type="submit" class="pay-button" id="payButton">
                Pay {{$currencySettings['symbol']}}<span id="payAmount">0.00</span>
            </button>
        </div>

    </form>
    <input type="hidden" id="currency" value='@json($currencySettings)'>
    <!-- Safe area for iPhone -->
    <div class="safe-area"></div>
    @if(App::environment('local'))
    <script src="{{ asset('plugins/jquery-3.5.1.min.js') }}"></script>
    <script src="{{ asset('plugins/sweetalert-2.1.2.min.js') }}"></script>
    <script src="{{ asset('plugins/currency.min.js') }}"></script>
    @else
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
    <script src="https://unpkg.com/currency.js@2.0.4/dist/currency.min.js"></script>
    @endif
    <script src="{{ asset('js/utils/currency-setup.js') }}"></script>
    <script>
        var gratuityType = 'fixed_amount'; // Example value, replace with actual logic
        var natServiceCommission = "{{ $tourOperator->stripe_commission_percentage }}";
        const currencyFormation = JSON.parse(document.getElementById('currency').value);
        setGlobalCurrencyOptions(currencyFormation);
    </script>
    <script>
        $(document).ready(function() {
            let amount = '0.00';
            let tipFees = 0.00;
            let tipAmount = 0.00;
            let totalAmount = 0.00;
            let showKeypad = false;

            // Handle amount click to show keypad
            $('#amountContainer').on('click touchstart', function() {
                showKeypad = true;
                $('#keypad').slideDown(300);
                $('#amountInput').focus();
            });

            // Prevent default keyboard on mobile
            $('#amountInput').on('focus', function(e) {
                e.preventDefault();
                showKeypad = true;
                $('#keypad').slideDown(300);
            });

            $('.keypad-btn[data-number]').on('click touchstart', function(e) {
                e.preventDefault(); // prevent double event
                const num = $(this).data('number').toString();
                handleNumberPress(num);
            });

            // Handle delete button click
            $('#deleteBtn').on('click touchstart', function() {
                handleDelete();
            });

            function handleNumberPress(num) {
                if (amount === '0.00') {
                    amount = '0.0' + num;
                } else {
                    // Remove decimal point for calculation, add number, then format back
                    const currentCents = parseInt(amount.replace('.', ''));
                    const newCents = currentCents * 10 + parseInt(num);
                    const newAmount = (newCents / 100).toFixed(2);
                    amount = newAmount;
                }
                updateDisplay();
            }


            function handleDelete() {
                if (amount === '0.00') return;

                const currentCents = parseInt(amount.replace('.', ''));
                const newCents = Math.floor(currentCents / 10);
                const newAmount = (newCents / 100).toFixed(2);
                amount = newAmount === '0.00' ? '0.00' : newAmount;
                updateDisplay();
            }

            function updateDisplay() {

                $('#tipAmount').val(amount || 0);
                var tipFees = roundout((amount * natServiceCommission) / 100, 2);
                $('#tipFees').val(tipFees);
                // Update submit button
                var totalTipAmount = roundout(parseFloat(amount) + parseFloat(tipFees), 2);
                $('#amountInput').val(currencyFormation.symbol + amount);
                $('#feesAmount').text(`${tipFees.toFixed(2)}`);
                $('#payAmount').text(totalTipAmount.toFixed(2));
            }

            // Close keypad when clicking outside (optional)
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.amount-section').length && showKeypad) {
                    $('#keypad').slideUp(300);
                    showKeypad = false;
                }
            });

            // Prevent zoom on double tap
            let lastTouchEnd = 0;
            $(document).on('touchend', function(event) {
                const now = (new Date()).getTime();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            });
        });

        function roundout(amount, places = 0) {
            if (places < 0) {
                places = 0;
            }

            let x = Math.pow(10, places);
            return (amount >= 0 ? Math.ceil(amount * x) : Math.floor(amount * x)) / x;
        }
        $(document).on("submit", "#tipForm", function(e) {
            const amount = parseFloat($("#tipAmount").val());

            if (isNaN(amount) || amount <= 0) {
                e.preventDefault(); // stop form submission
                swal('Sorry!', 'Please select a valid tip amount greater than 0.', 'warning');
                return false;
            }
        });
    </script>
</body>

</html>