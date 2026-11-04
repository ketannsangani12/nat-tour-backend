/**
 * Custom Field Add-ons by package id
 * this is to display the custom add on form on the tour-booking-add.blade.php
 **/
class TourBookingPos {

    constructor(props) {

        this.selectedCount = "";
        this.formData = null;
        this.toggleBtn = document.querySelector('.js-toggle-fullscreen-btn');
        this.countryCode = props.countryCode;
        this.admin = (props.admin === 'Admin') ? '/admin' : '';
        this.userType = props.admin;
        if (this.userType == '') {
            this.preFixUrl = '';
            this.tourOperator = '';
        } else {
            this.preFixUrl = `${this.admin}/tour-operator`;
            this.tourOperator = (props.admin === 'Admin') ? '/admin' : '/tour-operator';
        }
        this.paymentMethod = (this.userType != '') ? $(".book-pay-method").filter(".active").attr('id') : 'Card';
        this.categoryId = props.categoryId;
        this.stripe = props.stripe;
        this.elements = null;
        this.stripePublicKey = props.stripePublicKey;
        this.minMaxPaxApply = props.minMaxPaxApply;
        this.customerInfoMandatory = props.customerInfoMandatory;
        this.managerRequestEnableDiscount = props.managerRequestEnableDiscount;
        this.allowedCancelOrderPermission = props.allowedCancelOrderPermission;
        this.allowedRefundOrderPermission = props.allowedRefundOrderPermission;
        this.serverDatenow = props.serverDatenow;
        this.fetchPackagesUrl = `${this.preFixUrl}/pos/get-packages`;
        this.applyPromoCodeUrl = `${this.preFixUrl}/pos/apply-promo-code`;
        this.createStripePaymentIntentUrl = `${this.preFixUrl}/stripe/create-payment-intent-pos`;
        this.validateCartUrl = `${this.preFixUrl}/pos/validate-cart`;
        this.createBookingsUrl = `${this.preFixUrl}/pos/create-bookings`;
        this.fetchOrdersUrl = `${this.preFixUrl}/pos/orders`;
        this.fetchOrderUrl = `${this.preFixUrl}/pos/order`;
        this.printReceiptUrl = `${this.preFixUrl}/pos/print-receipt`;
        this.sendReceiptUrl = `${this.preFixUrl}/pos/send-receipt`;
        this.getTourSlotTimesByDateUrl = `${this.tourOperator}/get-tour-slot-times-by-date`;
        this.getTourRatesBySlotUrl = `${this.tourOperator}/get-tour-rates-by-slot`;
        this.checkOverBookingUrl = `${this.preFixUrl}/booking/check-overbooking`;
        this.checkManagerIdUrl = `${this.admin}/tour-operator/booking/manual-id-check`;
        this.checkManagerPinUrl = `${this.admin}/tour-operator/pos/manual-pin-check`;
        this.cancelOrderUrl = `${this.admin}/tour-operator/pos/cancel-order`;
        this.refundOrderUrl = `${this.admin}/tour-operator/pos/refund-order`;
        this.settingsUrl = `${this.admin}/tour-operator/pos/settings`;
        this.updateSettingsUrl = `${this.admin}/tour-operator/pos/update-settings`;
        this.requestToManagerUrl = `${this.admin}/tour-operator/pos/request-to-manager`;
        this.checkRequestUrl = `${this.admin}/tour-operator/pos/check-request`;

        this.currentMonth = new Date().getMonth();
        this.currentYear = new Date().getFullYear();
        this.selectedView = 'calendar'; // Can be 'calendar', 'monthSelection', or 'yearSelection'
        this.datesElement = document.getElementById("dates");
        this.calendarMonth = document.getElementById("calendarMonth");
        this.calendarYear = document.getElementById("calendarYear");
        this.monthGrid = document.getElementById("monthGrid");
        this.yearGrid = document.getElementById("yearGrid");
        this.monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        this.selectedSlotId = '';
        this.selectedDay = '';
        this.selectedMonth = '';
        this.selectedYear = '';


        this.catalogue = '';
        this.allCards = [];
        this.tourPackages = [];
        this.rateNameForGroupRate = 'Person(s)';
        this.todayDate = moment().format('YMMDD');
        this.applyOnDate = false;
        this.applyOnSlots = false;
        this.dynamicRateGroups = [];
        this.dynamicPaxWiseGroupAdditionalCharges = [];
        this.dynamicPaxWiseGroupRateIds = [];
        this.dynamicPaxWiseGroupRates = [];
        this.currentPackageId = '';
        this.isOverBooking = 1;
        this.availableSeats = -1;
        this.tourSlots = [];
        this.selectedBookingIds = [];
        this.requestTypeCancelOrder = 'Cancel';
        this.requestTypeRefundOrder = 'Refund';
        this.requestPermissions = [];
        this.permissionType = '';
        this.requestToManager = 0;
        this.manager = '';
        this.manualManagerId = '';
        this.manualManagerPin = '';
        this.currentTourPackageId = '';
        this.currentTourPackages = [];
        this.selectedTourPackages = [];
        this.currentCartId = '';
        this.IsOnHoldCart = 0;
        this.selectedOnHoldCartIndex = '';
        this.onHoldCarts = [];
        this.orders = [];
        this.orderInfo = [];
        this.orderId = '';
        this.parentIdForSendReceipt = '';
        this.currentPackageSubTotal = 0;
        this.currentPackageServiceCommission = 0;
        this.currentPackageTotal = 0;

        this.subTotalBeforeDiscount = 0;
        this.subTotal = 0;
        this.discountTotal = 0;
        this.platformFeesTotal = 0;
        this.total = 0;
        this.creditAmount = 0;
        this.BalanceAmoount = 0;

        this.note = '';
        this.isCartReaderPayment = false;


        this.targetDiv = "tourPackages";
        this.targetDivForOnHold = "mainContentOnHold";
        this.targetDivForOnHoldOrders = "onHoldOrders";
        this.targetDivForOrders = "ordersList";
        this.targetDivForCustomerOrder = "customerOrder";
        this.selectedPackagesDiv = "selected-packages";
        this.cartSectionDiv = "cart-section";
        this.confirmPaymentSectionDiv = "confirm-payment";
        this.thankYouSectionDiv = "thank-you-step";
        this.orderInfoDiv = "order-info";
        this.settingDiv = "setting-main";
        this.posMainDiv = "pos-main";
        this.menuForHome = "home";
        this.menuForOrders = "orders";
        this.discountFixedMoney = $("#discount_fixed_money");
        this.discountPercentage = $("#discount_percentage");
        this.discountPromoCode = $("#discount_promo_code");
        // NOTE: this page's layout (layouts.default-5) never loads
        // css/app.css — only Bootstrap. StripeReaderIdentity.applyState()
        // builds its markup entirely out of Tailwind/app.css classes, so it
        // would render unstyled here. renderCardReaderState() below is a
        // POS-local equivalent built from Bootstrap classes only, driven by
        // the same state names for consistency of meaning, not markup.
        //
        // Wrapped in try/catch — unlike backend-card-reader.js and
        // backend-card-reader-info.js, this constructor never guarded
        // against StripeTerminal failing to load (e.g. the CDN script
        // blocked/slow), which would otherwise throw silently here with no
        // visible error and no console-free way to tell "Connect" is
        // pointless because this.cardReader was never actually created.
        this.cardReader = null;
        try {
            this.cardReader = new StripeCardReader({
                messageDiv: '.card-reader-status-output',
                onStateChange: (state, meta) => this.renderCardReaderState(state, meta),
            });
            this.renderCardReaderState('idle', {});
        } catch (err) {
            console.error('Failed to initialize Stripe Terminal:', err);
            $('.card-reader-status-output').html(
                `<div class="alert alert-danger w-100" role="alert">Card reader is unavailable (${err.message}). Please refresh the page or contact support.</div>`
            );
        }
        // The workstation's reader assignment lives in localStorage
        // (StripeReaderIdentity), so the browser's own 'storage' event is
        // the right tool for "another tab changed it" — it fires instantly
        // in every other tab of this origin the moment that happens,
        // regardless of whether POS currently has focus. Covers the case
        // that mattered: switching readers on the Card Reader Settings
        // page in another tab, then coming back here — the payment-step
        // check (reconnectCardReaderIfChanged called from
        // renderConfirmPaymentStep) only catches a *new* transaction
        // starting, not this.
        window.addEventListener('storage', (evt) => {
            if (evt.key === 'stripeCardReaderInfo' || evt.key === 'stripeCardReader') {
                this.reconnectCardReaderIfChanged();
            }
        });
        this.html5QrCode = null;
        this.paymentIntent = null;
        this.paymentIntentId = null;
        this.retrieveOnHoldCarts();
        this.renderOnHoldOrdersCount();
        this.fetchPackages();
        this.initScriptForFullScreen();
        this.initCalendar();
        this.updateCalendar();
        this.populateMonthGrid();
        this.populateYearGrid();


        $(document).off('click', '.tour-category').on('click', '.tour-category', (evt) => {
            this.categoryId = evt.target.dataset.id;
            this.makeCategoryActive();
            if ($(`#${this.thankYouSectionDiv}`).is(':visible')) {
                $(`#${this.posMainDiv}`).removeClass('d-none');
                $(`#mainContent`).removeClass('d-none');
                $(`#${this.thankYouSectionDiv}`).addClass('d-none');
                $(`#${this.cartSectionDiv}`).removeClass('d-none');
            }
            this.fetchPackages();
        });
        $(document).off('click', '#searchPackage').on('click', '#searchPackage', (evt) => {
            var search = $('#searchBox').val();
            if (search != '') {
                this.searchPackages();
            } else {
                this.categoryId = $('#category-search option:selected').val();
                this.makeCategoryActive();
                this.fetchPackages();
            }
        });
        $(document).off('click', '.date').on('click', '.date', (evt) => {
            this.selectedDay = '';
            this.selectedMonth = '';
            this.selectedYear = '';
            var dayNumber = $(evt.target).text();
            var month = $('#calendarMonth').text();
            var monthNumber = Object.keys(this.monthNames).find(key => this.monthNames[key] === month);
            monthNumber = parseInt(monthNumber) + 1;
            if (dayNumber < 10) {
                dayNumber = "0" + dayNumber;
            }
            if (monthNumber < 10) {
                monthNumber = "0" + monthNumber;
            }
            var year = $('#calendarYear').text();
            var date = year + monthNumber + dayNumber;
            var tourPackageId = this.currentPackageId;
            if (tourPackageId != '') {
                this.fetchTourSlots(tourPackageId, date);
            }
        });
        $(document).off('click', '#addInfoBtn').on('click', '#addInfoBtn', (evt) => {
            var activeTab = $('#addInfoModal button.nav-item.active').attr('id');
            if (activeTab == 'nav-note') {
                var note = $('#pos-note').val();
                if (note != '') {
                    $('#add-info').addClass('info-added-btn');
                } else {
                    $('#add-info').removeClass('info-added-btn');
                }

            } else if (activeTab == 'nav-contact-info') {
                var customerName = $('#customer_name').val();
                var customerPhone = $('#customer_phone_number').val();
                var customerEmail = $('#customer_email').val();

                if (!validateEmailAddressFormat({ inputId: 'customer_email' }))
                    return false;

                if (customerEmail != '' || customerPhone != '' || customerName != '') {
                    $('#add-info').addClass('info-added-btn');
                }
            }
            $('#addInfoModal').modal('hide');
        });
        $(document).off('click', '#radioButtonContainerId input[type="radio"]').on('click', '#radioButtonContainerId input[type="radio"]', (evt) => {
            var discountType = $(evt.target).val();

            if (discountType == 'Percent') {
                this.disableAndClear(this.discountFixedMoney, this.discountPromoCode);
                this.enableAndFocus(this.discountPercentage);
            } else if (discountType == 'Promo Code') {
                this.disableAndClear(this.discountFixedMoney, this.discountPercentage);
                this.enableAndFocus(this.discountPromoCode);
            } else {
                this.enableAndFocus(this.discountFixedMoney);
                this.disableAndClear(this.discountPercentage, this.discountPromoCode);
            }
        });
        $(document).off('click', '#applyDiscountBtn').on('click', '#applyDiscountBtn', (evt) => {
            this.applyDiscount();
        });
        $(document).off('click', '#managerRequestBtn').on('click', '#managerRequestBtn', (evt) => {
            this.sendRequestToManager();
        });
        $(document).off('click', '#requestManualOverrideBtn').on('click', '#requestManualOverrideBtn', (evt) => {
            var manager = $('#manager option:selected').val();
            if (manager == '') {
                swal('Please select manager.', '', 'warning');
                return false;
            }
            this.manager = manager;
            $("#requestManualIdModal").modal("show");

        });
        $(document).off('click', '#requestManualManagerIdBtn').on('click', '#requestManualManagerIdBtn', (evt) => {
            var manualManagerId = $('#manualManagerId').val();
            if (manualManagerId == '') {
                swal('Please enter Manager Id', '', 'warning');
                return false;
            }
            this.manualManagerId = manualManagerId;

            this.checkManagerId();
        });
        $(document).off('click', '#requestManualManagerPinBtn').on('click', '#requestManualManagerPinBtn', (evt) => {
            var manualManagerPin = $('#manualManagerPin').val();
            if (manualManagerPin == '') {
                swal('Please enter Manager PIN', '', 'warning');
                return false;
            }
            this.manualManagerPin = manualManagerPin;
            this.checkManagerPin();
        });
        $(document).off('click', '#continue-btn').on('click', '#continue-btn', (evt) => {
            // reset payment intent for card reader
            this.isCartReaderPayment = false;
            this.paymentIntent = null;

            var selectedDateRadio = $('.slots-radio:checked');
            if (selectedDateRadio.length == 0) {
                swal('Please select slot.', '', 'warning');
                return false;
            }
            var date = this.getSelectedDate();
            var time = selectedDateRadio.val();
            var slotId = selectedDateRadio.data('id');
            var tourPackageId = $('#calendarModal').data('tourPackageId');
            var rateGroupId = $('#calendarModal').data('rateGroupId');
            //This is for dynamic pricing of rategroups datewise or slotwise

            if (rateGroupId != '' && (this.applyOnDate || this.applyOnSlots)) {
                this.manipulateRateGroups(tourPackageId, rateGroupId, slotId, date, time);
            } else {
                if (rateGroupId == '') {
                    var tourPackages = this.selectedTourPackages;
                    var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
                    if (tourPackageIndex != -1 && tourPackages[tourPackageIndex].rategroups.length > 0) {
                        var tourists = tourPackages[tourPackageIndex].tourists;
                        var isOverBooking = tourPackages[tourPackageIndex].isOverBooking;
                        //Check if overbooking allowed when package added in the cart
                        if (isOverBooking != 1) {
                            this.checkOverBookingUpdateSlot(slotId, tourPackageId, tourists, date, time);
                        } else {
                            this.updateSlot(tourPackageId, slotId, date, time);
                            $('#calendarModal').modal('hide');
                        }
                    }
                } else {
                    this.checkOverBooking(slotId, tourPackageId, rateGroupId, date, time);
                }
            }
        });
        $(document).off('click', '#hold-order').on('click', '#hold-order', (evt) => {
            this.createOnHoldCart();
        });
        $(document).off('click', '#onhold-orders').on('click', '#onhold-orders', (evt) => {
            this.renderOnHoldCartOrders();
        });
        $(document).off('click', '#refresh').on('click', '#refresh', (evt) => {
            this.refresh();
        });
        $(document).off('click', '#home').on('click', '#home', (evt) => {
            this.makeMenuActive(this.menuForHome);
            this.renderHome();
        });
        $(document).off('click', '#orders').on('click', '#orders', (evt) => {
            if (this.userType == '') {
                this.showScannerCustomerOrder();
            } else {
                this.fetchOrders();
            }
        });
        $(document).off('click', '.printReceipt').on('click', '.printReceipt', (evt) => {
            var orderId = this.orderId;
            if (orderId != '') {
                var printReceiptUrl = `${this.printReceiptUrl}/${orderId}`;
                window.open(printReceiptUrl, '_blank');
            }
        });

        $(document).off('keyup', '#searchBox').on('keyup', '#searchBox', (evt) => {
            this.searchPackages();

        });
        $(document).off('keyup', '#orderSearch').on('keyup', '#orderSearch', (evt) => {
            this.searchOrders();

        });
        $(document).off('click', '.book-pay-method').on('click', '.book-pay-method', (evt) => {
            this.paymentMethod = evt.target.id;
            this.calculateTotals();
            this.renderCardDetails();
        });
        $(document).off('keyup', '#credit-amount-input').on('keyup', '#credit-amount-input', (evt) => {
            this.creditAmount = ($(evt.target).val() != '' && $(evt.target).val() > 0) ? $(evt.target).val() : 0;
            this.calculateBalanceAmount();
        });
        $(document).off('click', '.calculator-keys').on('click', '.calculator-keys', (evt) => {

            var digitVal = evt.target.dataset.value;

            if (digitVal != '.' && digitVal != 'x' && digitVal != 'clear') {
                var creditAmount = (this.creditAmount == 0) ? '' : this.creditAmount;
                creditAmount = creditAmount + digitVal;
                this.creditAmount = creditAmount;

                $('#credit-amount-input').val(creditAmount);
                this.calculateBalanceAmount();
            } else {
                var creditAmount = parseFloat(this.creditAmount);
                if (digitVal == 'x') {
                    if (creditAmount != '' && creditAmount >= 0) {
                        var strLength = creditAmount.toString().length - 1;
                        this.creditAmount = (creditAmount > 0) ? creditAmount.toString().substring(0, strLength) : 0;
                        if (this.creditAmount == '') {
                            this.creditAmount = 0;
                            $('#credit-amount-input').val('');
                        } else {
                            $('#credit-amount-input').val(this.creditAmount);
                        }
                    }
                } else if (digitVal == '.') {
                    if (creditAmount != '' && creditAmount >= 0 && creditAmount % 1 === 0) {
                        creditAmount = creditAmount.toString() + digitVal;
                        this.creditAmount = creditAmount;
                        $('#credit-amount-input').val(creditAmount);

                    }
                } else if (digitVal == 'clear') {
                    this.creditAmount = 0;
                    $('#credit-amount-input').val('');
                }
                this.calculateBalanceAmount();
            }
        });
        $(document).off('click', '#confirm-pay-button').on('click', '#confirm-pay-button', (evt) => {
            this.validateCart();
        });
        $(document).off('click', '#back-to-cart-step').on('click', '#back-to-cart-step', (evt) => {
            this.cardReader.disconnectReader();
            this.renderCartStep();
        });
        $(document).off('click', '#home-btn').on('click', '#home-btn', (evt) => {
            this.backToHome();
        });
        $(document).off('click', '#back-to-home-btn').on('click', '#back-to-home-btn', (evt) => {
            this.backToHome();
        });
        $(document).off('click', '#confirm-payment-button').on('click', '#confirm-payment-button', (evt) => {
            this.createBookings();
        });
        $(document).off('click', '.confirm-pay-method').on('click', '.confirm-pay-method', (evt) => {
            var oldPaymentMethod = this.paymentMethod;
            this.paymentMethod = evt.target.id;
            this.calculateTotals();
            this.renderTotalsInConfirmPaymentStep();
            // Card/Cash both submit through #confirm-payment-button — make
            // sure it's back if the Card Reader tab had hidden it.
            $('#confirm-payment-button').removeClass('d-none');
            if (this.paymentMethod == 'Card' && oldPaymentMethod != 'Card') {
                this.initializeStripe();
            }
        });
        // Card Reader tab: it has its own Charge button (which bypasses
        // #confirm-payment-button entirely via onCardReaderSuccess), so hide
        // that button while this tab is open, and only connect to the
        // reader once someone has actually opened this tab — never on the
        // cart-to-payment transition itself, regardless of payment method.
        $(document).off('shown.bs.tab', '#pos-card-reader-tab-btn').on('shown.bs.tab', '#pos-card-reader-tab-btn', () => {
            $('#confirm-payment-button').addClass('d-none');
            $('.connect-to-card-reader').trigger('click');
        });
        // Button visibility itself is driven by renderCardReaderState() off
        // the actual connection state (so an auto-reconnect after an
        // unexpected disconnect keeps them in sync too) — these just trigger
        // the action.
        $(document).off('click', '.connect-to-card-reader').on('click', '.connect-to-card-reader', async () => {
            if (!this.cardReader) {
                $('.card-reader-status-output').html('<div class="alert alert-danger w-100" role="alert">Card reader is unavailable. Please refresh the page.</div>');
                return;
            }
            try {
                await this.cardReader.connectToReader("make-payment-with-card-reader", false, null, this.total);
            } catch (err) {
                console.error('Card reader connect failed:', err);
                $('.card-reader-status-output').html(`<div class="alert alert-danger w-100" role="alert">Couldn't connect: ${err.message || err}</div>`);
            }
        });
        $(document).off('click', '.dis-connect-to-card-reader').on('click', '.dis-connect-to-card-reader', async () => {
            if (!this.cardReader) return;
            await this.cardReader.disconnectReader();
        });
        $(document).off('click', '.send-receipt').on('click', '.send-receipt', (evt) => {
            var parentId = $(evt.target).parent().parent().parent().attr('id');
            this.sendReceipt(parentId);
        });
        $(document).off('click', '#setting').on('click', '#setting', (evt) => {
            this.showPosSetting();
        });
        $(document).off('click', '#update-settings').on('click', '#update-settings', (evt) => {
            this.updatePosSetting();
        });
        $(document).off('click', '#cancel-btn').on('click', '#cancel-btn', (evt) => {
            $('#cancelBookingModal').modal('show');
        });
        $(document).off('click', '#refund-btn').on('click', '#refund-btn', (evt) => {
            $('#refundOrderModal').modal('show');
        });
        $('#cancelBookingModal').on('show.bs.modal', function (e) {
            initTourBookingPos.renderOrderBookingIds();
            $('#cancelBookingModal #ticketRefund').prop('checked', true);
            $('#cancelBookingModal #isCash').prop('checked', false);
            $('#cancelBookingModal #isWire').prop('checked', false);
            $('#cancelBookingModal #cancelNote').val('');
        });
        $('#refundOrderModal').on('show.bs.modal', function (e) {
            initTourBookingPos.renderOrderBookingIdsForRefund();
            $('#refundOrderModal #ticketRefundOpt').prop('checked', true);
            $('#refundOrderModal #isCash').prop('checked', false);
            $('#refundOrderModal #isWire').prop('checked', false);
            $('#refundOrderModal #refundNote').val('');
        });
        $(document).on('change', '#cancelBookingModal input[type=checkbox][name=isCash]', function () {
            if (this.value == 'Cash') {
                $('#cancelBookingModal #isWire').prop('checked', false);
            }
            if (this.value == 'Wire Transfer') {
                $('#cancelBookingModal #isCash').prop('checked', false);
            }
        });
        $(document).on('change', '#refundOrderModal input[type=checkbox][name=isCash]', function () {
            if (this.value == 'Cash') {
                $('#refundOrderModal #isWire').prop('checked', false);
            }
            if (this.value == 'Wire Transfer') {
                $('#refundOrderModal #isCash').prop('checked', false);
            }
        });
        $(document).off('click', '#cancelOrderBtn').on('click', '#cancelOrderBtn', (evt) => {
            if (this.allowedCancelOrderPermission == 'Yes') {
                this.cancelOrder();
            } else {
                this.checkRequest(this.requestTypeCancelOrder);
            }
        });
        $(document).off('click', '#refundOrderBtn').on('click', '#refundOrderBtn', (evt) => {
            if (this.allowedRefundOrderPermission == 'Yes') {
                this.refundOrder();
            } else {
                this.checkRequest(this.requestTypeRefundOrder);
            }
        });

        // Card Reader Events
        $(document).off('click', '.make-payment-with-card-reader').on('click', '.make-payment-with-card-reader', () => {
            this.capturePaymenWithStripeCardReader();
        });

        $(document).off('click', '.cancel-card-reader-payment').on('click', '.cancel-card-reader-payment', () => {
            // note you can pass custom success and error functions below:
            // example: this.cardReader.cancelPayment(onSuccess, onError);
            this.cardReader.cancelPayment();
        });

    }
    initScriptForFullScreen() {
        if (document.fullscreenEnabled || document.webkitFullscreenEnabled) {
            const toggleBtn = this.toggleBtn;

            const styleEl = document.createElement('link');
            styleEl.setAttribute('rel', 'stylesheet');
            styleEl.addEventListener('load', function () {
                toggleBtn.hidden = false;
            });
            document.head.appendChild(styleEl);

            toggleBtn.addEventListener('click', function () {
                if (document.fullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitFullscreenElement) {
                    document.webkitCancelFullScreen()
                } else if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else {
                    document.documentElement.webkitRequestFullScreen();
                }
            });

            document.addEventListener('fullscreenchange', this.handleFullscreen());
            document.addEventListener('webkitfullscreenchange', this.handleFullscreen());

        }
    }
    handleFullscreen() {
        const toggleBtn = this.toggleBtn;
        if (document.fullscreen || document.webkitFullscreenElement) {
            toggleBtn.classList.add('on');
            toggleBtn.setAttribute('aria-label', 'Exit fullscreen mode');
        } else {
            toggleBtn.classList.remove('on');
            toggleBtn.setAttribute('aria-label', 'Enter fullscreen mode');
        }
    }
    renderOnHoldOrdersCount() {
        var onHoldCarts = [];
        var cookies = decodeURIComponent(document.cookie).split(';');
        for (var i = 0; i < cookies.length; i++) {
            var c = cookies[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf('onHoldCarts') == 0) {
                onHoldCarts = c;
            }
        }

        if (onHoldCarts.length > 0) {
            var od = onHoldCarts.split('=');
            var onHoldCartOrders = JSON.parse(od[1]);

            if (onHoldCartOrders.length > 0) {
                $('#on-hold-orders-count').text(onHoldCartOrders.length);
            } else {
                $('#on-hold-orders-count').text(0);
            }
        } else {
            $('#on-hold-orders-count').text(0);
        }
    }
    setParamsFetchPackages() {
        var selectedPackages = this.selectedTourPackages;
        var search = $('#searchBox').val();
        var data = [];
        selectedPackages.forEach((f) => {
            data.push(
                {
                    tourPackageId: f.tourPackageId,
                    slotId: f.slotId,
                    slotDate: (f.slotDate != '') ? moment(f.slotDate).format('YMMDD') : '',
                    slotTime: f.slotTime,
                    applyOnDate: f.applyOnDate,
                    applyOnSlots: f.applyOnSlots
                }
            );
        });
        var params = {
            tourPackages: data,
            search: search
        };
        return params;

    }
    fetchPackages() {
        showLoading();
        $.post({
            url: `${this.fetchPackagesUrl}/${this.categoryId}`,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            dataType: 'json',
            data: JSON.stringify(this.setParamsFetchPackages()),
            contentType: 'application/json;',
            success: function (data) {
                initTourBookingPos.fetchPackagesOk(data);
            }
        }).fail(function (jqXHR) {
            swal('Error occurred', 'Unable to get packages from server.', 'error');
        })
            .always(hideLoading);

    }
    fetchPackagesOk(data) {
        this.tourPackages = data.tourPackages;
        var tourPackages = data.tourPackages;
        var html = ``;
        for (let i = 0; i < tourPackages.length; i++) {
            html += this.renderPackage(tourPackages[i], i);
        }
        $(`#${this.targetDiv}`).html(html);
        this.catalogue = document.querySelector(".catalogue");
        this.allCards = document.querySelectorAll(".item");
        if (this.allCards.length > 0) {
            this.updateCardPositions();
            this.setUpEventListeners();
        }
        this.makeMenuActive(this.menuForHome);
        this.renderOnHoldOrdersCount();

    }
    searchPackages() {
        let admin = (userType === 'Admin') ? "/admin" : "";
        var categoryId = $('#category-search option:selected').val();
        var search = $('#searchBox').val();
        $(`#${this.posMainDiv}`).removeClass('d-none');
        if (search != '') {
            if (search.length >= 3) {
                var oldActive = document.getElementsByClassName("tour-category active");
                for (var i = 0; i < oldActive.length; i++) {
                    oldActive[i].classList.remove("active");
                }
                $(`.tour-category[data-id="${categoryId}"]`).addClass('active');

                showLoading();

                $.post({
                    url: `${this.fetchPackagesUrl}/${categoryId}`,
                    dataType: 'json',
                    data: JSON.stringify(this.setParamsFetchPackages()),
                    contentType: 'application/json;',
                    success: function (data) {
                        initTourBookingPos.fetchPackagesOk(data);
                    }
                }).fail(function (jqXHR) {
                    swal('Error occurred', 'Unable to get packages from server.', 'error');
                })
                    .always(hideLoading);
            }
        } else {
            var oldActive = document.getElementsByClassName("tour-category active");
            for (var i = 0; i < oldActive.length; i++) {
                oldActive[i].classList.remove("active");
            }
            $(`.tour-category[data-id="${this.categoryId}"]`).addClass('active');
            this.fetchPackages();
        }
    }
    getContentHeight(card) {
        const content = card.querySelector('.selected-category');
        return content ? content.offsetHeight : 0;
    }
    updateCardPositions() {

        const cardWidth = this.allCards[0].offsetWidth;
        const count = Math.floor(this.catalogue.offsetWidth / cardWidth);

        let colIndex = 1;
        let rowIndex = 1;

        this.allCards.forEach(card => {
            if (colIndex > count) {
                colIndex = 1;
                rowIndex++;
            }

            card.dataset.column = colIndex;
            card.dataset.row = rowIndex;

            colIndex++;
        });
    }
    setUpEventListeners() {

        this.allCards.forEach(card => {
            card.addEventListener("mouseover", (e) => {
                const column = e.currentTarget.dataset['column'];
                let row = parseInt(e.currentTarget.dataset['row'], 10);
                const contentHeight = this.getContentHeight(e.currentTarget);

                // Push down cards below the hovered card based on content height
                this.allCards.forEach(targetCard => {
                    const targetColumn = targetCard.dataset.column;
                    const targetRow = parseInt(targetCard.dataset.row, 10);
                    if (targetColumn === column && targetRow > row) {
                        targetCard.style.transform = `translateY(${contentHeight}px)`;
                    }
                });
            });

            card.addEventListener("mouseout", (e) => {
                this.allCards.forEach(targetCard => {
                    targetCard.style.transform = 'translateY(0)';
                });
            });
        });
    }
    initCalendar() {
        document.getElementById("calendarMonth").addEventListener("click", () => {
            this.showMonthSelection();
        });
        document.getElementById("calendarYear").addEventListener("click", () => {
            this.showYearSelection();
        });
        document.getElementById("nextButton").addEventListener("click", () => {
            this.next();
        });
        document.getElementById("prevButton").addEventListener("click", () => {
            this.prev();
        });
    }
    updateCalendar() {
        calendarMonth.textContent = this.monthNames[this.currentMonth];
        calendarYear.textContent = this.currentYear;
        this.renderCalendar(this.currentMonth, this.currentYear);
    }

