@php
$routePrefix = routePrefix();
$userRole = checkUserRole(); 
$currencySettings = [
'code' => currencyFormation(true, false),
'symbol' => currencyFormation(false, true),
'separator' => ',',
'decimal' => '.',
'precision' => 2,
];
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
<script>
    // We pre-filled your app ID in the widget URL: 'https://widget.intercom.io/widget/o658qwaz'
    (function() {
        var w = window;
        var ic = w.Intercom;
        if (typeof ic === "function") {
            ic('reattach_activator');
            ic('update', w.intercomSettings);
        } else {
            var d = document;
            var i = function() {
                i.c(arguments);
            };
            i.q = [];
            i.c = function(args) {
                i.q.push(args);
            };
            w.Intercom = i;
            var l = function() {
                var s = d.createElement('script');
                s.type = 'text/javascript';
                s.async = true;
                s.src = 'https://widget.intercom.io/widget/o658qwaz';
                var x = d.getElementsByTagName('script')[0];
                x.parentNode.insertBefore(s, x);
            };
            if (document.readyState === 'complete') {
                l();
            } else if (w.attachEvent) {
                w.attachEvent('onload', l);
            } else {
                w.addEventListener('load', l, false);
            }
        }
    })();
</script>
    <meta charset="UTF-8">
    <meta name="google-site-verification" content="Mh5XbOYqjxpvzLMc-VEuY2gstDIjS4_7yReDuyT0ob4" />
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-223629951-1"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', 'UA-223629951-1');
    </script>

    <!-- Google Tag Manager -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-W4CHJCP');
    </script><!-- End Google Tag Manager -->

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $page_title }} | {{ config('app.name') }}</title>
    <link href="{{ asset('images/favicon.png') }}" rel="icon">


    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    @yield('css-libraries')


    <link href="{{ asset('css/tip-center.css') }}" rel="stylesheet">

    @yield('css-custom')

    <script>
        var rootPath = "{{ asset('') }}";

        function noPhoto(elImg) {
            elImg.onerror = null;
            elImg.src = (typeof rootPath == 'undefined' ? '/' : rootPath) + 'images/no-photo.png';
        }
    </script>
</head>

<body>
    <!-- Google Tag Manager (noscript) --><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W4CHJCP" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript><!-- End Google Tag Manager (noscript) -->

    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold fs-3 text-primary" href="#">
                <i class="fas fa-coins me-2"></i>Tip Center
            </a>
            <div class="d-flex align-items-center">
                <i class="fas fa-bell text-muted me-3 fs-5 cursor-pointer"></i>
                <i class="fas fa-user-circle text-muted fs-5 cursor-pointer"></i>
            </div>
        </div>
    </nav>

    <!-- Navigation Tabs -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <ul class="nav nav-tabs border-0" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                <a class="nav-link {{ Request::routeIs($routePrefix.'.tip-center') ? 'active' : '' }} custom-tab" href="{{ route($routePrefix . '.tip-center') }}" id="dashboard-tab">
                    <i class="fas fa-chart-bar me-2"></i> Dashboard
                </a>
                </li>
                @if(userType() != 'Admin' && $userRole == 'tour-guide')
                <li class="nav-item" role="presentation">
                    <a class="nav-link custom-tab {{ Request::routeIs($routePrefix.'.tip-center.bookings') ? 'active' : '' }}" id="bookings-tab" href="{{ route($routePrefix . '.tip-center.bookings') }}">
                        <i class="fas fa-calendar-check me-2"></i>Bookings
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                <a class="nav-link {{ Request::routeIs($routePrefix.'.tip-center.create-tip') ? 'active' : '' }} custom-tab" href="{{ route($routePrefix . '.tip-center.create-tip') }}" id="profile-tab">
                    <i class="fa-solid fa-money-bill-wave me-2"></i> Create Tip
                </a>
                </li>
                <li class="nav-item" role="presentation">
                <a class="nav-link {{ Request::routeIs($routePrefix.'.tip-center.profile') ? 'active' : '' }} custom-tab" href="{{ route($routePrefix . '.tip-center.profile') }}" id="profile-tab">
                    <i class="fas fa-user me-2"></i> Profile
                </a>
                </li>
                @endif
                @if($userRole == 'staff' || userType() == 'Admin')
                <li class="nav-item" role="presentation">
                    <a class="nav-link custom-tab {{ Request::routeIs($routePrefix.'.tip-center.settings') ? 'active' : '' }}" id="configuration-tab" href="{{ route($routePrefix . '.tip-center.settings') }}">
                        <i class="fas fa-cog me-2"></i>Gratuity Settings
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link custom-tab {{ Request::routeIs($routePrefix.'.tip-center.guides') ? 'active' : '' }}" id="guides-tab" href="{{ route($routePrefix . '.tip-center.guides') }}">
                        <i class="fas fa-users me-2"></i>Guide Management
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link custom-tab {{ Request::routeIs($routePrefix.'.tip-center.bookings') ? 'active' : '' }}" id="guides-tab" href="{{ route($routePrefix . '.tip-center.bookings') }}">
                        <i class="fas fa-calendar-check me-2"></i>Bookings
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link custom-tab {{ Request::routeIs($routePrefix.'.tip-center.distributions') ? 'active' : '' }}" id="distribution-tab" href="{{ route($routePrefix . '.tip-center.distributions') }}">
                        <i class="fas fa-share-alt me-2"></i>Distribution
                    </a>
                </li>
                @endif

            </ul>
        </div>
    </nav>
    <input type="hidden" id="currency" value='@json($currencySettings)'>

    @yield('main-content')

    <div class="loading-container" id="loading-container">
        <img class='loading-img' src="{{ asset('images/loading.gif') }}">
    </div>

    @if(App::environment('local'))
    <script src="{{ asset('plugins/jquery-3.5.1.min.js') }}"></script>
    <script src="{{ asset('plugins/bootstrap-5.1.1/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('plugins/sweetalert-2.1.2.min.js') }}"></script>
    <script src="{{ asset('plugins/currency.min.js') }}"></script>
    @else
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
    <script src="https://unpkg.com/currency.js@2.0.4/dist/currency.min.js"></script>
    @endif

    <script src="{{ asset('plugins/dark-editable.js') }}"></script>
    <script src="{{ asset('js/utils/currency-setup.js') }}"></script>

    <script type="text/javascript">
        const currencyFormation = JSON.parse(document.getElementById('currency').value);
        setGlobalCurrencyOptions(currencyFormation);
    </script>

    @yield('js-libraries')

    <x-custom-js />

    @yield('js-custom')

</body>

</html>