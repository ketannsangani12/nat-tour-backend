<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Receipt Print | {{ config('app.name') }}</title>
    <link href="{{ asset('images/favicon.png') }}" rel="icon">
    @if(App::environment('local'))
        <link rel="stylesheet" href="{{ asset('plugins/bootstrap-5.1.1/css/bootstrap.min.css') }}">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    @endif
    <link href="{{ asset('plugins/font-awesome-5.15.4/css/all.min.css') }}" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        /* Adjust for iframe/modal display */
        @media (max-width: 520px) {
            .row { flex-direction: column; align-items: center !important; }
            .status-card { margin-bottom: 20px; }
        }
        .status-card { max-width: 400px; }
        .spinner-border { width: 3rem; height: 3rem; }
        .receipt-preview {
            width: 300px;
            background: #fff;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        .receipt-preview .header { text-align: center; font-weight: bold; font-size: 16px; margin-bottom: 5px; }
        .receipt-preview .subheader { text-align: center; font-size: 11px; color: #666; }
        .receipt-preview .divider { border-top: 1px dashed #333; margin: 10px 0; }
        .receipt-preview .line { display: flex; justify-content: space-between; }
        .receipt-preview .line-item { margin: 2px 0; }
        .receipt-preview .total-line { font-weight: bold; font-size: 14px; margin-top: 5px; }
        .receipt-preview .center { text-align: center; }
        .receipt-preview .bold { font-weight: bold; }
        .receipt-preview .logo { text-align: center; margin-bottom: 10px; }
        .receipt-preview .logo img { max-width: 150px; max-height: 60px; }
        .receipt-preview.hide-prices .price-section { display: none; }
        .print-options { margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
        .receipt-preview .loading-placeholder { text-align: center; padding: 40px 0; color: #999; }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-start mt-4">
            <!-- Status Card -->
            <div class="col-auto">
                <div class="status-card card shadow">
                    <div class="card-body text-center p-4">
                        <div id="print-status">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h5>Preparing receipt...</h5>
                            <p class="text-muted" id="status-message">Loading receipt data...</p>
                        </div>
                        <div id="print-success" class="d-none">
                            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                            <h5>Receipt sent to printer!</h5>
                            <p class="text-muted">You can close this window.</p>
                        </div>
                        <div id="print-error" class="d-none">
                            <i class="fas fa-exclamation-circle text-warning fa-4x mb-3"></i>
                            <h5 id="error-title">Printer not configured</h5>
                            <p class="text-muted" id="error-message">Falling back to browser print...</p>
                            <a href="{{ $fallbackPrintRoute }}" target="_blank" class="btn btn-outline-secondary btn-sm mt-2 d-none" id="fallback-link">
                                <i class="fas fa-external-link-alt me-1"></i> Open Print Page
                            </a>
                        </div>

                        <div class="print-options d-none" id="print-options">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="hide-prices-checkbox">
                                <label class="form-check-label" for="hide-prices-checkbox">Print without prices</label>
                            </div>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <button type="button" class="btn btn-primary btn-sm" id="btn-print-now">
                                    <i class="fas fa-print me-1"></i> Print
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-print-both">
                                    <i class="fas fa-copy me-1"></i> Print Combo
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-close-modal">
                                    <i class="fas fa-times me-1"></i> Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Preview -->
            <div class="col-auto">
                <p class="text-muted text-center mb-2"><small>Receipt Preview</small></p>
                <div class="receipt-preview" id="receipt-preview">
                    <div class="loading-placeholder">
                        <i class="fas fa-spinner fa-spin mb-2"></i>
                        <div>Loading receipt...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const BOOKING_ID = {{ $bookingM->id }};
        const API_URL = '{{ route(prefixedRouteName("tour-operator.booking.receipt-print-data"), $bookingM->id) }}?logoBase64=false';
        const FALLBACK_URL = @json($fallbackPrintRoute);
        let BOOKING_DATA = null;
        let CURRENCY_SYMBOL = '$';

        function stripCurrencySymbol(value) {
            if (typeof value === 'string') {
                return value.replace(/^[^0-9.-]+/, '');
            }
            return value;
        }

        function parseMoneyValue(value) {
            return parseFloat(stripCurrencySymbol(value)) || 0;
        }

        function formatMoney(amount) {
            return CURRENCY_SYMBOL + parseFloat(stripCurrencySymbol(amount)).toFixed(2);
        }

        function renderReceiptPreview(data) {
            const preview = document.getElementById('receipt-preview');
            let html = '';

            // Set currency symbol from API
            if (data.currencySymbol) {
                CURRENCY_SYMBOL = data.currencySymbol;
            }

            const tourOperator = data.tourOperator || {};
            const customer = data.customer || {};
            const tour = data.tour || {};

            // Logo
            if (tourOperator.logoBase64 || tourOperator.logo) {
                html += `<div class="logo">
                    <img src="${tourOperator.logoBase64 || tourOperator.logo}" alt="${tourOperator.name || ''}" id="receipt-logo">
                </div>`;
            }


            // Tour Operator Header
            html += `<div class="header">${tourOperator.name || ''}</div>`;
            if (tourOperator.email) html += `<div class="subheader">${tourOperator.email}</div>`;
            if (tourOperator.phone) html += `<div class="subheader">${tourOperator.phone}</div>`;
            if (tourOperator.website) html += `<div class="subheader">${tourOperator.website}</div>`;

            html += '<div class="divider"></div>';

            // Booking info
            html += `<div class="bold">Booking: #${data.bookingId || ''}</div>`;
            html += `<div>${customer.name || ''}</div>`;
            if (customer.email) html += `<div>${customer.email}</div>`;
            html += `<div class="bold" style="margin-top: 5px;">${tour.package || ''}</div>`;
            html += `<div>${tour.dateTime || ''}</div>`;

            // Rate groups with price breakdown
            if (data.rateGroups && Array.isArray(data.rateGroups) && data.rateGroups.length > 0) {
                for (let i = 0; i < data.rateGroups.length; i++) {
                    const group = data.rateGroups[i];
                    html += '<div class="divider"></div>';
                    html += `<div class="bold">${group.count || 0}: ${group.label || 'Guest'}</div>`;
                    html += '<div class="divider"></div>';
                    html += `<div class="line"><span>Price</span><span>${group.priceTotal || formatMoney(0)}</span></div>`;
                    html += `<div class="line"><span>Permit Fee</span><span>${group.permitFeeTotal || formatMoney(0)}</span></div>`;
                    html += `<div class="line"><span>${group.taxLabel || 'Tax'}</span><span>${group.taxTotal || formatMoney(0)}</span></div>`;
                }
            }

            // Price section
            html += '<div class="price-section">';
            html += '<div class="divider"></div>';

            const pricing = data.pricing || {};
            html += `<div class="line"><span>Ticket Cost:</span><span>${pricing.ticketCost || formatMoney(0)}</span></div>`;

            // Discount (if any)
            if (parseMoneyValue(pricing.discount) > 0) {
                const discountLabel = parseFloat(pricing.discountPercentage) > 0
                    ? `Discount (${pricing.discountPercentage}%):`
                    : 'Discount:';
                html += `<div class="line"><span>${discountLabel}</span><span>-${pricing.discount}</span></div>`;
            }

            // Subtotal (after discount)
            const subtotalAfterDiscount = parseMoneyValue(pricing.ticketCost) - parseMoneyValue(pricing.discount);
            html += `<div class="line"><span>Subtotal:</span><span>${formatMoney(subtotalAfterDiscount)}</span></div>`;

            // Custom Charge (if any)
            if (parseMoneyValue(pricing.customCharge) > 0) {
                html += `<div class="line"><span>Custom Charge:</span><span>${pricing.customCharge}</span></div>`;
            }

            if (parseMoneyValue(pricing.fee) > 0) {
                html += `<div class="line"><span>Fee:</span><span>${pricing.fee}</span></div>`;
            }
            html += `<div class="line"><span>Total Cost:</span><span>${pricing.totalCost || formatMoney(0)}</span></div>`;
            html += `<div class="line"><span>Refund Amount:</span><span>${pricing.refundAmount || formatMoney(0)}</span></div>`;
            html += `<div class="line total-line"><span>Payment Total:</span><span>${pricing.paymentTotal || formatMoney(0)}</span></div>`;

            html += '<div class="divider"></div>';

            // Payment method
            html += `<div class="center">${data.paymentMethod || 'N/A'}</div>`;

            // Payment history
            if (data.payments && Array.isArray(data.payments) && data.payments.length > 0) {
                html += '<div class="divider"></div>';
                for (let i = 0; i < data.payments.length; i++) {
                    const payment = data.payments[i];
                    html += `<div style="font-size: 10px;">${payment.dateTime || ''} ${payment.amount || formatMoney(0)} ${payment.method || 'N/A'}</div>`;
                }
            }

            html += '</div>';

            preview.innerHTML = html;
        }

        // Convert S3 URL to proxy URL (bypasses CORS)
        function s3UrlToProxyUrl(s3Url) {
            try {
                const urlObj = new URL(s3Url);
                let path = urlObj.pathname;
                if (path.startsWith('/')) {
                    path = path.substring(1);
                }
                return '/api/v1/image/proxy?path=' + encodeURIComponent(path);
            } catch (e) {
                console.warn('s3UrlToProxyUrl error:', e);
                return null;
            }
        }

        async function fetchReceiptData() {
            try {
                const response = await fetch(API_URL);
                const result = await response.json();

                console.log('API Response:', result);

                if (result.success && result.data) {
                    const d = result.data;
                    const tourOp = d.tourOperator || {};
                    const cust = d.customer || {};
                    const tour = d.tour || {};

                    // Set currency symbol from API
                    if (d.currencySymbol) {
                        CURRENCY_SYMBOL = d.currencySymbol;
                    }

                    // Convert S3 logo URL to proxy URL (no CORS issues)
                    let logoUrl = tourOp.logoBase64 || null;
                    if (!logoUrl && tourOp.logo) {
                        logoUrl = s3UrlToProxyUrl(tourOp.logo);
                        console.log('Using proxy URL for logo:', logoUrl);
                    }

                    // Map to BOOKING_DATA for thermal printer
                    BOOKING_DATA = {
                        bookingId: d.bookingId || '',
                        currencySymbol: d.currencySymbol || '$',
                        tourOperator: tourOp.name || '',
                        tourOperatorLogo: logoUrl,
                        tourOperatorLogoPath: tourOp.logo || null,
                        tourOperatorEmail: tourOp.email || null,
                        tourOperatorPhone: tourOp.phone || null,
                        tourOperatorWebsite: tourOp.website || null,
                        customer: cust.name || '',
                        customerEmail: cust.email || null,
                        tourPackage: tour.package || '',
                        dateTime: tour.dateTime || '',
                        isGroupBooking: d.isGroupBooking || false,
                        rateGroups: Array.isArray(d.rateGroups) ? d.rateGroups : [],
                        pricing: d.pricing || {},
                        paymentMethod: d.paymentMethod || 'N/A',
                        payments: Array.isArray(d.payments) ? d.payments : [],
                        status: d.status || '',
                        createdAt: d.createdAt || ''
                    };
                    console.log('BOOKING_DATA mapped:', BOOKING_DATA);

                    renderReceiptPreview(result.data);
                    return true;
                } else {
                    console.error('API Error:', result.message);
                    return false;
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                return false;
            }
        }
    </script>

    <script src="{{ asset('js/vendor/epos-2.27.0.js') }}"></script>
    <script>
    class ReceiptPrinter {
        constructor() {
            this.ePosDev = null;
            this.printer = null;
            this.config = null;
            this.isConnected = false;
            this.STORAGE_KEY = 'epsonPrinterConfig';
            this.isInIframe = window.self !== window.top;
            this.dataLoaded = false;
        }

        initEventListeners() {
            // Toggle prices in preview
            document.getElementById('hide-prices-checkbox').addEventListener('change', (e) => {
                const preview = document.querySelector('.receipt-preview');
                if (e.target.checked) {
                    preview.classList.add('hide-prices');
                } else {
                    preview.classList.remove('hide-prices');
                }
            });

            // Print button
            document.getElementById('btn-print-now').addEventListener('click', () => {
                if (this.isConnected && this.printer) {
                    this.printReceipt();
                } else {
                    alert('Printer not connected. Please wait for connection or check printer settings.');
                }
            });

            // Print Both button - prints with prices then without prices
            document.getElementById('btn-print-both').addEventListener('click', () => {
                if (this.isConnected && this.printer) {
                    this.printBoth();
                } else {
                    alert('Printer not connected. Please wait for connection or check printer settings.');
                }
            });

            // Close button - send message to parent to close modal
            document.getElementById('btn-close-modal').addEventListener('click', () => {
                if (this.isInIframe) {
                    window.parent.postMessage({ action: 'closeReceiptPrintModal' }, '*');
                } else {
                    window.close();
                }
            });
        }

        updateStatus(message) {
            document.getElementById('status-message').textContent = message;
        }

        showReady() {
            document.getElementById('print-status').classList.add('d-none');
            document.getElementById('print-options').classList.remove('d-none');
        }

        showSuccess() {
            // Don't hide options - allow multiple prints
            const statusEl = document.getElementById('status-message');
            if (statusEl) {
                statusEl.textContent = 'Receipt printed successfully!';
                statusEl.classList.add('text-success');
                setTimeout(() => {
                    statusEl.textContent = 'Ready to print';
                    statusEl.classList.remove('text-success');
                }, 2000);
            }
        }

        showError(title, message) {
            document.getElementById('print-status').classList.add('d-none');
            document.getElementById('print-error').classList.remove('d-none');
            document.getElementById('error-title').textContent = title;
            document.getElementById('error-message').textContent = message;
        }

        fallbackToBrowserPrint() {
            if (this.isInIframe) {
                // In iframe (modal) - show link instead of redirecting
                this.showError('Printer not configured', 'Use the link below to open the print page.');
                document.getElementById('fallback-link').classList.remove('d-none');
            } else {
                // Standalone page - redirect
                this.showError('Printer not configured', 'Redirecting to browser print...');
                setTimeout(() => {
                    window.location.href = FALLBACK_URL;
                }, 1500);
            }
        }

        loadConfig() {
            const saved = localStorage.getItem(this.STORAGE_KEY);
            if (!saved) return null;
            try {
                return JSON.parse(saved);
            } catch (e) {
                return null;
            }
        }
        
        async init() {
            // First fetch receipt data from API
            this.updateStatus('Loading receipt data...');
            const dataLoaded = await fetchReceiptData();

            if (!dataLoaded) {
                this.showError('Data Error', 'Failed to load receipt data.');
                return;
            }

            this.dataLoaded = true;
            this.initEventListeners();

            this.config = this.loadConfig();

            if (!this.config || !this.config.printerIP) {
                this.fallbackToBrowserPrint();
                return;
            }

            this.updateStatus('Connecting to printer...');

            if (typeof epson === 'undefined' || typeof epson.ePOSDevice === 'undefined') {
                this.showError('SDK Error', 'Epson SDK not loaded. Falling back to browser print...');
                setTimeout(() => this.fallbackToBrowserPrint(), 2000);
                return;
            }

            this.connect();
        }

        connect() {
            const port = this.config.protocol === 'https' ? 8043 : 8008;

            try {
                this.ePosDev = new epson.ePOSDevice();

                this.ePosDev.connect(this.config.printerIP, port, (data) => {
                    if (data === 'OK' || data === 'SSL_CONNECT_OK') {
                        this.updateStatus('Creating printer device...');
                        this.ePosDev.createDevice('local_printer', this.ePosDev.DEVICE_TYPE_PRINTER,
                            { crypto: false, buffer: false },
                            (deviceObj, errorCode) => {
                                if (errorCode === 'OK') {
                                    this.printer = deviceObj;
                                    this.isConnected = true;
                                    this.updateStatus('Ready to print');
                                    this.showReady();
                                } else {
                                    this.showError('Device Error', `Failed to create printer: ${errorCode}`);
                                    setTimeout(() => this.fallbackToBrowserPrint(), 2000);
                                }
                            }
                        );
                    } else {
                        this.showError('Connection Failed', `Could not connect: ${data}`);
                        setTimeout(() => this.fallbackToBrowserPrint(), 2000);
                    }
                });
            
                this.ePosDev.onerror = (error) => {
                    this.showError('Connection Error', `Error: ${error.status}`);
                    setTimeout(() => this.fallbackToBrowserPrint(), 2000);
                };
            } catch (e) {
                this.showError('Error', e.message);
                setTimeout(() => this.fallbackToBrowserPrint(), 2000);
            }
        }

        formatMoney(amount) {
            const symbol = (BOOKING_DATA && BOOKING_DATA.currencySymbol) ? BOOKING_DATA.currencySymbol : CURRENCY_SYMBOL;
            return symbol + parseFloat(stripCurrencySymbol(amount)).toFixed(2);
        }

        // Load image from URL or base64 and return canvas context for printing
        loadImageToCanvas(src, maxWidth = 200, useCrossOrigin = true) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                if (useCrossOrigin) {
                    img.crossOrigin = 'anonymous';
                }
                img.onload = () => {
                    try {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        const scale = Math.min(1, maxWidth / img.naturalWidth);
                        canvas.width = Math.floor(img.naturalWidth * scale);
                        canvas.height = Math.floor(img.naturalHeight * scale);
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        resolve({ ctx, width: canvas.width, height: canvas.height });
                    } catch (e) {
                        reject(e);
                    }
                };
                img.onerror = () => reject(new Error('Failed to load image'));
                img.src = src;
            });
        }

        async printReceipt(overrideHidePrices = null, onComplete = null) {
            if (!this.printer || !BOOKING_DATA) {
                console.error('printReceipt: printer or BOOKING_DATA not available', { printer: !!this.printer, data: !!BOOKING_DATA });
                return;
            }

            const data = BOOKING_DATA;
            const autoCut = this.config.autoCut !== false;
            const hidePrices = overrideHidePrices !== null ? overrideHidePrices : document.getElementById('hide-prices-checkbox').checked;

            // Debug: log data structure
            console.log('printReceipt data:', JSON.stringify(data, null, 2));

            try {
                this.printer.onreceive = (response) => {
                    if (response.success) {
                        this.showSuccess();
                        if (onComplete && typeof onComplete === 'function') {
                            onComplete();
                        }
                    } else {
                        this.showError('Print Error', `Error code: ${response.code}`);
                    }
                };

                this.printer.onerror = (error) => {
                    this.showError('Printer Error', error.status);
                };

                // Logo: load from proxy URL or base64
                if (data.tourOperatorLogo) {
                    try {
                        const logoData = await this.loadImageToCanvas(data.tourOperatorLogo, 200, false);
                        this.printer.addTextAlign(this.printer.ALIGN_CENTER);
                        this.printer.addImage(logoData.ctx, 0, 0, logoData.width, logoData.height, this.printer.COLOR_1, this.printer.MODE_MONO);
                        this.printer.addFeedLine(1);
                    } catch (logoErr) {
                        console.warn('Could not print logo:', logoErr);
                    }
                }

                // Tour Operator Header
                this.printer.addTextAlign(this.printer.ALIGN_CENTER);
                this.printer.addTextStyle(false, false, true, this.printer.COLOR_1);
                this.printer.addTextSize(2, 2);
                this.printer.addText(data.tourOperator + '\n');
                this.printer.addTextSize(1, 1);
                this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);

                // Tour operator contact
                
                if (data.tourOperatorEmail) this.printer.addText(data.tourOperatorEmail + '\n');
                if (data.tourOperatorPhone) this.printer.addText(data.tourOperatorPhone + '\n');
                if (data.tourOperatorWebsite) this.printer.addText(data.tourOperatorWebsite + '\n');
                this.printer.addFeedLine(1);
                
                this.printer.addTextAlign(this.printer.ALIGN_LEFT);
                // Divider
                const divider = '------------------------------------------\n';  // 42 chars for 80mm thermal paper
                this.printer.addText(divider);

                // Booking Info
                this.printer.addTextAlign(this.printer.ALIGN_LEFT);
                this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);
                this.printer.addText('Booking: #' + data.bookingId + '\n');
                this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);
                this.printer.addText(data.customer + '\n');
                if (data.customerEmail) this.printer.addText(data.customerEmail + '\n');

                // Tour Package
                this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);
                this.printer.addText(data.tourPackage + '\n');
                this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);
                this.printer.addText(data.dateTime + '\n');

                // Rate groups with price breakdown
                if (data.rateGroups && Array.isArray(data.rateGroups) && data.rateGroups.length > 0) {
                    for (let i = 0; i < data.rateGroups.length; i++) {
                        const group = data.rateGroups[i];
                        this.printer.addText(divider);
                        this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);
                        this.printer.addText((group.count || 0) + ': ' + (group.label || 'Guest') + '\n');
                        this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);

                        if (!hidePrices) {
                            this.printer.addText(divider);
                            this.printer.addText(this.padLine('Price', group.priceTotal || this.formatMoney(0)));
                            this.printer.addText(this.padLine('Permit Fee', group.permitFeeTotal || this.formatMoney(0)));
                            this.printer.addText(this.padLine(group.taxLabel || 'Tax', group.taxTotal || this.formatMoney(0)));
                        }
                    }
                }

                // Totals (skip if hiding prices)
                if (!hidePrices && data.pricing) {
                    this.printer.addText(divider);
                    this.printer.addText(this.padLine('Ticket Cost:', data.pricing.ticketCost || this.formatMoney(0)));

                    // Discount (if any)
                    if (parseMoneyValue(data.pricing.discount) > 0) {
                        const discountLabel = parseFloat(data.pricing.discountPercentage) > 0
                            ? 'Discount (' + data.pricing.discountPercentage + '%):'
                            : 'Discount:';
                        this.printer.addText(this.padLine(discountLabel, '-' + data.pricing.discount));
                    }

                    // Subtotal (after discount)
                    const subtotalAfterDiscount = parseMoneyValue(data.pricing.ticketCost) - parseMoneyValue(data.pricing.discount);
                    this.printer.addText(this.padLine('Subtotal:', this.formatMoney(subtotalAfterDiscount)));

                    // Custom Charge (if any)
                    if (parseMoneyValue(data.pricing.customCharge) > 0) {
                        this.printer.addText(this.padLine('Custom Charge:', data.pricing.customCharge));
                    }

                    if (parseMoneyValue(data.pricing.fee) > 0) {
                        this.printer.addText(this.padLine('Fee:', data.pricing.fee));
                    }
                    this.printer.addText(this.padLine('Total Cost:', data.pricing.totalCost || this.formatMoney(0)));
                    this.printer.addText(this.padLine('Refund Amount:', data.pricing.refundAmount || this.formatMoney(0)));

                    this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);
                    this.printer.addText(this.padLine('Payment Total:', data.pricing.paymentTotal || this.formatMoney(0)));
                    this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);

                    this.printer.addText(divider);

                    // Payment method
                    this.printer.addTextAlign(this.printer.ALIGN_LEFT);
                    this.printer.addText((data.paymentMethod || 'N/A') + '\n');

                    // Payment history
                    if (data.payments && Array.isArray(data.payments) && data.payments.length > 0) {
                        this.printer.addText(divider);
                        this.printer.addTextAlign(this.printer.ALIGN_LEFT);
                        for (let i = 0; i < data.payments.length; i++) {
                            const payment = data.payments[i];
                            this.printer.addText((payment.dateTime || '') + ' ' + (payment.amount || this.formatMoney(0)) + ' ' + (payment.method || 'N/A') + '\n');
                        }
                    }
                }

                this.printer.addFeedLine(3);

                // Cut paper
                if (autoCut) {
                    this.printer.addCut(this.printer.CUT_FEED);
                }

                // Send to printer
                this.printer.send();

            } catch (e) {
                this.showError('Print Error', e.message);
                setTimeout(() => this.fallbackToBrowserPrint(), 2000);
            }
        }

        // Helper to pad text for thermal printer alignment
        padLine(label, value) {
            const totalWidth = 42;  // 80mm thermal paper = 42 chars with Font A
            const padding = totalWidth - label.length - value.length;
            return label + ' '.repeat(Math.max(1, padding)) + value + '\n';
        }

        // Print both with prices and without prices
        printBoth() {
            if (!this.printer) return;

            this.updateStatus('Printing with prices...');
            // First print with prices, then print without prices
            this.printReceipt(false, () => {
                this.updateStatus('Printing without prices...');
                // Small delay between prints
                setTimeout(() => {
                    this.printReceipt(true, () => {
                        this.updateStatus('Both receipts printed!');
                    });
                }, 500);
            });
        }
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        const printer = new ReceiptPrinter();
        printer.init();
    });
    </script>
</body>
</html>