    showMonthSelection() {
        this.selectedView = 'monthSelection';
        document.getElementById("monthSelection").classList.add("active");
    }

    showYearSelection() {
        this.selectedView = 'yearSelection';
        document.getElementById("yearSelection").classList.add("active");
    }

    closeSelection() {
        document.getElementById("monthSelection").classList.remove("active");
        document.getElementById("yearSelection").classList.remove("active");
        this.selectedView = 'calendar';
    }

    populateMonthGrid() {
        this.monthGrid.innerHTML = "";
        this.monthNames.forEach((month, index) => {
            const monthSpan = document.createElement("span");
            monthSpan.classList.add('month');
            monthSpan.textContent = month;
            monthSpan.classList.toggle("selected", index === this.currentMonth);
            monthSpan.addEventListener("click", () => {
                this.currentMonth = index;
                this.closeSelection();
                this.updateCalendar();
            });
            this.monthGrid.appendChild(monthSpan);
        });
    }

    populateYearGrid() {
        this.yearGrid.innerHTML = "";

        // Show years from currentYear - 10 to currentYear + 10
        for (let i = this.currentYear - 10; i <= this.currentYear + 10; i++) {
            const yearSpan = document.createElement("span");
            yearSpan.classList.add('year');
            yearSpan.textContent = i;
            yearSpan.classList.toggle("selected", i === this.currentYear);
            yearSpan.addEventListener("click", () => {
                this.currentYear = i;
                this.closeSelection();
                this.updateCalendar();
            });
            this.yearGrid.appendChild(yearSpan);
        }
    }

    prev() {
        if (this.selectedView === 'calendar') {
            if (this.currentMonth == 0) {
                this.currentMonth = 11;
                this.currentYear--;
            } else {
                this.currentMonth--;
            }
            this.updateCalendar();
        } else if (this.selectedView === 'yearSelection') {
            this.currentYear -= 10;
            this.populateYearGrid();
        }
    }

    next() {
        if (this.selectedView === 'calendar') {
            if (this.currentMonth === 11) {
                this.currentMonth = 0;
                this.currentYear++;
            } else {
                this.currentMonth++;
            }
            this.updateCalendar();
        } else if (this.selectedView === 'yearSelection') {
            this.currentYear += 10;
            this.populateYearGrid();
        }
    }
    disableAndClear(element1, element2) {
        element1.prop('disabled', true).val('');
        element2.prop('disabled', true).val('');
    }

    enableAndFocus(element) {
        element.prop('disabled', false).focus();
    }
    renderCalendar(month, year) {
        this.datesElement.innerHTML = "";

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        for (let i = 0; i < firstDay; i++) {
            this.datesElement.innerHTML += "<span></span>";
        }

        var selectedDay = this.selectedDay;
        var selectedMonth = this.selectedMonth;
        var selectedYear = this.selectedYear;
        selectedMonth = (selectedMonth >= 1) ? selectedMonth - 1 : 0;

        for (let day = 1; day <= daysInMonth; day++) {
            const dateSpan = document.createElement("span");
            dateSpan.textContent = day;

            if (year < today.getFullYear() || (year == today.getFullYear() && month < today.getMonth()) || (year == today.getFullYear() && month == today.getMonth() && day < today.getDate())) {
                dateSpan.classList.add("disabled");
            } else if (selectedDay != '' && selectedMonth >= 0 && selectedYear != '') {

                if (selectedYear == year && selectedMonth == month && day == selectedDay) {
                    dateSpan.classList.add("selected");
                }
                dateSpan.classList.add("date");
                dateSpan.addEventListener("click", () => this.selectDate(dateSpan));
            } else if ((year == today.getFullYear() && month == today.getMonth() && day == today.getDate())) {
                dateSpan.classList.add("selected");
                dateSpan.classList.add("date");
            } else {
                dateSpan.classList.add("date");
                dateSpan.addEventListener("click", () => this.selectDate(dateSpan));
            }

            this.datesElement.appendChild(dateSpan);
        }
    }

