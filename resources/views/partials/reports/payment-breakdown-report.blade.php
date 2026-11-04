@extends('partials.reports.layout')

@section('title', 'Payment Breakdown Report')

@section('css')
<style>
/* ============================================
   LOWER ANTELOPE CANYON COLOR PALETTE
   Inspired by the natural beauty of Arizona
   ============================================ */
:root {
    /* Canyon Colors - Warm & Earthy */
    --canyon-orange: #E67E22;        /* Deep canyon orange */
    --canyon-rust: #D35400;          /* Rich rust red */
    --canyon-sand: #F39C12;          /* Golden sand */
    --canyon-terracotta: #CA6F1E;    /* Terracotta */
    --canyon-amber: #DC7633;         /* Warm amber */
    
    /* Neutral Earth Tones */
    --sand-light: #FDF6E3;           /* Light sandy beige */
    --sand-medium: #F4E8D0;          /* Medium sand */
    --stone-gray: #7D6E5C;           /* Stone gray-brown */
    --rock-dark: #5D4E37;            /* Dark rock brown */
    
    /* Accent Colors */
    --success-green: #27AE60;        /* Desert plant green */
    --warning-amber: #F39C12;        /* Warning amber */
    --danger-red: #C0392B;           /* Deep red */
    --info-blue: #3498DB;            /* Sky blue */
    
    /* UI Colors */
    --border-color: #E8DCC8;
    --bg-light: #FDF8F3;
    --text-primary: #3E2723;
    --text-secondary: #6D4C41;
    --shadow-sm: 0 2px 4px rgba(211, 84, 0, 0.08);
    --shadow-md: 0 4px 8px rgba(211, 84, 0, 0.12);
    --shadow-lg: 0 8px 16px rgba(211, 84, 0, 0.16);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
}

/* Global Styles */
body {
    background: linear-gradient(135deg, #FDF8F3 0%, #F4E8D0 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Main content wrapper */
.container-fluid {
    padding: 0 2rem;
}

/* Section spacing */
#summarySection,
#netTotalSection,
#checkInSection,
#chartSection,
#tableSection {
    margin-bottom: 2rem;
}

/* ============================================
   HEADER - Override layout header for proper alignment
   ============================================ */
/* Note: Header is defined in layout.blade.php, these are overrides for this specific report */

/* ============================================
   FILTER ACCORDION - Clean & Professional
   ============================================ */
.filter-section {
    margin-bottom: 2.5rem;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

.filter-toggle-btn {
    background: white;
    border: none;
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.75rem;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.12);
    text-align: left;
}

.filter-toggle-btn:hover {
    box-shadow: 0 4px 16px rgba(230, 126, 34, 0.2);
    transform: translateY(-2px);
}

.filter-toggle-btn:active {
    transform: translateY(0);
}

.filter-toggle-btn.active {
    background: linear-gradient(135deg, #FDF6E3 0%, #F4E8D0 100%);
    box-shadow: 0 4px 16px rgba(230, 126, 34, 0.25);
}

.filter-toggle-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.filter-toggle-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
    box-shadow: 0 4px 12px rgba(230, 126, 34, 0.3);
    transition: all 0.3s ease;
}

.filter-toggle-btn:hover .filter-toggle-icon {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(230, 126, 34, 0.4);
}

.filter-toggle-text h5 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
}

.filter-toggle-text p {
    margin: 0.25rem 0 0 0;
    font-size: 0.813rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.filter-toggle-arrow {
    font-size: 1.25rem;
    color: var(--canyon-orange);
    transition: transform 0.3s ease;
}

.filter-toggle-btn.active .filter-toggle-arrow {
    transform: rotate(180deg);
}

/* Collapse animation */
.collapse {
    transition: height 0.35s ease;
}

.collapsing {
    transition: height 0.35s ease;
}

/* ============================================
   FILTER FORM - Compact & Clean Layout
   ============================================ */
.filter-section-group {
    background: white;
    border-radius: var(--radius-md);
    padding: 1.25rem 1.5rem;
    border: none;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.filter-section-header {
    border-bottom: 2px solid var(--sand-light);
    padding-bottom: 0.625rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-section-header h6 {
    margin: 0;
    font-size: 0.938rem;
    font-weight: 700;
    color: var(--canyon-rust);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-section-header i {
    color: var(--canyon-orange);
    font-size: 1rem;
}

/* Main content container with consistent spacing */
.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
}

/* Ensure all sections have consistent spacing */
#summarySection,
#netTotalSection,
#checkInSection,
#chartSection,
#tableSection {
    margin-bottom: 2rem;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

/* ============================================
   TABLE CONTROLS - Show Entries & Search
   ============================================ */
.card-body.border-bottom {
    background: linear-gradient(to bottom, #FDF8F3, #F4E8D0);
    border-bottom: 2px solid var(--canyon-sand) !important;
    padding: 1.25rem 1.5rem;
}

.card-body.border-bottom label {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
}

#tablePageSize {
    border: 2px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 0.5rem 2.5rem 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23E67E22' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 12px 12px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

#tablePageSize:focus {
    border-color: var(--canyon-orange);
    box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.15);
    outline: none;
    background-color: #FDF8F3;
}

#tablePageSize:hover {
    border-color: var(--canyon-orange);
}

#tableSearch {
    border: 2px solid var(--border-color);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    background: white;
    font-weight: 500;
    transition: all 0.3s ease;
}

#tableSearch:focus {
    border-color: var(--canyon-orange);
    box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.15);
    outline: none;
    background: #FDF8F3;
}

#tableSearch::placeholder {
    color: var(--stone-gray);
    opacity: 0.6;
}

.input-group-text {
    background: white;
    border: 2px solid var(--border-color);
    border-right: none;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    color: var(--canyon-orange);
    font-size: 1rem;
}

.input-group #tableSearch {
    border-left: none;
}

.input-group:focus-within .input-group-text {
    border-color: var(--canyon-orange);
    background: white;
    color: var(--canyon-orange);
}

.input-group:focus-within #tableSearch {
    border-color: var(--canyon-orange);
}

/* ============================================
   FORM CONTROLS - Compact & Modern
   ============================================ */
.form-label {
    font-weight: 600;
    font-size: 0.813rem;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: block;
    letter-spacing: 0.025em;
}

.form-control,
.form-select {
    border: 2px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 0.5rem 0.875rem;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    background: white;
    color: var(--text-primary);
    font-weight: 500;
    height: auto;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--canyon-orange);
    box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.1);
    outline: none;
    background: #FDF8F3;
}

.form-control::placeholder {
    color: var(--stone-gray);
    opacity: 0.6;
    font-size: 0.813rem;
}

/* Compact margin for form groups */
.mb-3 {
    margin-bottom: 0.875rem !important;
}

.row {
    margin-left: -0.5rem;
    margin-right: -0.5rem;
}

.row > [class*="col-"] {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

/* Input Group Styling - Compact */
.input-group-text {
    background: linear-gradient(135deg, var(--canyon-sand), var(--canyon-orange));
    border: 2px solid var(--canyon-sand);
    color: white;
    font-weight: 600;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}

.input-group .form-control {
    border-left: none;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
}

.input-group .form-control:focus {
    border-left: 2px solid var(--canyon-orange);
}

/* Date Input Improvements */
input[type="date"] {
    position: relative;
    cursor: pointer;
    background: white;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='22' height='22' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 2V5M14 2V5M3 8H17M4 4H16C16.5523 4 17 4.44772 17 5V17C17 17.5523 16.5523 18 16 18H4C3.44772 18 3 17.5523 3 17V5C3 4.44772 3.44772 4 4 4Z' stroke='%23E67E22' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 20px 20px;
    padding-right: 2.75rem;
    font-weight: 500;
}

input[type="date"]::-webkit-calendar-picker-indicator {
    position: absolute;
    right: 0.75rem;
    cursor: pointer;
    opacity: 0;
    width: 22px;
    height: 22px;
}

input[type="date"]:hover {
    border-color: var(--canyon-orange);
    background-color: #FDF8F3;
}

input[type="date"]:focus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='22' height='22' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 2V5M14 2V5M3 8H17M4 4H16C16.5523 4 17 4.44772 17 5V17C17 17.5523 16.5523 18 16 18H4C3.44772 18 3 17.5523 3 17V5C3 4.44772 3.44772 4 4 4Z' stroke='%23D35400' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-color: #FDF8F3;
}

/* ============================================
   FLATPICKR DATE RANGE PICKER - Canyon Theme
   ============================================ */
