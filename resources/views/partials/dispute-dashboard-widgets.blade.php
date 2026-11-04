{{-- Dispute Dashboard Widgets --}}
@php
    $disputeSummary = app(\App\Services\Dispute\DisputeNotificationService::class)->getDisputeSummary();
@endphp

<section class="py-4 bg-[#F9FAFB]" id="dispute-dashboard-widgets">
    <div class="w-full container">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <span class="mr-2">⚖️</span>
                Payment Disputes Overview
            </h3>
            <div class="flex items-center space-x-2">
                <span class="text-xs text-gray-500">Last updated: {{ now()->format('g:i A') }}</span>
                <button onclick="refreshDisputeWidgets()" class="text-blue-600 hover:text-blue-800 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="flex flex-wrap -mx-3" id="dispute-widgets-container">
            {{-- Active Disputes Widget --}}
            <x-dispute-dashboard-widget 
                title="Active Disputes" 
                icon="🔥" 
                color="red" 
                size="small">
                <div class="space-y-2">
                    <div class="text-2xl font-bold text-gray-900" id="active-disputes-count">
                        {{ $disputeSummary['active_disputes'] }}
                    </div>
                    <div class="text-xs text-gray-600">
                        Requiring attention
                    </div>
                    @if($disputeSummary['active_disputes'] > 0)
                    <div class="mt-2">
                        <a href="{{ route(prefixedRouteName('disputes.index')) }}?status=active" 
                           class="text-xs text-red-600 hover:text-red-800 font-medium">
                            View All →
                        </a>
                    </div>
                    @endif
                </div>
            </x-dispute-dashboard-widget>

            {{-- Urgent Disputes Widget --}}
            <x-dispute-dashboard-widget 
                title="Urgent Disputes" 
                icon="⚠️" 
                color="yellow" 
                size="small">
                <div class="space-y-2">
                    <div class="text-2xl font-bold text-gray-900" id="urgent-disputes-count">
                        {{ $disputeSummary['urgent_disputes'] }}
                    </div>
                    <div class="text-xs text-gray-600">
                        Due within 3 days
                    </div>
                    @if($disputeSummary['urgent_disputes'] > 0)
                    <div class="mt-2">
                        <a href="{{ route(prefixedRouteName('disputes.index')) }}?urgent=true" 
                           class="text-xs text-yellow-600 hover:text-yellow-800 font-medium">
                            Review Now →
                        </a>
                    </div>
                    @endif
                </div>
            </x-dispute-dashboard-widget>

            {{-- Overdue Disputes Widget --}}
            <x-dispute-dashboard-widget 
                title="Overdue Disputes" 
                icon="🚨" 
                color="red" 
                size="small">
                <div class="space-y-2">
                    <div class="text-2xl font-bold text-gray-900" id="overdue-disputes-count">
                        {{ $disputeSummary['overdue_disputes'] }}
                    </div>
                    <div class="text-xs text-gray-600">
                        Past deadline
                    </div>
                    @if($disputeSummary['overdue_disputes'] > 0)
                    <div class="mt-2">
                        <a href="{{ route(prefixedRouteName('disputes.index')) }}?overdue=true" 
                           class="text-xs text-red-600 hover:text-red-800 font-medium animate-pulse">
                            Action Required →
                        </a>
                    </div>
                    @endif
                </div>
            </x-dispute-dashboard-widget>

            {{-- Total Disputes Widget --}}
            <x-dispute-dashboard-widget 
                title="Total Disputes" 
                icon="📊" 
                color="blue" 
                size="small">
                <div class="space-y-2">
                    <div class="text-2xl font-bold text-gray-900" id="total-disputes-count">
                        {{ $disputeSummary['total_disputes'] }}
                    </div>
                    <div class="text-xs text-gray-600">
                        All time
                    </div>
                    <div class="mt-2">
                        <a href="{{ route(prefixedRouteName('disputes.index')) }}" 
                           class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                            View All →
                        </a>
                    </div>
                </div>
            </x-dispute-dashboard-widget>
        </div>

        {{-- Detailed Status Breakdown --}}
        @if($disputeSummary['active_disputes'] > 0 || $disputeSummary['urgent_disputes'] > 0)
        <div class="mt-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                    <span class="mr-2">📈</span>
                    Dispute Status Breakdown
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="dispute-status-breakdown">
                    {{-- This will be populated by JavaScript --}}
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">
                            <span class="inline-block w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                            Response rate: <span class="font-medium" id="response-rate">--</span>
                        </span>
                        <span class="text-gray-600">
                            <span class="inline-block w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                            Win rate: <span class="font-medium" id="win-rate">--</span>
                        </span>
                        <span class="text-gray-600">
                            <span class="inline-block w-2 h-2 bg-yellow-500 rounded-full mr-2"></span>
                            Avg. response time: <span class="font-medium" id="avg-response-time">--</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Quick Actions Panel --}}
        @can('view-payment-dispute')
        <div class="mt-6">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6">
                <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                    <span class="mr-2">⚡</span>
                    Quick Actions
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="{{ route(prefixedRouteName('disputes.index')) }}?status=warning_needs_response" 
                       class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:border-blue-300 hover:shadow-sm transition-all duration-200">
                        <div class="flex-shrink-0 w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                            <span class="text-yellow-600">⚠️</span>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Review Pending</div>
                            <div class="text-xs text-gray-600">Check disputes needing response</div>
                        </div>
                    </a>

                    <a href="{{ route(prefixedRouteName('disputes.index')) }}?urgent=true" 
                       class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:border-red-300 hover:shadow-sm transition-all duration-200">
                        <div class="flex-shrink-0 w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                            <span class="text-red-600">🚨</span>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Urgent Items</div>
                            <div class="text-xs text-gray-600">Handle time-sensitive disputes</div>
                        </div>
                    </a>

                    <a href="{{ route(prefixedRouteName('disputes.index')) }}?view=analytics" 
                       class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:border-green-300 hover:shadow-sm transition-all duration-200">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <span class="text-green-600">📊</span>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">View Analytics</div>
                            <div class="text-xs text-gray-600">Analyze dispute trends</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        @endcan
    </div>