    selectDate(dateSpan) {
        const selectedDates = document.querySelectorAll(".dates .selected");
        selectedDates.forEach(selected => selected.classList.remove("selected"));
        dateSpan.classList.add("selected");
    }
    resetCalendar() {
        this.currentMonth = new Date().getMonth();
        this.currentYear = new Date().getFullYear();
    }
    showCalendar(tourPackageId, rateGroupId) {
        this.currentPackageId = tourPackageId;
        var tourPackages = this.selectedTourPackages;
        var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
        if (tourPackageIndex != -1 && tourPackages[tourPackageIndex].rategroups.length > 0) {
            this.addRateGroup(tourPackageId, rateGroupId);
        } else {
            $('#calendarModal').data('tourPackageId', tourPackageId);
            $('#calendarModal').data('rateGroupId', rateGroupId);
            this.resetCalendar();
            this.updateCalendar();
            this.fetchTourSlots(tourPackageId, this.todayDate);
        }
    }
    changeSlot(tourPackageId, slotId, slotDate) {
        var tourPackages = this.selectedTourPackages;
        var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
        if (tourPackageIndex != -1 && tourPackages[tourPackageIndex].rategroups.length > 0) {
            var date = moment(slotDate).format('YMMDD');
            var day = moment(slotDate).format('DD');
            var month = moment(slotDate).format('M');
            var year = moment(slotDate).format('YYYY');
            this.currentYear = year;
            this.currentMonth = parseInt(month - 1);
            this.selectedDay = day;
            this.selectedMonth = month;
            this.selectedYear = year;
            this.currentPackageId = tourPackageId;
            this.updateCalendar();
            this.selectedSlotId = slotId;
            this.fetchTourSlots(tourPackageId, date);
            $('#calendarModal').data('tourPackageId', tourPackageId);
            $('#calendarModal').data('rateGroupId', '');
            $('#calendarModal').modal('show');
        }

    }
    updateSlot(tourPackageId, slotId, date, slotTime, isOverBooking = '', availableSeats = '') {
        if (this.applyOnDate || this.applyOnSlots) {
            this.manipulateSelectedRateGroups(tourPackageId);
        }
        var tourPackages = this.selectedTourPackages;
        if (tourPackages.length > 0) {
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1 && tourPackages[tourPackageIndex].rategroups.length > 0) {
                tourPackages[tourPackageIndex].slotId = slotId;
                tourPackages[tourPackageIndex].slotDate = date;
                tourPackages[tourPackageIndex].slotTime = slotTime;
                if (isOverBooking != '') {
                    tourPackages[tourPackageIndex].isOverBooking = isOverBooking;
                }
                if (availableSeats != '') {
                    tourPackages[tourPackageIndex].availableSeats = availableSeats;
                }
                var slotDate = moment(date).format('ddd, MMM Do, YYYY');
                var slotTime = slotDate + ' @ ' + slotTime;
                $(`#collapse_${tourPackageId} .date-time`).text(slotTime);
                var html = `<button class="signupbtn edit-btn" onclick="initTourBookingPos.changeSlot('${tourPackageId}','${slotId}','${date}');"><i class="fa fa-pencil" aria-hidden="true"></i></button>`;
                $(`#collapse_${tourPackageId} .edit-slot`).html(html);
            }
            this.selectedTourPackages = tourPackages;

        }
    }
    getSelectedDate() {
        var dayNumber = $(".date").filter(".selected").text();
        var year = $('#calendarYear').text();
        var month = $('#calendarMonth').text();
        var date = year + "-" + month + "-" + dayNumber;

        //var formattedDate = moment(date).format('dddd, MMM Do, YYYY');
        return date;
    }
    markSelectedDate(day) {
        this.selectedDay = day;
        $("#dates span.date").each(function () {
            var $this = $(this);
            var text = $this.text();
            if (day == text) {
                $this.addClass('selected');
            } else {
                $this.removeClass('selected');
            }
        });
    }
    fetchTourSlots(tourPackageId, date) {
        showLoading();
        $.post({
            url: `${this.getTourSlotTimesByDateUrl}`,
            headers: { Accept: 'application/json' },
            dataType: 'json',
            data: { date: date, tourPackageId: tourPackageId, getRateGroups: 1 },
            success: function (data) {
                initTourBookingPos.fetchTourSlotsOk(data, date, tourPackageId);
            }
        }).fail(function (jqXHR) {
            showSubmissionError(jqXHR);
        })
            .always(hideLoading);
    }
    fetchTourSlotsOk(data, date, tourPackageId) {
        this.tourSlots = data.slotTimes;
        this.applyOnSlots = data.applyOnSlots;
        if (!this.applyOnSlots) {
            var tourPackages = this.tourPackages;
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);

            if (tourPackageIndex != -1 && tourPackages[tourPackageIndex].rategroups.length > 0 && data.rateGroups.length > 0) {
                var currentRateGroups = tourPackages[tourPackageIndex].rategroups;
                var dynamicRateGroups = data.rateGroups;
                if (currentRateGroups[0].id != dynamicRateGroups[0].id) {
                    this.applyOnDate = true;
                    for (let i = 0; i < dynamicRateGroups.length; i++) {
                        delete dynamicRateGroups[i].cart_tourists;
                        delete dynamicRateGroups[i].additional_tax_percentage;
                        delete dynamicRateGroups[i].description;
                        delete dynamicRateGroups[i].margin;
                        delete dynamicRateGroups[i].stripe_commission_percentage;
                        delete dynamicRateGroups[i].scheduling_year;
                        delete dynamicRateGroups[i].processing_charge_percentage;
                    }
                    this.dynamicRateGroups = dynamicRateGroups;
                    if (tourPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                        this.dynamicPaxWiseGroupAdditionalCharges = data.paxWiseGroupAdditionalCharges;
                        this.dynamicPaxWiseGroupRateIds = data.paxWiseGroupRateIds;
                        this.dynamicPaxWiseGroupRates = data.paxWiseGroupRates;
                    }
                } else {
                    this.applyOnDate = false;
                    this.dynamicRateGroups = [];
                }
            }
        }

        this.renderTourSlots(date, tourPackageId);
    }
    fetchSlotBasedRateGroups(tourPackageId, date, customRateId = 0, tourSlotId = 0) {
        var schedulingYear = moment(date).format('YYYY');
        showLoading();
        $.post({
            url: `${this.getTourRatesBySlotUrl}`,
            headers: { Accept: 'application/json' },
            dataType: 'json',
            data: { scheduling_year: schedulingYear, date: date, tour_package_id: tourPackageId, affiliate_id: 0, custom_date_id: customRateId, time_slot_id: tourSlotId },
            success: function (data) {
                initTourBookingPos.fetchSlotBasedRateGroupsOk(data, date, tourPackageId, customRateId);
            }
        }).fail(function (jqXHR) {
            showSubmissionError(jqXHR);
        })
            .always(hideLoading);

    }
    fetchSlotBasedRateGroupsOk(data, date, tourPackageId, customRateId = 0) {
        if (this.applyOnSlots) {
            var tourPackages = this.tourPackages;
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1 && tourPackages[tourPackageIndex].rategroups.length > 0 && data.rates.length > 0) {
                var currentRateGroups = tourPackages[tourPackageIndex].rategroups;
                var dynamicRateGroups = data.rates;
                if (currentRateGroups[0].id != dynamicRateGroups[0].id) {
                    this.applyOnDate = false;
                    for (let i = 0; i < dynamicRateGroups.length; i++) {
                        delete dynamicRateGroups[i].cart_tourists;
                        delete dynamicRateGroups[i].additional_tax_percentage;
                        delete dynamicRateGroups[i].description;
                        delete dynamicRateGroups[i].margin;
                        delete dynamicRateGroups[i].stripe_commission_percentage;
                        delete dynamicRateGroups[i].scheduling_year;
                        delete dynamicRateGroups[i].processing_charge_percentage;
                    }
                    this.dynamicRateGroups = dynamicRateGroups;
                    if (tourPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                        this.dynamicPaxWiseGroupAdditionalCharges = data.paxWiseGroupAdditionalCharges;
                        this.dynamicPaxWiseGroupRateIds = data.paxWiseGroupRateIds;
                        this.dynamicPaxWiseGroupRates = data.paxWiseGroupRates;
                    }
                } else {
                    this.applyOnSlots = false;
                    this.dynamicRateGroups = [];
                    var tourPackageIndex = this.selectedTourPackages.findIndex(x => x.tourPackageId == tourPackageId);//Check if the selected package is in the cart and user trying to change the slot
                    if (tourPackageIndex == -1) {
                        if (dynamicRateGroups.length > 0) {
                            $('#calendarModal').data('rateGroupId', dynamicRateGroups[0].id);
                        }
                    }
                }
            }

        }
    }
    fetchOrders() {
        showLoading();
        $.get(`${this.fetchOrdersUrl}`)
            .done(this.fetchOrdersOk.bind(this))
            .fail(function () {
                swal('Error occurred', 'Unable to get orders from server.', 'error');
            })
            .always(hideLoading);
    }
    fetchOrdersOk(data) {
        this.orders = data.orders;
        this.renderOrders();
    }
    searchOrders() {
        showLoading();
        var search = $('#orderSearch').val();
        $.get(`${this.fetchOrdersUrl}?search=${search}`)
            .done(this.searchOrdersOk.bind(this))
            .fail(function () {
                swal('Error occurred', 'Unable to get orders from server.', 'error');
            })
            .always(hideLoading);

    }
    searchOrdersOk(data) {
        this.orders = data.orders;
        this.renderSearchOrders();
    }
    viewOrder(orderId) {
        this.orderId = orderId;
        $.get(`${this.fetchOrderUrl}/${orderId}`)
            .done(this.viewOrderOk.bind(this))
            .fail(function (jqXHR) {
                showSubmissionError(jqXHR);
            })
            .always(hideLoading);
    }
    viewOrderOk(data) {
        this.orderInfo = data;
        this.requestPermissions = data.requestPermissions;
        this.renderOrderInfo();
    }
    makeMenuActive(menuId) {
        var oldActive = document.getElementsByClassName("sidebarmenu active");
        for (var i = 0; i < oldActive.length; i++) {
            oldActive[i].classList.remove("active");
        }
        $(`#${menuId}`).parent().addClass('active');
    }
    makeCategoryActive() {
        var oldActive = document.getElementsByClassName("tour-category active");
        for (var i = 0; i < oldActive.length; i++) {
            oldActive[i].classList.remove("active");
        }
        $(`.tour-category[data-id="${this.categoryId}"]`).addClass('active');

    }
    renderPackage(packageData, index) {
        var selectedPackageIndex = this.selectedTourPackages.findIndex(x => x.tourPackageId == packageData.tourPackageId);
        var html = `<div class="item">
                                    <div class="details-box detail-pkg-box" data-id="${packageData.tourPackageId}">
                                        <img src="${packageData.packageImage}" data-id="${packageData.tourPackageId}" width="348" height="142" alt="Category Image">
                                        <div class="text-wrap">
                                            <h4 class="category-title">${packageData.tourPackageName}</h4>
                                            <div class="selected-category" id="panel_${packageData.tourPackageId}">`;

        for (let i = 0; i < packageData.rategroups.length; i++) {
            if (packageData.isGroupRateEnabled == 1) {
                var rate = this.roundout(parseFloat(packageData.rategroups[i].rate) + parseFloat(packageData.rategroups[i].additional_charge));

            } else {
                var rate = this.roundout(parseFloat(packageData.rategroups[i].rate) + parseFloat(packageData.rategroups[i].additional_charge) + parseFloat(packageData.rategroups[i].permit_fee), 2);

            }
            var activeClass = '';
            if (selectedPackageIndex != -1) {
                if (packageData.isGroupRateEnabled == 1) {
                    activeClass = 'active';
                } else {
                    var selectedRateGroupIndex = this.selectedTourPackages[selectedPackageIndex].rategroups.findIndex(x => x.id == packageData.rategroups[i].id);
                    if (selectedRateGroupIndex != -1) {
                        activeClass = 'active';
                    }
                }
            }
            if (packageData.packageHasSlots == '1') {
                html += `<button class = "rategroups-btn ${activeClass}" title="${packageData.rategroups[i].rate_for}" id = "rategrp_${packageData.tourPackageId}_${packageData.rategroups[i].id}" onclick="initTourBookingPos.showCalendar('${packageData.tourPackageId}','${packageData.rategroups[i].id}');">`;
            } else {
                html += `<button class = "rategroups-btn ${activeClass}" title="${packageData.rategroups[i].rate_for}" id = "rategrp_${packageData.tourPackageId}_${packageData.rategroups[i].id}" onclick="initTourBookingPos.addRateGroup('${packageData.tourPackageId}','${packageData.rategroups[i].id}');">`;
            }
            if (packageData.isGroupRateEnabled == 1) {
                packageData.rategroups[i].rate_for = this.rateNameForGroupRate;
            }
            html += `<span class="name" title="${packageData.rategroups[i].rate_for}">${packageData.rategroups[i].rate_for}</span><span  class="price">${currency(rate).format()}</span></button>`;
            if (packageData.isGroupRateEnabled == 1) {
                if (i == 0) {
                    break;
                }
            }
        }


        html += `</div>
                                        </div>
                                    </div>
                                </div>` ;


        return html;
    }
    renderTourSlots(date, tourPackageId) {
        var tourSlots = this.tourSlots;
        var html = ``;
        if (tourSlots.length == 0) {
            $(`#time-slots`).html('<h4 class="watermark static-date-range" style="color: #f07777;">Apologies, No slots available on your chosen date.</h4>');
            return false;
        }
        var slotDate = moment().format(date.substr(0, 4) + '-' + date.substr(4, 2) + '-' + date.substr(6), 'America/Phoenix');
        var sDT = this.serverDatenow.format('YYYY-MM-DD HH:mm:ss');

        for (let i = 0; i < tourSlots.length; i++) {
            var emptySeats = tourSlots[i].seats - tourSlots[i].tourists;
            if (emptySeats < 0) {
                emptySeats = 0;
            }
            var slotDateTime = slotDate + ' ' + tourSlots[i].time_from;
            if (slotDateTime > sDT) {
                var checked = (this.selectedSlotId == tourSlots[i].id) ? 'checked' : '';
                var disabled = ((tourSlots[i].bookable_status == 'Closed') || (this.userType == '' && emptySeats == 0)) ? 'disabled' : '';
                if ((tourSlots[i].bookable_status == 'Closed') || (this.userType == '' && emptySeats == 0)) {
                    html += `<input type="radio" class="slots-radio" id="slot${tourSlots[i].id}" name="timeSlot" value="${formatTime(tourSlots[i].time_from)}" data-id="${tourSlots[i].id}"  ${checked} ${disabled}><label for="slot${tourSlots[i].id}" >${formatTime(tourSlots[i].time_from)}<br><span style="color:red;">Closed</span></label>`;
                } else {
                    if (this.applyOnSlots) {
                        html += `<input type="radio" class="slots-radio" onclick="initTourBookingPos.fetchSlotBasedRateGroups('${tourPackageId}','${date}','${tourSlots[i].custom_rate}','${tourSlots[i].id}');" id="slot${tourSlots[i].id}" name="timeSlot" value="${formatTime(tourSlots[i].time_from)}" data-id="${tourSlots[i].id}"  ${checked}><label for="slot${tourSlots[i].id}">${formatTime(tourSlots[i].time_from)}<br><span>${emptySeats} Available</span></label>`;
                    } else {
                        html += `<input type="radio" class="slots-radio" id="slot${tourSlots[i].id}" name="timeSlot" value="${formatTime(tourSlots[i].time_from)}" data-id="${tourSlots[i].id}"  ${checked}><label for="slot${tourSlots[i].id}" ${disabled}>${formatTime(tourSlots[i].time_from)}<br><span>${emptySeats} Available</span></label>`;
                    }
                }
            }
        }
        $(`#time-slots`).html(html);
        $('#calendarModal').modal('show');
    }
    checkOverBooking(slotId, tourPackageId = '', rateGroupId = '', date = '', slotTime = '') {
        showLoading();
        $.post({
            url: `${this.checkOverBookingUrl}`,
            headers: { Accept: 'application/json' },
            dataType: 'json',
            data: { 'slot_time': slotId },
            success: function (data) {
                initTourBookingPos.checkOverBookingOk(data, slotId, tourPackageId, rateGroupId, date, slotTime);
            },
        }).fail(function (jqXHR) {
            showSubmissionError(jqXHR);
        })
            .always(hideLoading);


    }
    checkOverBookingOk(data, slotId, tourPackageId, rateGroupId = '', date, slotTime) {
        var availableSeats = parseInt(data.seats) - parseInt(data.booked_seats);
        if (availableSeats <= 1) {

            var addText = '';
            if (availableSeats < 0) {
                var overbooked = Math.abs(availableSeats) + 1;
            } else if (availableSeats == 1) {
                var overbooked = 1;
            } else {
                var overbooked = availableSeats + 1;
            }

            if (overbooked > 1) {
                if (this.userType == '') {
                    swal('The slot will be full.', '', 'warning');
                    return false;
                }
                addText += ' This slot will be overbooked by ' + overbooked + ' seats.';

            } else if (overbooked == 1) {
                if (this.userType == '') {
                    initTourBookingPos.addRateGroup(tourPackageId, rateGroupId, slotId, date, slotTime, 1, availableSeats);
                    $('#calendarModal').modal('hide');
                } else {
                    addText += ' This slot will be full.';
                }
            }

            addText += ' Do you want to proceed?';

            swal({
                title: 'An overbooking event',
                text: addText,
                icon: "warning",
                dangerMode: true,
                buttons: true,
                buttons: {
                    no: {
                        text: 'Cancel',
                        value: false,
                    },
                    yes: {
                        text: 'Book',
                        value: true,
                        className: 'btn-danger',
                    }
                }
            }).then(function (result) {
                if (result) {
                    initTourBookingPos.addRateGroup(tourPackageId, rateGroupId, slotId, date, slotTime, 1, availableSeats);
                    $('#calendarModal').modal('hide');
                } else {
                    return false;
                }
            });
        } else {
            this.addRateGroup(tourPackageId, rateGroupId, slotId, date, slotTime, 0, availableSeats);
            $('#calendarModal').modal('hide');
        }

    }
    checkOverBookingUpdateSlot(slotId, tourPackageId, tourists, date = '', slotTime = '') {
        $.post({
            url: `${this.checkOverBookingUrl}`,
            headers: { Accept: 'application/json' },
            dataType: 'json',
            data: { 'slot_time': slotId },
            success: function (data) {
                initTourBookingPos.checkOverBookingUpdateSlotOk(data, slotId, tourPackageId, tourists, date, slotTime);
            },
        }).fail(function (jqXHR) {
            showSubmissionError(jqXHR);
        })
            .always(hideLoading);

    }
    checkOverBookingUpdateSlotOk(data, slotId, tourPackageId, tourists, date, slotTime) {
        var overbooked = (parseInt(data.booked_seats) + parseInt(tourists)) - parseInt(data.seats);
        var availableSeats = parseInt(data.seats) - parseInt(data.booked_seats);
        if (overbooked >= 0) {
            if (this.userType == '') {
                if (availableSeats < tourists) {
                    swal('The slot does not have enough seats to book.', '', 'warning');
                    return false;
                } else {
                    this.updateSlot(tourPackageId, slotId, date, slotTime, '', availableSeats);
                    $('#calendarModal').modal('hide');
                    return false;
                }
            }
            var addText = '';
            if (overbooked > 0) {
                addText += ' This slot will be overbooked by ' + overbooked + ' seats.';
            } else if (overbooked == 0) {
                addText += ' This slot will be full.';
            }


            addText += ' Do you want to proceed?';

            swal({
                title: 'An overbooking event',
                text: addText,
                icon: "warning",
                dangerMode: true,
                buttons: true,
                buttons: {
                    no: {
                        text: 'Cancel',
                        value: false,
                    },
                    yes: {
                        text: 'Book',
                        value: true,
                        className: 'btn-danger',
                    }
                }
            }).then(function (result) {
                if (result) {
                    initTourBookingPos.updateSlot(tourPackageId, slotId, date, slotTime, 1, availableSeats);
                    $('#calendarModal').modal('hide');
                } else {
                    return false;
                }
            });
        } else {
            this.updateSlot(tourPackageId, slotId, date, slotTime, '', availableSeats);
            $('#calendarModal').modal('hide');
        }

    }
    checkOverBookingNewTourists() {
        var addText = '';

        addText += ' This slot will be full. Do you want to proceed?';

        swal({
            title: 'An overbooking event',
            text: addText,
            icon: "warning",
            dangerMode: true,
            buttons: true,
            buttons: {
                no: {
                    text: 'Cancel',
                    value: false,
                },
                yes: {
                    text: 'Book',
                    value: true,
                    className: 'btn-danger',
                }
            }
        }).then(function (result) {
            if (result) {
                return true;
            } else {
                return false;
            }
        });
    }
    addRateGroup(tourPackageId, rateGroupId, slotId = '', date = '', slotTime = '', isOverBooking = '', availableSeats = '') {
        var tourPackages = this.tourPackages;

        // reset payment intent for card reader
        this.isCartReaderPayment = false;
        this.paymentIntent = null;

        if (tourPackages.length > 0) {
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);

            if (tourPackageIndex != -1 && tourPackages[tourPackageIndex].rategroups.length > 0) {
                var rateGroupIndex = tourPackages[tourPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);

                var selectedRateGroup = {};
                var isGroupRateEnabled = tourPackages[tourPackageIndex].isGroupRateEnabled;
                var selectedPackageIndex = this.selectedTourPackages.findIndex(x => x.tourPackageId == tourPackageId);

                if (isGroupRateEnabled == 1) {
                    var paxWiseGroupRates = tourPackages[tourPackageIndex].paxWiseGroupRates;
                    var paxWiseGroupRateIds = tourPackages[tourPackageIndex].paxWiseGroupRateIds;
                    var paxWiseGroupAdditionalCharges = tourPackages[tourPackageIndex].paxWiseGroupAdditionalCharges;

                    selectedRateGroup.rate_for = this.rateNameForGroupRate;

                    if (selectedPackageIndex != -1) {
                        var tourists = this.selectedTourPackages[selectedPackageIndex].tourists + 1;
                    } else {
                        var tourists = 1;
                    }
                    //This is for group rate discount feature and fetching rategroup ids and rate as per number of pax
                    if (Object.keys(paxWiseGroupRates).length > 0 && Object.keys(paxWiseGroupRateIds).length > 0) {
                        if (paxWiseGroupRates[tourists] !== undefined && paxWiseGroupRateIds[tourists] !== undefined) {
                            selectedRateGroup.id = paxWiseGroupRateIds[tourists];
                            selectedRateGroup.tourists = tourists;
                            selectedRateGroup.rate = paxWiseGroupRates[tourists];
                            selectedRateGroup.additional_charge = paxWiseGroupAdditionalCharges[tourists];
                            selectedRateGroup.permit_fee = 0;
                        } else {
                            return false;
                        }
                    } else {
                        return false;
                    }

                } else {
                    selectedRateGroup.id = rateGroupId;
                    selectedRateGroup.tourists = 1;
                    selectedRateGroup.rate_for = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate_for;
                    selectedRateGroup.rate = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate;
                    selectedRateGroup.additional_charge = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].additional_charge;
                    selectedRateGroup.permit_fee = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].permit_fee;
                    selectedRateGroup.mapping_key = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].mapping_key;

                }


                if (selectedPackageIndex != -1) {
                    var slotId = this.selectedTourPackages[selectedPackageIndex].slotId;
                    var isOverBooking = this.selectedTourPackages[selectedPackageIndex].isOverBooking;
                    var availableSeats = this.selectedTourPackages[selectedPackageIndex].availableSeats;
                    var isGroupRateEnabled = this.selectedTourPackages[selectedPackageIndex].isGroupRateEnabled;
                    var tourists = this.selectedTourPackages[selectedPackageIndex].tourists + 1;
                    var addText = '';
                    if (slotId != '' && isOverBooking != 1 && tourists > availableSeats) {
                        if (this.userType == '') {
                            swal('The slot will be full.', '', 'warning');
                            return false;
                        }
                        addText += ' This slot will be overbooked by 1 seat. Do you want to proceed sdsd?';

                        swal({
                            title: 'An overbooking event',
                            text: addText,
                            icon: "warning",
                            dangerMode: true,
                            buttons: true,
                            buttons: {
                                no: {
                                    text: 'Cancel',
                                    value: false,
                                },
                                yes: {
                                    text: 'Book',
                                    value: true,
                                    className: 'btn-danger',
                                }
                            }
                        }).then(function (result) {
                            if (result) {
                                initTourBookingPos.selectedTourPackages[selectedPackageIndex].tourists = tourists;
                                initTourBookingPos.selectedTourPackages[selectedPackageIndex].isOverBooking = 1;
                                var selectedRateGroupIndex = initTourBookingPos.selectedTourPackages[selectedPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);
                                if (selectedRateGroupIndex != -1) {
                                    var newTourists = initTourBookingPos.selectedTourPackages[selectedPackageIndex].rategroups[selectedRateGroupIndex].tourists + 1;
                                    if (isGroupRateEnabled == 1) {
                                        initTourBookingPos.updateRatesForGroupRate(tourPackageId, rateGroupId, newTourists);
                                    } else {
                                        initTourBookingPos.selectedTourPackages[selectedPackageIndex].rategroups[selectedRateGroupIndex].tourists = newTourists;
                                    }
                                } else {
                                    if (isGroupRateEnabled == 1) {
                                        var newTourists = totalTourists;
                                        initTourBookingPos.updateRatesForGroupRate(tourPackageId, rateGroupId, newTourists);
                                    } else {
                                        var oldRateGroups = initTourBookingPos.selectedTourPackages[selectedPackageIndex].rategroups;
                                        oldRateGroups.push(selectedRateGroup);
                                    }
                                }
                                $('#rategrp_' + tourPackageId + "_" + rateGroupId).addClass('active');
                                $(`#${initTourBookingPos.cartSectionDiv}`).removeClass('d-none');
                                $(`#${initTourBookingPos.confirmPaymentSectionDiv}`).addClass('d-none');
                                $(`#${initTourBookingPos.thankYouSectionDiv}`).addClass('d-none');
                                initTourBookingPos.makePaymentMethodActive(initTourBookingPos.paymentMethod);
                                initTourBookingPos.renderSelectedRateGroup();
                            } else {
                                return false;
                            }
                        });
                        return false;

                    } else {
                        var totalTourists = this.selectedTourPackages[selectedPackageIndex].tourists + 1;
                        var selectedRateGroupIndex = this.selectedTourPackages[selectedPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);
                        if (selectedRateGroupIndex != -1) {
                            var newTourists = this.selectedTourPackages[selectedPackageIndex].rategroups[selectedRateGroupIndex].tourists + 1;
                            if (isGroupRateEnabled == 1) {
                                this.updateRatesForGroupRate(tourPackageId, rateGroupId, newTourists);
                            } else {
                                this.selectedTourPackages[selectedPackageIndex].tourists = totalTourists;
                                this.selectedTourPackages[selectedPackageIndex].rategroups[selectedRateGroupIndex].tourists = newTourists;
                            }
                        } else {
                            if (isGroupRateEnabled == 1) {
                                var newTourists = totalTourists;
                                this.updateRatesForGroupRate(tourPackageId, rateGroupId, newTourists);
                            } else {
                                var oldRateGroups = this.selectedTourPackages[selectedPackageIndex].rategroups;
                                oldRateGroups.push(selectedRateGroup);
                                this.selectedTourPackages[selectedPackageIndex].tourists = totalTourists;
                            }
                        }

                    }


                } else {

                    var rateGroups = [];
                    rateGroups.push(selectedRateGroup);
                    var applyOnDate = this.applyOnDate;
                    var applyOnSlots = this.applyOnSlots;
                    if (this.selectedTourPackages.length == 0) {
                        this.selectedTourPackages.cartId = Date.now().toString(36) + Math.random().toString(36).substr(2);
                        this.selectedTourPackages.canApplyDiscount = (checkPermissionOfEnableDiscount == 1) ? 1 : 0;
                        this.currentCartId = this.selectedTourPackages.cartId;
                    }
                    this.selectedTourPackages.push(
                        {
                            tourPackageId: tourPackageId,
                            tourPackageName: tourPackages[tourPackageIndex].tourPackageName,
                            isGroupRateEnabled: tourPackages[tourPackageIndex].isGroupRateEnabled,
                            applyOnDate: this.applyOnDate,
                            applyOnSlots: this.applyOnSlots,
                            serviceCommissionPercentage: tourPackages[tourPackageIndex].serviceCommissionPercentage,
                            slotId: slotId,
                            slotDate: date,
                            slotTime: slotTime,
                            availableSeats: availableSeats,
                            tourists: 1,
                            isOverBooking: isOverBooking,
                            rategroups: rateGroups,
                            discountType: '',
                            discount: 0,
                            promoCode: ''
                        }
                    );
                    this.applyOnDate = false;
                    this.applyOnSlots = false;
                }
                $('#rategrp_' + tourPackageId + "_" + rateGroupId).addClass('active');
            }
        }
        $(`#${this.cartSectionDiv}`).removeClass('d-none');
        $(`#${this.confirmPaymentSectionDiv}`).addClass('d-none');
        $(`#${this.thankYouSectionDiv}`).addClass('d-none');
        this.makePaymentMethodActive(this.paymentMethod);
        this.renderSelectedRateGroup();
        if (slotId != '') {
            this.renderCalendar(this.currentMonth, this.currentYear);
        }
    }
    removeRateGroup(tourPackageId, rateGroupId) {

        var tourPackages = this.selectedTourPackages;

        if (tourPackages.length > 0) {
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1) {
                var rateGroupIndex = tourPackages[tourPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);
                if (rateGroupIndex != -1) {
                    var removedTourists = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists;
                    tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists - removedTourists;
                    var rateGroups = tourPackages[tourPackageIndex].rategroups.filter(item => item.id != rateGroupId);
                    tourPackages[tourPackageIndex].rategroups = rateGroups;
                    if (tourPackages[tourPackageIndex].rategroups.length == 0) {
                        tourPackages = tourPackages.filter(item => item.tourPackageId != tourPackageId);
                    }
                    $('#rategrp_' + tourPackageId + "_" + rateGroupId).removeClass('active');
                }

            }
        }
        this.selectedTourPackages = tourPackages;
        this.renderSelectedRateGroup();
    }

    increaseTourists(tourPackageId, rateGroupId) {

        var tourPackages = this.selectedTourPackages;

        if (tourPackages.length > 0) {
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1) {

                var rateGroupIndex = tourPackages[tourPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);
                if (rateGroupIndex != -1) {
                    var newTourists = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists;
                    if (tourPackages[tourPackageIndex].slotId != '') {
                        var availableSeats = tourPackages[tourPackageIndex].availableSeats;
                        var tourists = tourPackages[tourPackageIndex].tourists + 1;
                        var addText = '';
                        if (tourists > availableSeats && tourPackages[tourPackageIndex].isOverBooking != 1) {
                            if (this.userType == '') {
                                swal('The slot will be full.', '', 'warning');
                                return false;
                            }
                            addText += ' This slot will be overbooked by 1 seat. Do you want to proceed?';

                            swal({
                                title: 'An overbooking event',
                                text: addText,
                                icon: "warning",
                                dangerMode: true,
                                buttons: true,
                                buttons: {
                                    no: {
                                        text: 'Cancel',
                                        value: false,
                                    },
                                    yes: {
                                        text: 'Book',
                                        value: true,
                                        className: 'btn-danger',
                                    }
                                }
                            }).then(function (result) {
                                if (result) {
                                    tourPackages[tourPackageIndex].isOverBooking = 1;
                                    newTourists = newTourists + 1;
                                    var isGroupRateEnabled = tourPackages[tourPackageIndex].isGroupRateEnabled;
                                    if (isGroupRateEnabled == 1) {
                                        initTourBookingPos.updateRatesForGroupRate(tourPackageId, rateGroupId, newTourists);
                                    } else {
                                        tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists = newTourists;
                                        $('#qty_' + tourPackageId + "_" + rateGroupId).val(newTourists);
                                        var rate = parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].additional_charge) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].permit_fee);
                                        var totalRate = initTourBookingPos.roundout(rate * newTourists);
                                        $('#price_' + tourPackageId + "_" + rateGroupId).text(`${currency(totalRate).format()}`);
                                        tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists + 1;
                                    }
                                    initTourBookingPos.selectedTourPackages = tourPackages;
                                    initTourBookingPos.calculateTotals();

                                } else {
                                    return false;
                                }
                            });
                            return false;
                        } else {
                            newTourists = newTourists + 1;
                        }
                    } else {
                        newTourists = newTourists + 1;
                    }


                    var isGroupRateEnabled = tourPackages[tourPackageIndex].isGroupRateEnabled;
                    if (isGroupRateEnabled == 1) {
                        this.updateRatesForGroupRate(tourPackageId, rateGroupId, newTourists);
                    } else {
                        tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists = newTourists;
                        $('#qty_' + tourPackageId + "_" + rateGroupId).val(newTourists);
                        var rate = parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].additional_charge) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].permit_fee);
                        var totalRate = this.roundout(rate * newTourists);
                        $('#price_' + tourPackageId + "_" + rateGroupId).text(`${currency(totalRate).format()}`);
                        tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists + 1;
                    }
                    var groupTotalOfPackage = this.calculateGroupTotalPackage(tourPackageId);
                    $('#groupTotal_' + tourPackageId).text(`${currency(groupTotalOfPackage).format()}`);
                }
            }
        }
        this.selectedTourPackages = tourPackages;
        this.calculateTotals();
    }
    decreaseTourists(tourPackageId, rateGroupId) {

        var tourPackages = this.selectedTourPackages;

        if (tourPackages.length > 0) {
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1) {
                var rateGroupIndex = tourPackages[tourPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);
                if (rateGroupIndex != -1 && tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists >= 1) {
                    var newTourists = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists - 1;
                    if (newTourists == 0) {
                        var rateGroups = tourPackages[tourPackageIndex].rategroups.filter(item => item.id != rateGroupId);
                        tourPackages[tourPackageIndex].rategroups = rateGroups;
                        tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists - 1;
                        $('#selectedrategrp_' + rateGroupId).remove();
                        $('#rategrp_' + tourPackageId + "_" + rateGroupId).removeClass('active');
                        if (rateGroups.length == 0) {
                            tourPackages = tourPackages.filter(item => item.tourPackageId != tourPackageId);
                            this.selectedTourPackages = tourPackages;
                            this.renderSelectedRateGroup();
                        }
                    } else {
                        var isGroupRateEnabled = tourPackages[tourPackageIndex].isGroupRateEnabled;
                        if (isGroupRateEnabled == 1) {
                            this.updateRatesForGroupRate(tourPackageId, rateGroupId, newTourists);
                        } else {
                            tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists = newTourists;
                            tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists - 1;
                            $('#qty_' + tourPackageId + "_" + rateGroupId).val(newTourists);
                            var rate = parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].additional_charge) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].permit_fee);
                            var totalRate = this.roundout(rate * newTourists);
                            $('#price_' + tourPackageId + "_" + rateGroupId).text(`${currency(totalRate).format()}`);
                        }
                    }

                }

                var groupTotalOfPackage = this.calculateGroupTotalPackage(tourPackageId);
                $('#groupTotal_' + tourPackageId).text(`${currency(groupTotalOfPackage).format()}`);

            }
        }
        this.selectedTourPackages = tourPackages;

        this.calculateTotals();
    }
    updateTourists(tourPackageId, rateGroupId) {

        var tourPackages = this.selectedTourPackages;

        if (tourPackages.length > 0) {
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1) {
                var rateGroupIndex = tourPackages[tourPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);
                if (rateGroupIndex != -1) {
                    if (tourPackages[tourPackageIndex].slotId != '') {
                        var tourists = ($('#qty_' + tourPackageId + "_" + rateGroupId).val() != '') ? parseInt($('#qty_' + tourPackageId + "_" + rateGroupId).val()) : tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists;

                        var oldTourists = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists;
                        if (tourists > oldTourists) {
                            var newTourists = tourists - oldTourists;
                        } else if (tourists < oldTourists) {
                            var newTourists = oldTourists - tourists;
                        } else {
                            var newTourists = 0;
                        }
                        if (newTourists > 0) {
                            if (tourists > oldTourists) {
                                var totalTourists = tourPackages[tourPackageIndex].tourists + newTourists;
                            } else if (tourists < oldTourists) {
                                var totalTourists = tourPackages[tourPackageIndex].tourists - newTourists;
                            }
                            var availableSeats = tourPackages[tourPackageIndex].availableSeats;
                            var addText = '';
                            if (totalTourists > availableSeats && tourPackages[tourPackageIndex].isOverBooking != 1) {
                                if (this.userType == '') {
                                    swal('The slot will be full.', '', 'warning');
                                    $('#qty_' + tourPackageId + "_" + rateGroupId).val(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists);
                                    return false;
                                }
                                var overbooked = totalTourists - availableSeats;
                                addText += ' This slot will be overbooked by ' + overbooked + ' seat. Do you want to proceed?';

                                swal({
                                    title: 'An overbooking event',
                                    text: addText,
                                    icon: "warning",
                                    dangerMode: true,
                                    buttons: true,
                                    buttons: {
                                        no: {
                                            text: 'Cancel',
                                            value: false,
                                        },
                                        yes: {
                                            text: 'Book',
                                            value: true,
                                            className: 'btn-danger',
                                        }
                                    }
                                }).then(function (result) {
                                    if (result) {
                                        tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists = tourists;
                                        tourPackages[tourPackageIndex].isOverBooking = 1;
                                        if (tourists > oldTourists) {
                                            tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists + newTourists;
                                        } else if (tourists < oldTourists) {
                                            tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists - newTourists;
                                        }
                                        var rate = parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].additional_charge) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].permit_fee);
                                        var totalRate = initTourBookingPos.roundout(rate * tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists);
                                        $('#price_' + tourPackageId + "_" + rateGroupId).text(`${currency(totalRate).format()}`);
                                        var groupTotalOfPackage = initTourBookingPos.calculateGroupTotalPackage(tourPackageId);
                                        $('#groupTotal_' + tourPackageId).text(`${currency(groupTotalOfPackage).format()}`);
                                        initTourBookingPos.selectedTourPackages = tourPackages;
                                        initTourBookingPos.calculateTotals();
                                    } else {
                                        $('#qty_' + tourPackageId + "_" + rateGroupId).val(oldTourists);
                                        tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists = oldTourists;
                                    }
                                });
                                return false;

                            } else {
                                tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists = tourists;
                                if (tourists > oldTourists) {
                                    tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists + newTourists;
                                } else if (tourists < oldTourists) {
                                    tourPackages[tourPackageIndex].tourists = tourPackages[tourPackageIndex].tourists - newTourists;
                                }
                                var rate = parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].additional_charge) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].permit_fee);
                                var totalRate = this.roundout(rate * tourists);
                                $('#price_' + tourPackageId + "_" + rateGroupId).text(`${currency(totalRate).format()}`);
                            }
                        }
                    } else {
                        var newTourists = ($('#qty_' + tourPackageId + "_" + rateGroupId).val() != '' && $('#qty_' + tourPackageId + "_" + rateGroupId).val() > 0) ? $('#qty_' + tourPackageId + "_" + rateGroupId).val() : tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists;
                        tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists = parseInt(newTourists);
                        var rate = parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].additional_charge) + parseFloat(tourPackages[tourPackageIndex].rategroups[rateGroupIndex].permit_fee);
                        var totalRate = this.roundout(rate * newTourists);
                        $('#price_' + tourPackageId + "_" + rateGroupId).text(`${currency(totalRate).format()}`);
                    }
                }
                var groupTotalOfPackage = this.calculateGroupTotalPackage(tourPackageId);
                $('#groupTotal_' + tourPackageId).text(`${currency(groupTotalOfPackage).format()}`);
            }
        }
        this.selectedTourPackages = tourPackages;
        this.calculateTotals();
    }
    updateRatesForGroupRate(tourPackageId, rateGroupId, tourists) {
        var tourPackages = this.tourPackages;
        var selectedTourPackages = this.selectedTourPackages;
        var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
        var selectedTourPackageIndex = selectedTourPackages.findIndex(x => x.tourPackageId == tourPackageId);
        if (selectedTourPackageIndex != -1 && tourPackageIndex != -1) {
            var paxWiseGroupRates = tourPackages[tourPackageIndex].paxWiseGroupRates;
            var paxWiseGroupRateIds = tourPackages[tourPackageIndex].paxWiseGroupRateIds;
            var paxWiseGroupAdditionalCharges = tourPackages[tourPackageIndex].paxWiseGroupAdditionalCharges;
            var rateGroupIndex = selectedTourPackages[tourPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);
            if (rateGroupIndex != -1 && Object.keys(paxWiseGroupRates).length > 0 && Object.keys(paxWiseGroupRateIds).length > 0) {
                if (paxWiseGroupRates[tourists] !== undefined && paxWiseGroupRateIds[tourists] !== undefined) {
                    selectedTourPackages[tourPackageIndex].rategroups[rateGroupIndex].id = paxWiseGroupRateIds[tourists];
                    selectedTourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists = tourists;
                    selectedTourPackages[tourPackageIndex].rategroups[rateGroupIndex].rate = paxWiseGroupRates[tourists];
                    selectedTourPackages[tourPackageIndex].rategroups[rateGroupIndex].additional_charge = paxWiseGroupAdditionalCharges[tourists];
                    selectedTourPackages[tourPackageIndex].rategroups[rateGroupIndex].permit_fee = 0;
                    var newRateGroupId = paxWiseGroupRateIds[tourists];
                    if (rateGroupId != newRateGroupId) {
                        $(`#qty_${tourPackageId}_${rateGroupId}`).attr('onkeyup', `initTourBookingPos.updateTourists('${tourPackageId}','${newRateGroupId}');`);
                        $(`#qty_${tourPackageId}_${rateGroupId}`).attr('onblur', `initTourBookingPos.checkTourists('${tourPackageId}','${newRateGroupId}');`);
                        $(`#selectedrategrp_${rateGroupId}`).find('.increaseqty').attr('onclick', `initTourBookingPos.increaseTourists('${tourPackageId}','${newRateGroupId}');`);
                        $(`#selectedrategrp_${rateGroupId}`).find('.decreaseqty').attr('onclick', `initTourBookingPos.decreaseTourists('${tourPackageId}','${newRateGroupId}');`);
                        $(`#selectedrategrp_${rateGroupId}`).attr('id', `selectedrategrp_${newRateGroupId}`);
                        $(`#panel_${tourPackageId}`).find('.rategroups-btn').attr('onclick', `initTourBookingPos.addRateGroup('${tourPackageId}','${newRateGroupId}');`);
                        $(`#panel_${tourPackageId}`).find('.rategroups-btn').attr('id', `rategrp_${tourPackageId}_${newRateGroupId}`);
                        $(`#qty_${tourPackageId}_${rateGroupId}`).attr('id', `qty_${tourPackageId}_${newRateGroupId}`);
                        $(`#qty_${tourPackageId}_${newRateGroupId}`).val(tourists);
                        $(`#price_${tourPackageId}_${rateGroupId}`).attr('id', `price_${tourPackageId}_${newRateGroupId}`);
                        var rate = parseFloat(paxWiseGroupRates[tourists]) + parseFloat(paxWiseGroupAdditionalCharges[tourists]);
                        var totalRate = this.roundout(rate);
                        $(`#price_${tourPackageId}_${newRateGroupId}`).text(`${currency(totalRate).format()}`);
                    } else {
                        $(`#qty_${tourPackageId}_${rateGroupId}`).val(tourists);
                    }
                    selectedTourPackages[tourPackageIndex].tourists = tourists;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        this.selectedTourPackages = selectedTourPackages;
    }
    manipulateRateGroups(tourPackageId, rateGroupId = '', slotId = '', date = '', time = '') {
        var applyOnDate = this.applyOnDate;
        var applyOnSlots = this.applyOnSlots;

        var dynamicRateGroups = this.dynamicRateGroups;
        var tourPackages = this.tourPackages;
        var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);

        if (tourPackageIndex != -1 && (applyOnDate || applyOnSlots) && dynamicRateGroups.length > 0) {
            var rateGroupIndex = tourPackages[tourPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);

            if (rateGroupIndex != -1) {
                var currentRateGroupMappingKey = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].mapping_key;
                var newRateGroupIndex = dynamicRateGroups.findIndex(x => x.mapping_key == currentRateGroupMappingKey);
                var newRateGroups = dynamicRateGroups;
                tourPackages[tourPackageIndex].rategroups = newRateGroups;
                if (tourPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                    tourPackages[tourPackageIndex].paxWiseGroupAdditionalCharges = this.dynamicPaxWiseGroupAdditionalCharges;
                    tourPackages[tourPackageIndex].paxWiseGroupRateIds = this.dynamicPaxWiseGroupRateIds;
                    tourPackages[tourPackageIndex].paxWiseGroupRates = this.dynamicPaxWiseGroupRates;
                    this.dynamicPaxWiseGroupAdditionalCharges = [];
                    this.dynamicPaxWiseGroupRateIds = [];
                    this.dynamicPaxWiseGroupRates = [];
                }
                this.tourPackages = tourPackages;
                this.dynamicRateGroups = [];
                this.renderManipulatedRateGroups(tourPackageId);
                if (newRateGroupIndex != -1) {
                    var newRateGroupId = dynamicRateGroups[newRateGroupIndex].id;
                    this.checkOverBooking(slotId, tourPackageId, newRateGroupId, date, time);
                }
            }
        }
    }
    renderManipulatedRateGroups(tourPackageId) {
        var tourPackages = this.tourPackages;
        var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
        var selectedPackageIndex = this.selectedTourPackages.findIndex(x => x.tourPackageId == tourPackageId);
        if (tourPackageIndex != -1 && tourPackages[tourPackageIndex].rategroups.length > 0) {
            var html = '';
            var rateGroups = tourPackages[tourPackageIndex].rategroups;
            for (let i = 0; i < rateGroups.length; i++) {
                if (tourPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                    var rate = this.roundout(parseFloat(rateGroups[i].rate) + parseFloat(rateGroups[i].additional_charge));

                } else {
                    var rate = this.roundout(parseFloat(rateGroups[i].rate) + parseFloat(rateGroups[i].additional_charge) + parseFloat(rateGroups[i].permit_fee), 2);

                }
                var activeClass = '';
                if (selectedPackageIndex != -1) {
                    if (tourPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                        activeClass = 'active';
                    } else {
                        var selectedRateGroupIndex = this.selectedTourPackages[selectedPackageIndex].rategroups.findIndex(x => x.id == rateGroups[i].id);
                        if (selectedRateGroupIndex != -1) {
                            activeClass = 'active';
                        }
                    }
                }
                if (tourPackages[tourPackageIndex].packageHasSlots == '1') {
                    html += `<button class = "rategroups-btn ${activeClass}" title="${rateGroups[i].rate_for}" id = "rategrp_${tourPackageId}_${rateGroups[i].id}" onclick="initTourBookingPos.showCalendar('${tourPackageId}','${rateGroups[i].id}');">`;
                } else {
                    html += `<button class = "rategroups-btn ${activeClass}" title="${rateGroups[i].rate_for}" id = "rategrp_${tourPackageId}_${rateGroups[i].id}" onclick="initTourBookingPos.addRateGroup('${packageData.tourPackageId}','${rateGroups[i].id}');">`;
                }
                if (tourPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                    rateGroups[i].rate_for = this.rateNameForGroupRate;
                }
                html += `<span class="name" title="${rateGroups[i].rate_for}">${rateGroups[i].rate_for}</span><span  class="price">${currency(rate).format()}</span></button>`;
                if (tourPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                    if (i == 0) {
                        break;
                    }
                }
            }
            $(`#panel_${tourPackageId}`).html(html);

        }

    }
    manipulateSelectedRateGroups(tourPackageId) {
        var applyOnDate = this.applyOnDate;
        var applyOnSlots = this.applyOnSlots;

        var dynamicRateGroups = this.dynamicRateGroups;
        var selectedTourPackages = this.selectedTourPackages;

        var selectedPackageIndex = selectedTourPackages.findIndex(x => x.tourPackageId == tourPackageId);
        if (selectedPackageIndex != -1 && (applyOnDate || applyOnSlots) && dynamicRateGroups.length > 0) {
            var currentSelectedRateGroups = selectedTourPackages[selectedPackageIndex].rategroups;
            var rateGroups = [];
            if (selectedTourPackages[selectedPackageIndex].isGroupRateEnabled == 1) {
                var selectedRateGroup = {};
                var paxWiseGroupRates = this.dynamicPaxWiseGroupRates;
                var paxWiseGroupRateIds = this.dynamicPaxWiseGroupRateIds;
                var paxWiseGroupAdditionalCharges = this.dynamicPaxWiseGroupAdditionalCharges;
                var tourists = currentSelectedRateGroups[0].tourists;
                if (paxWiseGroupRates[tourists] !== undefined && paxWiseGroupRateIds[tourists] !== undefined) {
                    selectedRateGroup.id = paxWiseGroupRateIds[tourists];
                    selectedRateGroup.tourists = tourists;
                    selectedRateGroup.rate = paxWiseGroupRates[tourists];
                    selectedRateGroup.additional_charge = paxWiseGroupAdditionalCharges[tourists];
                    selectedRateGroup.rate_for = this.rateNameForGroupRate;
                    selectedRateGroup.permit_fee = 0;
                }
                rateGroups.push(selectedRateGroup);
            } else {
                for (let i = 0; i < currentSelectedRateGroups.length; i++) {
                    var selectedRateGroup = {};
                    var dynamicRateGroupIndex = dynamicRateGroups.findIndex(x => x.mapping_key == currentSelectedRateGroups[i].mapping_key);
                    if (dynamicRateGroupIndex != -1) {
                        selectedRateGroup.id = dynamicRateGroups[dynamicRateGroupIndex].id;
                        selectedRateGroup.tourists = currentSelectedRateGroups[i].tourists;
                        selectedRateGroup.rate_for = dynamicRateGroups[dynamicRateGroupIndex].rate_for;
                        selectedRateGroup.rate = dynamicRateGroups[dynamicRateGroupIndex].rate;
                        selectedRateGroup.additional_charge = dynamicRateGroups[dynamicRateGroupIndex].additional_charge;
                        selectedRateGroup.permit_fee = dynamicRateGroups[dynamicRateGroupIndex].permit_fee;
                        selectedRateGroup.mapping_key = dynamicRateGroups[dynamicRateGroupIndex].mapping_key;

                    } else {
                        //If mapping key not matched then first rategroup of dynamic rategroups considered
                        selectedRateGroup.id = dynamicRateGroups[0].id;
                        selectedRateGroup.tourists = currentSelectedRateGroups[i].tourists;
                        selectedRateGroup.rate_for = dynamicRateGroups[0].rate_for;
                        selectedRateGroup.rate = dynamicRateGroups[0].rate;
                        selectedRateGroup.additional_charge = dynamicRateGroups[0].additional_charge;
                        selectedRateGroup.permit_fee = dynamicRateGroups[0].permit_fee;
                        selectedRateGroup.mapping_key = dynamicRateGroups[0].mapping_key;
                    }

                    rateGroups.push(selectedRateGroup);
                }
            }

            var tourPackageIndex = this.tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
            this.tourPackages[tourPackageIndex].rategroups = dynamicRateGroups;
            selectedTourPackages[selectedPackageIndex].rategroups = rateGroups;
            selectedTourPackages[selectedPackageIndex].serviceCommissionPercentage = dynamicRateGroups[0].service_commission_percentage;
            selectedTourPackages[selectedPackageIndex].applyOnSlots = applyOnSlots;
            this.selectedTourPackages = selectedTourPackages;
            this.renderManipulatedRateGroups(tourPackageId);
            this.renderSelectedRateGroup();
        }

    }
    checkTourists(tourPackageId, rateGroupId) {
        var tourPackages = this.selectedTourPackages;

        if (tourPackages.length > 0) {
            var tourPackageIndex = tourPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1) {
                var rateGroupIndex = tourPackages[tourPackageIndex].rategroups.findIndex(x => x.id == rateGroupId);
                if (rateGroupIndex != -1) {
                    var newTourists = $('#qty_' + tourPackageId + "_" + rateGroupId).val();
                    if (newTourists == '' || newTourists == 0) {
                        newTourists = tourPackages[tourPackageIndex].rategroups[rateGroupIndex].tourists;
                        $('#qty_' + tourPackageId + "_" + rateGroupId).val(newTourists);
                    }
                }
            }
        }
    }
    renderSelectedRateGroup() {
        var html = ``;
        var selectedPackages = this.selectedTourPackages;

        for (let i = selectedPackages.length; i--;) {
            var collapsed = (i == (selectedPackages.length - 1)) ? '' : 'collapsed';
            var isShow = (i == (selectedPackages.length - 1)) ? 'show' : '';
            var areaExpanded = (i == (selectedPackages.length - 1)) ? 'true' : 'false';
            var groupTotalPackage = this.calculateGroupTotalPackage(selectedPackages[i].tourPackageId);
            html += `<div class="item-details accordion" id="accordion_${selectedPackages[i].tourPackageId}">
                       <div class="accordion-item border-0 w-100">
                            <h2 class="accordion-header mt-0">
                            <button class="accordion-button d-flex align-items-start justify-content-between border-0 bg-white ${collapsed}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_${selectedPackages[i].tourPackageId}" aria-expanded="${areaExpanded}" aria-controls="collapseOne">
                            <div class="content d-flex align-items-center justify-content-between w-100">
                                                <h4 class="name">${selectedPackages[i].tourPackageName}</h4>
                                                <h6 class="price" id="groupTotal_${selectedPackages[i].tourPackageId}">${currency(groupTotalPackage).format()}</h6>
                                            </div>
                                        </button>
                                        </h2>
                                        <div id="collapse_${selectedPackages[i].tourPackageId}" class="accordion-collapse collapse ${isShow}" data-bs-parent="#selected-packages">
                                        <div class="accordion-body">`;
            if (selectedPackages[i].slotId != '' && selectedPackages[i].slotDate != '' && selectedPackages[i].slotTime != '') {
                var slotDate = moment(selectedPackages[i].slotDate).format('ddd, MMM Do, YYYY');
                var slotTime = slotDate + ' @ ' + selectedPackages[i].slotTime;
                html += `<div class="input-group d-flex align-items-center">
                                <div class="form-control date-time" style="box-shadow: none;padding: 0 0 0 5px;font-size: 14px;letter-spacing: 0px;">${slotTime}</div>
                                <div class="input-group-append edit-slot ms-0">
                                    <button class="signupbtn edit-btn" onclick="initTourBookingPos.changeSlot('${selectedPackages[i].tourPackageId}','${selectedPackages[i].slotId}','${selectedPackages[i].slotDate}');"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                </div>
                            </div>`;
            }
            for (let j = 0; j < selectedPackages[i].rategroups.length; j++) {
                var rate = parseFloat(selectedPackages[i].rategroups[j].rate) + parseFloat(selectedPackages[i].rategroups[j].additional_charge) + parseFloat(selectedPackages[i].rategroups[j].permit_fee);
                if (selectedPackages[i].isGroupRateEnabled == 1) {
                    var totalRate = rate;
                } else {
                    var totalRate = this.roundout(rate * selectedPackages[i].rategroups[j].tourists);
                }

                html += ` <div id="selectedrategrp_${selectedPackages[i].rategroups[j].id}" class="selected-item d-flex align-items-center justify-content-between">
                                                <h4 class="item-name">${selectedPackages[i].rategroups[j].rate_for}</h4>
                                                <div class="quantity-manage d-flex align-items-center">
                                                    <button class="decreaseqty" onclick="initTourBookingPos.decreaseTourists('${selectedPackages[i].tourPackageId}','${selectedPackages[i].rategroups[j].id}');"><i class="fa fa-minus" aria-hidden="true"></i></button>
                                                    <input type="number" min="1" step="1" onkeypress="return event.charCode >= 48 && event.charCode <= 57" id = "qty_${selectedPackages[i].tourPackageId}_${selectedPackages[i].rategroups[j].id}" value="${selectedPackages[i].rategroups[j].tourists}" onBlur="initTourBookingPos.checkTourists('${selectedPackages[i].tourPackageId}','${selectedPackages[i].rategroups[j].id}');" onkeyup="initTourBookingPos.updateTourists('${selectedPackages[i].tourPackageId}','${selectedPackages[i].rategroups[j].id}');">
                                                    <button class="increaseqty" onclick="initTourBookingPos.increaseTourists('${selectedPackages[i].tourPackageId}','${selectedPackages[i].rategroups[j].id}');"><i class="fa fa-plus" aria-hidden="true"></i></button>
                                                </div>
                                                <h6 id= "price_${selectedPackages[i].tourPackageId}_${selectedPackages[i].rategroups[j].id}" class="item-price">${currency(totalRate).format()}</h6>
                                                <a href="javascript:void(0);" class="close" onclick="initTourBookingPos.removeRateGroup('${selectedPackages[i].tourPackageId}','${selectedPackages[i].rategroups[j].id}');" title="Delete"><i class="fa fa-trash" aria-hidden="true"></i></a>
                                            </div>`;
            }
            if (this.managerRequestEnableDiscount == 'Yes') {
                html += `<div id="discount_${selectedPackages[i].tourPackageId}">`;
                if (selectedPackages[i].discount > 0) {
                    html += `<div class="promo-code-wrap d-flex justify-content-between align-items-center mt-3">
                                                <h4 class="discount-applied"><i class="fa fa-check-circle" aria-hidden="true"></i>`;
                    if (selectedPackages[i].promoCode != '') {
                        html += `${selectedPackages[i].promoCode}<span>(Applied)</span>`;
                    } else if (selectedPackages[i].discountType == 'Fixed Money') {
                        html += `${currency(selectedPackages[i].discount).format()} <span>(Discount Applied)</span>`;
                    } else if (selectedPackages[i].discountType == 'Percent') {
                        var discountAmount = this.roundout((groupTotalPackage * parseFloat(selectedPackages[i].discount)) / 100);
                        html += `${currency(discountAmount).format()}(${selectedPackages[i].discount}%) <span>(Discount Applied)</span>`;
                    }

                    html += `</h4><button class="btn-primary px-2 py-1" onclick="initTourBookingPos.removeDiscount('${selectedPackages[i].tourPackageId}');">Remove</button>
                                            </div>`;
                } else {
                    html += `<a class="mt-2 add-promo-code" onclick="initTourBookingPos.addDiscount('${selectedPackages[i].tourPackageId}');">Add Discount?</a>`;
                }
            }

            html += `</div>
                       </div>
                        </div>
                         </div>
                         </div>`;

        }

        $(`#${this.selectedPackagesDiv}`).html('');
        $(`#${this.selectedPackagesDiv}`).html(html);
        if (selectedPackages.length > 0 && selectedPackages[0].hasOwnProperty("note") && selectedPackages[0].note != '') {
            $('#pos-note').val(selectedPackages[0].note);
            $('#add-info').addClass('info-added-btn');
        }
        this.calculateTotals();
        if ($(`#${this.confirmPaymentSectionDiv}`).is(':visible')) {
            this.renderConfirmPaymentStep();
        }

    }
    makePaymentMethodActive(paymentMethod) {
        if (paymentMethod == 'Card') {
            $(`#${this.cartSectionDiv} #Cash`).removeClass('active');
            $(`#${this.cartSectionDiv} #${paymentMethod}`).addClass('active');
            $(`#${this.cartSectionDiv} #${paymentMethod}`).trigger('click');
        } else if (paymentMethod == 'Cash') {
            $(`#${this.cartSectionDiv} #${paymentMethod}`).addClass('active');
            $(`#${this.cartSectionDiv} #Card`).removeClass('active');
            $(`#${this.cartSectionDiv} #${paymentMethod}`).trigger('click');
        }
    }
    applyDiscount() {
        var currentPackageId = this.currentTourPackageId;
        var discountType = $('#radioButtonContainerId input[type="radio"]:checked').val();
        var selectedPackages = this.selectedTourPackages;
        var subTotal = this.calculateGroupTotalPackage(currentPackageId);
        if (discountType == '') {
            swal('Please select discount type.', '', 'warning');
            return false;
        }
        if (discountType == 'Fixed Money') {
            var discountAmount = $('#discount_fixed_money').val();
            if (discountAmount == '') {
                swal('Please enter discount amount.', '', 'warning');
                return false;
            }
        } else if (discountType == 'Percent') {
            var discountAmount = $('#discount_percentage').val();
            if (discountAmount == '') {
                swal('Please enter discount percentage.', '', 'warning');
                return false;
            }
        }

        if (subTotal > 0) {
            if (discountType == 'Fixed Money' || discountType == 'Percent') {
                if (selectedPackages.length > 0) {
                    var tourPackageIndex = selectedPackages.findIndex(x => x.tourPackageId == currentPackageId);
                    if (tourPackageIndex != -1) {

                        if (discountType == 'Fixed Money' && discountAmount > subTotal) {
                            swal('Please enter a valid discount.', '', 'warning');
                            return false;
                        }
                        if (discountType == 'Percent') {
                            var percentDiscountAmount = this.roundout((subTotal * parseFloat(discountAmount)) / 100);
                            if (percentDiscountAmount > subTotal) {
                                swal('Please enter a valid discount.', '', 'warning');
                                return false;
                            }
                        }
                        this.selectedTourPackages[tourPackageIndex].discountType = discountType;
                        this.selectedTourPackages[tourPackageIndex].discount = discountAmount;
                        this.selectedTourPackages[tourPackageIndex].promoCode = '';
                    }
                    this.calculateTotals();
                    this.renderDiscountApplied(currentPackageId, discountType, discountAmount);
                }
                $('#addDiscountModal').modal('hide');
                this.clearDiscountFields();
            } else if (discountType == 'Promo Code') {
                var promoCode = $('#discount_promo_code').val();
                if (promoCode == '') {
                    swal('Please enter Promo Code.', '', 'warning');
                    return false;
                }
                this.applyPromoCode(currentPackageId, promoCode);
            }
        }

    }
    clearDiscountFields() {
        $('#radioButtonContainerId input[type="radio"]').prop('checked', false);
        $('#discount_fixed_money').val('');
        $('#discount_percentage').val('');
        $('#discount_promo_code').val('');
        $('#discount_fixed_money').prop('disabled', true);
        $('#discount_percentage').prop('disabled', true);
        $('#discount_promo_code').prop('disabled', true);
    }
    applyPromoCode(tourPackageId, promoCode) {
        var selectedPackages = this.selectedTourPackages;
        var tourPackageIndex = selectedPackages.findIndex(x => x.tourPackageId == tourPackageId);
        if (tourPackageIndex != -1) {
            showLoading();
            $.post({
                url: this.applyPromoCodeUrl,
                headers: { Accept: 'application/json' },
                dataType: 'json',
                data: { tourPackageId: tourPackageId, promoCode: promoCode },
                success: this.onApplyPromoCodeOk.bind(this),
            }).fail(function (jqXHR) {
                showSubmissionError(jqXHR);
            })
                .always(hideLoading);
        }
    }
    onApplyPromoCodeOk(data) {
        var selectedPackages = this.selectedTourPackages;
        var discountType = data.discountType;
        var discount = data.discount;
        var tourPackageId = data.tourPackageId;
        var promoCode = data.promoCode;
        if (selectedPackages.length > 0) {
            var tourPackageIndex = selectedPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1) {
                this.selectedTourPackages[tourPackageIndex].discountType = discountType;
                this.selectedTourPackages[tourPackageIndex].discount = discount;
                this.selectedTourPackages[tourPackageIndex].promoCode = promoCode;
                this.renderDiscountApplied(tourPackageId, discountType, discount, promoCode);
            }
            this.calculateTotals();

        }

    }

    calculateGroupTotalPackage(tourPackageId) {
        var selectedPackages = this.selectedTourPackages;
        var subTotal = 0;
        var tourPackageIndex = selectedPackages.findIndex(x => x.tourPackageId == tourPackageId);
        if (tourPackageIndex != -1) {
            for (let j = 0; j < selectedPackages[tourPackageIndex].rategroups.length; j++) {
                if (selectedPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                    var rate = parseFloat(selectedPackages[tourPackageIndex].rategroups[j].rate) + parseFloat(selectedPackages[tourPackageIndex].rategroups[j].additional_charge);

                } else {
                    var rate = parseFloat(selectedPackages[tourPackageIndex].rategroups[j].rate) + parseFloat(selectedPackages[tourPackageIndex].rategroups[j].additional_charge) + parseFloat(selectedPackages[tourPackageIndex].rategroups[j].permit_fee);

                }
                var tourists = selectedPackages[tourPackageIndex].rategroups[j].tourists;
                if (selectedPackages[tourPackageIndex].isGroupRateEnabled == 1) {
                    subTotal += this.roundout(parseFloat(rate));
                } else {
                    subTotal += this.roundout(parseFloat(rate * tourists));
                }

            }
        }
        return subTotal;
    }
    calculateGroupTotalPackageForOnHold(index, tourPackageId) {
        var onHoldCartOrder = this.onHoldCarts[index];
        var subTotal = 0;
        var totalTourists = 0;
        var tourPackageIndex = onHoldCartOrder.findIndex(x => x.tourPackageId == tourPackageId);
        if (onHoldCartOrder) {
            for (let j = 0; j < onHoldCartOrder[tourPackageIndex].rategroups.length; j++) {
                var rate = parseFloat(onHoldCartOrder[tourPackageIndex].rategroups[j].rate) + parseFloat(onHoldCartOrder[tourPackageIndex].rategroups[j].additional_charge) + parseFloat(onHoldCartOrder[tourPackageIndex].rategroups[j].permit_fee);
                var tourists = onHoldCartOrder[tourPackageIndex].rategroups[j].tourists;
                totalTourists += tourists;
                subTotal += this.roundout(rate * tourists);
            }
        }

        return subTotal;
    }
    calculateBalanceAmount() {
        if (this.creditAmount > 0) {
            var balanceAmount = this.total - this.creditAmount;
            this.balanceAmount = balanceAmount;

        } else {
            this.balanceAmount = 0;
        }
        this.renderBalanceCreditAmounts();
    }
    calculateTotals() {
        var selectedPackages = this.selectedTourPackages;
        this.subTotal = 0;
        this.subTotalBeforeDiscount = 0;
        this.discountTotal = 0;
        this.platformFeesTotal = 0;
        this.total = 0;
        for (let i = 0; i < selectedPackages.length; i++) {
            var subTotalPackage = 0;
            for (let j = 0; j < selectedPackages[i].rategroups.length; j++) {
                var rate = parseFloat(selectedPackages[i].rategroups[j].rate) + parseFloat(selectedPackages[i].rategroups[j].additional_charge) + parseFloat(selectedPackages[i].rategroups[j].permit_fee);
                var tourists = selectedPackages[i].rategroups[j].tourists;
                var bookingServiceCommission = parseFloat(selectedPackages[i].serviceCommissionPercentage);
                var isGroupRateEnabled = selectedPackages[i].isGroupRateEnabled;
                if (isGroupRateEnabled == 1) {
                    var subTotal = this.roundout(rate);
                } else {
                    var subTotal = this.roundout(rate * tourists);
                }
                subTotalPackage += subTotal;
                this.subTotalBeforeDiscount += subTotal;
                if (selectedPackages[i].discountType == '' && selectedPackages[i].discount == 0) {
                    this.subTotal += subTotal;
                    if (this.paymentMethod == 'Card' || (this.paymentMethod == 'Cash' && cashFees == 1)) {
                        if (isGroupRateEnabled == 1) {
                            var platformFees = this.roundout(((Number(parseFloat(rate)) * bookingServiceCommission) / 100));
                        } else {
                            var platformFeesPax = this.roundout(((parseFloat(rate)) * bookingServiceCommission / 100), 2);
                            var platformFees = platformFeesPax * tourists;
                        }
                        this.platformFeesTotal += platformFees;
                        this.total += subTotal + platformFees;
                    } else {
                        this.platformFeesTotal = 0;
                        this.total += subTotal;
                    }
                }
            }
            if (selectedPackages[i].discountType != '' && selectedPackages[i].discount > 0) {
                var discountAmount = 0;
                if (selectedPackages[i].discountType == 'Fixed Money') {
                    discountAmount = parseFloat(selectedPackages[i].discount);
                } else if (selectedPackages[i].discountType == 'Percent') {
                    discountAmount = this.roundout((subTotalPackage * parseFloat(selectedPackages[i].discount)) / 100);
                    this.renderAppliedDiscountPercentForPackage(selectedPackages[i].tourPackageId, selectedPackages[i].discount, discountAmount);
                }
                subTotalPackage -= discountAmount
                this.discountTotal += discountAmount
                this.subTotal += subTotalPackage;
                var bookingServiceCommission = parseFloat(selectedPackages[i].serviceCommissionPercentage);
                if (this.paymentMethod == 'Card' || (this.paymentMethod == 'Cash' && cashFees == 1)) {
                    var platformFees = this.roundout(((Number(parseFloat(subTotalPackage)) * bookingServiceCommission) / 100));
                    this.platformFeesTotal += platformFees;
                    this.total += subTotalPackage + platformFees;
                } else {
                    this.platformFeesTotal = 0;
                    this.total += subTotalPackage;
                }
            }

        }
        this.subTotal = this.roundout(this.subTotal, 2);
        this.renderTotals();
    }

    createOnHoldCart() {
        if (this.selectedTourPackages.length == 0) {
            swal('Please add packages in the cart.', '', 'warning');
            return false;
        }
        if (this.onHoldCarts.length == 5) {
            swal('You can add upto 5 Onhold orders.', '', 'warning');
            return false;
        }
        var note = $('#pos-note').val();
        var onHoldCartOrders = this.onHoldCarts;
        this.selectedTourPackages[0].note = note;
        onHoldCartOrders.push(this.selectedTourPackages);
        this.onHoldCarts = onHoldCartOrders;
        this.selectedTourPackages = [];
        this.renderSelectedRateGroup();
        $('.rategroups-btn').removeClass('active');
        if (this.onHoldCarts.length > 0) {
            $('#on-hold-orders-count').text(this.onHoldCarts.length);
            $('#pos-note').val('');
            $('#customer_name').val('');
            $('#customer_phone_number').val('');
            $('#customer_email').val('');
            $('#add-info').removeClass('info-added-btn');
            this.storeHoldOrdersInCookie();
            this.retrieveOnHoldCarts();
        }
    }
    removeOnHoldCartOrder(index) {
        var onHoldCarts = this.onHoldCarts;
        onHoldCarts.splice(index, 1);
        this.onHoldCarts = onHoldCarts;
        this.storeHoldOrdersInCookie();
        this.renderOnHoldCartOrders();

    }
    storeHoldOrdersInCookie() {
        var now = new Date();
        var minutes = 30;
        now.setTime(now.getTime() + (minutes * 60 * 1000));
        var expires = "expires=" + now.toUTCString();
        document.cookie = 'onHoldCarts=' + JSON.stringify(this.onHoldCarts) + ";" + expires;
    }
    deleteCookie(name) {
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
    }
    retrieveOnHoldCarts() {
        var onHoldCarts = [];
        var cookies = decodeURIComponent(document.cookie).split(';');

        for (var i = 0; i < cookies.length; i++) {
            var c = cookies[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf('onHoldCarts') == 0) {
                onHoldCarts = c;
            }

        }

        if (onHoldCarts.length > 0) {
            var od = onHoldCarts.split('=');
            this.onHoldCarts = JSON.parse(od[1]);
        }
    }

    renderOnHoldCartInfo(index) {
        this.retrieveOnHoldCarts();
        var onHoldCarts = this.onHoldCarts;
        if (onHoldCarts.length > 0 && typeof onHoldCarts[index] !== 'undefined') {
            $(`#mainContent`).removeClass('d-none');
            $(`#${this.targetDivForOnHold}`).addClass('d-none');
            this.selectedTourPackages = onHoldCarts[index];
            this.fetchPackages();
            this.selectedOnHoldCartIndex = index;
            this.renderSelectedRateGroup();
            var onHoldCarts = this.onHoldCarts;
            onHoldCarts.splice(index, 1);
            this.onHoldCarts = onHoldCarts;
            this.storeHoldOrdersInCookie();
        }
        this.renderOnHoldOrdersCount();
    }
    renderOnHoldCartOrders() {
        this.retrieveOnHoldCarts();
        var onHoldCartOrders = this.onHoldCarts;

        let html = ``;
        if (onHoldCartOrders.length > 0) {
            for (var i = 0; i < onHoldCartOrders.length; i++) {
                html += `<div class="col-12 col-lg-6"><div class="onhold-boxwrap">
                <a href="javascript:void(0);" class="order-id">#${i + 1}</a>`;

                if (typeof onHoldCartOrders[i][0].note != 'undefined' && onHoldCartOrders[i][0].note != '') {
                    html += `<div class="note-box">
                   <h6>Note</h6>
                            <p>${onHoldCartOrders[i][0].note}</p></div><div class="order-holder">`;
                } else {
                    html += `<div class="non-note">`;
                }

                for (var j = 0; j < onHoldCartOrders[i].length; j++) {
                    var subTotalPackage = this.calculateGroupTotalPackageForOnHold(i, onHoldCartOrders[i][j].tourPackageId);
                    var slotTime = '';
                    var slotHtml = '';
                    if (onHoldCartOrders[i][j].slotId != '' && onHoldCartOrders[i][j].slotDate != '' && onHoldCartOrders[i][j].slotTime != '') {
                        var slotDate = moment(onHoldCartOrders[i][j].slotDate).format('ddd, MMM Do, YYYY');
                        slotTime = `${slotDate} @ ${onHoldCartOrders[i][j].slotTime}`;
                        slotHtml = `<div class="flip-box">
                                    <h4 class="flip-box-inner">
                                        <span class="flip-box-front">${onHoldCartOrders[i][j].tourPackageName}</span>
                                        <span class="flip-box-back">${slotTime}</span>
                                    </h4>
                                    </div>`;
                    } else {
                        slotHtml = `<h6>${onHoldCartOrders[i][j].tourPackageName}</h6>`;
                    }

                    html += `<div class="orderDetails d-flex align-items-center justify-content-between">
                                                ${slotHtml}
                                                <p>${currency(subTotalPackage).format()}</p>
                                            </div>`;


                }
                html += `</div><div class="d-flex mt-3 btn-holder">
                                                <button class="resume" onclick="initTourBookingPos.renderOnHoldCartInfo('${i}');"><i class="fa fa-chevron-circle-right" aria-hidden="true"></i>Resume</button>
                                                <button class="remove" onclick="initTourBookingPos.removeOnHoldCartOrder('${i}');"><i class="fa fa-times-circle" aria-hidden="true"></i>Remove</button>
                                            </div></div>
                                    </div>`;

            }
            html += ``;
            $(`#${this.posMainDiv}`).removeClass('d-none');
            $(`#mainContent`).addClass('d-none');
            $(`#${this.targetDivForOnHold}`).removeClass('d-none');
            $(`#${this.targetDivForOnHold} #${this.targetDivForOrders}`).addClass('d-none');
            $(`#${this.targetDivForOnHold} #${this.targetDivForCustomerOrder}`).addClass('d-none');
            $(`#${this.targetDivForOnHold} #${this.targetDivForOnHoldOrders}`).removeClass('d-none');
            $(`#${this.targetDivForOnHold} #${this.targetDivForOnHoldOrders}`).html(html);
            this.clearCartAndSetDefaults();
            $(`#${this.cartSectionDiv}`).removeClass('d-none');
            $(`#${this.confirmPaymentSectionDiv}`).addClass('d-none');
            $(`#${this.thankYouSectionDiv}`).addClass('d-none');
            this.makeMenuActive(this.menuForHome);
        }

        this.renderOnHoldOrdersCount();
    }
    clearCartAndSetDefaults() {
        this.selectedTourPackages = [];
        this.paymentMethod = 'Card';
        $(`#${this.cartSectionDiv} #Card`).addClass('active');
        $(`#${this.cartSectionDiv} #Cash`).removeClass('active');
        $(`#${this.selectedPackagesDiv}`).html('');
        this.calculateTotals();
        $('.rategroups-btn').removeClass('active');
    }
    getRequestPermissionAccess(requestType) {
        var requestPermissions = this.requestPermissions;
        var permissionValue = '';
        if (requestPermissions.hasOwnProperty(requestType)) {
            permissionValue = requestPermissions[requestType];
        }
        return permissionValue;
    }
    checkRequest(requestType) {
        var validationCheck = this.validateBookingsChecked(requestType);
        if (validationCheck != false) {
            showLoading();
            $.post({
                url: `${this.checkRequestUrl}/${this.orderId}`,
                headers: { Accept: 'application/json' },
                dataType: 'json',
                data: this.setParamsCheckRequest(requestType),
                success: function (data) {
                    initTourBookingPos.onCheckRequestOk(data, requestType);
                },
            }).fail(function (jqXHR) {
                showSubmissionError(jqXHR);
            })
                .always(hideLoading);
        }
    }
    setParamsCheckRequest(requestType) {
        var data = [];
        if (requestType == this.requestTypeCancelOrder) {
            var selectedBookingIds = $('#cancelBookingModal .bookingid-checkbox:checked');
            selectedBookingIds.each(function () {
                data.push($(this).val());
            });
            var refundType = $('#cancelBookingModal .refund-type:checked').val();

        } else if (requestType == this.requestTypeRefundOrder) {
            var selectedBookingIds = $('#refundOrderModal .refund-bookingid-checkbox:checked');
            selectedBookingIds.each(function () {
                data.push($(this).val());
            });
            var refundType = $('#refundOrderModal .refund-type:checked').val();
        }
        var params = {
            bookings: data,
            refund_type: refundType,
            request_type: requestType
        }
        return params;
    }
    onCheckRequestOk(data, requestType) {
        if (data.status == 'success') {
            if (data.requestStatus == 'Approved') {
                if (requestType == this.requestTypeCancelOrder) {
                    this.cancelOrder();
                } else if (requestType == this.requestTypeRefundOrder) {
                    this.refundOrder();
                }
            } else if (data.requestStatus == '' || data.requestStatus == 'Pending') {
                this.permissionType = requestType;
                $('#managerRequestModal #manager').val('');
                $('#managerRequestModal').modal('show');
                $('#managerRequestModal #managerRequestBtn').show();
                if (requestType == this.requestTypeCancelOrder) {
                    $('#cancelBookingModal').modal('hide');
                } else if (requestType == this.requestTypeRefundOrder) {
                    $('#refundOrderModal').modal('hide');
                }
            }
        }
    }
    sendRequestToManager() {
        var manager = $('#manager option:selected').val();
        if (manager == '') {
            swal('Please enter manager id.', '', 'warning');
            return false;
        }
        this.manager = manager;
        this.requestToManager = 1;
        if (this.permissionType == this.requestTypeCancelOrder) {
            var params = this.setParamsCancelOrder();
            params.manager_id = manager;
            params.order_id = this.orderId;
            params.request_type = this.requestTypeCancelOrder;
        } else if (this.permissionType == this.requestTypeRefundOrder) {
            var params = this.setParamsRefundOrder();
            params.manager_id = manager;
            params.order_id = this.orderId;
            params.request_type = this.requestTypeRefundOrder;
        }
        $.post({
            url: `${this.requestToManagerUrl}`,
            headers: { Accept: 'application/json' },
            dataType: 'json',
            data: params,
            success: this.onSendRequestToManagerOk.bind(this),
        }).fail(function (jqXHR) {
            showSubmissionError(jqXHR);
        })
            .always(hideLoading);
    }
    onSendRequestToManagerOk(data) {
        if (data.status == 'success') {
            $('#managerRequestModal').modal('hide');
            var message = 'Request sent to manager successfully.';
            this.showSuccessMessage(message);
        }
    }
    checkManagerId() {
        $.post({
            url: `${this.checkManagerIdUrl}`,
            headers: { Accept: 'application/json' },
            dataType: 'json',
            data: { manager_id: this.manualManagerId, user_id: this.manager },
            success: function (data) {
                initTourBookingPos.checkManagerIdOk(data);
            },
        }).fail(function (jqXHR) {
            showSubmissionError(jqXHR);
        })
            .always(hideLoading);
    }
    checkManagerIdOk(data) {
        if (data.message == 'Correct') {
            $("#requestManualPinModal").modal("hide");
            $("#requestManualPinModal").modal("show");
        } else {
            swal('Manager id is incorrect', '', 'warning');
            return false;
        }
    }
    checkManagerPin() {
        $.post({
            url: `${this.checkManagerPinUrl}`,
            headers: { Accept: 'application/json' },
            dataType: 'json',
            data: { manager: this.manager, manager_id: this.manualManagerId, manager_pin: this.manualManagerPin },
            success: function (data) {
                initTourBookingPos.checkManagerPinOk(data);
            },
        }).fail(function (jqXHR) {
            showSubmissionError(jqXHR);
        })
            .always(hideLoading);
    }
    checkManagerPinOk(data) {
        if (data.message == 'Correct') {
            $("#requestManualPinModal").modal("hide");
            $("#requestManualIdModal").modal("hide");
            $("#managerRequestModal").modal("hide");
            if (this.permissionType == 'discount') {
                this.selectedTourPackages.canApplyDiscount = 1;
                this.addDiscount(this.currentTourPackageId);
                this.permissionType = '';
            } else if (this.permissionType == this.requestTypeCancelOrder) {
                this.cancelOrder();
            } else if (this.permissionType == this.requestTypeRefundOrder) {
                this.refundOrder();
            }

        } else {
            swal('Manager PIN is incorrect', '', 'warning');
            return false;
        }
    }
    addDiscount(tourPackageId) {
        var selectedPackages = this.selectedTourPackages;
        var canApplyDiscount = selectedPackages.canApplyDiscount;
        this.currentTourPackageId = tourPackageId;
        if (checkPermissionOfEnableDiscount == 1 || canApplyDiscount == 1) {
            $('#addDiscountModal').modal('show');
            this.clearDiscountFields();
        } else {
            this.permissionType = 'discount';
            $('#managerRequestModal #manager').val('');
            $('#managerRequestModal').modal('show');
            $('#managerRequestModal #managerRequestBtn').hide();
        }

    }
    addPromoCode(tourPackageId) {
        var selectedPackages = this.selectedTourPackages;
        var canApplyDiscount = selectedPackages.canApplyDiscount;
        if (checkPermissionOfEnableDiscount == 1 || canApplyDiscount == 1) {
            var html = `<div class="input-group promo-code-section">
            <input type="text" class="form-control promo-code input-no-shadow" name="promocode" placeholder="Add promotion code" autocomplete="one-time-code" >
            <div class="input-group-append">
                <button class="apply-discount-btn signupbtn" onclick="initTourBookingPos.applyPromoCode('${tourPackageId}');">Apply</button>
            </div>
        </div> `;
            $(`#discount_${tourPackageId}`).html(html);
        } else {
            this.currentTourPackageId = tourPackageId;
            this.permissionType = 'discount';
            $('#managerRequestModal #manager').val('');
            $('#managerRequestModal').modal('show');
        }

    }
    removeDiscount(tourPackageId) {
        var selectedPackages = this.selectedTourPackages;
        if (tourPackageId != '' && selectedPackages.length > 0) {
            var tourPackageIndex = selectedPackages.findIndex(x => x.tourPackageId == tourPackageId);
            if (tourPackageIndex != -1) {
                this.selectedTourPackages[tourPackageIndex].discountType = '';
                this.selectedTourPackages[tourPackageIndex].discount = 0;
                this.selectedTourPackages[tourPackageIndex].promoCode = '';
                this.renderDiscountRemoved(tourPackageId);
            }
            this.calculateTotals();
        }
    }
    renderDiscountApplied(tourPackageId, discountType, discount, promoCode = '') {
        if (tourPackageId != '') {
            var html = `<div class="promo-code-wrap d-flex justify-content-between align-items-center mt-3">
                                                <h4 class="discount-applied"><i class="fa fa-check-circle" aria-hidden="true"></i>`;
            if (promoCode != '') {
                html += `${promoCode}<span>(Applied)</span>`;
            } else if (discountType == 'Fixed Money') {
                html += `${currency(discount).format()} <span>(Discount Applied)</span>`;
            } else if (discountType == 'Percent') {
                var subTotal = this.calculateGroupTotalPackage(tourPackageId);
                var discountAmount = this.roundout((subTotal * parseFloat(discount)) / 100);
                html += `${currency(discountAmount).format()}(${discount}%) <span>(Discount Applied)</span>`;
            }

            html += `</h4><button class="btn-primary px-2 py-1" onclick="initTourBookingPos.removeDiscount('${tourPackageId}');">Remove</button>
                                            </div>`;

            $(`#discount_${tourPackageId}`).html(html);
        }

    }
    renderDiscountRemoved(tourPackageId, promoCode) {
        if (tourPackageId != '' && promoCode != '') {
            var html = `<a class="mt-2 add-promo-code" onclick="initTourBookingPos.addDiscount('${tourPackageId}');">Add Discount?</a>`;
            $(`#discount_${tourPackageId}`).html(html);
        }

    }
    renderAppliedDiscountPercentForPackage(tourPackageId, discountPercentage, discount) {
        if (tourPackageId != '' && discount > 0) {
            $(`#discount_${tourPackageId} h4.discount-applied`).html(`<i class="fa fa-check-circle" aria-hidden="true"></i>${currency(discount).format()}(${discountPercentage}%) <span>(Discount Applied)</span>`);
        }
    }
    showScannerCustomerOrder() {
        this.makeMenuActive(this.menuForOrders);
        $(`#mainContent`).addClass('d-none');
        $(`#${this.targetDivForOnHold}`).removeClass('d-none');
        $(`#${this.targetDivForOnHold} #${this.targetDivForOnHoldOrders}`).addClass('d-none');
        $(`#${this.cartSectionDiv}`).addClass('d-none');
        $(`#${this.confirmPaymentSectionDiv}`).addClass('d-none');
        $(`#${this.thankYouSectionDiv}`).addClass('d-none');
        $(`#${this.posMainDiv}`).removeClass('d-none');
        $(`#${this.settingDiv}`).addClass('d-none');
        $(`#${this.targetDivForOnHold} #${this.targetDivForCustomerOrder}`).removeClass('d-none');
        this.html5QrCode = new Html5Qrcode("qr-reader");
        this.html5QrCode.start({
            facingMode: "environment"
        }, // use back camera
            {
                fps: 10,
                qrbox: 250
            },
            this.onScanSuccess.bind(this), // on success callback
        ).catch(err => {
            console.error("Camera start failed", err);
            swal('Camera Error', 'Unable to access the camera. Please allow permissions and try again.', 'error');
        });
    }
    onScanSuccess(decodedText, decodedResult) {
        console.log(`QR Code detected: ${decodedText}`);
        const content = decodedText;
        const orderId = content.toString().replace(/[^0-9]/gi, "");
        if (isNaN(orderId) || orderId == '') {
            swal('Invalid QR Code', 'You scanned an incorrect QR code.', 'warning')
                .then(function () {
                });
            return false;
        }
        $('.order-info-sec-title').addClass('d-none');
        initTourBookingPos.viewOrder(orderId);
    }

    renderOrders() {
        this.makeMenuActive(this.menuForOrders);
        var orders = this.orders;
        var firstOrderId = '';
        let html = `<div class="category-items order-list"><div><div class="form-group" style="position: sticky;top: 0px; background-color:white; padding-top:25px;">
                                        <input type="text" class="form-control" id="orderSearch" placeholder="Search Order Id or Customers.">
                                        <a href="javascript:void(0);" onclick="initTourBookingPos.searchOrders();"><i class="fa fa-search" aria-hidden="true"></i></a>
                                    </div>`;

        html += `<table id="ordersTable">
        <thead style="   position: sticky;
    top: 80px;">
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Date</th>
                                                <th>Sale</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;
        if (orders.length > 0) {
            firstOrderId = orders[0].orderId;
            for (var i = 0; i < orders.length; i++) {
                html += `<tr>
                   <td><a href="javascript:void(0);" onclick="initTourBookingPos.viewOrder(${orders[i].orderId});">#${orders[i].orderId}</a></td>
                   <td>${this.formatDate(orders[i].createdAt)}</td>
                   <td>${currency(orders[i].totalSale).format()}</td>
                </tr>`;

            }
        } else {
            html += `<tr>
                   <td colspan="3">No Orders Found.</a></td>

                </tr>`;
        }
        html += `</tbody></table></div></div>`;
        $(`#mainContent`).addClass('d-none');
        $(`#${this.targetDivForOnHold}`).removeClass('d-none');
        $(`#${this.targetDivForOnHold} #${this.targetDivForOnHoldOrders}`).addClass('d-none');
        $(`#${this.cartSectionDiv}`).addClass('d-none');
        $(`#${this.confirmPaymentSectionDiv}`).addClass('d-none');
        $(`#${this.thankYouSectionDiv}`).addClass('d-none');
        $(`#${this.posMainDiv}`).removeClass('d-none');
        $(`#${this.settingDiv}`).addClass('d-none');
        $(`#${this.targetDivForOnHold} #${this.targetDivForOrders}`).removeClass('d-none');
        $(`#${this.targetDivForOnHold} #${this.targetDivForOrders}`).html(html);
        if (firstOrderId != '') {
            this.viewOrder(firstOrderId);
        }
        this.renderOnHoldOrdersCount();
    }
    renderSearchOrders() {

        var orders = this.orders;
        let html = ``;
        if (orders.length > 0) {
            for (var i = 0; i < orders.length; i++) {
                html += `<tr>
                   <td><a href="javascript:void(0);" onclick="initTourBookingPos.viewOrder(${orders[i].orderId});">#${orders[i].orderId}</a></td>
                   <td>${orders[i].createdAt}</td>
                   <td>${currency(orders[i].totalSale).format()}</td>
                </tr>`;

            }
        } else {
            html += `<tr>
                   <td colspan="3">No Orders Found.</a></td>

                </tr>`;
        }
        $(`#${this.targetDivForOnHold} #${this.targetDivForOrders} #ordersTable tbody`).html(html);

    }
    renderOrderInfo() {
        var orderInfo = this.orderInfo;
        var bookingsInfo = orderInfo.bookingsInfo;
        var customChargeTotal = orderInfo.customChargeTotal;
        var customChargeFeesTotal = orderInfo.customChargeFeesTotal;
        var addOnsTotal = orderInfo.addOnsTotal;
        var addOnsFeesTotal = orderInfo.addOnsFeesTotal;
        var customerName = orderInfo.customerName;
        var customerEmail = orderInfo.customerEmail;
        var customerPhone = orderInfo.customerPhone;
        var payments = orderInfo.payments;
        var subTotalBeforeDiscount = 0;
        var subTotal = 0;
        var platformFeesTotal = 0;
        var discountTotal = 0;
        var total = 0;
        var paymentCost = 0;
        var refundCost = 0;
        var bookingsListHtml = ``;

        if (bookingsInfo.length > 0) {
            $(`#cart-section`).addClass('d-none');
            $(`#${this.orderInfoDiv}`).removeClass('d-none');
            $(`#${this.orderInfoDiv} #orderInfoId`).text(`#${orderInfo.orderId}`);
            $(`#${this.orderInfoDiv} #orderInfoDate`).text(`${orderInfo.date}`);
            $(`#${this.orderInfoDiv} #orderInfoTime`).text(`${orderInfo.time}`);
            for (let i = 0; i < bookingsInfo.length; i++) {
                var time = formatTime(bookingsInfo[i].time_from);
                var noFlipClass = (bookingsInfo[i].packageHasSlots == 0) ? 'no-flip' : '';
                var dateTime = moment(bookingsInfo[i].date).format('ddd, MMM Do, YYYY') + ' @ ' + time;
                var cancelledClass = (bookingsInfo[i].status == 'Cancelled') ? 'booking-cancelled' : '';
                var bookingStatus = (bookingsInfo[i].status == 'Cancelled') ? '(Cancelled)' : '';
                var ticketsHtml = (bookingsInfo[i].status == 'Cancelled') ? `<span style="color: #ffffff;margin-left: 5px;font-weight: 600;">(${bookingsInfo[i].tourists} Tickets)</span>` : `<span style="color:#2F9DD5;margin-left: 5px;font-weight: 600;">(${bookingsInfo[i].tourists} Tickets)</span>`;
                bookingsListHtml += `<div class="details d-flex align-items-center justify-content-between">
                <div class="flip-box">
                <h4 class="flip-box-inner ${noFlipClass}">
                    <span class="flip-box-front ${cancelledClass}">${bookingsInfo[i].tourPackageName} ${ticketsHtml} ${bookingStatus}</span>
                    <span class="flip-box-back">${dateTime}</span>
                </h4>
                </div>
                <h6>${currency(bookingsInfo[i].bookingTotal).format()}</h6>
                                        </div>`;
                subTotalBeforeDiscount += parseFloat(bookingsInfo[i].bookingTotal);
                platformFeesTotal += parseFloat(bookingsInfo[i].platformFees);
                discountTotal += parseFloat(bookingsInfo[i].discount2_value);
                //Calculate remaining payment amount for bookings/order
                paymentCost += parseFloat(bookingsInfo[i].paymentCost);
                refundCost += parseFloat(bookingsInfo[i].refundCost);
            }
            subTotal = subTotalBeforeDiscount - discountTotal;
            total = subTotal + platformFeesTotal;
            var remainingCost = paymentCost - refundCost;
            var customChargeTotal = parseFloat(customChargeTotal);
            var customChargeFeesTotal = parseFloat(customChargeFeesTotal);
            platformFeesTotal += customChargeFeesTotal + addOnsFeesTotal;
            bookingsListHtml += `<div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Ticket Cost</h4>
                                            <h6>${currency(subTotalBeforeDiscount).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Discount</h4>
                                            <h6>${currency(discountTotal).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Subtotal</h4>
                                            <h6>${currency(subTotal).format()}</h6>
                                        </div>`;
            if (customChargeTotal > 0) {
                bookingsListHtml += `<div class="details other d-flex align-items-center justify-content-between">
                                        <h4>Custom Charge</h4>
                                        <h6>${currency(customChargeTotal).format()}</h6>
                                    </div>`;
            }
            if (addOnsTotal > 0) {
                bookingsListHtml += `<div class="details other d-flex align-items-center justify-content-between">
                                        <h4>Add-ons</h4>
                                        <h6>${currency(addOnsTotal).format()}</h6>
                                    </div>`;
            }
            bookingsListHtml += `<div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Fees</h4>
                                            <h6>${currency(platformFeesTotal).format()}</h6>
                                        </div>
                                         <div class="details other-bg d-flex align-items-center justify-content-between">
                                            <h4>Total</h4>
                                            <h6>${currency(paymentCost).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Refund</h4>
                                            <h6>${currency(refundCost).format()}</h6>
                                        </div>
                                         <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Payment</h4>
                                            <h6>${currency(remainingCost).format()}</h6>
                                        </div>`;
            $(`#${this.orderInfoDiv} #orderBookings`).html(bookingsListHtml);
            var htmlContactInfo = `<div class="details d-flex align-items-center justify-content-between">
                                        <h4>Name</h4>
                                        <h6>${customerName}</h6>
                                        </div>
                                        <div class="details d-flex align-items-center justify-content-between">
                                        <h4>Email</h4>
                                        <h6>${customerEmail}</h6>
                                    </div>`;
            if (customerPhone != '') {
                htmlContactInfo += `<div class="details d-flex align-items-center justify-content-between">
                                        <h4>Phone</h4>
                                        <h6>${customerPhone}</h6>
                                    </div>`;
            }

            $(`#${this.orderInfoDiv} #orderContactDetails`).html(htmlContactInfo);
            var htmlPayments = ``;
            for (let j = 0; j < payments.length; j++) {
                htmlPayments += `<div class="details d-flex align-items-center justify-content-between">`;
                if (payments[j].paymentMethod == 'Card') {
                    if (payments[j].cardLastDigits != null && payments[j].cardType != null) {
                        htmlPayments += `<h4>${payments[j].paymentMethod} (${payments[j].cardLastDigits} - ${payments[j].cardType.charAt(0).toUpperCase() + payments[j].cardType.slice(1)})</h4>`;
                    } else {
                        htmlPayments += `<h4>${payments[j].paymentMethod}</h4>`;
                    }

                } else {
                    htmlPayments += `<h4>${payments[j].paymentMethod}</h4>`;
                }
                htmlPayments += `<h6>${currency(parseFloat(payments[j].amount)).format()}</h6>
                </div>`;
            }
            $(`#${this.orderInfoDiv} #orderPaymentDetails`).html(htmlPayments);
            var digitalCopyHtml = `<div class="input-group align-items-center" >
                                            <input type="email" class="form-control input-no-shadow email-receipt" name="email" id="email" placeholder="Email Address" required>
                                            <div class="input-group-append">
                                                <button class="signupbtn send-receipt" id="send-receipt">Send</button>
                                            </div>
                                        </div>`;
            $('#digitalCopy #orderInfoSendReceipt').html(digitalCopyHtml);
        }

    }

    setParams() {
        var data = [];
        this.selectedTourPackages.forEach((f) => {
            data.push(
                {
                    tourPackageId: f.tourPackageId,
                    slotId: f.slotId,
                    rategroups: f.rategroups,
                    discountType: f.discountType,
                    discount: f.discount,
                    promoCode: f.promoCode
                }
            );
        });
        if (this.paymentMethod == 'Card') {
            var params = {
                tourPackages: data,
                paymentMethod: this.paymentMethod,
                note: $('#pos-note').val(),
                customerName: $('#customer_name').val(),
                customerPhone: $('#customer_phone_number').val(),
                customerEmail: $('#customer_email').val(),
                //paymentIntentId : this.stripePaymentIntent
                cardReaderPaymentIntentId: this.paymentIntentId
            }
        } else {
            var params = {
                tourPackages: data,
                paymentMethod: this.paymentMethod,
                note: $('#pos-note').val(),
                customerName: $('#customer_name').val(),
                customerPhone: $('#customer_phone_number').val(),
                customerEmail: $('#customer_email').val()
            }
        }
        return params;
    }
    createBookings() {
        if (this.paymentMethod == 'Card') {
            this.handleCardPayment();
        } else {
            if (this.paymentMethod == 'Cash' && this.creditAmount != 0) {
                var creditAmount = parseFloat(this.creditAmount);
                var total = this.total;
                if (creditAmount < total) {
                    swal('Ensure the added amount is equal to or more than the total.', '', 'warning');
                    return false;
                }
            }
            showLoading();
            $.post({
                url: this.createBookingsUrl,
                data: JSON.stringify(this.setParams()),
                contentType: 'application/json;',
                success: this.onFormSubmitOk.bind(this),
            })
                .done(function (data) {
                })
                .fail(function (jqXHR) {
                    showSubmissionError(jqXHR);
                })
                .always(hideLoading);
        }
    }
    payWithCard() {
        showLoading();
        $.post({
            url: this.createBookingsUrl,
            data: JSON.stringify(this.setParams()),
            contentType: 'application/json;',
            success: this.onFormSubmitOk.bind(this),
        })
            .fail(function (jqXHR) {
                showSubmissionError(jqXHR);
            })
            .always(hideLoading);
    }
    onFormSubmitOk(data) {
        var total = this.total;
        var creditAmount = parseFloat(this.creditAmount);
        var balanceAmount = creditAmount - parseFloat(total);
        var paymentMethod = this.paymentMethod;
        var message = 'Order placed successfully';
        this.showSuccessMessage(message);
        this.renderThankYouStep(data);
        this.fetchPackages();
        this.paymentIntentId = null;

        if (paymentMethod == 'Cash' && balanceAmount > 0) {
            var elemdiv = document.createElement("div");
            elemdiv.innerHTML = "Please give change of <br> " + currency(balanceAmount).format() + "";
            elemdiv.classList.add("swal-title");

            swal({
                icon: "warning",
                content: elemdiv
            });
        }
    }
    sendReceipt(parentId) {
        var email = $(`#${parentId} .email-receipt`).val();
        if (email == '') {
            swal('Email is required', '', 'warning');
            return false;
        }
        this.parentIdForSendReceipt = parentId;
        showLoading();
        $.post({
            url: `${this.sendReceiptUrl}/${this.orderId}`,
            headers: { Accept: 'application/json' },
            dataType: 'json',
            data: { email: email },
            success: this.sendReceiptOk.bind(this),
        }).fail(function (jqXHR) {
            showSubmissionError(jqXHR);
        })
            .always(hideLoading);
    }
    sendReceiptOk(data) {
        var html = `<div class="promo-code-wrap d-flex justify-content-between align-items-center mt-3">
        <h4><i class="fa fa-check-circle" aria-hidden="true"></i><span>Digital Copy Sent</span></h4>
    </div>`;
        $(`#${this.parentIdForSendReceipt}`).html(html);
    }

    roundout(amount, places = 2) {
        if (places < 0) places = 0;
        let factor = Math.pow(10, places);
        return Math.round(amount * factor) / factor;
    }
    renderCartStep() {
        $(`#${this.cartSectionDiv}`).removeClass('d-none');
        $(`#${this.confirmPaymentSectionDiv}`).addClass('d-none');
        $(`#${this.cartSectionDiv} #${this.paymentMethod}`).addClass('active');
        if (this.paymentMethod == 'Card') {
            $(`#${this.cartSectionDiv} #Cash`).removeClass('active');
        } else if (this.paymentMethod == 'Cash') {
            $(`#${this.cartSectionDiv} #Card`).removeClass('active');
        }
        this.calculateTotals();
    }
    validateCart() {
        if (this.selectedTourPackages.length == 0) {
            swal('Your cart is empty.Please add packages in the cart.', '', 'warning');
            return false;
        }
        // Validate customer info if mandatory
        var customerEmail = $('#customer_email').val();
        var customerName = $('#customer_name').val();
        if (this.customerInfoMandatory == 1 && (customerEmail == '' || customerName == '')) {
            swal('Customer Name and Email are mandatory.', '', 'warning');
            return false;
        }

        showLoading();
        $.post({
            url: this.validateCartUrl,
            data: JSON.stringify(this.setParamsToValidateCart()),
            contentType: 'application/json;',
            success: this.onValidateCartOk.bind(this),
        })
            .fail(function (jqXHR) {
                showSubmissionError(jqXHR);

            })
            .always(hideLoading());
    }
    setParamsToValidateCart() {
        var data = [];
        var customerEmail = $('#customer_email').val();
        var customerName = $('#customer_name').val();
        this.selectedTourPackages.forEach((f) => {
            data.push(
                {
                    tourPackageId: f.tourPackageId,
                    tourPackageName: f.tourPackageName,
                    slotDate: moment(f.slotDate).format('YMMDD'),
                    slotTime: f.slotTime,
                    slotId: f.slotId,
                    applyOnDate: f.applyOnDate,
                    applyOnSlots: f.applyOnSlots,
                    tourists: f.tourists,
                    rateGroups: f.rategroups
                }
            );
        });
        var params = {
            tourPackages: data,
            customerEmail: customerEmail,
            customerName: customerName
        }
        return params;
    }
    onValidateCartOk(data) {

        if (typeof data.tourPackages !== 'undefined' && data.tourPackages.length > 0) {
            hideLoading();
            var tourPackages = data.tourPackages;
            var html = `<h5>Slots of following packages will overbooked. Please remove or change slots:</h5>
                        <br><table class="table table-bordered">
                        <thead>
                            <tr>
                            <th scope="col">No.</th>
                            <th scope="col">Package</th>
                            </tr>
                        </thead>
                        <tbody>`;
            for (let i = 0; i < tourPackages.length; i++) {
                html += `<tr><td>${i + 1}</td>
                         <td>${tourPackages[i]}</td>`;

            }
            html += `</tbody></table>`;
            var elemdiv = document.createElement("div");
            elemdiv.innerHTML = html;
            swal({
                icon: "warning",
                content: elemdiv
            });
            return false;
        } else if (typeof data.closedSlotsOfPackages !== 'undefined' && data.closedSlotsOfPackages.length > 0) {
            hideLoading();
            var closedSlotsOfPackages = data.closedSlotsOfPackages;
            var html = `<h5>Slots of following packages are already Closed. Please remove or change slots:</h5>
                        <br><table class="table table-bordered">
                        <thead>
                            <tr>
                            <th scope="col">No.</th>
                            <th scope="col">Package</th>
                            </tr>
                        </thead>
                        <tbody>`;
            for (let i = 0; i < closedSlotsOfPackages.length; i++) {
                html += `<tr><td>${i + 1}</td>
                         <td>${closedSlotsOfPackages[i]}</td>`;

            }
            html += `</tbody></table>`;
            var elemdiv = document.createElement("div");
            elemdiv.innerHTML = html;
            swal({
                icon: "warning",
                content: elemdiv
            });
            return false;
        } else if (typeof data.tourPackagesMinMaxPax !== 'undefined' && data.tourPackagesMinMaxPax.length > 0) {
            var tourPackagesMinMaxPax = data.tourPackagesMinMaxPax;
            var html = `<h5>Following Paxes required to process your bookings:</h5>
                        <br><table class="table table-bordered">
                        <thead>
                            <tr>
                            <th scope="col">Package</th>
                            <th scope="col">Rate Group</th>
                            <th scope="col">Min Pax</th>
                            <th scope="col">Max Pax</th>
                            </tr>
                        </thead>
                        <tbody>`;
            for (let i = 0; i < tourPackagesMinMaxPax.length; i++) {
                html += `<tr><td>${tourPackagesMinMaxPax[i].tourPackageName}</td>
                         <td>${tourPackagesMinMaxPax[i].rateFor}</td>
                         <td>${tourPackagesMinMaxPax[i].minPaxAllowed}</td>
                         <td>${tourPackagesMinMaxPax[i].maxPaxAllowed}</td></tr>`;

            }
            html += `</tbody></table>`;
            var elemdiv = document.createElement("div");
            elemdiv.innerHTML = html;
            swal({
                icon: "warning",
                content: elemdiv
            });
            return false;

        } else {
            if (this.paymentMethod == 'Card') {
                this.initializeStripe();
            } else {
                hideLoading();
            }
            // Connecting here unconditionally — on every cart-to-payment
            // transition, regardless of which payment method gets used —
            // silently re-claims the reader's Stripe Terminal session even
            // when nobody asked to use it. Since a reader only holds one
            // authenticated session at a time, that can steal it away from
            // another tab/workstation actively mid-payment on it (see the
            // "POS is no longer authenticated" bug this caused). Connecting
            // now happens only when the Card Reader tab is explicitly
            // opened — see the shown.bs.tab binding in initEvents().
            initTourBookingPos.renderConfirmPaymentStep();
        }

    }
    async initializeStripe() {
        // Show the loader
        showLoading();

        try {
            $('#payment-element').empty();
            // $('#link-authentication-element').empty();

            // Get CSRF token from meta tag
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            const intentResponse = await fetch(this.createStripePaymentIntentUrl, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken // Include CSRF token in headers
                },
                body: JSON.stringify(this.setParams()), // Send JSON data
            });

            const responseData = await intentResponse.json();

            if (!intentResponse.ok) {
                throw new Error(responseData.message || 'Unknown backend error');
            } else if (responseData.error) {
                throw new Error(responseData.error);
            }

            // Store clientSecret globally
            this.clientSecret = responseData.clientSecret;

            const appearance = {
                theme: 'stripe', // Options: 'stripe', 'night', 'flat', 'none'
                variables: {
                    colorText: '#333',
                    fontFamily: 'Inter, sans-serif',
                    fontWeightNormal: '400',
                    fontWeightBold: '700',
                    // See all possible variables below
                },
                rules: {
                    '.Input': {
                        fontSize: '14px',
                        fontWeight: '400', // Apply font-weight to input fields
                    },
                    '.Label': {
                        fontSize: '15px',
                        fontWeight: '600', // Apply font-weight to labels
                    }
                }
            };

            // Enable the skeleton loader UI for the optimal loading experience.
            const loader = 'auto';

            const options = {
                layout: {
                    type: 'auto', // Options: 'accordion', 'tabs', 'inline', 'auto'
                },
                paymentMethodOrder: ['card', 'apple_pay', 'google_pay'], // Prioritize payment methods
                wallets: {
                    applePay: 'auto',
                    googlePay: 'auto'
                },
                defaultValues: {
                    billingDetails: {
                        email: $('#customer_email').val(),
                        address: {
                            country: this.countryCode, // Default country
                        },
                    },
                },
            };

            this.elements = stripe.elements({ clientSecret: this.clientSecret, appearance, loader });

            // Create payment element
            const paymentElement = this.elements.create("payment", options);

            // Create and mount Link Authentication Element
            // const linkAuthenticationElement = this.elements.create("linkAuthentication");

            // Wait until elements are mounted before hiding the loader
            await new Promise((resolve, reject) => {
                paymentElement.mount("#payment-element");
                paymentElement.on("ready", resolve);

                // linkAuthenticationElement.mount("#link-authentication-element");
                // linkAuthenticationElement.on("ready", resolve);

                // Timeout to prevent indefinite loading
                setTimeout(() => reject(new Error("Element mounting timed out")), 30000);
            });
        } catch (error) {
            console.error("Error initializing Stripe elements:", error);
            hideLoading();
            swal('Error occurred', error.message, 'error');
        } finally {
            hideLoading();
        }
    }
    async handleCardPayment() {
        showLoading();
        try {
            let error = null;
            let paymentIntent = null;
            if (this.isCartReaderPayment === false) {
                // Confirm payment with Stripe
                ({ paymentIntent, error } = await stripe.confirmPayment({
                    elements: this.elements,
                    confirmParams: {
                        return_url: "https://google.com",
                        payment_method_data: {
                            billing_details: {
                                name: $('[name="customer_name"]').val(),
                                email: $('[name="customer_email"]').val(),
                                phone: $('[name="customer_phone_number"]').val(),
                            }
                        }
                    },
                    redirect: "if_required",
                }));
            } else {
                paymentIntent = this.paymentIntent;
            }

            if (error) {
                swal('Error occurred', error.message, 'error');
                hideLoading();
            } else {
                console.log("Payment Intent:", paymentIntent);
                // Append the hidden input field if it doesn't already exist
                this.paymentIntentId = paymentIntent.id;
                $.post({
                    url: this.createBookingsUrl,
                    data: JSON.stringify(this.setParams()),
                    contentType: 'application/json;',
                    success: this.onFormSubmitOk.bind(this),
                })
                    .done(function (data) {
                    })
                    .fail(function (jqXHR) {
                        showSubmissionError(jqXHR);
                    })
                    .always(hideLoading);
            }
        } catch (apiError) {
            console.error("API Error:", apiError);
            swal('Error occurred', apiError, 'error');
            hideLoading();
            $('#makePaymentBtn')[0].disabled = false;
        } finally {
            //hideLoading();
        }
    }
    /**
     * Card Reader Capture
     * Process the payment with stripe card reader
     */
    capturePaymenWithStripeCardReader() {

        let note = [];
        let pkgs = [];
        this.selectedTourPackages.forEach((t) => {
            pkgs.push(`${t.tourPackageName}`);
            note.push(`${t.tourPackageName} pax count: ${t.tourists} @ ${t.slotDate || new Date().toLocaleDateString()}`);
        })
        const description = `${this.selectedTourPackages.length} tour packages on ${new Date().toLocaleDateString()}`;
        // Settlement-mode resolution (see CardReaderRepository::resolveModeFromInput()) reads
        // these tour dates server-side — no booking row exists yet for a brand-new POS booking,
        // so the dates have to come from what's already selected client-side. slotDate is stored
        // as 'YMMDD' (see changeSlot()), not ISO — reparse explicitly rather than let moment()
        // guess the format. A package added via the no-slot-needed path (addRateGroup() with no
        // date arg) has slotDate === '' — skip it rather than let moment('', 'YMMDD') produce its
        // own literal "Invalid date" string, which Stripe's metadata rejects outright.
        const cartDates = this.selectedTourPackages
            .filter((t) => !!t.slotDate)
            .map((t) => moment(t.slotDate, 'YMMDD').format('YYYY-MM-DD'));
        // Send only the LATEST date across the cart, not every item's — equivalent to
        // PaymentModeResolver::resolve()'s own "any date >= 2027-01-01" rule (the max only
        // crosses the boundary when at least one item does), so this changes nothing about which
        // mode gets picked, just how many values get sent.
        const latestCartDate = cartDates.length ? [cartDates.reduce((latest, d) => (d > latest ? d : latest))] : [];
        // Note this metadata is updated on createBookings method on the backend
        const meta = {
            packages: pkgs.join(","),
            type: "pos-card-present",
            note: note.join("\n"),
            dates: latestCartDate,
        }

        const fees = this.platformFeesTotal.toFixed(2);
        const amount = this.total.toFixed(2);
        const onSuccess = this.onCardReaderSuccess.bind(this);
        const onFail = this.onCardReaderFail.bind(this);

        this.cardReader.makePayment(amount, description, fees, meta, onSuccess, onFail);
    };

    /**
     * on Card Reader Payment success
     * @param {object} data
     */
    onCardReaderSuccess = (data) => {
        this.paymentMethod = "Card";
        this.paymentIntent = data;
        this.paymentIntentId = data.id;
        this.isCartReaderPayment = true;
        this.createBookings();
    }

    /**
     * on Card Reader Payment fail
     * @param {*} data
     */
    onCardReaderFail(data) {
        this.paymentIntentId = null;
        swal("Error", data.message, "error");
    }

    /**
     * Bootstrap-only equivalent of StripeReaderIdentity.applyState() for the
     * POS Card Reader tab — this page's layout doesn't load css/app.css, so
     * the shared Tailwind-built chip/state-area markup wouldn't render here.
     * Same state names (idle|searching|connecting|reconnecting|connected|
     * needs_setup|error) as the rest of the app for consistency of meaning.
     * @param {string} state
     * @param {object} meta
     */
    renderCardReaderState(state, meta = {}) {
        const chipEl = document.getElementById('card-reader-identity-chip');
        const placeholderEl = document.getElementById('card-reader-generic-placeholder');
        const stateAreaEl = document.getElementById('card-reader-state-area');
        if (!chipEl || !placeholderEl || !stateAreaEl) return;

        const badgeByState = {
            idle: { cls: 'bg-secondary', text: 'Not connected' },
            searching: { cls: 'bg-info', text: 'Searching…' },
            connecting: { cls: 'bg-info', text: 'Connecting…' },
            reconnecting: { cls: 'bg-warning text-dark', text: 'Reconnecting…' },
            connected: { cls: 'bg-success', text: 'Connected' },
            needs_setup: { cls: 'bg-danger', text: 'Needs setup' },
            error: { cls: 'bg-danger', text: 'Disconnected' },
        };
        const badge = badgeByState[state] || badgeByState.idle;

        const reader = meta.reader || (this.cardReader.getSavedReader ? this.cardReader.getSavedReader() : null) || {};
        const known = !!(reader.label || reader.id);

        if (known) {
            const model = (typeof StripeReaderIdentity !== 'undefined') ? StripeReaderIdentity.deviceTypeLabel(reader.device_type) : (reader.device_type || '');
            chipEl.innerHTML = `
                <div class="d-flex align-items-center gap-2 border rounded p-2 bg-light">
                    <i class="fa fa-wifi text-secondary" aria-hidden="true"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size:14px;">${reader.label || reader.id}</div>
                        <div class="text-muted" style="font-size:12px;">${model}</div>
                    </div>
                    <span class="badge ${badge.cls}">${badge.text}</span>
                </div>
            `;
            chipEl.classList.remove('d-none');
            placeholderEl.classList.add('d-none');
        } else {
            chipEl.classList.add('d-none');
            placeholderEl.classList.remove('d-none');
        }

        if (state === 'searching' || state === 'connecting' || state === 'reconnecting') {
            const label = (state === 'reconnecting' && meta.attempt && meta.maxAttempts)
                ? `Reconnecting — attempt ${meta.attempt} of ${meta.maxAttempts}…`
                : badge.text;
            stateAreaEl.innerHTML = `
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <div class="mt-2 text-muted" style="font-size:13px;">${label}</div>
            `;
            stateAreaEl.classList.remove('d-none');
        } else {
            // needs_setup/error (and connected/idle): stay out of the way
            // here. The identity chip's badge already flags "Needs setup",
            // and .card-reader-status-output (StripeCardReader.setMessage())
            // already carries the actionable message *with* a working
            // Retry/Reconnect button — a second, button-less repeat of the
            // same text here was pure duplication.
            stateAreaEl.innerHTML = '';
            stateAreaEl.classList.add('d-none');
        }

        // Mirrors the actual connection state, not just which button was
        // last clicked — so an auto-reconnect after an unexpected disconnect
        // keeps Connect/Disconnect in sync too.
        if (state === 'connected') {
            $('.connect-to-card-reader').hide();
            $('.dis-connect-to-card-reader').show();
        } else if (state === 'idle' || state === 'needs_setup' || state === 'error') {
            $('.connect-to-card-reader').show();
            $('.dis-connect-to-card-reader').hide();
        }
    }

    /**
     * If the workstation's assigned reader was switched elsewhere (e.g. on
     * the Card Reader Settings page) since this instance last connected,
     * reconnect to the current one instead of silently keeping the stale
     * reader showing "Connected". A no-op if nothing's actually stale.
     */
    reconnectCardReaderIfChanged() {
        if (this._reconnectCheckScheduled) return;
        const savedId = this.cardReader?.getSavedReader?.()?.id;
        const connectedId = this.cardReader?.reader?.id;
        const staleReader = savedId && connectedId && connectedId !== savedId;
        if (staleReader) {
            this._reconnectCheckScheduled = true;
            setTimeout(() => {
                this._reconnectCheckScheduled = false;
                $('.connect-to-card-reader').trigger('click');
            }, 300);
        }
    }

    renderConfirmPaymentStep() {
        if (this.selectedTourPackages.length == 0) {
            swal('Please add packages in the cart.', '', 'warning');
            return false;
        }
        $(`#${this.cartSectionDiv}`).addClass('d-none');
        $(`#${this.confirmPaymentSectionDiv}`).removeClass('d-none');
        // The Card Reader tab only unconditionally reconnects when its
        // shown.bs.tab event actually fires — i.e. on an explicit tab
        // click. Back-to-back transactions where it was ALREADY the
        // selected tab from the previous booking never re-trigger that
        // event, so a reader switched elsewhere (e.g. Card Reader Settings)
        // mid-session would otherwise keep showing the stale one here too.
        this.reconnectCardReaderIfChanged();

        var html = ``;
        var selectedPackages = this.selectedTourPackages;
        for (let i = 0; i < selectedPackages.length; i++) {
            var subTotal = this.calculateGroupTotalPackage(selectedPackages[i].tourPackageId);
            var slotTime = '';
            var slotHtml = '';
            if (selectedPackages[i].slotId != '' && selectedPackages[i].slotDate != '' && selectedPackages[i].slotTime != '') {
                var slotDate = moment(selectedPackages[i].slotDate).format('ddd, MMM Do, YYYY');
                slotTime = `${slotDate} @ ${selectedPackages[i].slotTime}`;
                slotHtml = `<div class="flip-box">
                <h4 class="flip-box-inner">
                    <span class="flip-box-front">${selectedPackages[i].tourPackageName}</span>
                    <span class="flip-box-back">${slotTime}</span>
                </h4>
                </div>`;
            } else {
                slotHtml = `<h4>${selectedPackages[i].tourPackageName} ${slotTime}</h4>`;
            }
            html += `<div class="details d-flex align-items-center justify-content-between">
                      ${slotHtml}
                    <h6>${currency(subTotal).format()}</h6>
                                        </div>`;
        }
        html += `<div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Ticket Cost</h4>
                                            <h6 id="ticketCost">${currency(this.subTotalBeforeDiscount).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Discount</h4>
                                            <h6 id="discountTotal">${currency(this.discountTotal).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Subtotal</h4>
                                            <h6 id="subTotal">${currency(this.subTotal).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Fees</h4>
                                            <h6 id="platformFeesTotal">${currency(this.platformFeesTotal).format()}</h6>
                                        </div>`
        $(`#${this.confirmPaymentSectionDiv} #cartPkgTotals`).html(html);
        $(`#${this.confirmPaymentSectionDiv} #total`).text(`${currency(this.total).format()}`);
        $(`#${this.confirmPaymentSectionDiv} #myTab #${this.paymentMethod}`).trigger("click");
        $('#credit-amount-input').val('');
        this.creditAmount = 0;
        this.balanceAmount = 0;
        this.calculateBalanceAmount();
        this.renderOnHoldOrdersCount();
    }
    renderThankYouStep(data) {
        this.orderId = data.orderId;
        $(`#${this.confirmPaymentSectionDiv}`).addClass('d-none');
        $(`#${this.thankYouSectionDiv}`).removeClass('d-none');
        $(`#${this.thankYouSectionDiv} #orderId`).text(`#${data.orderId}`);
        $(`#${this.thankYouSectionDiv} #orderDate`).text(`${data.date}`);
        $(`#${this.thankYouSectionDiv} #orderTime`).text(`${data.time}`);
        $('#nav-contact-info').trigger('click');
        $('#pos-note').val('');
        $('#customer_name').val('');
        $('#customer_phone_number').val('');
        $('#customer_email').val('');
        $('#add-info').removeClass('info-added-btn');
        $('#cardnumber').val('');
        $('#card-name').val('');
        $('#expiration').val('');
        $('#cvv').val('');
        var html = ``;
        var selectedPackages = this.selectedTourPackages;
        for (let i = 0; i < selectedPackages.length; i++) {
            var subTotal = this.calculateGroupTotalPackage(selectedPackages[i].tourPackageId);
            var slotTime = '';
            var slotHtml = '';
            var noFlipClass = (selectedPackages[i].slotId == '') ? 'no-flip' : '';
            if (selectedPackages[i].slotId != '' && selectedPackages[i].slotDate != '' && selectedPackages[i].slotTime != '') {
                var slotDate = moment(selectedPackages[i].slotDate).format('ddd, MMM Do, YYYY');
                slotTime = `${slotDate} @ ${selectedPackages[i].slotTime}`;
            }
            slotHtml = `<div class="flip-box">
                <h4 class="flip-box-inner ${noFlipClass}">
                    <span class="flip-box-front">${selectedPackages[i].tourPackageName}</span>
                    <span class="flip-box-back">${slotTime}</span>
                </h4>
                </div>`;
            html += `<div class="details d-flex align-items-center justify-content-between">
                    ${slotHtml}
                    <h6>${currency(subTotal).format()}</h6>
                    </div>`;
        }
        html += `<div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Ticket Cost</h4>
                                            <h6>${currency(this.subTotalBeforeDiscount).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Discount</h4>
                                            <h6>${currency(this.discountTotal).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Subtotal</h4>
                                            <h6>${currency(this.subTotal).format()}</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Fees</h4>
                                            <h6>${currency(this.platformFeesTotal).format()}</h6>
                                        </div>
                                         <div class="details other-bg d-flex align-items-center justify-content-between">
                                            <h4>Total</h4>
                                            <h6>${currency(this.total).format()}</h6>
                                        </div>`;
        $(`#${this.thankYouSectionDiv} #orderPackages`).html(html);
        var htmlContactInfo = `<div class="details d-flex align-items-center justify-content-between">
                                        <h4>Name</h4>
                                        <h6>${data.customerName}</h6>
                                        </div>
                                        <div class="details d-flex align-items-center justify-content-between">
                                        <h4>Email</h4>
                                        <h6>${data.customerEmail}</h6>
                                    </div>`;
        if (data.customerPhone != '') {
            htmlContactInfo += `<div class="details d-flex align-items-center justify-content-between">
                                        <h4>Phone</h4>
                                        <h6>${data.customerPhone}</h6>
                                    </div>`;
        }
        $(`#${this.thankYouSectionDiv} #orderContactDet`).html(htmlContactInfo);
        var htmlPaymentType = `<div class="details d-flex align-items-center justify-content-between">
                                            <h4>${this.paymentMethod}</h4>`;
        if (this.paymentMethod == 'Cash' && this.creditAmount > 0) {
            htmlPaymentType += `<h6>${currency(this.creditAmount).format()}</h6>`;
        } else {
            htmlPaymentType += `<h6>${currency(this.total).format()}</h6>`;
        }

        htmlPaymentType += `</div>`;
        $(`#${this.thankYouSectionDiv} #orderPaymentType`).html(htmlPaymentType);
        var htmlDigitalCopy = `<div class="input-group align-items-center">
                                <input type="email" class="form-control input-no-shadow email-receipt" name="email" placeholder="Email Address" required>
                                <div class="input-group-append">
                                    <button class="signupbtn send-receipt" id="send-order-receipt">Send</button>
                                </div>
                               </div>`;
        $(`#orderSendReceipt`).html(htmlDigitalCopy);
        this.selectedTourPackages = [];
        this.renderSelectedRateGroup();
        $('.rategroups-btn').removeClass('active');
        this.paymentMethod = 'Card';
        $(`#${this.cartSectionDiv} #Card`).addClass('active');
        $(`#${this.cartSectionDiv} #Cash`).removeClass('active');
        this.selectedDay = '';
        this.selectedMonth = '';
        this.selectedYear = '';
    }
    showSuccessMessage(message) {
        $.notify({
            // options
            title: '<strong style="color: #0f5132;font-size: 16px;">Success!</strong>',
            message: '<br><span style="color: #0f5132;font-size: 16px;">' + message + '</span>',
            icon: 'glyphicon glyphicon-ok'
        }, {
            // settings
            element: 'body',
            type: "success",
            allow_dismiss: true,
            showProgressbar: false,
            placement: {
                from: "top",
                align: "right"
            },
            offset: 20,
            spacing: 10,
            z_index: 1031,
            delay: 3300,
            timer: 1000,
            animate: {
                enter: 'animated fadeInDown',
                exit: 'animated fadeOutRight'
            }
        });
    }

    backToHome() {
        $(`#${this.cartSectionDiv}`).removeClass('d-none');
        $(`#${this.thankYouSectionDiv}`).addClass('d-none');
        $(`#${this.cartSectionDiv} #Cash`).removeClass('active');
        $(`#${this.cartSectionDiv} #Card`).addClass('active');
    }
    renderHome() {
        this.fetchPackages();
        $(`#${this.posMainDiv}`).removeClass('d-none');
        $(`#mainContent`).removeClass('d-none');
        $(`#${this.targetDivForOnHold}`).addClass('d-none');
        $(`#${this.orderInfoDiv}`).addClass('d-none');
        $(`#${this.cartSectionDiv}`).removeClass('d-none');
    }
    renderTotals() {
        $('#pos-ticketcost').text(currencyFormation.symbol + this.subTotalBeforeDiscount.toFixed(2));

        $('#pos-discounttotal').text(currencyFormation.symbol + this.discountTotal.toFixed(2));
        $('#pos-subtotal').text(currencyFormation.symbol + this.subTotal.toFixed(2));
        $('#pos-subtotal').text(currencyFormation.symbol + this.subTotal.toFixed(2));
        $('#pos-fees').text(currencyFormation.symbol + this.platformFeesTotal.toFixed(2));
        $('#pos-final-total').text(currencyFormation.symbol + this.total.toFixed(2));
        $('#pos-payment-total').text(currencyFormation.symbol + this.total.toFixed(2));
        $('#final-total').text(currencyFormation.symbol + this.total.toFixed(2));
    }
    renderTotalsInConfirmPaymentStep() {
        $('#confirm-payment #subTotal').text(currencyFormation.symbol + this.subTotal.toFixed(2));
        $('#confirm-payment #platformFeesTotal').text(currencyFormation.symbol + this.platformFeesTotal.toFixed(2));
        $('#confirm-payment #total').text(currencyFormation.symbol + this.total.toFixed(2));
    }
    renderBalanceCreditAmounts() {
        $('#cashCalculator #credit-amount').text(parseFloat(this.creditAmount).toFixed(2));
        $('#cashCalculator #balance-amount').text(parseFloat(this.balanceAmount).toFixed(2));
    }
    renderCardDetails() {
        if (this.paymentMethod == 'Card') {
            $('#card-element').show();
        } else {
            $('#card-element').hide();
        }

    }
    formatDate(date) {
        return moment(date).format('D MMM YYYY, hh:mm a');
    }
    refresh() {
        if ($('#mainContent').is(":visible")) {
            this.fetchPackages();
            this.clearCartAndSetDefaults();
            $(`#${this.cartSectionDiv}`).removeClass('d-none');
            $(`#${this.targetDivForOnHold}`).addClass('d-none');
        } else if ($(`#ordersList`).is(":visible")) {
            this.fetchOrders();
        } else if ($(`#${this.targetDivForOnHold}`).is(":visible")) {
            $('#mainContent').removeClass('d-none');
            this.fetchPackages();
            this.clearCartAndSetDefaults();
            $(`#${this.cartSectionDiv}`).removeClass('d-none');
            $(`#${this.targetDivForOnHold}`).addClass('d-none');
        } else if ($(`#${this.settingDiv}`).is(":visible")) {
            this.showPosSetting();
        }
    }
    renderOrderBookingIds() {
        var orderInfo = this.orderInfo;
        var bookingsInfo = orderInfo.bookingsInfo;
        if (bookingsInfo.length > 0) {
            var html = ``;
            for (let i = 0; i < bookingsInfo.length; i++) {
                var isDisabled = (bookingsInfo[i].status == 'Cancelled') ? 'disabled' : '';
                var isChecked = (bookingsInfo[i].status == 'Cancelled') ? 'checked' : '';
                var cancelledHtml = (bookingsInfo[i].status == 'Cancelled') ? '<span style="color:red;">(Cancelled)</span>' : '';
                var chkboxClass = (bookingsInfo[i].status == 'Booked') ? 'bookingid-checkbox' : '';
                html += `<div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input type="checkbox" id="package_${bookingsInfo[i].id}" class="form-check-input ${chkboxClass}" value="${bookingsInfo[i].id}" ${isDisabled} ${isChecked}>
                                            <label for="package_${bookingsInfo[i].id}" class="form-check-label">${bookingsInfo[i].tourPackageName} (#${bookingsInfo[i].id}) ${cancelledHtml}</label>
                                        </div>
                                    </div>`;

            }

            $('#cancelBookingModal #orderBookings').html(html);


        }
    }
    renderOrderBookingIdsForRefund() {
        var orderInfo = this.orderInfo;
        var bookingsInfo = orderInfo.bookingsInfo;
        if (bookingsInfo.length > 0) {
            var html = ``;
            for (let i = 0; i < bookingsInfo.length; i++) {
                var isDisabled = (bookingsInfo[i].status == 'Cancelled') ? 'disabled' : '';
                var isChecked = (bookingsInfo[i].status == 'Cancelled') ? 'checked' : '';
                var cancelledHtml = (bookingsInfo[i].status == 'Cancelled') ? '<span style="color:red;">(Cancelled)</span>' : '';
                var chkboxClass = (bookingsInfo[i].status == 'Booked') ? 'refund-bookingid-checkbox' : '';
                html += `<div class="col-md-6">
                                        <div class="form-check mb-2">
                                            <input type="checkbox" id="package_refund_${bookingsInfo[i].id}" class="form-check-input ${chkboxClass}" value="${bookingsInfo[i].id}" ${isDisabled} ${isChecked}>
                                            <label for="package_refund_${bookingsInfo[i].id}" class="form-check-label">${bookingsInfo[i].tourPackageName} (#${bookingsInfo[i].id}) ${cancelledHtml}</label>
                                        </div>
                                    </div>`;

            }

            $('#refundOrderModal #orderBookings').html(html);


        }
    }
    validateBookingsChecked(requestType) {
        if (requestType == this.requestTypeCancelOrder) {
            var selectedBookingIds = $('#cancelBookingModal .bookingid-checkbox:checked');
            var note = $('#cancelBookingModal #cancelNote').val();
        } else if (requestType == this.requestTypeRefundOrder) {
            var selectedBookingIds = $('#refundOrderModal .refund-bookingid-checkbox:checked');
            var note = $('#refundOrderModal #refundNote').val();
        }
        if (selectedBookingIds.length == 0) {
            swal('Please select bookings.', '', 'warning');
            return false;
        }

        if (note == '') {
            swal('Please add a note.', '', 'warning');
            return false;
        }
    }
    setParamsCancelOrder() {
        var data = [];
        var selectedBookingIds = $('#cancelBookingModal .bookingid-checkbox:checked');
        selectedBookingIds.each(function () {
            data.push($(this).val());
        });
        var refundType = $('#cancelBookingModal .refund-type:checked').val();
        var isCash = ($('#cancelBookingModal .is-cash-chkbox:checked').length > 0) ? $('#cancelBookingModal .is-cash-chkbox:checked').val() : '';

        var cancelNote = $('#cancelBookingModal #cancelNote').val();
        var params = {
            bookings: data,
            refund_type: refundType,
            is_cash: isCash,
            fareharbor: '',
            comment: cancelNote,
        }

        return params;
    }
    cancelOrder() {
        var selectedBookingIds = $('#cancelBookingModal .bookingid-checkbox:checked');
        if (selectedBookingIds.length == 0) {
            swal('Please select bookings.', '', 'warning');
            return false;
        }
        var refundType = $('#cancelBookingModal .refund-type:checked').val();
        if (refundType == 'partial_refund' && $('#cancelBookingModal #partialRefundAmount').val() == '') {
            swal('Please enter partial refund amount.', '', 'warning');
            return false;
        }
        showLoading();
        $.post({
            url: `${this.cancelOrderUrl}/${this.orderId}`,
            data: JSON.stringify(this.setParamsCancelOrder()),
            contentType: 'application/json;',
            success: this.onCancelOrderOk.bind(this, refundType),
        })
            .fail(function (jqXHR) {
                showSubmissionError(jqXHR);
            })
            .always(hideLoading);
    }
    onCancelOrderOk(refundType, data) {
        var orderInfo = this.orderInfo;
        var cancelledBookings = data.bookings;
        var orderBookings = orderInfo.bookingsInfo;
        for (let i = 0; i < orderBookings.length; i++) {
            if (Object.values(cancelledBookings).includes(orderBookings[i].id.toString())) {
                orderBookings[i].status = 'Cancelled';
            }
        }
        orderInfo.bookingsInfo = orderBookings;
        $('#cancelBookingModal').modal('hide');
        this.viewOrder(orderInfo.orderId);

        var message = 'Booking has been cancelled and the refund is being issued.';
        if (refundType == "dont_refund") {
            var message = 'Booking has been cancelled.';
        }

        this.showSuccessMessage(message);
    }
    setParamsRefundOrder() {
        var data = [];
        var selectedBookingIds = $('#refundOrderModal .refund-bookingid-checkbox:checked');
        selectedBookingIds.each(function () {
            data.push($(this).val());
        });
        var refundType = $('#refundOrderModal .refund-type:checked').val();
        var isCash = ($('#refundOrderModal .is-cash-chkbox:checked').length > 0) ? $('#refundOrderModal .is-cash-chkbox:checked').val() : '';

        var refundNote = $('#refundOrderModal #refundNote').val();
        var params = {
            bookings: data,
            refund_type: refundType,
            is_cash: isCash,
            fareharbor: '',
            comment: refundNote,
        }

        return params;
    }
    refundOrder() {
        var selectedBookingIds = $('#refundOrderModal .refund-bookingid-checkbox:checked');
        if (selectedBookingIds.length == 0) {
            swal('Please select bookings.', '', 'warning');
            return false;
        }
        var refundType = $('#refundOrderModal .refund-type:checked').val();

        var refundNote = $('#refundOrderModal #refundNote').val();
        if (refundNote == '') {
            swal('Please add a note.', '', 'warning');
            return false;
        }
        showLoading();
        $.post({
            url: `${this.refundOrderUrl}/${this.orderId}`,
            data: JSON.stringify(this.setParamsRefundOrder()),
            contentType: 'application/json;',
            success: this.onRefundOrderOk.bind(this),
        })
            .fail(function (jqXHR) {
                showSubmissionError(jqXHR);
            })
            .always(hideLoading);
    }
    onRefundOrderOk(data) {
        var orderInfo = this.orderInfo;
        var orderBookings = orderInfo.bookingsInfo;

        orderInfo.bookingsInfo = orderBookings;
        $('#refundOrderModal').modal('hide');
        this.viewOrder(orderInfo.orderId);
        var message = 'Refund is being issued.';
        this.showSuccessMessage(message);
    }
    showPosSetting() {
        showLoading();
        $.get(`${this.settingsUrl}`)
            .done(this.showPosSettingOk.bind(this))
            .fail(() => swal('Error occurred', 'Unable to get setting from server.', 'error'))
            .always(hideLoading);
    }

    showPosSettingOk(data) {
        $(`#${this.settingDiv}`).removeClass('d-none');
        $(`#${this.posMainDiv}`).addClass('d-none');

        let categories = data.categories;
        let html = '';
        categories.forEach(category => {
            html += this.renderTourPackageSetting(category);
        });

        $(`#${this.settingDiv} #tourPackageOrder`).html(html);
        this.initSortable(); // Initialize drag-and-drop sorting
    }

    renderTourPackageSetting(category) {
        let html = `<div class="col-md-3 panel">
                        <div class="main-setting-box">
                            <h5 class="categorized-title">${category.categoryName}</h5>
                            <ul class="sortable-list" data-category-id="${category.categoryId}">`;

        category.tourPackages.forEach(pkg => {
            html += `<li class="sortable-item" data-id="${pkg.tourPackageId}">
                        <span class="drag-handle">☰</span>
                        <label>${pkg.tourPackageName}</label>
                        <input class="form-control pos-order-input input-no-shadow"
                               type="number"
                               value="${pkg.posOrder}"
                               readonly>
                    </li>`;
        });

        html += `</ul></div></div>`;
        return html;
    }

    initSortable() {
        $(".sortable-list").sortable({
            handle: ".drag-handle",
            update: (event, ui) => this.updateOrder(ui.item.closest(".sortable-list"))
        }).disableSelection();
    }

    updateOrder(categoryElement) {
        let data = [];
        $(categoryElement).find(".sortable-item").each(function (index) {
            let tourPackageId = $(this).data("id");
            let newOrder = index + 1; // 1-based index
            $(this).find("input").val(newOrder); // Update UI

            data.push({ tourPackageId, posOrder: newOrder });
        });

        this.saveOrder(data);
    }

    saveOrder(updatedPackages) {
        showLoading();
        $.ajax({
            url: this.updateSettingsUrl,
            method: "POST",
            data: JSON.stringify({ tourPackages: updatedPackages }),
            contentType: "application/json",
            success: () => this.showSuccessMessage("Order updated successfully"),
            error: (jqXHR) => showSubmissionError(jqXHR),
            complete: hideLoading
        });
    }
}

const initTourBookingPos = new TourBookingPos({ countryCode: countryCode, admin: userType, categoryId: categoryId, stripePublicKey: stripePublicKey, minMaxPaxApply: minMaxPaxApply, customerInfoMandatory: customerInfoMandatory, managerRequestEnableDiscount: managerRequestEnableDiscount, allowedCancelOrderPermission: allowedCancelOrderPermission, allowedRefundOrderPermission: allowedRefundOrderPermission, serverDatenow: serverDatenow, stripe: stripe });