.flatpickr-calendar {
    background: white;
    border: 2px solid var(--canyon-sand);
    border-radius: var(--radius-md);
    box-shadow: 0 8px 24px rgba(230, 126, 34, 0.2);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.flatpickr-months {
    background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
    border-radius: var(--radius-md) var(--radius-md) 0 0;
    padding: 0.75rem 0;
}

.flatpickr-month {
    color: white;
    fill: white;
}

.flatpickr-current-month {
    color: white;
    font-size: 1.125rem;
    font-weight: 700;
}

.flatpickr-current-month .flatpickr-monthDropdown-months {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.25rem 0.5rem;
    font-weight: 700;
}

.flatpickr-current-month .flatpickr-monthDropdown-months:hover {
    background: rgba(255, 255, 255, 0.3);
}

.flatpickr-current-month .numInputWrapper {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    padding: 0.25rem;
}

.flatpickr-current-month .numInputWrapper input {
    color: white;
    font-weight: 700;
}

.flatpickr-prev-month,
.flatpickr-next-month {
    fill: white;
    transition: all 0.2s ease;
}

.flatpickr-prev-month:hover,
.flatpickr-next-month:hover {
    fill: var(--sand-light);
    transform: scale(1.1);
}

.flatpickr-weekdays {
    background: var(--sand-light);
    border-bottom: 2px solid var(--border-color);
}

.flatpickr-weekday {
    color: var(--canyon-rust);
    font-weight: 700;
    font-size: 0.813rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.flatpickr-days {
    background: white;
}

.flatpickr-day {
    color: var(--text-primary);
    border: 1px solid transparent;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.flatpickr-day:hover {
    background: var(--sand-light);
    border-color: var(--canyon-sand);
    color: var(--canyon-rust);
}

.flatpickr-day.today {
    border-color: var(--canyon-orange);
    background: rgba(230, 126, 34, 0.1);
    color: var(--canyon-rust);
    font-weight: 700;
}

.flatpickr-day.today:hover {
    background: rgba(230, 126, 34, 0.2);
    border-color: var(--canyon-orange);
}

.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange {
    background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
    border-color: var(--canyon-orange);
    color: white;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);
}

.flatpickr-day.selected:hover,
.flatpickr-day.startRange:hover,
.flatpickr-day.endRange:hover {
    background: linear-gradient(135deg, var(--canyon-rust), var(--canyon-terracotta));
    border-color: var(--canyon-rust);
}

.flatpickr-day.inRange {
    background: rgba(230, 126, 34, 0.15);
    border-color: transparent;
    color: var(--canyon-rust);
    box-shadow: none;
}

.flatpickr-day.inRange:hover {
    background: rgba(230, 126, 34, 0.25);
}

.flatpickr-day.prevMonthDay,
.flatpickr-day.nextMonthDay {
    color: var(--stone-gray);
    opacity: 0.4;
}

.flatpickr-day.disabled,
.flatpickr-day.disabled:hover {
    color: var(--stone-gray);
    opacity: 0.3;
    cursor: not-allowed;
    background: transparent;
}

/* Flatpickr time picker (if enabled) */
.flatpickr-time {
    border-top: 2px solid var(--border-color);
    background: var(--sand-light);
}

.flatpickr-time input {
    color: var(--canyon-rust);
    font-weight: 700;
}

.flatpickr-time .flatpickr-am-pm {
    color: var(--canyon-rust);
    font-weight: 700;
}

/* Flatpickr input styling */
#dateRange {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='22' height='22' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 2V5M14 2V5M3 8H17M4 4H16C16.5523 4 17 4.44772 17 5V17C17 17.5523 16.5523 18 16 18H4C3.44772 18 3 17.5523 3 17V5C3 4.44772 3.44772 4 4 4Z' stroke='%23E67E22' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 20px 20px;
    padding-right: 2.75rem;
    cursor: pointer;
}

#dateRange:hover {
    border-color: var(--canyon-orange);
    background-color: #FDF8F3;
}

#dateRange:focus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='22' height='22' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 2V5M14 2V5M3 8H17M4 4H16C16.5523 4 17 4.44772 17 5V17C17 17.5523 16.5523 18 16 18H4C3.44772 18 3 17.5523 3 17V5C3 4.44772 3.44772 4 4 4Z' stroke='%23D35400' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-color: #FDF8F3;
}

/* ============================================
   CHECKBOXES - Compact with Icon Display
   ============================================ */
.form-check {
    margin-bottom: 0.75rem;
    padding-left: 0;
    display: flex;
    align-items: center;
}

.form-check-input {
    width: 1.125rem;
    height: 1.125rem;
    border: 2px solid var(--canyon-sand);
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-right: 0.625rem;
    background: white;
    flex-shrink: 0;
    position: relative;
}

.form-check-input:checked {
    background-color: var(--canyon-orange);
    border-color: var(--canyon-orange);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
    background-size: 100% 100%;
    background-position: center;
    background-repeat: no-repeat;
}

.form-check-input:focus {
    border-color: var(--canyon-orange);
    box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.15);
}

.form-check-input:hover {
    border-color: var(--canyon-orange);
}

.form-check-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
    cursor: pointer;
    user-select: none;
}

.form-check-label:hover {
    color: var(--canyon-orange);
}

/* Payment Types Grid - Compact & Contained */
.payment-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.625rem;
    padding: 1rem;
    background: linear-gradient(135deg, #FFFFFF 0%, #FDF8F3 100%);
}

.payment-types-grid .form-check {
    margin-bottom: 0;
    padding: 0.5rem 0.625rem;
    background: white;
    border-radius: 5px;
    transition: all 0.2s ease;
}

.payment-types-grid .form-check:hover {
    background: var(--sand-light);
    transform: translateX(2px);
}

/* Payment Types Header Buttons - Compact */
#selectAllPaymentTypes,
#deselectAllPaymentTypes {
    background: white;
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    padding: 0.3rem 0.75rem;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.2s ease;
}

#selectAllPaymentTypes {
    border-radius: 5px 0 0 5px;
}

#deselectAllPaymentTypes {
    border-radius: 0 5px 5px 0;
    border-left: none;
}

#selectAllPaymentTypes:hover,
#deselectAllPaymentTypes:hover {
    background: var(--canyon-orange);
    border-color: var(--canyon-orange);
    color: white;
}

#selectAllPaymentTypes:active,
#deselectAllPaymentTypes:active {
    background: var(--canyon-rust);
    border-color: var(--canyon-rust);
}

/* ============================================
   SELECT2 - Canyon Styling
   ============================================ */
.select2-container--default .select2-selection--single {
    border: 2px solid var(--border-color) !important;
    border-radius: var(--radius-sm) !important;
    height: auto !important;
    padding: 0.625rem 1rem !important;
    background: white !important;
    transition: all 0.3s ease !important;
}

.select2-container--default .select2-selection--single:focus,
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single {
    border-color: var(--canyon-orange) !important;
    box-shadow: 0 0 0 4px rgba(230, 126, 34, 0.1) !important;
    background: #FDF8F3 !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    padding: 0 !important;
    font-size: 0.938rem !important;
    color: var(--text-primary) !important;
    font-weight: 500 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 100% !important;
    right: 10px !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: var(--canyon-orange) transparent transparent transparent !important;
}

.select2-dropdown {
    border: 2px solid var(--canyon-orange) !important;
    border-radius: var(--radius-sm) !important;
    box-shadow: 0 8px 24px rgba(230, 126, 34, 0.15) !important;
    background: white !important;
}

.select2-results__option {
    padding: 0.75rem 1rem !important;
    font-size: 0.938rem !important;
    color: var(--text-primary) !important;
    transition: all 0.2s ease !important;
}

.select2-results__option--highlighted {
    background: linear-gradient(135deg, var(--canyon-sand), var(--canyon-orange)) !important;
    color: white !important;
}

.select2-results__option--selected {
    background: var(--sand-light) !important;
    color: var(--canyon-rust) !important;
    font-weight: 600 !important;
}

/* ============================================
   BUTTONS - Compact Canyon Style
   ============================================ */
.btn {
    padding: 0.5rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: var(--radius-sm);
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    letter-spacing: 0.025em;
}

.btn:active {
    transform: translateY(0) !important;
}

.btn-primary {
    background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
    color: white;
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(230, 126, 34, 0.4);
    background: linear-gradient(135deg, var(--canyon-rust), var(--canyon-terracotta));
}

.btn-outline-primary {
    background: white;
    color: var(--canyon-orange);
    border: 2px solid var(--canyon-orange);
}

.btn-outline-primary:hover {
    background: var(--canyon-orange);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);
}

.btn-outline-secondary {
    background: white;
    color: var(--stone-gray);
    border: 2px solid var(--border-color);
}

.btn-outline-secondary:hover {
    background: var(--sand-light);
    border-color: var(--canyon-sand);
    color: var(--canyon-rust);
}

.btn-sm {
    padding: 0.375rem 1rem;
    font-size: 0.813rem;
}

.btn-success {
    background: linear-gradient(135deg, var(--success-green), #229954);
    color: white;
    box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
}

/* Date Preset Buttons - More Compact */
.date-preset {
    padding: 0.5rem 1rem;
    font-size: 0.813rem;
    border-radius: var(--radius-sm);
    border: 2px solid var(--border-color);
    background: white;
    color: var(--text-primary);
    transition: all 0.3s ease;
    font-weight: 500;
    margin: 0 0.25rem 0.25rem 0;
}

.date-preset:hover {
    border-color: var(--canyon-orange);
    color: var(--canyon-orange);
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(230, 126, 34, 0.15);
}

.date-preset.active {
    background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust)) !important;
    color: white !important;
    border-color: var(--canyon-orange) !important;
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);
    font-weight: 600;
}

/* Button group styling */
.btn-group .btn {
    margin: 0;
    border: 2px solid var(--border-color);
}

.btn-group .btn:not(:first-child) {
    margin-left: -2px;
}

.btn-group .btn:first-child {
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
}

.btn-group .btn:last-child {
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
}

.btn-group .btn:not(:first-child):not(:last-child) {
    border-radius: 0;
}

/* Compact button group for date presets */
.btn-group-sm .btn {
    padding: 0.375rem 0.875rem;
    font-size: 0.75rem;
}

/* ============================================
   SUMMARY CARDS - Improved Design
   ============================================ */
.summary-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.25rem 1rem;
    box-shadow: 0 2px 12px rgba(230, 126, 34, 0.08);
    border: 2px solid var(--border-color);
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
    min-height: 110px;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--canyon-orange), var(--canyon-rust));
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: var(--radius-lg) 0 0 var(--radius-lg);
}

.summary-card:hover {
    box-shadow: 0 8px 24px rgba(230, 126, 34, 0.15);
    transform: translateY(-4px);
    border-color: var(--canyon-sand);
}

