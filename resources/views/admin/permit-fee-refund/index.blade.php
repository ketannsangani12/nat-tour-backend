@extends('layouts.default-2')

@section('title', 'Permit Fee Refund Management')

@section('css-libraries')
<style>
.permit-refund-container {
    padding: 20px;
    background: #f8f9fa;
    min-height: 100vh;
}

.permit-refund-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.permit-refund-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-bottom: none;
}

.permit-refund-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.filter-section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-primary-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    min-width: 120px;
    text-align: center;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: white;
    text-decoration: none;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.btn-success-custom {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-success-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-warning-custom {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border: none;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-warning-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn-info-custom {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border: none;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-info-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn-secondary-custom {
    background: #6b7280;
    border: none;
    color: white;
    padding: 12px 16px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary-custom:hover {
    background: #4b5563;
}

#searchBooking:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.highlight-row {
    background: #fef3c7 !important;
    animation: pulse 1s ease-in-out;
}

@keyframes pulse {
    0%, 100% { background: #fef3c7; }
    50% { background: #fde68a; }
}

.status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border-left: 4px solid;
    transition: all 0.3s ease;
}

.status-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.status-card.pending {
    border-left-color: #f59e0b;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

.status-card.processing {
    border-left-color: #3b82f6;
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
}

.status-card.completed {
    border-left-color: #10b981;
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
}

.status-card.failed {
    border-left-color: #ef4444;
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
}

.status-card h4 {
    font-size: 36px;
    font-weight: 700;
    margin: 0 0 8px 0;
    color: #1f2937;
}

.status-card p {
    margin: 0;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 1px;
}

.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.table-custom {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table-custom thead {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.table-custom th {
    padding: 16px 12px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
}

.table-custom td {
    padding: 16px 12px;
    border-bottom: 1px solid #f3f4f6;
    color: #6b7280;
    font-size: 14px;
}

.table-custom tbody tr:hover {
    background: #f9fafb;
}

.badge-custom {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-pending {
    background: #fef3c7;
    color: #92400e;
}

.badge-processing {
    background: #dbeafe;
    color: #1e40af;
}

.badge-completed {
    background: #d1fae5;
    color: #065f46;
}

.badge-failed {
    background: #fee2e2;
    color: #991b1b;
}

.btn-sm-custom {
    padding: 8px 16px;
    font-size: 12px;
    border-radius: 6px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-process {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-process:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
}

.loading-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-content {
    background: white;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f4f6;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.pagination-custom {
    display: flex;
    justify-content: center;
    margin-top: 30px;
}

.pagination-custom .pagination {
    display: flex;
    gap: 8px;
}

.pagination-custom .page-link {
    padding: 10px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    color: #6b7280;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination-custom .page-link:hover,
.pagination-custom .page-item.active .page-link {
    background: #667eea;
    border-color: #667eea;
    color: white;
}

.error-text {
    color: #ef4444;
    font-size: 12px;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .status-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .table-container {
        overflow-x: auto;
    }
}
</style>
@endsection

@section('main-content')
<div class="permit-refund-container">
    <div class="permit-refund-card">
        <div class="permit-refund-header">
            <h3>🎫 Permit Fee Refund Management</h3>
        </div>
        
        <div style="padding: 25px;">
            <!-- Filter Form -->
            <div class="filter-section">
                <form id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="package_id">📦 Package</label>
                            <select name="package_id" id="package_id" required>
                                <option value="">Select Package</option>
                                @foreach($packages as $package)
                                    <option value="{{ $package->id }}">{{ $package->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="year">📅 Year</label>
                            <input type="number" name="year" id="year" value="2026" required>
                        </div>
                        <div class="filter-group">
                            <label for="tour_date">🗓️ Specific Date</label>
                            <input type="date" name="tour_date" id="tour_date">
                        </div>
                        <div class="filter-group">
                            <label for="tour_date_from">📅 Date From</label>
                            <input type="date" name="tour_date_from" id="tour_date_from">
                        </div>
                        <div class="filter-group">
                            <label for="tour_date_to">📅 Date To</label>
                            <input type="date" name="tour_date_to" id="tour_date_to">
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn-primary-custom">🔍 Load Bookings</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button id="processBulk" class="btn-success-custom" disabled>⚡ Process All Pending</button>
                <button id="clearPending" class="btn-warning-custom" disabled>🗑️ Clear Pending</button>
                <button id="refreshStatus" class="btn-info-custom">🔄 Refresh Status</button>
                <div style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
                    <input type="text" id="searchBooking" placeholder="🔍 Search Booking ID..." value="{{ $search ?? '' }}" style="padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; width: 200px; font-size: 14px;">
                    <button id="searchBtn" class="btn-primary-custom" style="padding: 12px 16px;">🔍</button>
                    <button id="clearSearch" class="btn-secondary-custom" @if(empty($search)) style="display: none;" @endif>✕ Clear</button>
                    <button id="checkBookingBtn" class="btn-info-custom">🔎 Check Why Missing</button>
                </div>
            </div>

            <!-- Status Cards -->
            <div class="status-cards">
                <div class="status-card pending">
                    <h4 id="pendingCount">0</h4>
                    <p>⏳ Pending</p>
                </div>
                <div class="status-card processing">
                    <h4 id="processingCount">0</h4>
                    <p>⚙️ Processing</p>
                </div>
                <div class="status-card completed">
                    <h4 id="completedCount">0</h4>
                    <p>✅ Completed</p>
                </div>
                <div class="status-card failed">
                    <h4 id="failedCount">0</h4>
                    <p>❌ Failed</p>
                </div>
            </div>

            <!-- Queue Items Table -->
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>🆔 Booking ID</th>
                            <th>📦 Package</th>
                            <th>🗓️ Tour Date</th>
                            <th>👥 Total Pax</th>
                            <th>💵 Refund ($5/pax)</th>
                            <th>📊 Status</th>
                            <th>⏰ Created</th>
                            <th>✅ Processed</th>
                            <th>⚠️ Error</th>
                            <th>🎯 Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($queueItems as $item)
                        <tr>
                            <td><strong>{{ $item->booking_id }}</strong></td>
                            <td>{{ $item->package->name ?? 'N/A' }}</td>
                            <td>{{ $item->tour_date ? $item->tour_date->format('Y-m-d') : 'N/A' }}</td>
                            <td>{{ $item->tourists }}</td>
                            <td><strong>${{ number_format($item->tourists * 5, 2) }}</strong></td>
                            <td>
                                <span class="badge-custom badge-{{ $item->status }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $item->processed_at ? $item->processed_at->format('Y-m-d H:i') : '-' }}</td>
                            <td>
                                @if($item->error_message)
                                    <span class="error-text" title="{{ $item->error_message }}">
                                        {{ Str::limit($item->error_message, 30) }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($item->status === 'pending')
                                    <button class="btn-sm-custom btn-process process-single" data-id="{{ $item->id }}">
                                        ⚡ Process
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px; color: #9ca3af;">
                                <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                                <strong>No bookings loaded yet</strong><br>
                                <small>Use the filter form above to load bookings for processing</small>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($queueItems->hasPages())
            <div class="pagination-custom">
                {{ $queueItems->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="loading-modal" id="loadingModal">
    <div class="loading-content">
        <div class="spinner"></div>
        <p><strong>Processing...</strong></p>
        <small>Please wait while we process your request</small>
    </div>
</div>
@endsection

@section('js-custom')
<script>
$(document).ready(function() {
    // Load status on page load
    loadStatus();

    // Auto-refresh status every 10 seconds
    setInterval(loadStatus, 10000);

    // Search booking functionality - client side for current page
    $('#searchBooking').on('input', function() {
        const searchVal = $(this).val().trim().toLowerCase();
        const $clearBtn = $('#clearSearch');
        
        if (searchVal) {
            $clearBtn.show();
            $('.table-custom tbody tr').each(function() {
                const bookingId = $(this).find('td:first').text().trim().toLowerCase();
                if (bookingId.includes(searchVal)) {
                    $(this).show().addClass('highlight-row');
                } else {
                    $(this).hide().removeClass('highlight-row');
                }
            });
        } else {
            $clearBtn.hide();
            $('.table-custom tbody tr').show().removeClass('highlight-row');
        }
    });

    // Clear search
    $('#clearSearch').on('click', function() {
        $('#searchBooking').val('').trigger('input');
        // Reload page without search param
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        window.location.href = url.toString();
    });

    // Server-side search on Enter key
    $('#searchBooking').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            doServerSearch();
        }
    });

    // Search button click
    $('#searchBtn').on('click', function() {
        doServerSearch();
    });

    function doServerSearch() {
        const searchVal = $('#searchBooking').val().trim();
        const url = new URL(window.location.href);
        if (searchVal) {
            url.searchParams.set('search', searchVal);
            url.searchParams.set('page', 1);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }

    // Check why booking is missing
    $('#checkBookingBtn').on('click', function() {
        const bookingId = $('#searchBooking').val().trim();
        const year = $('#year').val();
        
        if (!bookingId) {
            showAlert('⚠️ Please enter a booking ID in the search box first', 'warning');
            return;
        }
        
        if (!year) {
            showAlert('⚠️ Please select a year first', 'warning');
            return;
        }

        const button = $(this);
        button.prop('disabled', true).html('🔍 Checking...');
        
        $.post('{{ route("admin.permit-fee-refund.check-booking") }}', {
            booking_id: bookingId,
            year: year,
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                let message = '✅ Booking ' + response.booking_id + ' is eligible for refund!<br><br>';
                if (response.info && response.info.length > 0) {
                    message += '<strong>Info:</strong><ul style="text-align:left;margin-top:5px;">';
                    response.info.forEach(function(item) {
                        if (item) message += '<li>' + item + '</li>';
                    });
                    message += '</ul>';
                }
                if (response.note) {
                    message += '<br><div style="background:#fef3c7;padding:10px;border-radius:8px;margin-top:10px;"><strong>⚠️ Note:</strong> ' + response.note + '</div>';
                }
                showCheckResult(message);
            } else {
                let message = '❌ Booking ' + response.booking_id + ' is not eligible:<br>';
                if (response.info && response.info.length > 0) {
                    message += '<ul style="text-align:left;margin-top:5px;">';
                    response.info.forEach(function(item) {
                        if (item) message += '<li>' + item + '</li>';
                    });
                    message += '</ul>';
                }
                message += '<br><strong>Issues:</strong><ul style="text-align:left;margin-top:5px;">';
                response.reasons.forEach(function(reason) {
                    message += '<li style="margin-bottom:5px;">' + reason + '</li>';
                });
                message += '</ul>';
                showCheckResult(message);
            }
        })
        .fail(function(xhr) {
            showAlert('❌ Error: ' + (xhr.responseJSON?.error || 'Unknown error'), 'error');
        })
        .always(function() {
            button.prop('disabled', false).html('🔎 Check Why Missing');
        });
    });

    // Filter form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        loadBookings();
    });

    // Process bulk
    $('#processBulk').on('click', function() {
        if (!confirm('🚀 Are you sure you want to process all pending bookings?\n\nThis action will queue all pending bookings for processing.')) return;
        
        const packageId = $('#package_id').val();
        const year = $('#year').val();
        
        if (!packageId || !year) {
            showAlert('⚠️ Please select package and year first', 'warning');
            return;
        }

        showLoading();
        
        $.post('{{ route("admin.permit-fee-refund.process-bulk") }}', {
            package_id: packageId,
            year: year,
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            showAlert('✅ ' + response.message, 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .fail(function(xhr) {
            showAlert('❌ Error: ' + (xhr.responseJSON?.error || 'Unknown error'), 'error');
        })
        .always(function() {
            hideLoading();
        });
    });

    // Clear pending
    $('#clearPending').on('click', function() {
        if (!confirm('🗑️ Are you sure you want to clear all pending bookings?\n\nThis will remove all pending bookings from the queue.')) return;
        
        const packageId = $('#package_id').val();
        const year = $('#year').val();
        
        if (!packageId || !year) {
            showAlert('⚠️ Please select package and year first', 'warning');
            return;
        }

        $.post('{{ route("admin.permit-fee-refund.clear-pending") }}', {
            package_id: packageId,
            year: year,
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            showAlert('✅ ' + response.message, 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .fail(function(xhr) {
            showAlert('❌ Error: ' + (xhr.responseJSON?.error || 'Unknown error'), 'error');
        });
    });

    // Process single
    $(document).on('click', '.process-single', function() {
        const id = $(this).data('id');
        const button = $(this);
        
        if (!confirm('⚡ Process this booking?\n\nThis will queue the booking for processing.')) return;
        
        button.prop('disabled', true).html('⏳ Processing...');
        
        $.post('{{ route("admin.permit-fee-refund.process-single", ":id") }}'.replace(':id', id), {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            showAlert('✅ ' + response.message, 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .fail(function(xhr) {
            showAlert('❌ Error: ' + (xhr.responseJSON?.error || 'Unknown error'), 'error');
            button.prop('disabled', false).html('⚡ Process');
        });
    });

    // Refresh status
    $('#refreshStatus').on('click', function() {
        const button = $(this);
        button.html('🔄 Refreshing...');
        loadStatus();
        setTimeout(() => {
            button.html('🔄 Refresh Status');
        }, 1000);
    });

    // Date field logic
    $('#tour_date').on('change', function() {
        if ($(this).val()) {
            $('#tour_date_from, #tour_date_to').val('').prop('disabled', true);
        } else {
            $('#tour_date_from, #tour_date_to').prop('disabled', false);
        }
    });

    $('#tour_date_from, #tour_date_to').on('change', function() {
        if ($('#tour_date_from').val() || $('#tour_date_to').val()) {
            $('#tour_date').val('').prop('disabled', true);
        } else {
            $('#tour_date').prop('disabled', false);
        }
    });

    function loadBookings() {
        showLoading();
        
        $.post('{{ route("admin.permit-fee-refund.load-bookings") }}', $('#filterForm').serialize() + '&_token={{ csrf_token() }}')
        .done(function(response) {
            showAlert('✅ ' + response.message, 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .fail(function(xhr) {
            showAlert('❌ Error: ' + (xhr.responseJSON?.error || 'Unknown error'), 'error');
        })
        .always(function() {
            hideLoading();
        });
    }

    function loadStatus() {
        $.get('{{ route("admin.permit-fee-refund.status") }}')
        .done(function(data) {
            $('#pendingCount').text(data.pending);
            $('#processingCount').text(data.processing);
            $('#completedCount').text(data.completed);
            $('#failedCount').text(data.failed);
            
            // Enable/disable bulk buttons based on pending count
            $('#processBulk, #clearPending').prop('disabled', data.pending === 0);
        });
    }

    function showLoading() {
        $('#loadingModal').show();
    }

    function hideLoading() {
        $('#loadingModal').hide();
    }

    function showAlert(message, type) {
        // Create alert element
        const alertClass = type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : 'alert-danger';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible" style="position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ${message}
            </div>
        `;
        
        $('body').append(alertHtml);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }

    function showCheckResult(message) {
        // Remove existing modal
        $('#checkResultModal').remove();
        
        const modalHtml = `
            <div id="checkResultModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 10000;">
                <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                    <h4 style="margin-top: 0; color: #991b1b;">🔍 Booking Check Result</h4>
                    <div style="color: #374151;">${message}</div>
                    <button onclick="$('#checkResultModal').remove();" style="margin-top: 20px; padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer;">Close</button>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
    }
});
</script>

<style>
.alert {
    border-radius: 8px;
    border: none;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border-left: 4px solid #f59e0b;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}
</style>
@endsection