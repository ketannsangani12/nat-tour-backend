<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        /* ============================================
           PROFESSIONAL PDF DESIGN WITH SUBTLE CANYON ACCENTS
           Clean, corporate aesthetic with warm touches
           ============================================ */
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #2C3E50;
            margin: 0;
            padding: 20px;
            background: #FFFFFF;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #E67E22;
            padding-bottom: 15px;
            background: #FFFFFF;
            padding: 20px;
        }
        
        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #2C3E50;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #34495E;
        }
        
        .report-info {
            font-size: 11px;
            color: #5D6D7E;
            line-height: 1.6;
        }
        
        .report-info strong {
            color: #2C3E50;
        }
        
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2C3E50;
            margin-bottom: 10px;
            border-left: 4px solid #E67E22;
            padding: 8px 12px;
            background: #F8F9FA;
        }
        
        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            border: 1px solid #E0E0E0;
        }
        
        .summary-row {
            display: table-row;
        }
        
        .summary-cell {
            display: table-cell;
            padding: 10px 12px;
            border-bottom: 1px solid #E0E0E0;
        }
        
        .summary-cell.label {
            font-weight: 700;
            width: 50%;
            background-color: #F8F9FA;
            color: #2C3E50;
        }
        
        .summary-cell.value {
            text-align: right;
            width: 50%;
            background-color: #FFFFFF;
            color: #2C3E50;
            font-weight: 700;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #E0E0E0;
        }
        
        th, td {
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #E0E0E0;
        }
        
        th {
            background: #34495E;
            color: white;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            font-weight: 500;
        }
        
        td strong {
            font-weight: 700;
        }
        
        tr:nth-child(even) {
            background-color: #F8F9FA;
        }
        
        tr:nth-child(odd) {
            background-color: #FFFFFF;
        }
        
        tbody tr:hover {
            background-color: #F0F0F0;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .filters {
            background: #F8F9FA;
            padding: 12px 15px;
            border-left: 3px solid #E67E22;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .filters-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #2C3E50;
            font-size: 12px;
        }
        
        .filter-item {
            margin-bottom: 4px;
            color: #5D6D7E;
            font-size: 10px;
        }
        
        .filter-item strong {
            color: #34495E;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #7F8C8D;
            border-top: 2px solid #E0E0E0;
            padding-top: 12px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .currency {
            text-align: right;
            font-weight: 700;
            color: #2C3E50;
        }
        
        .currency strong {
            font-weight: 700;
            color: #2C3E50;
        }
        
        .percentage {
            text-align: center;
            font-weight: 600;
            color: #E67E22;
        }
        
        .highlight {
            background: #E67E22 !important;
            color: white !important;
            font-weight: bold;
        }
        
        .no-data {
            text-align: center;
            font-style: italic;
            color: #7F8C8D;
            padding: 20px;
        }
        
        h4 {
            color: #34495E;
            font-size: 12px;
            margin: 10px 0 8px 0;
            font-weight: bold;
            border-left: 3px solid #E67E22;
            padding-left: 8px;
        }
        
        strong {
            color: #2C3E50;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        @if(!empty($branding['logo_url']))
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['company_name'] }}" style="max-width: 150px; max-height: 80px; height: auto;">
        </div>
        @endif
        <div class="company-name">{{ $branding['company_name'] }}</div>
        <div class="report-title">{{ $title }}</div>
        <div class="report-info">
            <strong>Date Range:</strong> {{ $dateRange }}<br>
            <strong>Generated:</strong> {{ $generatedAt }}<br>
            <strong>Currency:</strong> {{ $currency }}
        </div>
    </div>

    <!-- Applied Filters Section -->
    @if(($includeFilters ?? true) && !empty($filters))
    <div class="section">
        <div class="filters">
            <div class="filters-title">Applied Filters:</div>
            @foreach($filters as $filter => $value)
            <div class="filter-item"><strong>{{ $filter }}:</strong> {{ $value }}</div>
            @endforeach
        </div>
    </div>
    @endif

    @if($includeCharts ?? true)
    <!-- Executive Summary Section -->
    <div class="section">
        <div class="section-title">Executive Summary</div>
        <div class="summary-grid">
            <div class="summary-row">
                <div class="summary-cell label">Total Bookings</div>
                <div class="summary-cell value">{{ number_format($summary['totalBookings']) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell label">Total Tourists</div>
                <div class="summary-cell value">{{ number_format($summary['totalPax']) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell label">Total Revenue</div>
                <div class="summary-cell value">${{ number_format($summary['totalRevenue'], 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell label">Total Refunds</div>
                <div class="summary-cell value">${{ number_format($summary['totalRefunds'], 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell label">Total Tax</div>
                <div class="summary-cell value">${{ number_format($summary['totalTax'], 2) }}</div>
            </div>
            @if(($summary['totalDisputes'] ?? 0) > 0)
            <div class="summary-row">
                <div class="summary-cell label">Dispute Reversals</div>
                <div class="summary-cell value">${{ number_format($summary['totalDisputes'], 2) }}</div>
            </div>
            @endif
            <div class="summary-row">
                <div class="summary-cell label highlight">Net Revenue</div>
                <div class="summary-cell value highlight">${{ number_format($summary['netRevenue'] - ($summary['totalDisputes'] ?? 0), 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell label">Average Booking Value</div>
                <div class="summary-cell value">${{ number_format($summary['averageBookingValue'], 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Payment Types Breakdown -->
    @if(isset($data['paymentTypes']) && !empty($data['paymentTypes']))
    <div class="section">
        <div class="section-title">Payment Type Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th>Payment Type</th>
                    <th class="text-center">Bookings</th>
                    <th class="text-center">Tourists</th>
                    <th class="text-right">Total Amount</th>
                    <th class="text-right">Refunds</th>
                    <th class="text-right">Net Amount</th>
                    <th class="text-center">% of Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['paymentTypes'] as $type => $typeData)
                @php
                    $percentage = $summary['totalRevenue'] > 0 ? 
                        round(($typeData['total_amount'] / $summary['totalRevenue']) * 100, 1) : 0;
                @endphp
                <tr>
                    <td><strong>{{ $type }}</strong></td>
                    <td class="text-center">{{ number_format($typeData['booking_count']) }}</td>
                    <td class="text-center">{{ number_format($typeData['total_pax']) }}</td>
                    <td class="currency">${{ number_format($typeData['total_amount'], 2) }}</td>
                    <td class="currency">${{ number_format($typeData['refund_amount'], 2) }}</td>
                    <td class="currency"><strong>${{ number_format($typeData['net_amount'], 2) }}</strong></td>
                    <td class="percentage">{{ $percentage }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Affiliate Performance -->
    @if(isset($data['affiliateData']) && !empty($data['affiliateData']))
    <div class="section page-break">
        <div class="section-title">Affiliate Performance</div>
        <table>
            <thead>
                <tr>
                    <th>Affiliate Name</th>
                    <th class="text-center">Bookings</th>
                    <th class="text-center">Tourists</th>
                    <th class="text-right">Total Amount</th>
                    <th class="text-right">Refund Amount</th>
                    <th class="text-right">Fee Amount</th>
                    <th class="text-right">Net Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['affiliateData'] as $affiliate)
                <tr>
                    <td><strong>{{ $affiliate['name'] }}</strong></td>
                    <td class="text-center">{{ number_format($affiliate['booking_count']) }}</td>
                    <td class="text-center">{{ number_format($affiliate['total_pax']) }}</td>
                    <td class="currency">${{ number_format($affiliate['total_amount'], 2) }}</td>
                    <td class="currency">${{ number_format($affiliate['refund_amount'], 2) }}</td>
                    <td class="currency">${{ number_format($affiliate['fee_amount'], 2) }}</td>
                    <td class="currency"><strong>${{ number_format($affiliate['net_amount'], 2) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Third Party Platform Performance -->
    @if(isset($data['thirdPartyData']) && !empty($data['thirdPartyData']))
    <div class="section">
        <div class="section-title">Third Party Platform Performance</div>
        <table>
            <thead>
                <tr>
                    <th>Platform</th>
                    <th class="text-center">Bookings</th>
                    <th class="text-center">Tourists</th>
                    <th class="text-right">Total Amount</th>
                    <th class="text-right">Avg Booking Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['thirdPartyData'] as $platform)
                @php
                    $avgBookingValue = $platform['booking_count'] > 0 ? 
                        $platform['total_amount'] / $platform['booking_count'] : 0;
                @endphp
                <tr>
                    <td><strong>{{ $platform['name'] }}</strong></td>
                    <td class="text-center">{{ number_format($platform['booking_count']) }}</td>
                    <td class="text-center">{{ number_format($platform['total_pax']) }}</td>
                    <td class="currency">${{ number_format($platform['total_amount'], 2) }}</td>
                    <td class="currency">${{ number_format($avgBookingValue, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Operational Metrics -->
    @if(isset($data['operationalMetrics']))
    <div class="section">
        <div class="section-title">Operational Metrics</div>
        
        <!-- Check-in Status -->
        @if(isset($data['operationalMetrics']['check_in_status']))
        <div style="margin-bottom: 20px;">
            <h4>Check-in Status Breakdown</h4>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th class="text-center">Bookings</th>
                        <th class="text-center">Tourists</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['operationalMetrics']['check_in_status'] as $status => $statusData)
                    <tr>
                        <td><strong>{{ $status }}</strong></td>
                        <td class="text-center">{{ number_format($statusData['count']) }}</td>
                        <td class="text-center">{{ number_format($statusData['pax']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Waiver Status -->
        @if(isset($data['operationalMetrics']['waiver_status']))
        <div>
            <h4>Waiver Status Breakdown</h4>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th class="text-center">Bookings</th>
                        <th class="text-center">Tourists</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['operationalMetrics']['waiver_status'] as $status => $statusData)
                    <tr>
                        <td><strong>{{ ucfirst(str_replace('_', ' ', $status)) }}</strong></td>
                        <td class="text-center">{{ number_format($statusData['count']) }}</td>
                        <td class="text-center">{{ number_format($statusData['pax']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endif
    @endif

    <!-- Footer -->
    <div class="footer">
        <div><strong>Report generated by {{ $branding['company_name'] }}</strong></div>
        <div>Generated on {{ $generatedAt }}</div>
        @if($branding['website'])
        <div>{{ $branding['website'] }}</div>
        @endif
    </div>
</body>
</html>