.summary-card:hover::before {
    opacity: 1;
}

.summary-card h3,
.summary-card h4 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 0.375rem;
    color: var(--canyon-rust);
    line-height: 1.1;
    letter-spacing: -0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.summary-card p {
    font-size: 0.688rem;
    color: var(--text-secondary);
    margin: 0;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    line-height: 1.3;
}

.summary-card .fs-1 {
    font-size: 2.5rem !important;
    opacity: 0.06;
    position: absolute;
    right: 0.75rem;
    bottom: 0.75rem;
    color: var(--canyon-orange);
}

.summary-card.border-success::before {
    background: linear-gradient(180deg, var(--success-green), #229954);
}

.summary-card.border-warning::before {
    background: linear-gradient(180deg, var(--canyon-sand), var(--canyon-amber));
}

.summary-card.border-danger::before {
    background: linear-gradient(180deg, var(--danger-red), #A93226);
}

.summary-card.border-secondary::before {
    background: linear-gradient(180deg, var(--stone-gray), var(--rock-dark));
}

/* Check-in Section Header */
#checkInSection h5 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--canyon-rust);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--sand-light);
}

/* ============================================
   TABLE - Fixed Width & Improved Design
   ============================================ */
.report-card {
    border: none;
    box-shadow: 0 4px 16px rgba(230, 126, 34, 0.12);
    border-radius: var(--radius-lg);
    overflow: hidden;
    background: white;
}

.card-header {
    background: linear-gradient(135deg, var(--canyon-sand), var(--canyon-orange));
    border-bottom: none;
    padding: 1.25rem 1.75rem;
    color: white;
}

.card-title {
    font-size: 1.125rem;
    font-weight: 700;
    margin: 0;
    color: white;
}

.card-body {
    padding: 1.5rem;
    background: white;
}

.table-responsive {
    border-radius: var(--radius-md);
    background: white;
    border: 1px solid var(--border-color);
    overflow-x: auto;
    max-width: 100%;
}

/* Remove border-radius from payment breakdown table and its container */
#paymentBreakdownTable {
    border-radius: 0 !important;
}

#paymentBreakdownTable thead,
#paymentBreakdownTable tbody,
#paymentBreakdownTable tr,
#paymentBreakdownTable th,
#paymentBreakdownTable td {
    border-radius: 0 !important;
}

#paymentBreakdownTable thead th:first-child {
    border-top-left-radius: 0 !important;
}

#paymentBreakdownTable thead th:last-child {
    border-top-right-radius: 0 !important;
}

#paymentBreakdownTable tbody tr:last-child td:first-child {
    border-bottom-left-radius: 0 !important;
}

#paymentBreakdownTable tbody tr:last-child td:last-child {
    border-bottom-right-radius: 0 !important;
}

/* Remove border-radius from the table-responsive container when it contains paymentBreakdownTable */
#paymentBreakdownTable.table {
    border-radius: 0 !important;
}

#paymentBreakdownTable + * {
    border-radius: 0 !important;
}

.table-responsive:has(#paymentBreakdownTable) {
    border-radius: 0 !important;
}

.table {
    margin-bottom: 0;
    font-size: 0.875rem;
    color: var(--text-primary);
    width: 100%;
    table-layout: auto;
}

.table th {
    background: linear-gradient(to bottom, var(--sand-light), var(--sand-medium));
    border-top: none;
    border-bottom: 2px solid var(--canyon-sand);
    font-weight: 700;
    font-size: 0.75rem;
    color: var(--canyon-rust);
    padding: 0.875rem 0.75rem;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table td {
    padding: 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    font-weight: 500;
    white-space: nowrap;
}

.table tbody tr {
    transition: all 0.2s ease;
    background: white;
}

.table tbody tr:nth-child(even) {
    background: #FDFAF7;
}

.table tbody tr:hover {
    background: linear-gradient(135deg, rgba(230, 126, 34, 0.05), rgba(243, 156, 18, 0.05));
    box-shadow: inset 0 0 0 1px rgba(230, 126, 34, 0.15);
}

.sort-icon {
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.75rem;
    color: var(--canyon-orange);
}

.sort-icon:hover {
    color: var(--canyon-rust) !important;
}

/* ============================================
   CHARTS - Fixed Hover Border Issue
   ============================================ */
.card {
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.1);
    background: white;
    transition: all 0.3s ease;
    overflow: hidden;
}

/* Remove hover effect for chart and table cards */
#chartSection .card:hover,
#tableSection .card:hover {
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.1);
    border-color: var(--border-color);
}

.card-header {
    background: linear-gradient(to bottom, var(--sand-light), var(--sand-medium));
    border-bottom: 2px solid var(--sand-light);
    padding: 1.125rem 1.5rem;
}

.card-title {
    font-size: 1.063rem;
    font-weight: 700;
    margin: 0;
    color: var(--canyon-rust);
}

.card-body {
    padding: 1.5rem;
}

/* ============================================
   DROPDOWN MENU - Improved Checkbox Display
   ============================================ */
.dropdown-menu {
    border-radius: var(--radius-md);
    border: 2px solid var(--canyon-sand);
    box-shadow: 0 8px 24px rgba(230, 126, 34, 0.2);
    padding: 0.625rem;
    background: white;
    min-width: 200px;
    max-height: 450px;
    overflow-y: auto;
}

.dropdown-menu .form-check {
    margin-bottom: 0.25rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
    background: white;
    border: 1px solid transparent;
    display: flex;
    align-items: center;
}

.dropdown-menu .form-check:last-child {
    margin-bottom: 0;
}

.dropdown-menu .form-check:hover {
    background: var(--sand-light);
    border-color: var(--border-color);
}

.dropdown-menu .form-check-input {
    width: 1.125rem;
    height: 1.125rem;
    border: 2px solid var(--canyon-sand);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-right: 0.625rem;
    margin-left: 0;
    background: white;
    flex-shrink: 0;
}

.dropdown-menu .form-check-input:checked {
    background-color: var(--canyon-orange);
    border-color: var(--canyon-orange);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3.5' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
    background-size: 85% 85%;
    background-position: center;
    background-repeat: no-repeat;
}

.dropdown-menu .form-check-input:focus {
    border-color: var(--canyon-orange);
    box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.15);
    outline: none;
}

.dropdown-menu .form-check-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
    cursor: pointer;
    user-select: none;
    margin-bottom: 0;
    padding-left: 0;
}

.dropdown-menu .form-check-label:hover {
    color: var(--canyon-orange);
}

.dropdown-item {
    border-radius: var(--radius-sm);
    padding: 0.5rem 0.75rem;
    color: var(--text-primary);
    font-weight: 500;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.dropdown-item:hover {
    background: linear-gradient(135deg, var(--sand-light), var(--sand-medium));
    color: var(--canyon-rust);
}

.dropdown-item:active {
    background: linear-gradient(135deg, var(--canyon-sand), var(--canyon-orange));
    color: white;
}

/* Dropdown scrollbar styling */
.dropdown-menu::-webkit-scrollbar {
    width: 6px;
}

.dropdown-menu::-webkit-scrollbar-track {
    background: var(--sand-light);
    border-radius: 3px;
}

.dropdown-menu::-webkit-scrollbar-thumb {
    background: var(--canyon-sand);
    border-radius: 3px;
}

.dropdown-menu::-webkit-scrollbar-thumb:hover {
    background: var(--canyon-orange);
}

/* Dropdown scrollbar styling */
.dropdown-menu::-webkit-scrollbar {
    width: 6px;
}

.dropdown-menu::-webkit-scrollbar-track {
    background: var(--sand-light);
    border-radius: 3px;
}

.dropdown-menu::-webkit-scrollbar-thumb {
    background: var(--canyon-sand);
    border-radius: 3px;
}

.dropdown-menu::-webkit-scrollbar-thumb:hover {
    background: var(--canyon-orange);
}

/* ============================================
   PREVENT HORIZONTAL SCROLL
   ============================================ */
body {
    overflow-x: hidden;
    max-width: 100vw;
}

.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
    overflow-x: hidden;
}

/* Ensure table doesn't cause horizontal scroll */
.table-responsive {
    -webkit-overflow-scrolling: touch;
}

/* Prevent any element from causing horizontal scroll */
* {
    max-width: 100%;
}

.row {
    margin-left: 0;
    margin-right: 0;
}

.col, [class*="col-"] {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

/* ============================================
   BADGES - Canyon Colors
   ============================================ */
.badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    letter-spacing: 0.025em;
    text-transform: uppercase;
}

.bg-primary {
    background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust)) !important;
    color: white !important;
}

.bg-success {
    background: linear-gradient(135deg, var(--success-green), #229954) !important;
    color: white !important;
}

.bg-warning {
    background: linear-gradient(135deg, var(--canyon-sand), var(--canyon-amber)) !important;
    color: white !important;
}

.bg-danger {
    background: linear-gradient(135deg, var(--danger-red), #A93226) !important;
    color: white !important;
}

.bg-info {
    background: linear-gradient(135deg, var(--info-blue), #2874A6) !important;
    color: white !important;
}

.bg-secondary {
    background: linear-gradient(135deg, var(--stone-gray), var(--rock-dark)) !important;
    color: white !important;
}

/* ============================================
   LOADING INDICATOR - Canyon Style
   ============================================ */
#loadingIndicator {
    padding: 5rem 0;
    text-align: center;
}

#loadingIndicator .spinner-border {
    width: 4rem;
    height: 4rem;
    border-width: 0.4rem;
    border-color: var(--canyon-orange);
    border-right-color: transparent;
    animation: spinner-border 0.75s linear infinite;
}

#loadingIndicator p {
    margin-top: 2rem;
    color: var(--canyon-rust);
    font-size: 1.125rem;
    font-weight: 600;
    letter-spacing: 0.025em;
}

