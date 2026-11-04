<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Exception;

class ExportService
{
    /**
     * Export to PDF format with company branding
     *
     * @param array $data
     * @param array $filters
     * @return StreamedResponse
     */
    public function exportToPDF(array $data, array $filters): StreamedResponse
    {
        try {
            // Ensure required filter keys exist
            $dateFrom = $filters['dateFrom'] ?? 'unknown';
            $dateTo = $filters['dateTo'] ?? 'unknown';
            
            $fileName = 'payment_breakdown_report_' . $dateFrom . '_' . $dateTo . '.pdf';
            
            // Get company branding information
            $branding = $this->getCompanyBranding();

            // Get export options
            $includeCharts = $filters['includeCharts'] ?? true;
            $includeFilters = $filters['includeFilters'] ?? true;
            $includeBookingDetails = $filters['includeBookingDetails'] ?? false;

            // Prepare data for PDF template
            $pdfData = [
                'title' => 'Payment Breakdown Report',
                'dateRange' => $dateFrom . ' to ' . $dateTo,
                'generatedAt' => now()->format('F j, Y \a\t g:i A'),
                'filters' => $includeFilters ? $this->formatFiltersForDisplay($filters) : [],
                'data' => $data,
                'branding' => $branding,
                'summary' => $this->generatePdfSummary($data),
                'currency' => session('currency', 'USD'),
                'includeCharts' => $includeCharts,
                'includeFilters' => $includeFilters,
                'includeBookingDetails' => $includeBookingDetails
            ];

            // Generate PDF using view template
            $html = View::make('partials.reports.exports.payment-breakdown-pdf', $pdfData)->render();
            
            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'Arial',
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                    'chroot' => public_path(),
                ]);

            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            $callback = function () use ($pdf) {
                echo $pdf->output();
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $exception) {
            Log::error('ExportService::exportToPDF Error: ' . $exception->getMessage());
            
            // Return error PDF
            return $this->generateErrorPDF($exception->getMessage());
        }
    }

    /**
     * Export to CSV format with enhanced formatting
     *
     * @param array $data
     * @param array $filters
     * @return StreamedResponse
     */
    public function exportToCSV(array $data, array $filters): StreamedResponse
    {
        try {
            $fileName = 'payment_breakdown_report_' . $filters['dateFrom'] . '_' . $filters['dateTo'] . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            $callback = function () use ($data, $filters) {
                $file = fopen('php://output', 'w');
                
                // Add BOM for UTF-8 support in Excel
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Get company branding
                $branding = $this->getCompanyBranding();
                
                // Get export options
                $includeFilters = $filters['includeFilters'] ?? true;
                
                // Header information with branding
                fputcsv($file, [$branding['company_name']]);
                fputcsv($file, ['Payment Breakdown Report']);
                fputcsv($file, ['Date Range:', $filters['dateFrom'] . ' to ' . $filters['dateTo']]);
                fputcsv($file, ['Generated:', now()->format('F j, Y \a\t g:i A')]);
                fputcsv($file, ['Currency:', session('currency', 'USD')]);
                fputcsv($file, ['']); // Empty line

                // Applied Filters
                if ($includeFilters) {
                    $appliedFilters = $this->formatFiltersForDisplay($filters);
                    if (!empty($appliedFilters)) {
                        fputcsv($file, ['Applied Filters:']);
                        foreach ($appliedFilters as $filter => $value) {
                            fputcsv($file, [$filter . ':', $value]);
                        }
                        fputcsv($file, ['']); // Empty line
                    }
                }

                // Get includeCharts option
                $includeCharts = $filters['includeCharts'] ?? true;

                if ($includeCharts) {
                    // Executive Summary
                    $summary = $this->generatePdfSummary($data);
                    fputcsv($file, ['Executive Summary']);
                    fputcsv($file, ['Total Bookings:', $summary['totalBookings']]);
                    fputcsv($file, ['Total Tourists:', $summary['totalPax']]);
                    fputcsv($file, ['Total Revenue:', $this->formatCurrency($summary['totalRevenue'])]);
                    fputcsv($file, ['Total Fees:', $this->formatCurrency($summary['totalFees'])]);
                    fputcsv($file, ['Total Refunds:', $this->formatCurrency($summary['totalRefunds'])]);
                    fputcsv($file, ['Total Tax:', $this->formatCurrency($summary['totalTax'])]);
                    if (($summary['totalDisputes'] ?? 0) > 0) {
                        fputcsv($file, ['Dispute Reversals:', $this->formatCurrency($summary['totalDisputes'])]);
                    }
                    $netAfterDisputes = $summary['netRevenue'] - ($summary['totalDisputes'] ?? 0);
                    fputcsv($file, ['Net Revenue:', $this->formatCurrency($netAfterDisputes)]);
                    fputcsv($file, ['Average Booking Value:', $this->formatCurrency($summary['averageBookingValue'])]);
                    fputcsv($file, ['']); // Empty line

                    // Payment Types Summary
                    fputcsv($file, ['Payment Type Breakdown']);
                    fputcsv($file, ['Payment Type', 'Booking Count', 'Total Tourists', 'Total Amount', 'Fee Amount', 'Refund Amount', 'Net Amount', 'Percentage']);
                    
                    if (isset($data['paymentTypeBreakdown'])) {
                        foreach ($data['paymentTypeBreakdown'] as $type => $typeData) {
                            $percentage = $summary['totalRevenue'] > 0 ? 
                                round(($typeData['transactionAmount'] / $summary['totalRevenue']) * 100, 2) : 0;
                            
                            fputcsv($file, [
                                $type,
                                number_format($typeData['bookingCount'] ?? 0),
                                number_format($typeData['totalPax']),
                                $this->formatCurrency($typeData['transactionAmount'] ?? 0),
                                $this->formatCurrency($typeData['feeAmount'] ?? 0),
                                $this->formatCurrency($typeData['refundAmount'] ?? 0),
                                $this->formatCurrency($typeData['netAmount'] ?? 0),
                                $percentage . '%'
                            ]);
                        }
                    }

                    fputcsv($file, ['']); // Empty line

                    // Affiliate Data
                    if (isset($data['affiliateData']) && !empty($data['affiliateData'])) {
                        fputcsv($file, ['Affiliate Performance']);
                        fputcsv($file, ['Affiliate Name', 'Booking Count', 'Total Tourists', 'Total Amount', 'Fee Amount', 'Refund Amount', 'Net Amount']);
                        
                        foreach ($data['affiliateData'] as $affiliate) {
                            fputcsv($file, [
                                $affiliate['name'],
                                number_format($affiliate['booking_count']),
                                number_format($affiliate['total_pax']),
                                $this->formatCurrency($affiliate['total_amount']),
                                $this->formatCurrency($affiliate['fee_amount'] ?? 0),
                                $this->formatCurrency($affiliate['refund_amount']),
                                $this->formatCurrency($affiliate['net_amount'])
                            ]);
                        }
                        fputcsv($file, ['']); // Empty line
                    }

                    // Third Party Data
                    if (isset($data['thirdPartyData']) && !empty($data['thirdPartyData'])) {
                        fputcsv($file, ['Third Party Platform Performance']);
                        fputcsv($file, ['Platform', 'Booking Count', 'Total Tourists', 'Total Amount', 'Average Booking Value']);
                        
                        foreach ($data['thirdPartyData'] as $platform) {
                            $avgBookingValue = $platform['booking_count'] > 0 ? 
                                $platform['total_amount'] / $platform['booking_count'] : 0;
                            
                            fputcsv($file, [
                                $platform['name'],
                                number_format($platform['booking_count']),
                                number_format($platform['total_pax']),
                                $this->formatCurrency($platform['total_amount']),
                                $this->formatCurrency($avgBookingValue)
                            ]);
                        }
                        fputcsv($file, ['']); // Empty line
                    }

                    // Operational Metrics
                    if (isset($data['operationalMetrics'])) {
                        // Check-in Status
                        if (isset($data['operationalMetrics']['check_in_status'])) {
                            fputcsv($file, ['Check-in Status Breakdown']);
                            fputcsv($file, ['Status', 'Booking Count', 'Tourists Count']);
                            
                            foreach ($data['operationalMetrics']['check_in_status'] as $status => $statusData) {
                                fputcsv($file, [
                                    $status,
                                    number_format($statusData['count']),
                                    number_format($statusData['pax'])
                                ]);
                            }
                            fputcsv($file, ['']); // Empty line
                        }

                        // Waiver Status
                        if (isset($data['operationalMetrics']['waiver_status'])) {
                            fputcsv($file, ['Waiver Status Breakdown']);
                            fputcsv($file, ['Status', 'Booking Count', 'Tourists Count']);
                            
                            foreach ($data['operationalMetrics']['waiver_status'] as $status => $statusData) {
                                fputcsv($file, [
                                    ucfirst(str_replace('_', ' ', $status)),
                                    number_format($statusData['count']),
                                    number_format($statusData['pax'])
                                ]);
                            }
                            fputcsv($file, ['']); // Empty line
                        }
                    }
                }

                // Booking Details
                $includeBookingDetails = $filters['includeBookingDetails'] ?? false;
                if ($includeBookingDetails && isset($data['bookingDetails']) && !empty($data['bookingDetails'])) {
                    fputcsv($file, ['Booking Details']);
                    fputcsv($file, [
                        'Booking ID',
                        'Tour Date',
                        'Tour Time',
                        'Created Date',
                        'Purchase Date',
                        'Tour Package',
                        'Customer',
                        'Email',
                        'Phone',
                        'Payment Type',
                        'Payment',
                        'Unpaid',
                        'Fees',
                        'Refund',
                        'Discount',
                        'Net',
                        'Tourist',
                        'Rate',
                        'Tax',
                        'Permit Fee',
                        'Status',
                        'Check-in',
                        'Waiver',
                        'Platform',
                        'Affiliate',
                        'Reallocated',
                        'Soft Block'
                    ]);
                    
                    foreach ($data['bookingDetails'] as $booking) {
                        fputcsv($file, [
                            $booking['booking_id'],
                            $booking['tour_date'],
                            $booking['tour_time'],
                            $booking['created_date'],
                            $booking['purchase_date'],
                            $booking['tour_package'],
                            $booking['customer_name'],
                            $booking['customer_email'] ?? '',
                            $booking['customer_phone'] ?? '',
                            $booking['purchase_type'],
                            $this->formatCurrency($booking['amount']),
                            $this->formatCurrency($booking['unpaid_total']),
                            $this->formatCurrency($booking['fee_amount']),
                            $this->formatCurrency($booking['refund_amount']),
                            $this->formatCurrency($booking['discount']),
                            $this->formatCurrency($booking['net_total']),
                            $booking['total_pax'],
                            $this->formatCurrency((float) $booking['pax_rate']),
                            $this->formatCurrency((float) $booking['pax_tax']),
                            $this->formatCurrency((float) $booking['pax_permit_fee']),
                            $booking['booking_status'],
                            $booking['check_in_status'],
                            $booking['waiver_status'],
                            $booking['third_party_platform'],
                            $booking['affiliate_name'],
                            $booking['is_reallocated'] ? 'Yes' : 'No',
                            $booking['is_soft_block'] ? 'Yes' : 'No'
                        ]);
                    }
                }

                // Footer
                fputcsv($file, ['']);
                fputcsv($file, ['Report generated by:', $branding['company_name']]);
                fputcsv($file, ['Generated on:', now()->format('F j, Y \a\t g:i A T')]);

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $exception) {
            Log::error('ExportService::exportToCSV Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Export to Excel format with multiple worksheets
     *
     * @param array $data
     * @param array $filters
     * @return StreamedResponse
     */
    public function exportToExcel(array $data, array $filters): StreamedResponse
    {
        try {
            $fileName = 'payment_breakdown_report_' . $filters['dateFrom'] . '_' . $filters['dateTo'] . '.xlsx';
            
            // For now, create an enhanced CSV that Excel can open with proper formatting
            // This provides better structure than basic CSV
            $headers = [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            $callback = function () use ($data, $filters) {
                // Generate Excel-compatible HTML format
                echo $this->generateExcelHTML($data, $filters);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $exception) {
            Log::error('ExportService::exportToExcel Error: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Generate Excel-compatible HTML
     *
     * @param array $data
     * @param array $filters
     * @return string
     */
    private function generateExcelHTML(array $data, array $filters): string
    {
        $branding = $this->getCompanyBranding();
        $summary = $this->generatePdfSummary($data);
        
        $html = '<?xml version="1.0"?>' . "\n";
        $html .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $html .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $html .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $html .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $html .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        // Styles
        $html .= '<Styles>' . "\n";
        $html .= '<Style ss:ID="Header"><Font ss:Bold="1" ss:Size="14"/><Interior ss:Color="#4472C4" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF"/></Style>' . "\n";
        $html .= '<Style ss:ID="SubHeader"><Font ss:Bold="1" ss:Size="12"/><Interior ss:Color="#D9E2F3" ss:Pattern="Solid"/></Style>' . "\n";
        $html .= '<Style ss:ID="Currency"><NumberFormat ss:Format="Currency"/></Style>' . "\n";
        $html .= '<Style ss:ID="Percentage"><NumberFormat ss:Format="Percent"/></Style>' . "\n";
        $html .= '</Styles>' . "\n";
        
        // Summary Worksheet
        $html .= '<Worksheet ss:Name="Summary">' . "\n";
        $html .= '<Table>' . "\n";
        
        // Header
        $html .= '<Row><Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($branding['company_name']) . '</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Payment Breakdown Report</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell><Data ss:Type="String">Date Range: ' . htmlspecialchars($filters['dateFrom'] . ' to ' . $filters['dateTo']) . '</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell><Data ss:Type="String">Generated: ' . now()->format('F j, Y \a\t g:i A') . '</Data></Cell></Row>' . "\n";
        $html .= '<Row></Row>' . "\n"; // Empty row
        
        // Summary metrics
        $html .= '<Row><Cell ss:StyleID="SubHeader"><Data ss:Type="String">Executive Summary</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell><Data ss:Type="String">Total Bookings</Data></Cell><Cell><Data ss:Type="Number">' . $summary['totalBookings'] . '</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell><Data ss:Type="String">Total Tourists</Data></Cell><Cell><Data ss:Type="Number">' . $summary['totalPax'] . '</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell><Data ss:Type="String">Total Revenue</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $summary['totalRevenue'] . '</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell><Data ss:Type="String">Total Fees</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $summary['totalFees'] . '</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell><Data ss:Type="String">Total Refunds</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $summary['totalRefunds'] . '</Data></Cell></Row>' . "\n";
        $html .= '<Row><Cell><Data ss:Type="String">Total Tax</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $summary['totalTax'] . '</Data></Cell></Row>' . "\n";
        if (($summary['totalDisputes'] ?? 0) > 0) {
            $html .= '<Row><Cell><Data ss:Type="String">Dispute Reversals</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $summary['totalDisputes'] . '</Data></Cell></Row>' . "\n";
        }
        $excelNetAfterDisputes = $summary['netRevenue'] - ($summary['totalDisputes'] ?? 0);
        $html .= '<Row><Cell><Data ss:Type="String">Net Revenue</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $excelNetAfterDisputes . '</Data></Cell></Row>' . "\n";
        
        $html .= '</Table>' . "\n";
        $html .= '</Worksheet>' . "\n";
        
        // Payment Types Worksheet
        if (isset($data['paymentTypeBreakdown'])) {
            $html .= '<Worksheet ss:Name="Payment Types">' . "\n";
            $html .= '<Table>' . "\n";
            
            // Headers
            $html .= '<Row>' . "\n";
            $html .= '<Cell ss:StyleID="SubHeader"><Data ss:Type="String">Payment Type</Data></Cell>' . "\n";
            $html .= '<Cell ss:StyleID="SubHeader"><Data ss:Type="String">Bookings</Data></Cell>' . "\n";
            $html .= '<Cell ss:StyleID="SubHeader"><Data ss:Type="String">Tourists</Data></Cell>' . "\n";
            $html .= '<Cell ss:StyleID="SubHeader"><Data ss:Type="String">Total Amount</Data></Cell>' . "\n";
            $html .= '<Cell ss:StyleID="SubHeader"><Data ss:Type="String">Fee Amount</Data></Cell>' . "\n";
            $html .= '<Cell ss:StyleID="SubHeader"><Data ss:Type="String">Refunds</Data></Cell>' . "\n";
            $html .= '<Cell ss:StyleID="SubHeader"><Data ss:Type="String">Net Amount</Data></Cell>' . "\n";
            $html .= '</Row>' . "\n";
            
            foreach ($data['paymentTypeBreakdown'] as $type => $typeData) {
                $html .= '<Row>' . "\n";
                $html .= '<Cell><Data ss:Type="String">' . htmlspecialchars($type) . '</Data></Cell>' . "\n";
                $html .= '<Cell><Data ss:Type="Number">' . ($typeData['bookingCount'] ?? 0) . '</Data></Cell>' . "\n";
                $html .= '<Cell><Data ss:Type="Number">' . $typeData['totalPax'] . '</Data></Cell>' . "\n";
                $html .= '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . ($typeData['transactionAmount'] ?? 0) . '</Data></Cell>' . "\n";
                $html .= '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . ($typeData['feeAmount'] ?? 0) . '</Data></Cell>' . "\n";
                $html .= '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . ($typeData['refundAmount'] ?? 0) . '</Data></Cell>' . "\n";
                $html .= '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . ($typeData['netAmount'] ?? 0) . '</Data></Cell>' . "\n";
                $html .= '</Row>' . "\n";
            }
            
            $html .= '</Table>' . "\n";
            $html .= '</Worksheet>' . "\n";
        }
        
        $html .= '</Workbook>' . "\n";
        
        return $html;
    }

    /**
     * Get company branding information with tenant isolation
     *
     * @return array
     */
    private function getCompanyBranding(): array
    {
        // Get current tour operator using helper
        try {
            $tourOperator = tourOperator();

            // Get logo URL using helper function
            $logoUrl = null;
            if (function_exists('tourOperatorLogo')) {
                $logoUrl = tourOperatorLogo($tourOperator->tenant_id, $tourOperator->logo);
                // Don't use default "no-image-available.png" for PDF
                if (strpos($logoUrl, 'no-image-available.png') !== false) {
                    $logoUrl = null;
                }
            }

            return [
                'company_name' => $tourOperator->name ?? 'Tour Company',
                'logo_url' => $logoUrl,
                'address' => $tourOperator->address ?? '',
                'phone' => $tourOperator->phone ?? '',
                'email' => $tourOperator->email ?? '',
                'website' => $tourOperator->website ?? '',
                'timezone' => $tourOperator->timezone ?? config('app.timezone'),
                'currency' => session('currency', 'USD')
            ];
        } catch (Exception $e) {
            Log::warning('Could not fetch tour operator branding: ' . $e->getMessage());
            
            return $this->getDefaultBranding();
        }
    }

    /**
     * Get default branding when tenant-specific branding is not available
     *
     * @return array
     */
    private function getDefaultBranding(): array
    {
        return [
            'company_name' => config('app.name', 'Tour Management System'),
            'logo_url' => null,
            'address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'timezone' => config('app.timezone'),
            'currency' => session('currency', 'USD')
        ];
    }

    /**
     * Format filters for display
     *
     * @param array $filters
     * @return array
     */
    private function formatFiltersForDisplay(array $filters): array
    {
        $displayFilters = [];
        
        if (isset($filters['tourPackageId']) && $filters['tourPackageId']) {
            $displayFilters['Tour Package'] = $this->getTourPackageName($filters['tourPackageId']);
        }
        
        if (isset($filters['affiliateId']) && $filters['affiliateId']) {
            $displayFilters['Affiliate'] = $this->getAffiliateName($filters['affiliateId']);
        }
        
        if (isset($filters['paymentTypes']) && !empty($filters['paymentTypes'])) {
            $displayFilters['Payment Types'] = implode(', ', $filters['paymentTypes']);
        }

        if (isset($filters['chargeTypes']) && !empty($filters['chargeTypes'])) {
            $chargeTypeLabels = ['tours' => 'Tours', 'custom' => 'Custom', 'addons' => 'Add-ons'];
            $displayFilters['Charge Type'] = implode(', ', array_map(
                fn($type) => $chargeTypeLabels[$type] ?? $type,
                $filters['chargeTypes']
            ));
        }
        
        if (isset($filters['viewBy'])) {
            // Format viewBy properly: tourdate -> Tour Date, createddate -> Created Date, purchasedate -> Purchase Date
            $viewByFormatted = $filters['viewBy'];
            if ($viewByFormatted === 'tourdate') {
                $viewByFormatted = 'Tour Date';
            } elseif ($viewByFormatted === 'createddate') {
                $viewByFormatted = 'Created Date';
            } elseif ($viewByFormatted === 'purchasedate') {
                $viewByFormatted = 'Purchase Date';
            } else {
                $viewByFormatted = ucfirst($viewByFormatted);
            }
            $displayFilters['View By'] = $viewByFormatted;
        }
        
        if (isset($filters['bookingStatus']) && !empty($filters['bookingStatus'])) {
            $displayFilters['Booking Status'] = $filters['bookingStatus'];
        }
        
        if (isset($filters['checkInStatus']) && !empty($filters['checkInStatus'])) {
            $displayFilters['Check-in Status'] = $filters['checkInStatus'];
        }
        
        if (isset($filters['waiverStatus']) && !empty($filters['waiverStatus'])) {
            $displayFilters['Waiver Status'] = $filters['waiverStatus'];
        }
        
        if (isset($filters['thirdPartyPlatform']) && !empty($filters['thirdPartyPlatform'])) {
            $displayFilters['Platform'] = $filters['thirdPartyPlatform'];
        }

        // Handle new multi-select third party platforms format
        if (isset($filters['thirdPartyPlatforms']) && !empty($filters['thirdPartyPlatforms'])) {
            $displayFilters['Third Party Platforms'] = implode(', ', $filters['thirdPartyPlatforms']);
        }

        // Handle exclude all third party platforms
        if (isset($filters['excludeAllThirdParty']) && $filters['excludeAllThirdParty']) {
            $displayFilters['Third Party Platforms'] = 'Excluded All';
        }

        if (isset($filters['minAmount']) && $filters['minAmount'] > 0) {
            $displayFilters['Min Amount'] = $this->formatCurrency($filters['minAmount']);
        }
        
        if (isset($filters['maxAmount']) && $filters['maxAmount'] > 0) {
            $displayFilters['Max Amount'] = $this->formatCurrency($filters['maxAmount']);
        }
        
        return $displayFilters;
    }

    /**
     * Generate PDF summary data
     *
     * @param array $data
     * @return array
     */
    private function generatePdfSummary(array $data): array
    {
        $summary = [
            'totalBookings' => 0,
            'totalPax' => 0,
            'totalRevenue' => 0,
            'totalRefunds' => 0,
            'totalFees' => 0,
            'netRevenue' => 0,
            'averageBookingValue' => 0,
            'totalDisputes' => $data['totalDisputes'] ?? 0,
            'totalTax' => 0,
        ];

        if (isset($data['paymentTypeBreakdown'])) {
            foreach ($data['paymentTypeBreakdown'] as $typeData) {
                $summary['totalBookings'] += $typeData['bookingCount'] ?? 0;
                $summary['totalPax'] += $typeData['totalPax'];
                $summary['totalRevenue'] += $typeData['transactionAmount'] ?? 0;
                $summary['totalRefunds'] += $typeData['refundAmount'] ?? 0;
                $summary['totalFees'] += $typeData['feeAmount'] ?? 0;
                $summary['netRevenue'] += $typeData['netAmount'] ?? 0;
                $summary['totalTax'] += $typeData['totalTax'] ?? 0;
            }
        }

        $summary['totalTax'] = round($summary['totalTax'], 2);
        
        if ($summary['totalBookings'] > 0) {
            $summary['averageBookingValue'] = $summary['netRevenue'] / $summary['totalBookings'];
        }
        
        return $summary;
    }

    /**
     * Generate error PDF
     *
     * @param string $errorMessage
     * @return StreamedResponse
     */
    private function generateErrorPDF(string $errorMessage): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="export_error.txt"'
        ];

        $callback = function () use ($errorMessage) {
            echo "Export Error\n";
            echo "============\n\n";
            echo "An error occurred while generating the export:\n";
            echo $errorMessage . "\n\n";
            echo "Please try again or contact support if the problem persists.\n";
            echo "Generated: " . now()->toDateTimeString();
        };

        return response()->stream($callback, 500, $headers);
    }

    /**
     * Get tour package name by ID
     *
     * @param int $packageId
     * @return string
     */
    private function getTourPackageName(int $packageId): string
    {
        try {
            $package = DB::table('tour_packages')
                ->where('id', $packageId)
                ->where('deleted_by', 0)
                ->first();
            
            return $package->name ?? "Package #{$packageId}";
        } catch (Exception $e) {
            return "Package #{$packageId}";
        }
    }

    /**
     * Get affiliate name by ID
     *
     * @param int $affiliateId
     * @return string
     */
    private function getAffiliateName(int $affiliateId): string
    {
        try {
            $affiliate = DB::table('customers')
                ->where('id', $affiliateId)
                ->where('is_affiliate', 'Yes')
                ->where('deleted_by', 0)
                ->first();
            
            return $affiliate->name ?? "Affiliate #{$affiliateId}";
        } catch (Exception $e) {
            return "Affiliate #{$affiliateId}";
        }
    }

    /**
     * Format currency values
     *
     * @param float $amount
     * @return string
     */
    private function formatCurrency(float $amount): string
    {
        $currency = session('currency', 'USD');
        $symbol = $this->getCurrencySymbol($currency);
        return $symbol . number_format($amount, 2);
    }

    /**
     * Get currency symbol
     *
     * @param string $currency
     * @return string
     */
    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'JPY' => '¥'
        ];
        
        return $symbols[$currency] ?? $currency . ' ';
    }
}