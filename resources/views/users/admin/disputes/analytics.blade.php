@extends('layouts.default-2')

@section('main-content')

{{-- ── Page Header ── --}}
<div class="bg-white border-b border-gray-100 shadow-sm">
  <div class="w-full container">
    <div class="flex items-center justify-between py-4 gap-4 flex-wrap">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M1.5 13.5l4.5-4.5 3 3 4.5-6 3.5 4" stroke="#3B82F6" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="1.5" y="1.5" width="15" height="15" rx="2" stroke="#3B82F6" stroke-width="1.2"/>
          </svg>
        </div>
        <div>
          <div class="flex items-center gap-1.5 text-xs text-gray-400 mb-0.5">
            <a href="{{ route(prefixedRouteName('disputes.index')) }}" class="hover:text-blue-600 transition-colors">Disputes</a>
            <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3.5 2l3 3-3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="text-gray-600">Analytics</span>
          </div>
          <h1 class="text-base font-bold text-gray-900 leading-tight">Dispute Analytics & Reporting</h1>
        </div>
      </div>
      <a href="{{ route(prefixedRouteName('disputes.analytics.export')) }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v8M4.5 6.5L7 9l2.5-2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M1.5 10.5V12a.5.5 0 00.5.5h10a.5.5 0 00.5-.5v-1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        Export CSV
      </a>
    </div>
  </div>
</div>