/* ============================================
   UTILITY CLASSES
   ============================================ */
.fw-medium {
    font-weight: 500;
}

.fw-semibold {
    font-weight: 600;
}

.opacity-75 {
    opacity: 0.75;
}

.opacity-50 {
    opacity: 0.5;
}

.text-primary {
    color: var(--canyon-orange) !important;
}

.text-success {
    color: var(--success-green) !important;
}

.text-warning {
    color: var(--canyon-sand) !important;
}

.text-danger {
    color: var(--danger-red) !important;
}

.text-info {
    color: var(--info-blue) !important;
}

.text-dark {
    color: var(--text-primary) !important;
}

.text-muted {
    color: var(--text-secondary) !important;
}

/* ============================================
   CHARTS - Canyon Style
   ============================================ */
.card {
    border-radius: var(--radius-lg);
    border: 2px solid var(--border-color);
    box-shadow: var(--shadow-md);
    background: linear-gradient(135deg, #FFFFFF 0%, #FDF8F3 100%);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: var(--shadow-lg);
    border-color: var(--canyon-sand);
}

.card-header {
    background: linear-gradient(to bottom, var(--sand-light), var(--sand-medium));
    border-bottom: 3px solid var(--canyon-sand);
    padding: 1.25rem 1.5rem;
}

.card-title {
    font-size: 1.125rem;
    font-weight: 700;
    margin: 0;
    color: var(--canyon-rust);
    letter-spacing: -0.025em;
}

.card-body {
    padding: 1.5rem;
}

/* ============================================
   PAGINATION - Canyon Style
   ============================================ */
.pagination {
    gap: 0.375rem;
    margin: 0;
}

.page-item {
    margin: 0;
}

.page-link {
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    padding: 0.5rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
    background: white;
    margin: 0 2px;
}

.page-link:hover {
    background: var(--sand-light);
    border-color: var(--canyon-orange);
    color: var(--canyon-orange);
    transform: translateY(-1px);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
    border-color: var(--canyon-orange);
    color: white;
    box-shadow: 0 4px 12px rgba(230, 126, 34, 0.35);
    font-weight: 700;
    transform: translateY(-1px);
}

.page-item.active .page-link:hover {
    background: linear-gradient(135deg, var(--canyon-rust), var(--canyon-terracotta));
    border-color: var(--canyon-rust);
}

.page-item.disabled .page-link {
    background: var(--sand-light);
    border-color: var(--border-color);
    color: var(--stone-gray);
    opacity: 0.5;
    cursor: not-allowed;
}

.page-item.disabled .page-link:hover {
    transform: none;
    background: var(--sand-light);
    border-color: var(--border-color);
    color: var(--stone-gray);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust));
    border-color: var(--canyon-orange);
    color: white;
    box-shadow: 0 4px 12px rgba(230, 126, 34, 0.35);
    font-weight: 700;
    transform: translateY(-1px);
}

.page-item.active .page-link:hover {
    background: linear-gradient(135deg, var(--canyon-rust), var(--canyon-terracotta));
    border-color: var(--canyon-rust);
}

.page-item.disabled .page-link {
    background: var(--sand-light);
    border-color: var(--border-color);
    color: var(--stone-gray);
    opacity: 0.5;
    cursor: not-allowed;
}

.page-item.disabled .page-link:hover {
    transform: none;
}

/* ============================================
   DROPDOWN MENU - Canyon Style
   ============================================ */
.dropdown-menu {
    border-radius: var(--radius-md);
    border: 2px solid var(--canyon-sand);
    box-shadow: 0 8px 24px rgba(230, 126, 34, 0.15);
    padding: 0.5rem;
    background: white;
}

.dropdown-item {
    border-radius: var(--radius-sm);
    padding: 0.625rem 1rem;
    color: var(--text-primary);
    font-weight: 500;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background: linear-gradient(135deg, var(--sand-light), var(--sand-medium));
    color: var(--canyon-rust);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 768px) {
    .filter-toggle-btn {
        padding: 1.25rem 1.5rem;
    }
    
    .filter-toggle-icon {
        width: 45px;
        height: 45px;
    }
    
    .filter-toggle-text h5 {
        font-size: 1.125rem;
    }
    
    .filter-toggle-text p {
        font-size: 0.813rem;
    }
    
    .filter-section-group {
        padding: 1.5rem;
    }
    
    .payment-types-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-card {
        margin-bottom: 1rem;
    }
    
    .table {
        font-size: 0.813rem;
    }
    
    .table th,
    .table td {
        padding: 0.625rem 0.5rem;
    }
    
    .container-fluid {
        padding: 0 1rem;
    }
}

/* ============================================
   CARD FOOTER
   ============================================ */
.card-footer {
    background: linear-gradient(to top, var(--sand-light), var(--sand-medium));
    border-top: 2px solid var(--border-color);
    padding: 1.25rem 1.5rem;
}

.card-footer .text-muted {
    color: var(--text-secondary) !important;
    font-weight: 500;
    font-size: 0.875rem;
}
</style>
@endsection

@section('content')
<!-- Filter Section -->
<div class="filter-section">
    <!-- Improved Accordion Toggle Button -->
    <button type="button" class="filter-toggle-btn" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
        <div class="filter-toggle-content">
            <div class="filter-toggle-icon">
                <i class="fas fa-sliders-h"></i>
            </div>
            <div class="filter-toggle-text">
                <h5>Report Filters</h5>
                <p>Configure date range, packages, and payment options</p>
            </div>
        </div>
        <div class="filter-toggle-arrow">
            <i class="fas fa-chevron-down"></i>
        </div>
    </button>
    
    <!-- Collapsible Filter Form -->
    <div class="collapse mt-3" id="filterCollapse">
        <div class="card report-card">
            <div class="card-body">
                <form id="filterForm" action="#" method="post">
                    <!-- Business Filters Section -->
                    <div class="filter-section-group mb-4">
                        <div class="filter-section-header mb-3">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-building me-2"></i>
                                Business Filters
                            </h6>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="dateRange" class="form-label">Date Range</label>
                                    <input type="text" class="form-control" id="dateRange" name="dateRange" placeholder="Select date range" readonly>
                                    <input type="hidden" id="dateFrom" name="dateFrom">
                                    <input type="hidden" id="dateTo" name="dateTo">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="tourPackage" class="form-label">Tour Package</label>
                                    <select class="form-select" id="tourPackage" name="tourPackage">
                                        <option value="">All Packages</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="affiliate" class="form-label">Affiliate</label>
                                    <select class="form-select" id="affiliate" name="affiliate">
                                        <option value="">All Affiliates</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="viewBy" class="form-label">View By</label>
                                    <select class="form-select" id="viewBy" name="viewBy">
                                        <option value="tourdate">Tour Date</option>
                                        <option value="purchasedate">Purchase Date</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Filters Section -->
                    <div class="filter-section-group mb-4">
                        <div class="filter-section-header mb-3">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-credit-card me-2"></i>
                                Payment Filters
                            </h6>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-sm" id="selectAllPaymentTypes" style="background: white; border: 2px solid var(--border-color); color: var(--text-primary); padding: 0.3rem 0.75rem; font-weight: 600; border-radius: 5px 0 0 5px;">All</button>
                                            <button type="button" class="btn btn-sm" id="deselectAllPaymentTypes" style="background: white; border: 2px solid var(--border-color); border-left: none; color: var(--text-primary); padding: 0.3rem 0.75rem; font-weight: 600; border-radius: 0 5px 5px 0;">None</button>
                                        </div>
                                    </div>
                                    <div class="payment-types-grid">
                                        <div class="form-check">
                                            <input class="form-check-input payment-type-checkbox" type="checkbox" id="paymentCard" name="paymentTypes[]" value="Card" checked>
                                            <label class="form-check-label" for="paymentCard">Card</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input payment-type-checkbox" type="checkbox" id="paymentCash" name="paymentTypes[]" value="Cash" checked>
                                            <label class="form-check-label" for="paymentCash">Cash</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input payment-type-checkbox" type="checkbox" id="paymentInvoice" name="paymentTypes[]" value="Invoice" checked>
                                            <label class="form-check-label" for="paymentInvoice">Invoice</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input payment-type-checkbox" type="checkbox" id="paymentBillable" name="paymentTypes[]" value="Billable" checked>
                                            <label class="form-check-label" for="paymentBillable">Billable</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input payment-type-checkbox" type="checkbox" id="paymentWire" name="paymentTypes[]" value="Wire" checked>
                                            <label class="form-check-label" for="paymentWire">Wire</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input payment-type-checkbox" type="checkbox" id="paymentUnpaid" name="paymentTypes[]" value="Unpaid" checked>
                                            <label class="form-check-label" for="paymentUnpaid">Unpaid</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Filters Section -->
                    <div class="filter-section-group mb-4">
                        <div class="filter-section-header mb-3">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-tasks me-2"></i>
                                Status Filters
                            </h6>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="bookingStatus" class="form-label">Booking Status</label>
                                    <select class="form-select" id="bookingStatus" name="bookingStatus">
                                        <option value="">All Statuses</option>
                                        <option value="Booked">Booked</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="checkInStatus" class="form-label">Check-in Status</label>
                                    <select class="form-select" id="checkInStatus" name="checkInStatus">
                                        <option value="">All Check-in Status</option>
                                        <option value="Not Check-In">Not Check-In</option>
                                        <option value="1st Check-in">1st Check-in</option>
                                        <option value="2nd Check-in">2nd Check-in</option>
                                        <option value="Absent">Absent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="waiverStatus" class="form-label">Waiver Status</label>
                                    <select class="form-select" id="waiverStatus" name="waiverStatus">
                                        <option value="">All Waiver Status</option>
                                        <option value="Signed">Signed</option>
                                        <option value="Not Signed">Not Signed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="thirdPartyPlatform" class="form-label">Third Party Platform</label>
                                    <select class="form-select" id="thirdPartyPlatform" name="thirdPartyPlatform">
                                        <option value="">All Platforms</option>
                                        <!-- Options will be populated dynamically via JavaScript -->
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Options Section -->
                    <div class="filter-section-group mb-4">
                        <div class="filter-section-header mb-3">
                            <h6 class="mb-0 text-primary">
                                <i class="fas fa-cog me-2"></i>
                                Advanced Options
                            </h6>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="minAmount" class="form-label">Min Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="minAmount" name="minAmount" min="0" step="0.01" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="maxAmount" class="form-label">Max Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="maxAmount" name="maxAmount" min="0" step="0.01" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="mb-3 d-flex gap-2 w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        Generate Report
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="clearFiltersBtn">
                                        <i class="fas fa-times me-1"></i>
                                        Clear Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
                    
