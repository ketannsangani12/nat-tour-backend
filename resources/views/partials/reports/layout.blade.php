<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page_title }} | {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <link href="{{ asset('images/favicon.png') }}" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('plugins/font-awesome-5.15.4/css/all.min.css') }}" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- Date Picker CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <!-- Chart.js CSS -->
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    
    <style>
        /* Canyon Color Palette */
        :root {
            --canyon-orange: #E67E22;
            --canyon-rust: #D35400;
            --canyon-sand: #F39C12;
            --canyon-terracotta: #CA6F1E;
            --sand-light: #FDF6E3;
            --sand-medium: #F4E8D0;
            --text-primary: #3E2723;
            --text-secondary: #6D4C41;
            --border-color: #E8DCC8;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #FDF8F3 0%, #F4E8D0 100%);
            margin: 0;
            padding: 0;
        }
        
        /* Header Styling - Canyon Theme */
        .report-header {
            background: linear-gradient(135deg, 
                #E67E22 0%,      /* Canyon orange */
                #D35400 50%,     /* Rust red */
                #CA6F1E 100%     /* Terracotta */
            );
            color: white;
            padding: 2rem 0;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 24px rgba(211, 84, 0, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .report-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120"><path d="M0,0 Q300,60 600,30 T1200,0 L1200,120 L0,120 Z" fill="rgba(255,255,255,0.05)"/></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.3;
        }
        
        .report-header .container-fluid {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .report-header .row {
            align-items: center;
        }
        
        .company-logo {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 3px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            padding: 8px;
            flex-shrink: 0;
        }
        
        .company-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .report-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            color: white;
        }
        
        .report-header p {
            font-size: 0.938rem;
            margin: 0.25rem 0 0 0;
            opacity: 0.9;
            line-height: 1.4;
        }
        
        .report-header .btn-outline-light {
            border: 2px solid rgba(255, 255, 255, 0.7);
            color: white;
            font-weight: 600;
            padding: 0.625rem 1.5rem;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-header .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .report-card {
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 16px rgba(230, 126, 34, 0.12);
            margin-bottom: 1.5rem;
            background: white;
        }
        
        .filter-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(230, 126, 34, 0.1);
        }
        
        .summary-card {
            background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
            color: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
            border: none;
            border-radius: var(--radius-sm);
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(230, 126, 34, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--canyon-orange);
            color: var(--canyon-orange);
            border-radius: var(--radius-sm);
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            background: white;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--canyon-orange);
            border-color: var(--canyon-orange);
            color: white;
            transform: translateY(-2px);
        }
        
        .table {
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        
        .table thead th {
            background: linear-gradient(to bottom, var(--sand-light), var(--sand-medium));
            border: none;
            font-weight: 700;
            color: var(--canyon-rust);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .spinner {
            width: 4rem;
            height: 4rem;
            border: 0.4rem solid #f3f3f3;
            border-top: 0.4rem solid var(--canyon-orange);
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: var(--radius-sm);
        }
        
        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--canyon-orange);
            box-shadow: 0 0 0 4px rgba(230, 126, 34, 0.1);
        }
        
        .filter-section-group {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            background: white;
        }
        
        .filter-section-header {
            border-bottom: 2px solid var(--sand-light);
            padding-bottom: 0.875rem;
        }
        
        .filter-section-header h6 {
            font-weight: 700;
            color: var(--canyon-rust);
        }
        
        .btn-group .btn {
            margin: 0 2px 4px 0;
        }
        
        .input-group-text {
            background: linear-gradient(135deg, var(--canyon-sand), var(--canyon-orange));
            border: 2px solid var(--canyon-sand);
            color: white;
            font-weight: 600;
        }
        
        /* Table Styles */
        .table {
            color: var(--text-primary) !important;
        }
        
        .table td, .table th {
            color: var(--text-primary) !important;
            vertical-align: middle;
            padding: 0.75rem;
        }
        
        .table-hover tbody tr:hover {
            background: linear-gradient(135deg, rgba(230, 126, 34, 0.05), rgba(243, 156, 18, 0.05));
        }
        
        .table thead th {
            background: linear-gradient(to bottom, var(--sand-light), var(--sand-medium)) !important;
            color: var(--canyon-rust) !important;
            font-weight: 700;
            border-bottom: 2px solid var(--canyon-sand);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table thead th[data-sort] {
            cursor: pointer;
            user-select: none;
        }
        
        .table thead th[data-sort]:hover {
            background: var(--sand-medium) !important;
        }
        
        .sort-icon {
            font-size: 0.75rem;
            opacity: 0.6;
            color: var(--canyon-orange);
        }
        
        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
        }
        
        .text-dark {
            color: var(--text-primary) !important;
        }
        
        .fw-medium {
            font-weight: 500 !important;
        }
        
        .table-responsive {
            border-radius: var(--radius-lg);
        }
        
        .pagination .page-link {
            color: var(--canyon-orange);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            margin: 0 4px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
            border-color: var(--canyon-orange);
            color: white;
            box-shadow: 0 4px 12px rgba(230, 126, 34, 0.35);
        }
        
        .pagination .page-link:hover {
            background: var(--sand-light);
            border-color: var(--canyon-orange);
            color: var(--canyon-orange);
        }
        
        .dropdown-menu {
            border-radius: var(--radius-md);
            box-shadow: 0 8px 24px rgba(230, 126, 34, 0.2);
            border: 2px solid var(--canyon-sand);
        }
        
        .form-check {
            margin-bottom: 0.5rem;
        }
        
        .form-check-label {
            font-size: 0.875rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .card-footer {
            background: linear-gradient(to top, var(--sand-light), var(--sand-medium));
            border-top: 2px solid var(--border-color);
        }
    </style>
    
    @yield('css')
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="spinner"></div>
    </div>

    <!-- Header -->
    <div class="report-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="company-logo me-3">
                            <img src="{{ tourOperatorNavigationLogo() }}" alt="Company Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 8px;">
                        </div>
                        <div>
                            <h1 class="mb-0">{{ $page_title ?? 'Report' }}</h1>
                            <p class="mb-0 opacity-75">Comprehensive financial analysis and insights</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <a href="{{ url()->previous() }}" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        @yield('content')
    </div>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-white border-top">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0 text-muted">Generated on {{ now()->format('M d, Y \a\t g:i A') }}</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <!-- Global JavaScript Functions -->
    <script>
        // Global loading functions
        function showLoading() {
            $('#loadingOverlay').show();
        }
        
        function hideLoading() {
            $('#loadingOverlay').hide();
        }
        
        // Global currency formatting
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }
        
        // Global number formatting
        function formatNumber(number) {
            return new Intl.NumberFormat('en-US').format(number);
        }
        
        // Global date formatting
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        // CSRF token setup for AJAX
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>
    
    @yield('scripts')
</body>
</html>