{{-- Payment Breakdown Report - Custom Styles --}}
{{-- Additional custom CSS if needed beyond Tailwind --}}

<style>
/* Custom styles for Payment Breakdown Report */

/* Smooth transitions for collapsible sections */
#pbFilterCollapse {
    transition: all 0.3s ease-in-out;
}

/* Hover effect for chevron icon */
[data-bs-target="#pbFilterCollapse"] i {
    transition: transform 0.2s ease-in-out;
}

[data-bs-target="#pbFilterCollapse"][aria-expanded="true"] i {
    transform: rotate(180deg);
}

/* Loading spinner animation */
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Table hover effects */
#pbDataTable tbody tr {
    transition: background-color 0.2s ease;
}

#pbDataTable tbody tr:hover {
    background-color: rgba(249, 250, 251, 0.5);
}

/* Export button hover effects */
.pb-export-btn {
    transition: all 0.2s ease;
}

.pb-export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Gradient animation for net total card */
.bg-gradient-to-br {
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}

@keyframes gradient {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Chart container responsive height */
canvas {
    max-height: 300px;
}

/* ============================================
   ACCORDION - Rate Group Categorization
   ============================================ */
.accordion-header-row {
    cursor: pointer;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #e5e7eb;
}

.accordion-header-row:hover {
    background-color: #f9fafb;
}

.accordion-header-row td {
    padding: 0.75rem !important;
}

.accordion-icon {
    transition: transform 0.2s ease;
    display: inline-block;
}

.accordion-content-row {
    background-color: #fafafa;
}

.accordion-content-row:hover {
    background-color: #f5f5f5;
}

/* Hide accordion content by default */
.accordion-content-row.hidden {
    display: none;
}

/* Payment type specific colors for accordion headers */
.card-accordion-header {
    background-color: #eff6ff !important;
}

.card-accordion-header:hover {
    background-color: #dbeafe !important;
}

.cash-accordion-header {
    background-color: #f0fdf4 !important;
}

.cash-accordion-header:hover {
    background-color: #dcfce7 !important;
}

.billable-accordion-header {
    background-color: #faf5ff !important;
}

.billable-accordion-header:hover {
    background-color: #f3e8ff !important;
}

.invoiced-accordion-header {
    background-color: #fffbeb !important;
}

.invoiced-accordion-header:hover {
    background-color: #fef3c7 !important;
}

.unpaid-accordion-header {
    background-color: #f9fafb !important;
}

.unpaid-accordion-header:hover {
    background-color: #f3f4f6 !important;
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    #pbFilterCollapse {
        display: none !important;
    }
    
    #pbResultsContainer {
        page-break-inside: avoid;
    }
}
</style>