<!-- Loading Indicator -->
<div id="loadingIndicator" class="text-center" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
    </div>
    <p class="mt-2">Generating payment breakdown report...</p>
</div>
                    
<!-- Summary Cards -->
<div id="summarySection" class="row mb-4" style="display: none;">
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 id="totalBookings" class="mb-0">0</h3>
                    <p class="mt-2 opacity-75">Total Bookings</p>
                </div>
                <div class="fs-1 opacity-50">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 id="totalPax" class="mb-0">0</h3>
                    <p class="mt-2 opacity-75">Total Pax</p>
                </div>
                <div class="fs-1 opacity-50">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 id="totalRevenue" class="mb-0">$0</h3>
                    <p class="mt-2 opacity-75">Payment Total</p>
                </div>
                <div class="fs-1 opacity-50">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 id="totalRefunds" class="mb-0">$0</h3>
                    <p class="mt-2 opacity-75">Refund Total</p>
                </div>
                <div class="fs-1 opacity-50">
                    <i class="fas fa-undo"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 id="totalFees" class="mb-0">$0</h3>
                    <p class="mt-2 opacity-75">Fee Total</p>
                </div>
                <div class="fs-1 opacity-50">
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 id="totalDiscount" class="mb-0">$0</h3>
                    <p class="mt-2 opacity-75">Total Discount</p>
                </div>
                <div class="fs-1 opacity-50">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Net Total Highlight Card -->
<div id="netTotalSection" class="row mb-4" style="display: none;">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="background: white; border-left: 6px solid var(--canyon-orange) !important; border-radius: var(--radius-lg); overflow: hidden;">
            <div class="card-body text-center py-5 position-relative">
                <div class="position-absolute" style="top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.02; font-size: 10rem; color: var(--canyon-sand);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="position-relative" style="z-index: 1;">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--canyon-orange), var(--canyon-rust)); border-radius: 50%; box-shadow: 0 4px 20px rgba(230, 126, 34, 0.35);">
                            <i class="fas fa-calculator text-white" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <h2 class="mb-3" style="font-size: 1.125rem; font-weight: 700; color: var(--canyon-rust); text-transform: uppercase; letter-spacing: 0.15em;">
                        Total Net Amount
                    </h2>
                    <h1 id="totalNet" class="mb-3" style="font-size: 3.5rem; font-weight: 900; color: var(--success-green); line-height: 1; letter-spacing: -0.02em;">$0</h1>
                    <p class="mb-0" style="font-size: 0.875rem; font-weight: 500; color: var(--text-secondary); max-width: 500px; margin: 0 auto;">
                        After deducting fees and refunds from total payments
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Check-in Status Cards -->
<div id="checkInSection" class="row mb-4" style="display: none;">
    <div class="col-12 mb-3">
        <h5 class="text-primary">
            <i class="fas fa-clipboard-check me-2"></i>
            Check-in Status Overview
        </h5>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="summary-card border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 id="checkedIn2nd" class="mb-0 text-success">0</h4>
                    <p class="mb-0 opacity-75">2nd Check-in</p>
                </div>
                <div class="fs-1 opacity-50 text-success">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="summary-card border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 id="checkedIn1st" class="mb-0 text-warning">0</h4>
                    <p class="mb-0 opacity-75">1st Check-in</p>
                </div>
                <div class="fs-1 opacity-50 text-warning">
                    <i class="fas fa-check"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="summary-card border-secondary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 id="notCheckedIn" class="mb-0 text-secondary">0</h4>
                    <p class="mb-0 opacity-75">Not Check-In</p>
                </div>
                <div class="fs-1 opacity-50 text-secondary">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="summary-card border-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 id="absent" class="mb-0 text-danger">0</h4>
                    <p class="mb-0 opacity-75">Absent</p>
                </div>
                <div class="fs-1 opacity-50 text-danger">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        </div>
    </div>