</section>

<script>
// Dispute Dashboard Widget JavaScript
function refreshDisputeWidgets() {
    // Show loading state
    const container = document.getElementById('dispute-widgets-container');
    if (container) {
        container.style.opacity = '0.6';
    }

    // Fetch updated dispute summary
    fetch('{{ route(prefixedRouteName("disputes.dashboard-data")) }}')
        .then(response => response.json())
        .then(data => {
            updateWidgetCounts(data);
            updateStatusBreakdown(data);
            updateMetrics(data);
            
            // Remove loading state
            if (container) {
                container.style.opacity = '1';
            }
        })
        .catch(error => {
            console.error('Error refreshing dispute widgets:', error);
            
            // Remove loading state
            if (container) {
                container.style.opacity = '1';
            }
        });
}

function updateWidgetCounts(data) {
    const elements = {
        'active-disputes-count': data.active_disputes,
        'urgent-disputes-count': data.urgent_disputes,
        'overdue-disputes-count': data.overdue_disputes,
        'total-disputes-count': data.total_disputes
    };

    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            // Animate count change
            animateCountChange(element, parseInt(element.textContent) || 0, value);
        }
    });
}

function updateStatusBreakdown(data) {
    const breakdown = document.getElementById('dispute-status-breakdown');
    if (breakdown && data.status_breakdown) {
        breakdown.innerHTML = '';
        
        Object.entries(data.status_breakdown).forEach(([status, count]) => {
            const statusDiv = document.createElement('div');
            statusDiv.className = 'text-center p-3 bg-gray-50 rounded-lg';
            statusDiv.innerHTML = `
                <div class="text-lg font-bold text-gray-900">${count}</div>
                <div class="text-xs text-gray-600 capitalize">${status.replace('_', ' ')}</div>
            `;
            breakdown.appendChild(statusDiv);
        });
    }
}

function updateMetrics(data) {
    if (data.metrics) {
        const responseRate = document.getElementById('response-rate');
        const winRate = document.getElementById('win-rate');
        const avgResponseTime = document.getElementById('avg-response-time');

        if (responseRate) responseRate.textContent = data.metrics.response_rate || '--';
        if (winRate) winRate.textContent = data.metrics.win_rate || '--';
        if (avgResponseTime) avgResponseTime.textContent = data.metrics.avg_response_time || '--';
    }
}

function animateCountChange(element, from, to) {
    const duration = 500; // ms
    const steps = 20;
    const stepValue = (to - from) / steps;
    const stepDuration = duration / steps;
    
    let current = from;
    let step = 0;
    
    const timer = setInterval(() => {
        step++;
        current += stepValue;
        
        if (step >= steps) {
            element.textContent = to;
            clearInterval(timer);
        } else {
            element.textContent = Math.round(current);
        }
    }, stepDuration);
}

// Auto-refresh every 5 minutes
setInterval(refreshDisputeWidgets, 5 * 60 * 1000);

// Initial load of detailed data
document.addEventListener('DOMContentLoaded', function() {
    refreshDisputeWidgets();
});
</script>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

#dispute-dashboard-widgets .transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}

#dispute-dashboard-widgets .hover\:shadow-md:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
</style>