{{-- ── Main Content ── --}}
<div class="bg-[#F8F9FB] min-h-screen">
  <div class="w-full container py-5 space-y-5">

    {{-- ── Filters Card ── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
      <form method="POST" action="{{ route(prefixedRouteName('disputes.analytics.filters')) }}" id="analyticsFiltersForm"
            class="flex flex-wrap items-end gap-4">
        @csrf

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Date Range</label>
          <div class="flex items-center gap-2">
            <input type="text" id="date_from" name="date_from"
                   class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors cursor-pointer w-40"
                   value="{{ session('dispute_analytics_filters.date_from', $dateRange['start']->format('Y-m-d')) }}"
                   placeholder="From date" readonly>
            <span class="text-xs text-gray-400 font-medium">to</span>
            <input type="text" id="date_to" name="date_to"
                   class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors cursor-pointer w-40"
                   value="{{ session('dispute_analytics_filters.date_to', $dateRange['end']->format('Y-m-d')) }}"
                   placeholder="To date" readonly>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Group By</label>
          <select name="group_by"
                  class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
            <option value="day"   {{ $trendsGroupBy === 'day'   ? 'selected' : '' }}>Day</option>
            <option value="week"  {{ $trendsGroupBy === 'week'  ? 'selected' : '' }}>Week</option>
            <option value="month" {{ $trendsGroupBy === 'month' ? 'selected' : '' }}>Month</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Status</label>
          <select name="status"
                  class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
            <option value="">All Statuses</option>
            @foreach(['warning_needs_response' => 'Needs Response', 'warning_under_review' => 'Under Review', 'under_review' => 'Under Review (Charge)', 'charge_refunded' => 'Charge Refunded', 'won' => 'Won', 'lost' => 'Lost'] as $val => $label)
              <option value="{{ $val }}" {{ ($statusFilter ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="flex items-center gap-2">
          <button type="submit"
                  class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors">
            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M1.5 10l3-3 2 2 3-4 2.5 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Update
          </button>
          <form method="POST" action="{{ route(prefixedRouteName('disputes.analytics.filters.clear')) }}" id="clearAnalyticsFiltersForm" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
              Clear
            </button>
          </form>
        </div>

        <div class="ml-auto text-xs text-gray-400 self-end pb-2">
          {{ $dateRange['start']->format('M j, Y') }} — {{ $dateRange['end']->format('M j, Y') }}
          &nbsp;·&nbsp; grouped by {{ $trendsGroupBy }}
        </div>
      </form>
    </div>

    {{-- ── Financial Impact Stats ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="#3B82F6" stroke-width="1.3"/><path d="M9 5.5v7M6.5 7.5C6.5 6.4 7.6 5.5 9 5.5s2.5.9 2.5 2-.9 2-2.5 2-2.5.9-2.5 2 1.1 2 2.5 2 2.5-.9 2.5-2" stroke="#3B82F6" stroke-width="1.2" stroke-linecap="round"/></svg>
        </div>
        <div class="min-w-0">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Total Disputed</p>
          <p class="text-xl font-bold text-gray-900 leading-tight truncate">{{ currencyFormation(false, true) }}{{ number_format($analytics['financial_impact']['summary']['total_disputed_amount'], 2) }}</p>
          <p class="text-xs text-gray-400 mt-0.5">{{ $analytics['financial_impact']['summary']['total_disputes'] }} disputes</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2L2 15h14L9 2z" stroke="#EF4444" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 7v4M9 12.5v.5" stroke="#EF4444" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div class="min-w-0">
          <p class="text-xs font-medium text-red-400 uppercase tracking-wide">Lost Revenue</p>
          <p class="text-xl font-bold text-red-600 leading-tight truncate">{{ currencyFormation(false, true) }}{{ number_format($analytics['financial_impact']['summary']['lost_revenue'], 2) }}</p>
          <p class="text-xs text-red-400 mt-0.5">Revenue lost</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2.5" y="5.5" width="13" height="9" rx="1.5" stroke="#D97706" stroke-width="1.3"/><path d="M5.5 5.5V4a2.5 2.5 0 015 0v1.5" stroke="#D97706" stroke-width="1.3" stroke-linecap="round"/></svg>
        </div>
        <div class="min-w-0">
          <p class="text-xs font-medium text-amber-500 uppercase tracking-wide">Total Fees</p>
          <p class="text-xl font-bold text-amber-600 leading-tight truncate">{{ currencyFormation(false, true) }}{{ number_format($analytics['financial_impact']['summary']['total_fees'], 2) }}</p>
          <p class="text-xs text-amber-400 mt-0.5">Dispute fees</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="#16A34A" stroke-width="1.3"/><path d="M5.5 9l2.5 2.5 4.5-5" stroke="#16A34A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="min-w-0">
          <p class="text-xs font-medium text-green-500 uppercase tracking-wide">Recovery Rate</p>
          <p class="text-xl font-bold text-green-600 leading-tight">{{ $analytics['financial_impact']['summary']['recovery_rate'] }}%</p>
          <p class="text-xs text-green-400 mt-0.5">Disputes won</p>
        </div>
      </div>

    </div>

    {{-- ── Reversed Amount Banner (only when DISPUTE_REVERSAL setting is on) ── --}}
    @if(hasSetting('DISPUTE_REVERSAL'))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
      <div class="bg-white rounded-2xl border border-orange-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M15 9A6 6 0 113 9" stroke="#F97316" stroke-width="1.4" stroke-linecap="round"/><path d="M15 5v4h-4" stroke="#F97316" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="min-w-0">
          <p class="text-xs font-medium text-orange-400 uppercase tracking-wide">Total Reversed (Payout Deduction)</p>
          <p class="text-xl font-bold text-orange-600 leading-tight truncate">{{ currencyFormation(false, true) }}{{ number_format($totalReversed, 2) }}</p>
          <p class="text-xs text-orange-400 mt-0.5">Net transfer amount reversed from connected account</p>
        </div>
      </div>
      <div class="bg-orange-50 rounded-2xl border border-orange-100 shadow-sm px-5 py-4 flex items-center gap-3">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="flex-shrink-0 text-orange-400"><circle cx="8" cy="8" r="6.5" stroke="#F97316" stroke-width="1.2"/><path d="M8 5v3.5M8 10v.5" stroke="#F97316" stroke-width="1.5" stroke-linecap="round"/></svg>
        <p class="text-xs text-orange-700">
          <strong>Lost Revenue</strong> ({{ currencyFormation(false, true) }}{{ number_format($analytics['financial_impact']['summary']['lost_revenue'], 2) }}) = full disputed charge amount.<br>
          <strong>Total Reversed</strong> = net transfer amount actually deducted from payout (excludes Stripe platform fee).
        </p>
      </div>
    </div>
    @endif

    {{-- ── Net Financial Impact Banner ── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5 flex items-center justify-between gap-4 flex-wrap">
      <div class="flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="#EF4444" stroke-width="1.4"/><path d="M10 6v5M10 13v.5" stroke="#EF4444" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-gray-900">Net Financial Impact</h3>
          <p class="text-xs text-gray-400 mt-0.5">Total cost including lost revenue and fees — {{ $analytics['basic_statistics']['total_disputes'] }} total disputes</p>
        </div>
      </div>
      <div class="text-right">
        <div class="text-3xl font-bold text-red-600">
          {{ currencyFormation(false, true) }}{{ number_format($analytics['financial_impact']['summary']['net_financial_impact'], 2) }}
        </div>
        <div class="text-xs text-gray-400 mt-0.5">cumulative financial impact</div>
      </div>
    </div>

    {{-- ── Charts Row ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      {{-- Volume Trends --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M1.5 11l3-3 2.5 2 3-4 3.5 3" stroke="#3B82F6" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <h2 class="text-sm font-semibold text-gray-900">Dispute Volume Trends</h2>
            <p class="text-xs text-gray-400 mt-0.5">Count and amounts over time</p>
          </div>
        </div>
        <div class="p-5">
          <div class="h-64 relative" id="volumeTrendsChart">
            <div class="absolute inset-0 flex items-center justify-center text-gray-400">
              <div class="text-center">
                <svg class="animate-spin w-8 h-8 mx-auto mb-2 text-blue-300" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <p class="text-sm">Loading chart…</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Reason Breakdown --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
          <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M7.5 2a5.5 5.5 0 100 11A5.5 5.5 0 007.5 2z" stroke="#8B5CF6" stroke-width="1.2"/><path d="M7.5 2v5.5L11 10" stroke="#8B5CF6" stroke-width="1.2" stroke-linecap="round"/></svg>
          </div>
          <div>
            <h2 class="text-sm font-semibold text-gray-900">Dispute Reasons</h2>
            <p class="text-xs text-gray-400 mt-0.5">Breakdown by dispute category</p>
          </div>
        </div>
        <div class="p-5">
          <div class="h-64 relative" id="reasonBreakdownChart">
            <div class="absolute inset-0 flex items-center justify-center text-gray-400">
              <div class="text-center">
                <svg class="animate-spin w-8 h-8 mx-auto mb-2 text-purple-300" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <p class="text-sm">Loading chart…</p>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    {{-- ── Detail Tables Row ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      {{-- Reason Analysis Table --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
          <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2.5 4.5h10M2.5 7.5h10M2.5 10.5h6" stroke="#F97316" stroke-width="1.2" stroke-linecap="round"/></svg>
          </div>
          <div>
            <h2 class="text-sm font-semibold text-gray-900">Detailed Reason Analysis</h2>
            <p class="text-xs text-gray-400 mt-0.5">Performance metrics by dispute reason</p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-50">
                <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-5">Reason</th>
                <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Count</th>
                <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Amount</th>
                <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-5">Win Rate</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              @forelse($analytics['reason_breakdown']['by_reason'] as $reason)
              <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="py-3 px-5">
                  <div class="text-sm font-medium text-gray-800">{{ $reason['reason_label'] }}</div>
                  <div class="text-xs text-gray-400">{{ $reason['category'] }}</div>
                </td>
                <td class="py-3 px-3 text-right text-sm font-semibold text-gray-800">{{ $reason['count'] }}</td>
                <td class="py-3 px-3 text-right text-sm text-gray-600">{{ currencyFormation(false, true) }}{{ number_format($reason['total_amount'], 0) }}</td>
                <td class="py-3 px-5 text-right">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                    {{ $reason['win_rate'] >= 50 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                    {{ $reason['win_rate'] }}%
                  </span>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="py-10 text-center text-sm text-gray-400">No reason data available.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      {{-- Monthly Financial Impact Table --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
          <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><rect x="1.5" y="2" width="12" height="11" rx="1.5" stroke="#EF4444" stroke-width="1.2"/><path d="M5 2V5M10 2V5M1.5 7h12" stroke="#EF4444" stroke-width="1.2" stroke-linecap="round"/></svg>
          </div>
          <div>
            <h2 class="text-sm font-semibold text-gray-900">Monthly Financial Impact</h2>
            <p class="text-xs text-gray-400 mt-0.5">Breakdown of costs by month</p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-50">
                <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-5">Month</th>
                <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Disputes</th>
                <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Lost</th>
                <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Fees</th>
                <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-5">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              @forelse($analytics['financial_impact']['monthly_breakdown'] as $month)
              <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="py-3 px-5 text-sm font-medium text-gray-800">
                  {{ \Carbon\Carbon::createFromFormat('Y-m', $month['month'])->format('M Y') }}
                </td>
                <td class="py-3 px-3 text-right text-sm text-gray-600">{{ $month['dispute_count'] }}</td>
                <td class="py-3 px-3 text-right text-sm font-medium text-red-500">{{ currencyFormation(false, true) }}{{ number_format($month['lost_amount'], 0) }}</td>
                <td class="py-3 px-3 text-right text-sm text-amber-600">{{ currencyFormation(false, true) }}{{ number_format($month['total_fees'], 0) }}</td>
                <td class="py-3 px-5 text-right text-sm font-bold text-red-600">{{ currencyFormation(false, true) }}{{ number_format($month['net_impact'], 0) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="py-10 text-center text-sm text-gray-400">No monthly data available.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    </div>

    {{-- ── Individual Dispute List ── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2.5 4.5h10M2.5 7.5h10M2.5 10.5h6" stroke="#3B82F6" stroke-width="1.2" stroke-linecap="round"/></svg>
        </div>
        <div>
          <h2 class="text-sm font-semibold text-gray-900">Individual Disputes</h2>
          <p class="text-xs text-gray-400 mt-0.5">{{ count($analytics['dispute_list']) }} dispute(s) in selected period{{ $statusFilter ? ' · filtered by: ' . $statusFilter : '' }}</p>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-50 bg-gray-50/60">
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Booking</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Customer</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Status</th>
              <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Disputed</th>
              @if(hasSetting('DISPUTE_REVERSAL'))
              <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Reversed (Payout)</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Report Period</th>
              @endif
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Reason</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-3">Opened</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Lost On</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            @forelse($analytics['dispute_list'] as $d)
            @php
              $statusColors = [
                'won'                    => 'bg-green-100 text-green-700',
                'lost'                   => 'bg-red-100 text-red-700',
                'under_review'           => 'bg-blue-100 text-blue-700',
                'warning_under_review'   => 'bg-blue-100 text-blue-700',
                'warning_needs_response' => 'bg-amber-100 text-amber-700',
                'charge_refunded'        => 'bg-purple-100 text-purple-700',
              ];
              $statusColor = $statusColors[$d['status']] ?? 'bg-gray-100 text-gray-600';
              $statusLabel = ucwords(str_replace('_', ' ', $d['status']));
            @endphp
            <tr class="hover:bg-gray-50/50 transition-colors">
              <td class="py-3 px-4">
                <a href="{{ route(prefixedRouteName('disputes.show'), $d['id']) }}" class="text-sm font-medium text-blue-600 hover:underline">#{{ $d['booking_id'] ?? '—' }}</a>
                @if($d['stripe_dispute_id'])
                  <div class="text-xs text-gray-400 font-mono mt-0.5">{{ $d['stripe_dispute_id'] }}</div>
                @endif
              </td>
              <td class="py-3 px-3 text-sm text-gray-700">{{ $d['customer_name'] }}</td>
              <td class="py-3 px-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColor }}">{{ $statusLabel }}</span>
              </td>
              <td class="py-3 px-3 text-right text-sm font-semibold text-gray-800">{{ currencyFormation(false, true) }}{{ number_format($d['amount'], 2) }}</td>
              @if(hasSetting('DISPUTE_REVERSAL'))
              <td class="py-3 px-3 text-right text-sm font-semibold {{ $d['reversed_amount'] ? 'text-orange-600' : 'text-gray-300' }}">
                {{ $d['reversed_amount'] ? currencyFormation(false, true) . number_format($d['reversed_amount'], 2) : '—' }}
              </td>
              <td class="py-3 px-3 text-sm {{ $d['report_period'] ? 'text-gray-700' : 'text-gray-300' }}">{{ $d['report_period'] ?? '—' }}</td>
              @endif
              <td class="py-3 px-3 text-sm text-gray-600">{{ ucwords(str_replace('_', ' ', $d['reason'] ?? '')) }}</td>
              <td class="py-3 px-3 text-sm text-gray-500">{{ $d['created_at'] ?? '—' }}</td>
              <td class="py-3 px-4 text-sm {{ $d['lost_at'] ? 'text-red-500 font-medium' : 'text-gray-300' }}">{{ $d['lost_at'] ?? '—' }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="{{ hasSetting('DISPUTE_REVERSAL') ? 9 : 7 }}" class="py-10 text-center text-sm text-gray-400">No disputes found for the selected filters.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

@endsection

@section('js-custom')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fpOptions = {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'F j, Y',
        altInputClass: 'px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors cursor-pointer w-40',
        disableMobile: true,
    };
    flatpickr('#date_from', fpOptions);
    flatpickr('#date_to', fpOptions);

    const analyticsData = @json($analytics);
    const currencySymbol = '{{ currencyFormation(false, true) }}';

    initVolumeTrends();
    initReasonBreakdown();

    function initVolumeTrends() {
        const container = document.getElementById('volumeTrendsChart');
        if (!container) return;
        container.innerHTML = '<canvas></canvas>';
        const canvas = container.querySelector('canvas');
        const data = analyticsData.volume_trends;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.map(d => d.period),
                datasets: [
                    {
                        label: 'Dispute Count',
                        data: data.map(d => d.dispute_count),
                        borderColor: 'rgb(59,130,246)',
                        backgroundColor: 'rgba(59,130,246,0.08)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Amount (' + currencySymbol + ')',
                        data: data.map(d => d.total_amount),
                        borderColor: 'rgb(239,68,68)',
                        backgroundColor: 'rgba(239,68,68,0.06)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        tension: 0.35,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { font: { size: 11 }, color: '#9CA3AF' }
                    },
                    y: {
                        position: 'left',
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { font: { size: 11 }, color: '#9CA3AF' },
                        title: { display: true, text: 'Count', color: '#9CA3AF', font: { size: 11 } }
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            font: { size: 11 }, color: '#9CA3AF',
                            callback: v => currencySymbol + Number(v).toLocaleString()
                        },
                        title: { display: true, text: 'Amount', color: '#9CA3AF', font: { size: 11 } }
                    }
                },
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 12 }, color: '#6B7280', boxWidth: 12, padding: 16 } },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.85)',
                        titleFont: { size: 12 }, bodyFont: { size: 12 },
                        padding: 10, cornerRadius: 8,
                        callbacks: {
                            label: ctx => ctx.datasetIndex === 1
                                ? ' ' + currencySymbol + Number(ctx.raw).toLocaleString()
                                : ' ' + ctx.raw + ' disputes'
                        }
                    }
                }
            }
        });
    }

    function initReasonBreakdown() {
        const container = document.getElementById('reasonBreakdownChart');
        if (!container) return;
        container.innerHTML = '<canvas></canvas>';
        const canvas = container.querySelector('canvas');
        const data = analyticsData.reason_breakdown.by_category;

        const palette = [
            'rgb(59,130,246)', 'rgb(239,68,68)', 'rgb(245,158,11)',
            'rgb(34,197,94)', 'rgb(168,85,247)', 'rgb(236,72,153)',
            'rgb(20,184,166)', 'rgb(251,146,60)'
        ];

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.category),
                datasets: [{
                    data: data.map(d => d.count),
                    backgroundColor: palette.slice(0, data.length),
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 12 }, color: '#6B7280', boxWidth: 12, padding: 14 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.85)',
                        titleFont: { size: 12 }, bodyFont: { size: 12 },
                        padding: 10, cornerRadius: 8,
                        callbacks: {
                            label: ctx => ' ' + ctx.raw + ' disputes'
                        }
                    }
                }
            }
        });
    }
});
</script>
@endsection