</div>
                    
                    <!-- Payment Type Breakdown Chart -->
                    <div id="chartSection" class="row mb-4" style="display: none;">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Payment Type Distribution</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="paymentTypeChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Revenue vs Refunds</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueRefundChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Check-in Status Distribution</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="checkInChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Data Table -->
                    <div id="tableSection" style="display: none;">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="card-title mb-0">
                                        <i class="fas fa-table me-2"></i>
                                        Payment Breakdown Details
                                    </h3>
                                    <small class="text-muted" id="tableRecordCount">Showing 0 records</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-columns me-1"></i>
                                            Columns
                                        </button>
                                        <div class="dropdown-menu p-2" style="min-width: 200px;">
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-booking-id" checked>
                                                <label class="form-check-label" for="col-booking-id">Booking ID</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-tour-date" checked>
                                                <label class="form-check-label" for="col-tour-date">Tour Date</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-customer" checked>
                                                <label class="form-check-label" for="col-customer">Customer</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-payment-type" checked>
                                                <label class="form-check-label" for="col-payment-type">Payment Type</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-amount" checked>
                                                <label class="form-check-label" for="col-amount">Payment Total</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-fees" checked>
                                                <label class="form-check-label" for="col-fees">Fee Total</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-refund" checked>
                                                <label class="form-check-label" for="col-refund">Refund Total</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-discount" checked>
                                                <label class="form-check-label" for="col-discount">Total Discount</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-net-total" checked>
                                                <label class="form-check-label" for="col-net-total">Total Net</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-status" checked>
                                                <label class="form-check-label" for="col-status">Status</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-purchase-date">
                                                <label class="form-check-label" for="col-purchase-date">Purchase Date</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-tour-package">
                                                <label class="form-check-label" for="col-tour-package">Tour Package</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-pax">
                                                <label class="form-check-label" for="col-pax">Pax</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-checkin">
                                                <label class="form-check-label" for="col-checkin">Check-in</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-waiver">
                                                <label class="form-check-label" for="col-waiver">Waiver</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-platform">
                                                <label class="form-check-label" for="col-platform">Platform</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input column-toggle" type="checkbox" id="col-affiliate">
                                                <label class="form-check-label" for="col-affiliate">Affiliate</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-download me-1"></i>
                                            Export
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item export-btn" href="#" data-format="csv">
                                                <i class="fas fa-file-csv me-2"></i>
                                                Export as CSV
                                            </a>
                                            <a class="dropdown-item export-btn" href="#" data-format="excel">
                                                <i class="fas fa-file-excel me-2"></i>
                                                Export as Excel
                                            </a>
                                            <a class="dropdown-item export-btn" href="#" data-format="pdf">
                                                <i class="fas fa-file-pdf me-2"></i>
                                                Export as PDF
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Table Controls -->
                            <div class="card-body border-bottom">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-2">
                                            <label for="tablePageSize" class="form-label mb-0" style="white-space: nowrap;">Show:</label>
                                            <select class="form-select form-select-sm" id="tablePageSize" style="width: auto; min-width: 70px;">
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                                <option value="100" selected>100</option>
                                            </select>
                                            <span class="text-muted" style="white-space: nowrap;">entries</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end">
                                            <div class="input-group" style="max-width: 300px;">
                                                <span class="input-group-text">
                                                    <i class="fas fa-search"></i>
                                                </span>
                                                <input type="text" class="form-control" id="tableSearch" placeholder="Search in table...">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="paymentBreakdownTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="col-booking-id" data-sort="booking_id">
                                                <div class="d-flex align-items-center">
                                                    Booking ID
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-tour-date" data-sort="tour_date">
                                                <div class="d-flex align-items-center">
                                                    Tour Date
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-purchase-date" data-sort="purchase_date" style="display: none;">
                                                <div class="d-flex align-items-center">
                                                    Purchase Date
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-tour-package" data-sort="tour_package" style="display: none;">
                                                <div class="d-flex align-items-center">
                                                    Tour Package
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-customer" data-sort="customer_name">
                                                <div class="d-flex align-items-center">
                                                    Customer
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-payment-type" data-sort="payment_type">
                                                <div class="d-flex align-items-center">
                                                    Payment Type
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-amount text-end" data-sort="amount">
                                                <div class="d-flex align-items-center justify-content-end">
                                                    Payment Total
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-fees text-end" data-sort="fee_amount">
                                                <div class="d-flex align-items-center justify-content-end">
                                                    Fee Total
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-refund text-end" data-sort="refund_amount">
                                                <div class="d-flex align-items-center justify-content-end">
                                                    Refund Total
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-discount text-end" data-sort="discount">
                                                <div class="d-flex align-items-center justify-content-end">
                                                    Total Discount
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-net-total text-end" data-sort="net_total">
                                                <div class="d-flex align-items-center justify-content-end">
                                                    <strong>Total Net</strong>
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-pax text-center" data-sort="total_pax" style="display: none;">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    Pax
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-status" data-sort="booking_status">
                                                <div class="d-flex align-items-center">
                                                    Status
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-checkin" data-sort="check_in_status" style="display: none;">
                                                <div class="d-flex align-items-center">
                                                    Check-in
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-waiver" data-sort="waiver_status" style="display: none;">
                                                <div class="d-flex align-items-center">
                                                    Waiver
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-platform" data-sort="third_party_platform" style="display: none;">
                                                <div class="d-flex align-items-center">
                                                    Platform
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                            <th class="col-affiliate" data-sort="affiliate_name" style="display: none;">
                                                <div class="d-flex align-items-center">
                                                    Affiliate
                                                    <i class="fas fa-sort ms-1 text-muted sort-icon"></i>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="paymentBreakdownTableBody">
                                        <!-- Data will be populated via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="card-footer">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="text-muted" id="paginationInfo">
                                            Showing 0 to 0 of 0 entries
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <nav aria-label="Table pagination">
                                            <ul class="pagination pagination-sm justify-content-end mb-0" id="tablePagination">
                                                <!-- Pagination will be populated via JavaScript -->
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Payment Breakdown Report</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="form-group">
                        <label for="exportFormat">Export Format</label>
                        <select class="form-control" id="exportFormat" name="format" required>
                            <option value="csv">CSV (Comma Separated Values)</option>
                            <!-- <option value="excel">Excel (XLSX)</option> -->
                            <option value="pdf">PDF (Portable Document Format)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="includeCharts" name="includeCharts" checked>
                            <label class="custom-control-label" for="includeCharts">Include charts and summary</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="includeFilters" name="includeFilters" checked>
                            <label class="custom-control-label" for="includeFilters">Include applied filters</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmExportBtn">
                    <i class="fas fa-download mr-1"></i>
                    Export Report
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.umd.js"></script>
<script>
$(document).ready(function() {
    // Table management variables
    let currentTableData = [];
    let currentPage = 1;
    let pageSize = 100;
    let sortColumn = '';
    let sortDirection = 'asc';
    let searchTerm = '';
    
    // Column visibility state
    let columnVisibility = {
        'col-booking-id': true,
        'col-tour-date': true,
        'col-customer': true,
        'col-payment-type': true,
        'col-amount': true,
        'col-fees': true,
        'col-refund': true,
        'col-discount': true,
        'col-net-total': true,
        'col-status': true,
        'col-purchase-date': false,
        'col-tour-package': false,
        'col-pax': false,
        'col-checkin': false,
        'col-waiver': false,
        'col-platform': false,
        'col-affiliate': false
    };
    
    // Chart instances
    let paymentTypeChart = null;
    let revenueRefundChart = null;
    let checkInChart = null;
    
    // Initialize components
    initializeDatePickers();
    initializeSelect2();
    initializeColumnVisibility();
    loadFilterOptions();
    loadSavedFilters();
    initializeAccordion();
    
    // Event handlers - use more robust binding
    $(document).on('submit', '#filterForm', handleFilterSubmit);
    $('#resetFiltersBtn').on('click', resetFilters);
    $('#clearFiltersBtn').on('click', clearFilters);
    $('.export-btn').on('click', handleExport);
    $('#confirmExportBtn').on('click', confirmExport);
    
    // Payment type select all/none handlers
    $('#selectAllPaymentTypes').on('click', function() {
        $('.payment-type-checkbox').prop('checked', true);
    });
    
    $('#deselectAllPaymentTypes').on('click', function() {
        $('.payment-type-checkbox').prop('checked', false);
    });
    
    // Also bind to the submit button directly as a fallback
    $(document).on('click', '#filterForm button[type="submit"]', function(e) {
        console.log('Submit button clicked'); // Debug log
        e.preventDefault();
        e.stopPropagation();
        $('#filterForm').trigger('submit');
        return false;
    });
    
    // Table event handlers
    $('#tablePageSize').on('change', handlePageSizeChange);
    $('#tableSearch').on('input', debounce(handleTableSearch, 300));
    $('.column-toggle').on('change', handleColumnToggle);
    $(document).on('click', '[data-sort]', handleSort);
    $(document).on('click', '.page-link', handlePageChange);
    
    /**
     * Initialize date range picker with default values (today)
     */
    function initializeDatePickers() {
        const today = new Date();
        
        // Check if there are saved date values in the hidden fields
        const savedDateFrom = $('#dateFrom').val();
        const savedDateTo = $('#dateTo').val();
        
        let defaultDates = [today, today];
        
        // If saved dates exist, use them
        if (savedDateFrom && savedDateTo) {
            defaultDates = [new Date(savedDateFrom + 'T00:00:00'), new Date(savedDateTo + 'T00:00:00')];
        }
        
        // Initialize Flatpickr date range picker
        const fp = flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "M d, Y",
            defaultDate: defaultDates,
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    // Format dates as YYYY-MM-DD without timezone conversion
                    const formatDate = (date) => {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };
                    
                    $('#dateFrom').val(formatDate(selectedDates[0]));
                    $('#dateTo').val(formatDate(selectedDates[1]));
                } else if (selectedDates.length === 1) {
                    // Single date selected, use it for both from and to
                    const formatDate = (date) => {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };
                    
                    $('#dateFrom').val(formatDate(selectedDates[0]));
                    $('#dateTo').val(formatDate(selectedDates[0]));
                }
            },
            onReady: function(selectedDates, dateStr, instance) {
                // Set initial hidden field values if not already set
                if (!savedDateFrom || !savedDateTo) {
                    const formatDate = (date) => {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };
                    
                    $('#dateFrom').val(formatDate(today));
                    $('#dateTo').val(formatDate(today));
                }
            }
        });
        
        // Store flatpickr instance for later use
        window.dateRangePicker = fp;
    }
    
    /**
     * Initialize accordion toggle behavior
     */
    function initializeAccordion() {
        // Handle accordion toggle button state
        $('#filterCollapse').on('show.bs.collapse', function() {
            $('.filter-toggle-btn').addClass('active');
        });
        
        $('#filterCollapse').on('hide.bs.collapse', function() {
            $('.filter-toggle-btn').removeClass('active');
        });
    }
    
    /**
     * Initialize column visibility based on checkbox states
     */
    function initializeColumnVisibility() {
        // Set initial visibility based on checkbox states
        $('.column-toggle').each(function() {
            const columnClass = $(this).attr('id'); // Keep the original ID format (col-booking-id, etc.)
            const isChecked = $(this).is(':checked');
            columnVisibility[columnClass] = isChecked;
        });
        
        // Apply initial visibility to table headers and any existing content
        applyColumnVisibility();
    }
    
    /**
     * Initialize Select2 components - REMOVED as we're using standard selects
     */
    function initializeSelect2() {
        // Select2 removed for consistency with other form controls
        // Using standard Bootstrap form-select instead
    }
    
    /**
     * Load filter options from server
     */
    function loadFilterOptions() {
        $.ajax({
            url: '{{ route($routePrefix . ".report.payment-breakdown-report-filters") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    populateFilterOptions(response.filters);
                }
            },
            error: function(xhr) {
                console.error('Failed to load filter options:', xhr.responseText);
            }
        });
    }
    
    /**
     * Populate filter dropdown options
     */
    function populateFilterOptions(filters) {
        // Populate tour packages
        const tourPackageSelect = $('#tourPackage');
        tourPackageSelect.empty().append('<option value="">All Packages</option>');
        filters.tourPackages.forEach(function(package) {
            tourPackageSelect.append(`<option value="${package.id}">${package.name}</option>`);
        });
        
        // Populate affiliates
        const affiliateSelect = $('#affiliate');
        affiliateSelect.empty().append('<option value="">All Affiliates</option>');
        filters.affiliates.forEach(function(affiliate) {
            affiliateSelect.append(`<option value="${affiliate.id}">${affiliate.name}</option>`);
        });

        // Populate third party platforms
        const thirdPartySelect = $('#thirdPartyPlatform');
        thirdPartySelect.empty().append('<option value="">All Platforms</option>');
        if (filters.thirdPartyPlatforms && filters.thirdPartyPlatforms.length > 0) {
            filters.thirdPartyPlatforms.forEach(function(platform) {
                thirdPartySelect.append(`<option value="${platform}">${platform}</option>`);
            });
        }
    }
    
    /**
     * Load saved filters from session
     */
    function loadSavedFilters() {
        $.ajax({
            url: '{{ route($routePrefix . ".report.payment-breakdown-report-saved-filters") }}',
            method: 'GET',
            success: function(response) {
                if (response.success && response.hasFilters) {
                    applyFilters(response.filters);
                }
            }
        });
    }
    
    /**
     * Apply filters to form
     */
    function applyFilters(filters) {
        Object.keys(filters).forEach(function(key) {
            if (key === 'paymentTypes') {
                // Reverse map backend payment types to frontend values
                const reverseMapping = {
                    'Card': 'Card',
                    'Cash': 'Cash',
                    'Invoiced': 'Invoice',      // Backend 'Invoiced' -> Frontend 'Invoice'
                    'Billable': 'Billable',
                    'Check/Wire': 'Wire',       // Backend 'Check/Wire' -> Frontend 'Wire'
                    'Unpaid': 'Unpaid'
                };
                
                // Handle payment type checkboxes
                $('.payment-type-checkbox').prop('checked', false); // Uncheck all first
                if (Array.isArray(filters[key])) {
                    filters[key].forEach(function(value) {
                        // Map backend value to frontend value
                        const frontendValue = reverseMapping[value] || value;
                        $(`.payment-type-checkbox[value="${frontendValue}"]`).prop('checked', true);
                    });
                }
            } else if (key === 'dateFrom' || key === 'dateTo') {
                // Handle date range fields
                $(`#${key}`).val(filters[key]);
                
                // Update Flatpickr if both dates are present
                if (filters.dateFrom && filters.dateTo && window.dateRangePicker) {
                    window.dateRangePicker.setDate([
                        new Date(filters.dateFrom + 'T00:00:00'),
                        new Date(filters.dateTo + 'T00:00:00')
                    ]);
                }
            } else {
                const element = $(`#${key}`);
                if (element.length) {
                    if (element.is('select[multiple]')) {
                        element.val(filters[key]).trigger('change');
                    } else {
                        element.val(filters[key]);
                    }
                }
            }
        });
    }
    
    /**
     * Handle filter form submission
     */
    function handleFilterSubmit(e) {
        console.log('handleFilterSubmit called'); // Debug log
        e.preventDefault();
        e.stopPropagation(); // Also stop event propagation
        
        const formData = $(this).serialize();
        console.log('Form data:', formData); // Debug log
        
        showLoading();
        
        $.ajax({
            url: '{{ route($routePrefix . ".report.payment-breakdown-report-data") }}',
            method: 'GET',
            data: formData,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    if (response.data && Array.isArray(response.data)) {
                        displayReportData(response.data);
                    }
                    if (response.summary && typeof response.summary === 'object') {
                        updateSummaryCards(response.summary);
                    }
                    if (response.charts && typeof response.charts === 'object') {
                        updateCharts(response.charts);
                    }
                    // Handle check-in data from operational metrics
                    if (response.operationalMetrics && response.operationalMetrics.check_in_status) {
                        updateCheckInDataFromMetrics(response.operationalMetrics.check_in_status);
                    }
                    
                    // Close the accordion after successful filter
                    $('#filterCollapse').collapse('hide');
                    
                    // Scroll to top smoothly
                    $('html, body').animate({ scrollTop: 0 }, 600);
                } else {
                    console.error(response.message || 'Failed to generate report');
                    alert('Failed to generate report: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr) {
                hideLoading();
                const errorMessage = xhr.responseJSON?.message || 'Failed to generate report';
                console.error(errorMessage);
            }
        });
        
        return false; // Additional prevention of form submission
    }
    
    /**
     * Reset filters to default
     */
    function resetFilters() {
        $.ajax({
            url: '{{ route($routePrefix . ".report.payment-breakdown-report-reset-filters") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    applyFilters(response.default_filters);
                }
            }
        });
    }
    
    /**
     * Clear all filters
     */
    function clearFilters() {
        $('#filterForm')[0].reset();
        $('.select2').val(null).trigger('change');
        $('.date-preset').removeClass('active');
        initializeDatePickers();
        
        // Check all payment type checkboxes by default
        $('.payment-type-checkbox').prop('checked', true);
        
        // Reset booking status to show all options
        $('#bookingStatus').val('');
    }
    
    /**
     * Handle export button clicks
     */
    function handleExport(e) {
        e.preventDefault();
        
        const format = $(this).data('format');
        $('#exportFormat').val(format);
        $('#exportModal').modal('show');
    }
    
    /**
     * Confirm export
     */
    function confirmExport() {
        const format = $('#exportFormat').val();
        const formData = $('#filterForm').serialize();
        const exportOptions = $('#exportForm').serialize();
        
        const exportUrl = '{{ route($routePrefix . ".report.payment-breakdown-report-export") }}?' + formData + '&' + exportOptions;
        
        // Create temporary link to trigger download
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = `payment-breakdown-report.${format}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        $('#exportModal').modal('hide');
    }
    
    /**
     * Display report data in table with pagination
     */
    function displayReportData(data) {
        currentTableData = data || [];
        currentPage = 1;
        renderTable();
        
        // Ensure column visibility is applied after rendering
        setTimeout(function() {
            applyColumnVisibility();
        }, 100);
        
        $('#tableSection').show();
    }
    
    /**
     * Render table with current data, pagination, and sorting
     */
    function renderTable() {
        const tbody = $('#paymentBreakdownTableBody');
        tbody.empty();
        
        // Apply search filter - only search visible columns
        let filteredData = currentTableData;
        if (searchTerm) {
            filteredData = currentTableData.filter(row => {
                // Define column mappings to data keys
                const columnMappings = {
                    'col-booking-id': 'booking_id',
                    'col-tour-date': 'tour_date',
                    'col-purchase-date': 'purchase_date',
                    'col-tour-package': 'tour_package',
                    'col-customer': 'customer_name',
                    'col-payment-type': 'payment_type',
                    'col-amount': 'amount',
                    'col-fees': 'fee_amount',
                    'col-refund': 'refund_amount',
                    'col-discount': 'discount',
                    'col-net-total': 'net_total',
                    'col-pax': 'tourists',
                    'col-status': 'booking_status',
                    'col-checkin': 'check_in_status',
                    'col-waiver': 'waiver_status',
                    'col-platform': 'third_party_platform',
                    'col-affiliate': 'affiliate_name'
                };
                
                // Only search in visible columns
                return Object.keys(columnMappings).some(columnClass => {
                    if (columnVisibility[columnClass]) {
                        const dataKey = columnMappings[columnClass];
                        const value = row[dataKey];
                        // Check if value exists and is not null/undefined
                        if (value !== null && value !== undefined && value !== '') {
                            return String(value).toLowerCase().includes(searchTerm.toLowerCase());
                        }
                    }
                    return false;
                });
            });
        }
        
        // Apply sorting
        if (sortColumn) {
            filteredData.sort((a, b) => {
                let aVal = a[sortColumn] || '';
                let bVal = b[sortColumn] || '';
                
                // Handle numeric values
                if (!isNaN(aVal) && !isNaN(bVal)) {
                    aVal = parseFloat(aVal);
                    bVal = parseFloat(bVal);
                } else {
                    aVal = String(aVal).toLowerCase();
                    bVal = String(bVal).toLowerCase();
                }
                
                if (sortDirection === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });
        }
        
        // Calculate pagination
        const totalRecords = filteredData.length;
        const totalPages = Math.ceil(totalRecords / pageSize);
        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, totalRecords);
        const pageData = filteredData.slice(startIndex, endIndex);
        
        // Update record count
        $('#tableRecordCount').text(`Showing ${totalRecords} records`);
        
        if (pageData.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="14" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-search fa-2x mb-2"></i>
                            <p class="mb-0">No data found for the selected criteria</p>
                        </div>
                    </td>
                </tr>
            `);
        } else {
            pageData.forEach(function(row) {
                const netAmount = (row.amount || 0) - (row.refund_amount || 0);
                const tr = `
                    <tr>
                        <td class="col-booking-id" ${getColumnVisibility('col-booking-id')}>
                            <span class="fw-medium text-primary">${row.booking_id || ''}</span>
                        </td>
                        <td class="col-tour-date" ${getColumnVisibility('col-tour-date')}>
                            <span class="text-dark">${formatDate(row.tour_date) || ''}</span>
                        </td>
                        <td class="col-purchase-date" ${getColumnVisibility('col-purchase-date')}>
                            <span class="text-dark">${formatDate(row.purchase_date) || ''}</span>
                        </td>
                        <td class="col-tour-package" ${getColumnVisibility('col-tour-package')}>
                            <span class="text-dark">${row.tour_package || ''}</span>
                        </td>
                        <td class="col-customer" ${getColumnVisibility('col-customer')}>
                            <span class="text-dark">${row.customer_name || ''}</span>
                        </td>
                        <td class="col-payment-type" ${getColumnVisibility('col-payment-type')}>
                            <span class="badge bg-${getPaymentTypeBadge(row.payment_type)}">${row.payment_type || ''}</span>
                        </td>
                        <td class="col-amount text-end" ${getColumnVisibility('col-amount')}>
                            <span class="fw-medium text-success">${formatCurrency(row.amount || 0)}</span>
                        </td>
                        <td class="col-fees text-end" ${getColumnVisibility('col-fees')}>
                            <span class="fw-medium text-warning">${formatCurrency(row.fee_amount || 0)}</span>
                        </td>
                        <td class="col-refund text-end" ${getColumnVisibility('col-refund')}>
                            <span class="fw-medium text-danger">${formatCurrency(row.refund_amount || 0)}</span>
                        </td>
                        <td class="col-discount text-end" ${getColumnVisibility('col-discount')}>
                            <span class="fw-medium text-info">${formatCurrency(row.discount || 0)}</span>
                        </td>
                        <td class="col-net-total text-end" ${getColumnVisibility('col-net-total')}>
                            <span class="fw-bold text-primary">${formatCurrency(row.net_total || 0)}</span>
                        </td>
                        <td class="col-pax text-center" ${getColumnVisibility('col-pax')}>
                            <span class="badge bg-info">${row.tourists || 0}</span>
                        </td>
                        <td class="col-status" ${getColumnVisibility('col-status')}>
                            <span class="badge bg-${getStatusBadge(row.booking_status)}">${row.booking_status || ''}</span>
                        </td>
                        <td class="col-checkin" ${getColumnVisibility('col-checkin')}>
                            <span class="badge bg-${getCheckInBadge(row.check_in_status)}">${row.check_in_status || ''}</span>
                        </td>
                        <td class="col-waiver" ${getColumnVisibility('col-waiver')}>
                            <span class="badge bg-${getWaiverBadge(row.waiver_status)}">${row.waiver_status || ''}</span>
                        </td>
                        <td class="col-platform" ${getColumnVisibility('col-platform')}>
                            <span class="text-dark">${row.third_party_platform || 'Direct'}</span>
                        </td>
                        <td class="col-affiliate" ${getColumnVisibility('col-affiliate')}>
                            <span class="text-dark">${row.affiliate_name || ''}</span>
                        </td>
                    </tr>
                `;
                tbody.append(tr);
            });
        }
        
        // Update pagination info and controls
        updatePaginationInfo(startIndex + 1, endIndex, totalRecords);
        renderPagination(totalPages);
        
        // Apply column visibility after rendering
        applyColumnVisibility();
    }
    
    /**
     * Get column visibility style
     */
    function getColumnVisibility(columnClass) {
        return columnVisibility[columnClass] ? '' : 'style="display: none;"';
    }
    
    /**
     * Apply column visibility to existing table
     */
    function applyColumnVisibility() {
        Object.keys(columnVisibility).forEach(function(columnClass) {
            if (columnVisibility[columnClass]) {
                $(`.${columnClass}`).show();
            } else {
                $(`.${columnClass}`).hide();
            }
        });
    }
    
    /**
     * Handle page size change
     */
    function handlePageSizeChange() {
        pageSize = parseInt($(this).val());
        currentPage = 1;
        renderTable();
    }
    
    /**
     * Handle table search
     */
    function handleTableSearch() {
        searchTerm = $('#tableSearch').val();
        currentPage = 1;
        renderTable();
    }
    
    /**
     * Handle column visibility toggle
     */
    function handleColumnToggle() {
        const columnClass = $(this).attr('id'); // Keep the original ID format (col-booking-id, etc.)
        const isVisible = $(this).is(':checked');
        
        // Update the visibility state
        columnVisibility[columnClass] = isVisible;
        
        // Apply visibility to current table
        if (isVisible) {
            $(`.${columnClass}`).show();
        } else {
            $(`.${columnClass}`).hide();
        }
    }
    
    /**
     * Handle table sorting
     */
    function handleSort() {
        const column = $(this).data('sort');
        
        if (sortColumn === column) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = column;
            sortDirection = 'asc';
        }
        
        // Update sort icons
        $('.sort-icon').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
        const icon = $(this).find('.sort-icon');
        icon.removeClass('fa-sort').addClass(sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
        
        renderTable();
    }
    
    /**
     * Handle page change
     */
    function handlePageChange(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPage) {
            currentPage = page;
            renderTable();
        }
    }
    
    /**
     * Update pagination info
     */
    function updatePaginationInfo(start, end, total) {
        $('#paginationInfo').text(`Showing ${start} to ${end} of ${total} entries`);
    }
    
    /**
     * Render pagination controls
     */
    function renderPagination(totalPages) {
        const pagination = $('#tablePagination');
        pagination.empty();
        
        if (totalPages <= 1) return;
        
        // Previous button
        pagination.append(`
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `);
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            pagination.append(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
            if (startPage > 2) {
                pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            pagination.append(`
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            pagination.append(`<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`);
        }
        
        // Next button
        pagination.append(`
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `);
    }
    
    /**
     * Update summary cards
     */
    function updateSummaryCards(summary) {
        $('#totalBookings').text(summary.totalBookings || 0);
        $('#totalPax').text(summary.totalPax || 0);
        $('#totalRevenue').text(formatCurrency(summary.totalRevenue || 0));
        $('#totalRefunds').text(formatCurrency(summary.totalRefunds || 0));
        $('#totalFees').text(formatCurrency(summary.totalFees || 0));
        $('#totalDiscount').text(formatCurrency(summary.totalDiscount || 0));
        $('#totalNet').text(formatCurrency(summary.totalNet || 0));
        
        $('#summarySection').show();
        $('#netTotalSection').show();
    }
    
    /**
     * Update check-in data display from operational metrics
     */
    function updateCheckInDataFromMetrics(checkInMetrics) {
        // Update check-in summary cards using backend calculated data
        $('#checkedIn2nd').text(checkInMetrics['2nd Check-in']?.pax || 0);
        $('#checkedIn1st').text(checkInMetrics['1st Check-in']?.pax || 0);
        $('#notCheckedIn').text(checkInMetrics['Not Check-In']?.pax || 0);
        $('#absent').text(checkInMetrics['Absent']?.pax || 0);
        
        // Show check-in section
        $('#checkInSection').show();
        
        // Prepare data for chart
        const checkInTotals = {
            '2nd Check-in': checkInMetrics['2nd Check-in']?.pax || 0,
            '1st Check-in': checkInMetrics['1st Check-in']?.pax || 0,
            'Not Check-In': checkInMetrics['Not Check-In']?.pax || 0,
            'Absent': checkInMetrics['Absent']?.pax || 0
        };
        
        // Update check-in chart
        updateCheckInChart(checkInTotals);
    }
    
    /**
     * Update check-in data display (fallback method)
     */
    function updateCheckInData(bookingData) {
        // Calculate check-in status totals from booking data
        const checkInTotals = {
            'Not Check-In': 0,
            '1st Check-in': 0,
            '2nd Check-in': 0,
            'Absent': 0
        };
        
        // Count check-in statuses from booking details
        bookingData.forEach(function(booking) {
            const status = booking.check_in_status || 'Not Check-In';
            const pax = parseInt(booking.total_pax) || 0;
            
            if (checkInTotals.hasOwnProperty(status)) {
                checkInTotals[status] += pax;
            } else {
                checkInTotals['Not Check-In'] += pax;
            }
        });
        
        // Update check-in summary cards
        $('#checkedIn2nd').text(checkInTotals['2nd Check-in']);
        $('#checkedIn1st').text(checkInTotals['1st Check-in']);
        $('#notCheckedIn').text(checkInTotals['Not Check-In']);
        $('#absent').text(checkInTotals['Absent']);
        
        // Show check-in section
        $('#checkInSection').show();
        
        // Update check-in chart
        updateCheckInChart(checkInTotals);
    }
    
    /**
     * Update charts
     */
    function updateCharts(chartData) {
        if (chartData && typeof chartData === 'object') {
            if (chartData.payment_types) {
                updatePaymentTypeChart(chartData.payment_types);
            }
            if (chartData.revenue_refunds) {
                updateRevenueRefundChart(chartData.revenue_refunds);
            }
        }
        
        $('#chartSection').show();
    }
    
    /**
     * Update check-in status chart
     */
    function updateCheckInChart(checkInTotals) {
        const ctx = document.getElementById('checkInChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (checkInChart && typeof checkInChart.destroy === 'function') {
            checkInChart.destroy();
        }
        
        const labels = [];
        const values = [];
        const colors = [];
        
        // Only include statuses that have values > 0
        Object.keys(checkInTotals).forEach(function(status) {
            if (checkInTotals[status] > 0) {
                labels.push(status);
                values.push(checkInTotals[status]);
                
                // Set colors based on status
                switch(status) {
                    case '2nd Check-in':
                        colors.push('#28a745'); // Green
                        break;
                    case '1st Check-in':
                        colors.push('#ffc107'); // Yellow
                        break;
                    case 'Not Check-In':
                        colors.push('#6c757d'); // Gray
                        break;
                    case 'Absent':
                        colors.push('#dc3545'); // Red
                        break;
                    default:
                        colors.push('#007bff'); // Blue
                }
            }
        });
        
        checkInChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} pax (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Update payment type chart
     */
    function updatePaymentTypeChart(data) {
        const ctx = document.getElementById('paymentTypeChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (paymentTypeChart && typeof paymentTypeChart.destroy === 'function') {
            paymentTypeChart.destroy();
        }
        
        paymentTypeChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels || [],
                datasets: [{
                    data: data.values || [],
                    backgroundColor: [
                        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14',
                        '#17a2b8', '#6c757d', '#343a40', '#f8f9fa'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = formatCurrency(context.parsed);
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Update revenue vs refund chart
     */
    function updateRevenueRefundChart(data) {
        const ctx = document.getElementById('revenueRefundChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (revenueRefundChart && typeof revenueRefundChart.destroy === 'function') {
            revenueRefundChart.destroy();
        }
        
        revenueRefundChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Revenue', 'Refunds', 'Net Revenue'],
                datasets: [{
                    data: [data.revenue || 0, data.refunds || 0, data.net_revenue || 0],
                    backgroundColor: ['#28a745', '#dc3545', '#007bff'],
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Utility functions
     */
    function showLoading() {
        $('#loadingIndicator').show();
        $('#summarySection, #chartSection, #tableSection').hide();
    }
    
    function hideLoading() {
        $('#loadingIndicator').hide();
    }
    
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
    
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    function getPaymentTypeBadge(type) {
        const badges = {
            'Card': 'primary',
            'Cash': 'success',
            'Invoice': 'warning',
            'Billable': 'info',
            'Wire': 'secondary',
            'Unpaid': 'danger'
        };
        return badges[type] || 'secondary';
    }
    
    function getStatusBadge(status) {
        const badges = {
            'Booked': 'success',
            'Cancelled': 'danger'
        };
        return badges[status] || 'secondary';
    }
    
    function getCheckInBadge(status) {
        const badges = {
            'Not Check-In': 'secondary',
            '1st Check-in': 'warning',
            '2nd Check-in': 'success',
            'Absent': 'danger'
        };
        return badges[status] || 'secondary';
    }
    
    function getWaiverBadge(status) {
        const badges = {
            'Signed': 'success',
            'Not Signed': 'warning'
        };
        return badges[status] || 'secondary';
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
});
</script>
@endsection