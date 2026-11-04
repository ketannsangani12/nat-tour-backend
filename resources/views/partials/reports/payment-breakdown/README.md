# Payment Breakdown Report - Modular Structure

This document explains the modular structure of the Payment Breakdown Report, which serves as a template for all future reports in the V2 reporting system.

## Directory Structure

```
resources/views/partials/reports/payment-breakdown/
├── index.blade.php          # Entry point - includes all components
├── content.blade.php        # HTML structure with Tailwind CSS
├── export-modal.blade.php   # Export functionality modal
├── styles.blade.php         # Custom CSS styles
├── scripts.blade.php        # JavaScript functions
└── README.md               # This documentation file
```

## File Descriptions

### 1. index.blade.php
**Purpose**: Main entry point for the report
**Contents**:
- Includes all component files (content, modal, styles, scripts)
- Wraps everything in a container div with ID `paymentBreakdownReportSection`
- Initially hidden, shown when report is selected

**Usage**:
```blade
@include('partials.reports.payment-breakdown.index')
```

### 2. content.blade.php
**Purpose**: HTML structure and layout
**Styling**: Uses Tailwind CSS utility classes
**Sections**:
- Filter card with collapsible accordion
- Date range picker, tour package selector, view by dropdown
- Payment type checkboxes with select/deselect all buttons
- Loading indicator
- Results container with:
  - 6 Summary cards (Bookings, Pax, Revenue, Refunds, Fees, Discount)
  - Net Total highlight card with gradient
  - 4 Check-in status cards
  - 3 Charts (payment type distribution, revenue vs refunds, check-in status)
  - Data table with footer totals
- Empty state message

**Key IDs**:
- `pbFilterForm` - Main filter form
- `pbDateRange` - Date range picker input
- `pbTourPackage` - Tour package dropdown
- `pbViewBy` - View by dropdown
- `pbLoadingIndicator` - Loading spinner
- `pbResultsContainer` - Results section
- `pbEmptyState` - Empty state message
- `pbDataTable` - Main data table
- `pbTableBody` - Table body for data rows

### 3. export-modal.blade.php
**Purpose**: Export functionality modal
**Features**:
- Bootstrap 5 modal
- Three export format options: CSV, Excel, PDF
- Styled with Tailwind CSS
- Hover effects on format buttons

**Key IDs**:
- `pbExportModal` - Modal container
- `.pb-export-btn` - Export format buttons

### 4. styles.blade.php
**Purpose**: Custom CSS styles
**Contents**:
- Smooth transitions for collapsible sections
- Chevron rotation animation
- Loading spinner animation
- Table hover effects
- Export button hover effects
- Gradient animation for net total card
- Chart responsive height
- Print styles

**Note**: Most styling is done with Tailwind CSS. This file only contains custom animations and effects that can't be achieved with Tailwind alone.

### 5. scripts.blade.php
**Purpose**: All JavaScript functionality
**Functions**:

#### Core Functions:
- `togglePaymentBreakdownSection()` - Show/hide report based on selection
- `initializePaymentBreakdownReport()` - Initialize date pickers, dropdowns, event listeners
- `generatePaymentBreakdownReport()` - Fetch data from backend via AJAX
- `updatePaymentBreakdownDisplay(response)` - Update UI with report data
- `updatePaymentBreakdownTable(bookings)` - Populate data table
- `initializePaymentBreakdownCharts(response)` - Create/update Chart.js charts
- `exportPaymentBreakdownReport(format)` - Export report in selected format

#### Event Listeners:
- Form submission
- Date range picker changes
- Select/deselect all payment types
- Clear filters button
- Export format buttons

**Dependencies**:
- jQuery
- Moment.js (with optional moment-timezone)
- Bootstrap 5
- Chart.js (for charts)
- SweetAlert (for alerts)
- Daterangepicker

## Integration with report-v2.blade.php

To include this modular report in the main report view:

```blade
<!-- In resources/views/users/common/report-v2.blade.php -->
<section class="booking-action-sec overflow-y-auto bg-[#F9FAFB] py-6" id="paymentBreakdownReportSection" style="display: none;">
    <div class="w-full container">
        @include('partials.reports.payment-breakdown.index')
    </div>
</section>
```

## Backend Integration

### Controller Method:
```php
// app/Http/Controllers/Admin/ReportV2Controller.php
public function paymentBreakdownReportData(Request $request)
{
    // Validate request
    // Fetch data
    // Return JSON response with:
    // - success: boolean
    // - data: array of bookings
    // - summary: object with totals
    // - checkInData: object with check-in status counts
}
```

### Routes:
```php
// routes/backend/admin.php
Route::get('/report/payment-breakdown-report-data', [ReportV2Controller::class, 'paymentBreakdownReportData'])
    ->name('admin.tour-operator.report.payment-breakdown-report-data');
    
Route::get('/report/payment-breakdown-report-export', [ReportV2Controller::class, 'paymentBreakdownReportExport'])
    ->name('admin.tour-operator.report.payment-breakdown-report-export');
```

## Creating New Reports Using This Pattern

To create a new report following this modular structure:

1. **Create directory**: `resources/views/partials/reports/{report-name}/`

2. **Create files**:
   - `index.blade.php` - Entry point
   - `content.blade.php` - HTML with Tailwind CSS
   - `export-modal.blade.php` - Export modal (if needed)
   - `styles.blade.php` - Custom CSS
   - `scripts.blade.php` - JavaScript functions
   - `README.md` - Documentation

3. **Use consistent naming**:
   - Prefix all IDs with report abbreviation (e.g., `pb` for Payment Breakdown)
   - Use camelCase for JavaScript variables and functions
   - Use kebab-case for CSS classes

4. **Follow conventions**:
   - Use Tailwind CSS for styling
   - Keep JavaScript functions modular and well-documented
   - Include loading states and empty states
   - Validate inputs before making AJAX requests
   - Handle errors gracefully with user-friendly messages

5. **Include in report-v2.blade.php**:
   ```blade
   <section id="{reportName}Section" style="display: none;">
       <div class="w-full container">
           @include('partials.reports.{report-name}.index')
       </div>
   </section>
   ```

## Benefits of This Structure

1. **Modularity**: Each report is self-contained and independent
2. **Maintainability**: Easy to find and update specific parts
3. **Reusability**: Components can be reused across reports
4. **Scalability**: Adding new reports doesn't clutter existing files
5. **Clarity**: Clear separation of concerns (HTML, CSS, JS)
6. **Documentation**: Each report can have its own README
7. **Testing**: Easier to test individual components
8. **Collaboration**: Multiple developers can work on different reports simultaneously

## Best Practices

1. **Always use Tailwind CSS** for styling instead of custom CSS when possible
2. **Keep JavaScript functions small** and focused on a single responsibility
3. **Use consistent naming conventions** across all reports
4. **Document complex logic** with comments
5. **Handle edge cases** (empty data, errors, loading states)
6. **Test thoroughly** before deploying
7. **Follow accessibility guidelines** (ARIA labels, keyboard navigation)
8. **Optimize performance** (lazy loading, debouncing, caching)

## Future Enhancements

Potential improvements for this structure:

1. **Component library**: Create reusable components (cards, tables, charts)
2. **State management**: Implement a simple state management system
3. **TypeScript**: Add type safety to JavaScript code
4. **Unit tests**: Add JavaScript unit tests for functions
5. **E2E tests**: Add end-to-end tests for user workflows
6. **Performance monitoring**: Track report generation times
7. **Caching**: Implement client-side caching for frequently accessed data
8. **Real-time updates**: Add WebSocket support for live data updates

## Support

For questions or issues with this modular structure, please contact the development team or refer to the main project documentation.

---

**Last Updated**: January 19, 2026
**Version**: 1.0.0
**Maintainer**: Development Team
