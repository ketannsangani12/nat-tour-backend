@extends('layouts.default-2')

@section('main-content')

{{-- ═══════════════════════════════════════════════════════════════
     PAGE HEADER
════════════════════════════════════════════════════════════════ --}}
<div class="bg-white border-b border-gray-100 shadow-sm">
  <div class="w-full container">
    <div class="flex items-center justify-between py-4 gap-4 flex-wrap">
      {{-- Left: Icon + Title --}}
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M9 1.5L2.25 4.5v4.5C2.25 12.9 5.175 16.425 9 17.25c3.825-.825 6.75-4.35 6.75-8.25V4.5L9 1.5z" stroke="#3B82F6" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div>
          <h1 class="text-base font-bold text-gray-900 leading-tight">Payment Disputes</h1>
          <p class="text-xs text-gray-400 mt-0.5">Manage and track payment disputes from Stripe</p>
        </div>
      </div>

      {{-- Right: Action links --}}
      <div class="flex items-center gap-2">
        <a href="{{ route(prefixedRouteName('dispute-templates.index')) }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-colors shadow-sm">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2 2h7l3 3v8H2V2z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.5 6.5h5M4.5 8.5h5M4.5 4.5h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
          Templates
        </a>
        <a href="{{ route(prefixedRouteName('disputes.analytics')) }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-colors shadow-sm">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M1 11l3.5-3.5 2.5 2.5 3.5-4.5L13 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Analytics
        </a>
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════════════════════════════════ --}}
<div class="bg-[#F8F9FB] min-h-screen">
  <div class="w-full container py-5 space-y-4">

    {{-- ── Stats Strip ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

      {{-- Total Disputes --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="#3B82F6" stroke-width="1.3"/><path d="M9 6v4.5M9 12v.5" stroke="#3B82F6" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div>
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Total Disputes</p>
          <p class="text-2xl font-bold text-gray-900 leading-tight">{{ $statistics['total_disputes'] ?? 0 }}</p>
        </div>
      </div>

      {{-- Active --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2l1.8 5.4H16l-4.5 3.3 1.7 5.3L9 13l-4.2 3 1.7-5.3L2 7.4h5.2L9 2z" stroke="#EF4444" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <p class="text-xs font-medium text-red-400 uppercase tracking-wide">Active Disputes</p>
          <p class="text-2xl font-bold text-red-600 leading-tight">{{ $statistics['active_disputes'] ?? 0 }}</p>
        </div>
      </div>

      {{-- Need Attention --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2L1.5 15h15L9 2z" stroke="#D97706" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 7v4M9 12.5v.5" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div>
          <p class="text-xs font-medium text-amber-500 uppercase tracking-wide">Attention</p>
          <p class="text-2xl font-bold text-amber-600 leading-tight">{{ $statistics['requiring_attention'] ?? 0 }}</p>
        </div>
      </div>

      {{-- Overdue --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="#F43F5E" stroke-width="1.3"/><path d="M9 5.5V9l2.5 2" stroke="#F43F5E" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <p class="text-xs font-medium text-rose-400 uppercase tracking-wide">Overdue</p>
          <p class="text-2xl font-bold text-rose-600 leading-tight">{{ $statistics['overdue_disputes'] ?? 0 }}</p>
        </div>
      </div>

    </div>

    {{-- ── Filters + Table Card ── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

      {{-- Hidden clear-filters form --}}
      <form id="clearFiltersForm" method="POST" action="{{ route(prefixedRouteName('disputes.filters.clear')) }}">
        @csrf
      </form>

      {{-- Filters Bar --}}
      <div class="px-5 py-4 border-b border-gray-50">
        <form id="disputeFilters" method="POST" action="{{ route(prefixedRouteName('disputes.filters')) }}">
          @csrf

          {{-- Desktop filters --}}
          <div class="hidden sm:flex sm:flex-wrap sm:items-center sm:gap-3">

            {{-- Search --}}
            <div class="flex-1 min-w-[200px] max-w-[300px] relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.3"/><path d="M9.5 9.5l3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
              <input type="text" name="search" placeholder="Search dispute ID, booking, customer…"
                     class="w-full pl-8 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors"
                     value="{{ session('dispute_filters.search', '') }}">
            </div>

            {{-- Status --}}
            <select name="status" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors min-w-[140px]">
              <option value="">All Statuses</option>
              <option value="warning_needs_response" {{ session('dispute_filters.status') == 'warning_needs_response' ? 'selected' : '' }}>Needs Response</option>
              <option value="under_review" {{ session('dispute_filters.status') == 'under_review' ? 'selected' : '' }}>Under Review</option>
              <option value="charge_refunded" {{ session('dispute_filters.status') == 'charge_refunded' ? 'selected' : '' }}>Charge Refunded</option>
              <option value="lost" {{ session('dispute_filters.status') == 'lost' ? 'selected' : '' }}>Lost</option>
              <option value="won" {{ session('dispute_filters.status') == 'won' ? 'selected' : '' }}>Won</option>
            </select>

            {{-- Reason --}}
            <select name="reason" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors min-w-[140px]">
              <option value="">All Reasons</option>
              <option value="fraudulent" {{ session('dispute_filters.reason') == 'fraudulent' ? 'selected' : '' }}>Fraudulent</option>
              <option value="subscription_canceled" {{ session('dispute_filters.reason') == 'subscription_canceled' ? 'selected' : '' }}>Subscription Canceled</option>
              <option value="product_unacceptable" {{ session('dispute_filters.reason') == 'product_unacceptable' ? 'selected' : '' }}>Product Unacceptable</option>
              <option value="credit_not_processed" {{ session('dispute_filters.reason') == 'credit_not_processed' ? 'selected' : '' }}>Credit Not Processed</option>
              <option value="duplicate" {{ session('dispute_filters.reason') == 'duplicate' ? 'selected' : '' }}>Duplicate</option>
              <option value="product_not_received" {{ session('dispute_filters.reason') == 'product_not_received' ? 'selected' : '' }}>Product Not Received</option>
              <option value="unrecognized" {{ session('dispute_filters.reason') == 'unrecognized' ? 'selected' : '' }}>Unrecognized</option>
              <option value="general" {{ session('dispute_filters.reason') == 'general' ? 'selected' : '' }}>General</option>
            </select>

            <div class="flex gap-2 ml-auto">
              <button type="submit"
                      class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="5.5" cy="5.5" r="4" stroke="currentColor" stroke-width="1.3"/><path d="M8.5 8.5l3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                Filter
              </button>
              <button type="submit" form="clearFiltersForm"
                      class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                Clear
              </button>
            </div>
          </div>

          {{-- Mobile search --}}
          <div class="sm:hidden space-y-3">
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.3"/><path d="M9.5 9.5l3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
              <input type="text" name="search" placeholder="Search disputes…"
                     class="w-full pl-8 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                     value="{{ session('dispute_filters.search', '') }}">
            </div>

            {{-- Mobile advanced toggle --}}
            <button type="button" id="toggleMobileFilters"
                    class="flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1 3.5h12M3 7h8M5 10.5h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
              Advanced Filters
              <svg id="mobileFilterIcon" width="12" height="12" viewBox="0 0 12 12" fill="none" class="transition-transform"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>

            <div id="mobileFilters" class="hidden space-y-3 pt-3 border-t border-gray-100">
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" disabled class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">All Statuses</option>
                  <option value="warning_needs_response" {{ session('dispute_filters.status') == 'warning_needs_response' ? 'selected' : '' }}>Needs Response</option>
                  <option value="under_review" {{ session('dispute_filters.status') == 'under_review' ? 'selected' : '' }}>Under Review</option>
                  <option value="charge_refunded" {{ session('dispute_filters.status') == 'charge_refunded' ? 'selected' : '' }}>Charge Refunded</option>
                  <option value="lost" {{ session('dispute_filters.status') == 'lost' ? 'selected' : '' }}>Lost</option>
                  <option value="won" {{ session('dispute_filters.status') == 'won' ? 'selected' : '' }}>Won</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Reason</label>
                <select name="reason" disabled class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">All Reasons</option>
                  <option value="fraudulent" {{ session('dispute_filters.reason') == 'fraudulent' ? 'selected' : '' }}>Fraudulent</option>
                  <option value="subscription_canceled" {{ session('dispute_filters.reason') == 'subscription_canceled' ? 'selected' : '' }}>Subscription Canceled</option>
                  <option value="product_unacceptable" {{ session('dispute_filters.reason') == 'product_unacceptable' ? 'selected' : '' }}>Product Unacceptable</option>
                  <option value="credit_not_processed" {{ session('dispute_filters.reason') == 'credit_not_processed' ? 'selected' : '' }}>Credit Not Processed</option>
                  <option value="duplicate" {{ session('dispute_filters.reason') == 'duplicate' ? 'selected' : '' }}>Duplicate</option>
                  <option value="product_not_received" {{ session('dispute_filters.reason') == 'product_not_received' ? 'selected' : '' }}>Product Not Received</option>
                  <option value="unrecognized" {{ session('dispute_filters.reason') == 'unrecognized' ? 'selected' : '' }}>Unrecognized</option>
                  <option value="general" {{ session('dispute_filters.reason') == 'general' ? 'selected' : '' }}>General</option>
                </select>
              </div>
              <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors">Apply</button>
                <button type="submit" form="clearFiltersForm" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">Clear</button>
              </div>
            </div>
          </div>
        </form>
      </div>

      {{-- Table Toolbar --}}
      <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50 bg-gray-50/50">
        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-500">Sort:</span>
          <select id="sortBy" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="created_at" {{ $sortBy == 'created_at' ? 'selected' : '' }}>Date Created</option>
            <option value="evidence_due_by" {{ $sortBy == 'evidence_due_by' ? 'selected' : '' }}>Deadline</option>
            <option value="amount" {{ $sortBy == 'amount' ? 'selected' : '' }}>Amount</option>
            <option value="status" {{ $sortBy == 'status' ? 'selected' : '' }}>Status</option>
            <option value="reason" {{ $sortBy == 'reason' ? 'selected' : '' }}>Reason</option>
          </select>
          <button type="button" id="sortDirection"
                  class="w-7 h-7 flex items-center justify-center bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                  data-direction="{{ $sortDirection }}">
            <svg width="13" height="13" viewBox="0 0 13 13" fill="none" class="transition-transform {{ $sortDirection == 'desc' ? 'rotate-180' : '' }}">
              <path d="M6.5 2.5L9.5 6H3.5L6.5 2.5Z" fill="#6B7280"/>
              <path d="M6.5 10.5L3.5 7H9.5L6.5 10.5Z" fill="#D1D5DB"/>
            </svg>
          </button>
        </div>
        <span class="text-xs text-gray-400">
          {{ $disputes->firstItem() ?? 0 }}–{{ $disputes->lastItem() ?? 0 }} of {{ $disputes->total() }}
        </span>
      </div>

      {{-- Desktop Table --}}
      <div class="hidden lg:block overflow-x-auto">
        <table class="w-full table-auto">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-5">Dispute ID</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Booking</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Amount</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Reason</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Status</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Deadline</th>
              <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-5">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            @forelse($disputes as $dispute)
            @php
              $daysUntil = $dispute->getDaysUntilDeadline();
              $isOverdue  = $daysUntil !== null && $daysUntil <= 0;
              $isUrgent   = $daysUntil !== null && $daysUntil > 0 && $daysUntil <= 3;
            @endphp
            <tr class="hover:bg-blue-50/30 transition-colors group">

              {{-- Dispute ID --}}
              <td class="py-3.5 px-5">
                <div class="flex items-center gap-2.5">
                  @if($isOverdue)
                    <div class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></div>
                  @elseif($isUrgent)
                    <div class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></div>
                  @else
                    <div class="w-1.5 h-1.5 rounded-full bg-gray-200 flex-shrink-0"></div>
                  @endif
                  <div>
                    <div class="text-xs font-mono font-semibold text-gray-800">{{ $dispute->stripe_dispute_id }}</div>
                    <div class="text-xs text-gray-400">#{{ $dispute->id }}</div>
                  </div>
                </div>
              </td>

              {{-- Booking --}}
              <td class="py-3.5 px-4">
                @if($dispute->booking)
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0 text-purple-700 font-bold text-xs">
                      {{ strtoupper(substr($dispute->booking->name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                      <div class="text-sm font-medium text-gray-800 leading-tight">{{ $dispute->booking->name ?? 'N/A' }}</div>
                      <div class="text-xs text-gray-400">#{{ $dispute->booking->id }}</div>
                    </div>
                  </div>
                @else
                  <span class="text-sm text-gray-400 italic">No booking</span>
                @endif
              </td>

              {{-- Amount --}}
              <td class="py-3.5 px-4">
                <div class="text-sm font-semibold text-gray-900">{{ currencyFormation(false, true) }}{{ number_format($dispute->amount, 2) }}</div>
                <div class="text-xs text-gray-400">{{ strtoupper($dispute->currency) }}</div>
              </td>

              {{-- Reason --}}
              <td class="py-3.5 px-4">
                <span class="text-sm text-gray-600">{{ $dispute->reason_label }}</span>
              </td>

              {{-- Status --}}
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $dispute->status_css_class }}">
                  {{ $dispute->status_label }}
                </span>
                @if($dispute->requiresAttention())
                  <div class="text-xs text-red-500 font-medium mt-1">Urgent</div>
                @endif
              </td>

              {{-- Deadline --}}
              <td class="py-3.5 px-4">
                @if($dispute->evidence_due_by)
                  <div class="text-sm text-gray-700">{{ $dispute->evidence_due_by->format('M j, Y') }}</div>
                  @if($daysUntil !== null)
                    <div class="text-xs font-medium mt-0.5 {{ $isOverdue ? 'text-red-500' : ($isUrgent ? 'text-amber-500' : 'text-gray-400') }}">
                      @if($isOverdue) Overdue {{ abs($daysUntil) }}d
                      @else {{ $daysUntil }}d left
                      @endif
                    </div>
                  @endif
                @else
                  <span class="text-sm text-gray-400">—</span>
                @endif
              </td>

              {{-- Actions --}}
              <td class="py-3.5 px-5 text-right">
                <div class="flex items-center justify-end gap-2">
                  @if(in_array($dispute->status, ['warning_needs_response', 'under_review']))
                    <button type="button"
                            class="submit-evidence-btn inline-flex items-center gap-1 px-2.5 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-medium rounded-lg hover:bg-blue-100 transition-colors"
                            data-dispute-id="{{ $dispute->id }}">
                      <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v7M3.5 3L6 1 8.5 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M1 9v1.5a.5.5 0 00.5.5h9a.5.5 0 00.5-.5V9" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                      Evidence
                    </button>
                  @endif
                  <a href="{{ route(prefixedRouteName('disputes.show'), $dispute->id) }}"
                     class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg hover:bg-gray-700 transition-colors">
                    View
                    <svg width="11" height="11" viewBox="0 0 11 11" fill="none"><path d="M2 5.5h7M6 3l3 2.5L6 8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </a>
                </div>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                  <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center">
                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="11" cy="11" r="9" stroke="#D1D5DB" stroke-width="1.5"/><path d="M11 8v5M11 14.5v.5" stroke="#D1D5DB" stroke-width="1.8" stroke-linecap="round"/></svg>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-gray-500">No disputes found</p>
                    <p class="text-xs text-gray-400 mt-0.5">Try adjusting your filters or search terms.</p>
                  </div>
                </div>
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Mobile Cards --}}
      <div class="lg:hidden divide-y divide-gray-50">
        @forelse($disputes as $dispute)
        @php
          $daysUntil = $dispute->getDaysUntilDeadline();
          $isOverdue  = $daysUntil !== null && $daysUntil <= 0;
          $isUrgent   = $daysUntil !== null && $daysUntil > 0 && $daysUntil <= 3;
        @endphp
        <div class="p-4 hover:bg-gray-50/50 transition-colors">
          {{-- Header row --}}
          <div class="flex items-start justify-between mb-3">
            <div>
              <div class="flex items-center gap-1.5">
                @if($isOverdue)
                  <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>
                @elseif($isUrgent)
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>
                @endif
                <span class="text-xs font-mono font-semibold text-gray-800">{{ $dispute->stripe_dispute_id }}</span>
              </div>
              <span class="text-xs text-gray-400">#{{ $dispute->id }}</span>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $dispute->status_css_class }}">
              {{ $dispute->status_label }}
            </span>
          </div>

          {{-- Detail grid --}}
          <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm mb-3">
            <div>
              <div class="text-xs text-gray-400 mb-0.5">Booking</div>
              @if($dispute->booking)
                <div class="font-medium text-gray-800 text-xs">{{ $dispute->booking->name ?? 'N/A' }}</div>
                <div class="text-xs text-gray-400">#{{ $dispute->booking->id }}</div>
              @else
                <span class="text-xs text-gray-400 italic">No booking</span>
              @endif
            </div>
            <div>
              <div class="text-xs text-gray-400 mb-0.5">Amount</div>
              <div class="font-semibold text-gray-900 text-xs">{{ currencyFormation(false, true) }}{{ number_format($dispute->amount, 2) }}</div>
              <div class="text-xs text-gray-400">{{ strtoupper($dispute->currency) }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-400 mb-0.5">Reason</div>
              <div class="text-xs text-gray-700">{{ $dispute->reason_label }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-400 mb-0.5">Deadline</div>
              @if($dispute->evidence_due_by)
                <div class="text-xs text-gray-700">{{ $dispute->evidence_due_by->format('M j, Y') }}</div>
                @if($daysUntil !== null)
                  <div class="text-xs font-medium {{ $isOverdue ? 'text-red-500' : ($isUrgent ? 'text-amber-500' : 'text-gray-400') }}">
                    @if($isOverdue) Overdue {{ abs($daysUntil) }}d @else {{ $daysUntil }}d left @endif
                  </div>
                @endif
              @else
                <span class="text-xs text-gray-400">—</span>
              @endif
            </div>
          </div>

          {{-- Actions --}}
          <div class="flex gap-2 pt-3 border-t border-gray-100">
            <a href="{{ route(prefixedRouteName('disputes.show'), $dispute->id) }}"
               class="flex-1 text-center px-3 py-2 bg-gray-900 text-white text-xs font-medium rounded-xl hover:bg-gray-700 transition-colors">
              View Details
            </a>
            @if(in_array($dispute->status, ['warning_needs_response', 'under_review']))
              <button type="button"
                      class="submit-evidence-btn flex-1 px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-medium rounded-xl hover:bg-blue-100 transition-colors"
                      data-dispute-id="{{ $dispute->id }}">
                Submit Evidence
              </button>
            @endif
          </div>
        </div>
        @empty
        <div class="py-16 text-center">
          <div class="flex flex-col items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center">
              <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="11" cy="11" r="9" stroke="#D1D5DB" stroke-width="1.5"/><path d="M11 8v5M11 14.5v.5" stroke="#D1D5DB" stroke-width="1.8" stroke-linecap="round"/></svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-500">No disputes found</p>
              <p class="text-xs text-gray-400 mt-0.5">Try adjusting your filters.</p>
            </div>
          </div>
        </div>
        @endforelse
      </div>

      {{-- Pagination --}}
      @if($disputes->hasPages())
      <div class="px-5 py-4 border-t border-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="text-xs text-gray-400">
          Showing {{ $disputes->firstItem() ?? 0 }}–{{ $disputes->lastItem() ?? 0 }} of {{ $disputes->total() }} results
        </div>
        <div>
          {{ $disputes->appends(request()->query())->links() }}
        </div>
      </div>
      @endif

    </div>{{-- /Filters + Table Card --}}

  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     EVIDENCE MODAL
════════════════════════════════════════════════════════════════ --}}
<div id="evidenceModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
  <div id="evidenceModalBackdrop"
       class="flex min-h-full items-center justify-center p-4"
       style="background:rgba(15,23,42,0.45);">
    <div class="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden">

      {{-- Modal Header --}}
      <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
        <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
          <svg width="17" height="17" viewBox="0 0 17 17" fill="none"><path d="M8.5 1v11M5 4l3.5-3L12 4" stroke="#3B82F6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M1.5 12.5V15a.5.5 0 00.5.5h13a.5.5 0 00.5-.5v-2.5" stroke="#3B82F6" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="text-sm font-bold text-gray-900">Submit Evidence</h3>
          <p class="text-xs text-gray-400 mt-0.5" id="evidenceModalSubtitle">Dispute — </p>
        </div>
        <button id="closeEvidenceModal" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M11 4L4 11M4 4l7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
      </div>

      {{-- Modal Body --}}
      <div class="px-6 py-5 overflow-y-auto max-h-[65vh]">
        <form id="evidenceForm" enctype="multipart/form-data">
          <input type="hidden" id="evidenceDisputeId" name="dispute_id">

          {{-- File rows --}}
          <div class="mb-4">
            <div class="flex items-center justify-between mb-2.5">
              <label class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Evidence Files</label>
              <button type="button" id="addFileRowBtn"
                      class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="6.5" cy="6.5" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M6.5 4v5M4 6.5h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                Add File
              </button>
            </div>
            <div id="fileRowsContainer" class="space-y-2.5"></div>
          </div>

          {{-- Evidence Text --}}
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label for="evidenceTextarea" class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Written Statement</label>
              <span id="evidenceTextCount" class="text-xs text-gray-400">0 / 5000</span>
            </div>
            <textarea id="evidenceTextarea" name="evidence_text" rows="4" maxlength="5000"
                      class="w-full px-3.5 py-3 border border-gray-200 rounded-xl text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none transition-colors bg-gray-50 focus:bg-white"
                      placeholder="Provide written evidence or explanation…"></textarea>
          </div>

        </form>
      </div>

      {{-- Modal Footer --}}
      <div class="flex items-center justify-end gap-2.5 px-6 py-4 bg-gray-50 border-t border-gray-100">
        <button type="button" id="closeEvidenceModalFooter"
                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="button" id="submitEvidenceBtn"
                class="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1 7h10M8 4l4 3-4 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Submit to Stripe
        </button>
      </div>

    </div>
  </div>
</div>

@endsection

@section('js-custom')
<script>
$(document).ready(function() {

    // ── Evidence types ─────────────────────────────────────────
    const TYPE_OPTIONS = `
        <option value="">Select type…</option>
        <option value="receipt">Receipt</option>
        <option value="customer_communication">Customer Communication</option>
        <option value="service_documentation">Service Documentation</option>
        <option value="shipping_documentation">Shipping Documentation</option>
        <option value="duplicate_charge_documentation">Duplicate Charge Docs</option>
        <option value="refund_policy">Refund Policy</option>
        <option value="cancellation_policy">Cancellation Policy</option>
        <option value="customer_signature">Customer Signature</option>
        <option value="uncategorized_file">Other Documentation</option>
    `;

    function buildFileRow(index, removable) {
        const removeBtn = removable
            ? `<button type="button" class="remove-file-row flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors mt-5">
                   <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2 2l9 9M11 2l-9 9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
               </button>`
            : `<div class="w-7 flex-shrink-0 mt-5"></div>`;

        return $(`
            <div class="file-row border border-gray-200 rounded-xl p-3 bg-white hover:border-blue-200 hover:shadow-sm transition-all">
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-48">
                  <label class="text-xs font-medium text-gray-500 block mb-1.5">Document Type</label>
                  <select name="evidence_file_types[]"
                          class="w-full px-2.5 py-2 text-xs bg-gray-50 border border-gray-200 rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
                    ${TYPE_OPTIONS}
                  </select>
                </div>
                <div class="flex-1 min-w-0">
                  <label class="text-xs font-medium text-gray-500 block mb-1.5">File</label>
                  <label class="file-upload-label flex items-center gap-2.5 w-full px-3 py-2 border border-dashed border-gray-300 rounded-lg bg-gray-50 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-all">
                    <svg class="flex-shrink-0 text-gray-400" width="14" height="14" viewBox="0 0 14 14" fill="none">
                      <path d="M7 1v8M4.5 3.5L7 1 9.5 3.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M1 11v1.5a.5.5 0 00.5.5h11a.5.5 0 00.5-.5V11" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                    <span class="file-name-display text-xs text-gray-400 truncate flex-1">Choose a file…</span>
                    <input type="file" name="evidence_files[]" class="evidence-file-input sr-only"
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                  </label>
                  <span class="file-error text-xs text-red-500 hidden mt-1 block"></span>
                </div>
                ${removeBtn}
              </div>
            </div>
        `);
    }

    let fileRowCount = 0;

    function addFileRow(removable) {
        const $row = buildFileRow(fileRowCount++, removable);
        $('#fileRowsContainer').append($row);
    }

    // Remove file row
    $(document).on('click', '.remove-file-row', function() {
        $(this).closest('.file-row').remove();
    });

    // File change — update label display
    $(document).on('change', '.evidence-file-input', function() {
        const $label = $(this).closest('.file-upload-label');
        const $display = $label.find('.file-name-display');
        const $row = $(this).closest('.file-row');
        const $error = $row.find('.file-error');
        const file = this.files[0];

        $error.addClass('hidden').text('');

        if (!file) return;

        const maxMB = 10;
        if (file.size > maxMB * 1024 * 1024) {
            $error.text('File exceeds ' + maxMB + 'MB limit.').removeClass('hidden');
            this.value = '';
            $display.text('Choose a file…').removeClass('text-gray-700 font-medium').addClass('text-gray-400');
            $label.removeClass('border-blue-400 bg-blue-50').addClass('border-gray-300 bg-gray-50');
            return;
        }

        $display.text(file.name).removeClass('text-gray-400').addClass('text-gray-700 font-medium');
        $label.removeClass('border-gray-300 bg-gray-50').addClass('border-blue-400 bg-blue-50');
    });

    // Add file row button
    $('#addFileRowBtn').on('click', function() {
        addFileRow(true);
    });

    // Character counter
    $('#evidenceTextarea').on('input', function() {
        const count = $(this).val().length;
        const $counter = $('#evidenceTextCount');
        $counter.text(count + ' / 5000');
        if (count >= 4500) {
            $counter.removeClass('text-gray-400').addClass('text-orange-500');
        } else {
            $counter.removeClass('text-orange-500').addClass('text-gray-400');
        }
    });

    // Open modal
    $(document).on('click', '.submit-evidence-btn', function() {
        const disputeId = $(this).data('dispute-id');
        $('#evidenceDisputeId').val(disputeId);
        $('#evidenceModalSubtitle').text('Dispute #' + disputeId);

        // Reset + seed one row
        $('#fileRowsContainer').empty();
        fileRowCount = 0;
        $('#evidenceTextarea').val('');
        $('#evidenceTextCount').text('0 / 5000').removeClass('text-orange-500').addClass('text-gray-400');
        addFileRow(false);

        $('#evidenceModal').removeClass('hidden');
        $('body').addClass('overflow-hidden');
    });

    function closeModal() {
        $('#evidenceModal').addClass('hidden');
        $('body').removeClass('overflow-hidden');
    }

    $('#closeEvidenceModal, #closeEvidenceModalFooter').on('click', closeModal);

    // Backdrop click
    $('#evidenceModalBackdrop').on('click', function(e) {
        if (e.target === this) closeModal();
    });

    // Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && !$('#evidenceModal').hasClass('hidden')) closeModal();
    });

    // Submit evidence
    $('#submitEvidenceBtn').on('click', function() {
        const disputeId = $('#evidenceDisputeId').val();
        const formData = new FormData($('#evidenceForm')[0]);
        const $btn = $(this);

        $btn.prop('disabled', true).html(`
            <svg class="animate-spin" width="14" height="14" viewBox="0 0 14 14" fill="none">
              <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="20 14"/>
            </svg>
            Submitting…
        `);

        $.ajax({
            url: `{{ route(prefixedRouteName('disputes.submit-evidence'), ':id') }}`.replace(':id', disputeId),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                if (response.success) {
                    showNotification('Evidence submitted successfully!', 'success');
                    closeModal();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification('Error: ' + (response.message || 'Failed to submit evidence'), 'error');
                }
            },
            error: function(xhr) {
                let message = 'Failed to submit evidence';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join(', ');
                }
                showNotification('Error: ' + message, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(`
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1 7h10M8 4l4 3-4 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Submit to Stripe
                `);
            }
        });
    });

    // ── Sorting ────────────────────────────────────────────────
    $('#sortBy').on('change', updateSort);

    $('#sortDirection').on('click', function() {
        const cur = $(this).data('direction');
        const next = cur === 'asc' ? 'desc' : 'asc';
        $(this).data('direction', next);
        const $svg = $(this).find('svg');
        next === 'desc' ? $svg.addClass('rotate-180') : $svg.removeClass('rotate-180');
        updateSort();
    });

    function updateSort() {
        const url = new URL(window.location.href);
        url.searchParams.set('sort_by', $('#sortBy').val());
        url.searchParams.set('sort_direction', $('#sortDirection').data('direction'));
        window.location.href = url.toString();
    }

    // ── Mobile filter toggle ───────────────────────────────────
    $('#toggleMobileFilters').on('click', function() {
        const $filters = $('#mobileFilters');
        const $icon = $('#mobileFilterIcon');

        if ($filters.is(':visible')) {
            $filters.slideUp(200);
            $icon.removeClass('rotate-180');
            $filters.find('select').prop('disabled', true);
        } else {
            $filters.slideDown(200);
            $icon.addClass('rotate-180');
            $filters.find('select').prop('disabled', false);
        }
    });

    // ── Responsive table handler ───────────────────────────────
    function handleResponsiveTable() {
        const isDesktop = window.innerWidth >= 1024;
        $('.hidden.lg\\:block').css('display', isDesktop ? '' : '');
        $('.lg\\:hidden').css('display', isDesktop ? '' : '');
    }

    $(window).on('resize', function() {
        handleResponsiveTable();
    });

    handleResponsiveTable();

    // ── Notification ───────────────────────────────────────────
    function showNotification(message, type) {
        $('.dispute-notification').remove();
        const bg = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600';
        const $n = $(`
            <div class="dispute-notification fixed top-5 right-5 ${bg} text-white px-5 py-3 rounded-xl shadow-lg z-[99999] max-w-sm flex items-center justify-between gap-4">
              <span class="text-sm font-medium">${message}</span>
              <button class="text-white/70 hover:text-white" onclick="$(this).closest('.dispute-notification').remove()">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </button>
            </div>
        `);
        $('body').append($n);
        setTimeout(() => $n.fadeOut(300, function() { $(this).remove(); }), 5000);
    }

});
</script>
@endsection
