@extends('layouts.default-2')

@section('main-content')

{{-- ═══════════════════════════════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════════════════════════════════ --}}
<div class="bg-white border-b border-gray-100">
  <div class="w-full container px-4 sm:px-6 py-5">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-4">
      <a href="{{ route(prefixedRouteName('disputes.index')) }}" class="hover:text-gray-600 transition-colors font-medium">Disputes</a>
      <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="flex-shrink-0">
        <path d="M4.5 9L7.5 6 4.5 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span class="text-gray-600 font-mono truncate">{{ $dispute->stripe_dispute_id }}</span>
    </nav>

    <div class="flex flex-col sm:flex-row sm:items-center gap-4 justify-between">

      {{-- Title + meta --}}
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center flex-shrink-0">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M11 2L3 6v5c0 4.418 3.358 8.545 8 9.5C16.642 19.545 20 15.418 20 11V6l-8-4z"
                  stroke="#2563EB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M8 11l2 2 4-4" stroke="#2563EB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div>
          <h1 class="text-xl font-bold text-gray-900 leading-tight">Dispute Details</h1>
          <div class="flex flex-wrap items-center gap-2 mt-1.5">
            <code class="text-xs font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md">{{ $dispute->stripe_dispute_id }}</code>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $dispute->status_css_class }}">
              {{ $dispute->status_label }}
            </span>
            @if($daysUntilDeadline !== null)
              @php $urgency = $daysUntilDeadline <= 0 ? 'bg-red-100 text-red-700' : ($daysUntilDeadline <= 3 ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600'); @endphp
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $urgency }}">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                  <circle cx="5" cy="5" r="4" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M5 3v2.5l1.5 1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
                @if($daysUntilDeadline <= 0)
                  Overdue {{ abs($daysUntilDeadline) }}d
                @else
                  {{ $daysUntilDeadline }}d left
                @endif
              </span>
            @endif
          </div>
        </div>
      </div>

      {{-- Action buttons --}}
      <div class="flex items-center gap-2 flex-shrink-0">
        <button id="quickSyncBtn"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-colors shadow-sm">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
            <path d="M13 7.5A5.5 5.5 0 002.5 5M2 2v3h3M2 7.5A5.5 5.5 0 0012.5 10M13 13v-3h-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Sync
        </button>
        @if(in_array($dispute->status, ['warning_needs_response', 'under_review']))
          <button id="uploadEvidenceBtn"
                  class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
              <path d="M7.5 1v9M4.5 4L7.5 1 10.5 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M1.5 10.5V13A.5.5 0 002 13.5h11a.5.5 0 00.5-.5v-2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            Submit Evidence
          </button>
        @endif
        @can('view-payment-dispute')
          <button id="updateStatusBtn"
                  class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-colors shadow-sm">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
              <path d="M11.5 1.5l2 2-7 7-3 1 1-3 7-7z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Update Status
          </button>
        @endcan
        @if($dispute->booking)
          <a href="{{ route(routePrefix().'.booking.show', ['booking' => $dispute->booking->id]) }}"
             class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-colors shadow-sm">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><rect x="1.5" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M5 2V5m5-3v3M1.5 7h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
            View Booking
          </a>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     URGENT ALERT BANNER
══════════════════════════════════════════════════════════════════ --}}
@if($dispute->requiresAttention())
<div class="{{ $isOverdue ? 'bg-red-600' : 'bg-amber-500' }}">
  <div class="w-full container px-4 sm:px-6 py-3 flex items-center gap-3">
    <div class="w-7 h-7 rounded-full bg-white bg-opacity-20 flex items-center justify-center flex-shrink-0">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
        <path d="M7 1L13 12H1L7 1z" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M7 5v3M7 10h.01" stroke="white" stroke-width="1.2" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-white font-semibold text-sm">
        @if($isOverdue) Overdue: Evidence Required Immediately @else Deadline Approaching @endif
      </p>
      <p class="text-white text-opacity-90 text-xs mt-0.5">
        @if($isOverdue)
          This dispute is overdue by {{ abs($daysUntilDeadline) }} day(s). Submit evidence immediately.
        @else
          Evidence must be submitted within {{ $daysUntilDeadline }} day(s).
        @endif
      </p>
    </div>
    @if(in_array($dispute->status, ['warning_needs_response', 'under_review']))
      <button id="alertSubmitBtn"
              class="flex-shrink-0 px-3.5 py-1.5 bg-white text-sm font-semibold rounded-lg {{ $isOverdue ? 'text-red-600 hover:bg-red-50' : 'text-amber-600 hover:bg-amber-50' }} transition-colors">
        Submit Now
      </button>
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     MAIN CONTENT GRID
══════════════════════════════════════════════════════════════════ --}}
<div class="bg-[#F8F9FB] min-h-screen">
  <div class="w-full container px-4 sm:px-6 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      {{-- ────────────────────────────────────────────────────────
           LEFT COLUMN  (2/3)
      ─────────────────────────────────────────────────────────── --}}
      <div class="lg:col-span-2 space-y-6">

        {{-- ── Dispute Information ── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          {{-- card header --}}
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M8 1L2 4v4c0 3.314 2.686 6.41 6 7 3.314-.59 6-3.686 6-7V4L8 1z"
                      stroke="#3B82F6" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-900">Dispute Information</h2>
          </div>

          {{-- key stats strip --}}
          <div class="grid grid-cols-3 divide-x divide-gray-100 bg-gray-50 border-b border-gray-100">
            <div class="px-5 py-4 text-center">
              <div class="text-2xl font-bold text-gray-900">{{ currencyFormation(false, true) }}{{ number_format($dispute->amount, 2) }}</div>
              <div class="text-xs text-gray-400 mt-0.5 uppercase tracking-wide">{{ strtoupper($dispute->currency) }}</div>
            </div>
            <div class="px-5 py-4 flex flex-col items-center justify-center">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $dispute->status_css_class }}">
                {{ $dispute->status_label }}
              </span>
              <div class="text-xs text-gray-400 mt-1.5">Status</div>
            </div>
            <div class="px-5 py-4 text-center">
              <div class="text-sm font-semibold text-gray-800">{{ $dispute->reason_label }}</div>
              <div class="text-xs text-gray-400 mt-0.5">Reason</div>
            </div>
          </div>

          {{-- detail grid --}}
          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-8">
            {{-- Dispute ID --}}
            <div class="flex gap-3 items-start">
              <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1.5" y="1.5" width="11" height="11" rx="2" stroke="#9CA3AF" stroke-width="1.2"/><path d="M4.5 7h5M4.5 4.5h3M4.5 9.5h4" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round"/></svg>
              </div>
              <div class="min-w-0">
                <div class="text-xs text-gray-400 mb-0.5">Dispute ID</div>
                <code class="text-sm font-mono text-gray-900 break-all">{{ $dispute->stripe_dispute_id }}</code>
              </div>
            </div>

            {{-- Created --}}
            <div class="flex gap-3 items-start">
              <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1.5" y="2.5" width="11" height="10" rx="1.5" stroke="#9CA3AF" stroke-width="1.2"/><path d="M4.5 1v3M9.5 1v3M1.5 6h11" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round"/></svg>
              </div>
              <div>
                <div class="text-xs text-gray-400 mb-0.5">Created</div>
                <div class="text-sm font-medium text-gray-900">{{ $dispute->created_at->format('M j, Y') }}</div>
                <div class="text-xs text-gray-500">{{ $dispute->created_at->format('g:i A') }}</div>
              </div>
            </div>

            {{-- Evidence Deadline --}}
            <div class="flex gap-3 items-start">
              <div class="w-8 h-8 rounded-lg {{ $daysUntilDeadline !== null && $daysUntilDeadline <= 3 ? 'bg-red-50' : 'bg-gray-50' }} flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="{{ $daysUntilDeadline !== null && $daysUntilDeadline <= 3 ? '#EF4444' : '#9CA3AF' }}" stroke-width="1.2"/><path d="M7 4v3.5l2 1.5" stroke="{{ $daysUntilDeadline !== null && $daysUntilDeadline <= 3 ? '#EF4444' : '#9CA3AF' }}" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <div>
                <div class="text-xs text-gray-400 mb-0.5">Evidence Deadline</div>
                @if($dispute->evidence_due_by)
                  <div class="text-sm font-medium text-gray-900">{{ $dispute->evidence_due_by->format('M j, Y') }}</div>
                  <div class="text-xs {{ $daysUntilDeadline !== null && $daysUntilDeadline <= 0 ? 'text-red-600 font-semibold' : ($daysUntilDeadline !== null && $daysUntilDeadline <= 3 ? 'text-orange-600' : 'text-gray-500') }}">
                    @if($daysUntilDeadline !== null)
                      @if($daysUntilDeadline <= 0) Overdue by {{ abs($daysUntilDeadline) }} day(s)
                      @else {{ $daysUntilDeadline }} day(s) remaining
                      @endif
                    @endif
                  </div>
                @else
                  <div class="text-sm text-gray-400">No deadline set</div>
                @endif
              </div>
            </div>

            {{-- Amount detail --}}
            <div class="flex gap-3 items-start">
              <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="#9CA3AF" stroke-width="1.2"/><path d="M7 4v1.5m0 3V10m-1.5-4.5h2.25c.69 0 1.25.56 1.25 1.25v0c0 .69-.56 1.25-1.25 1.25H6.5c-.69 0-1.25.56-1.25 1.25v0c0 .69.56 1.25 1.25 1.25H8.5" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round"/></svg>
              </div>
              <div>
                <div class="text-xs text-gray-400 mb-0.5">Disputed Amount</div>
                <div class="text-sm font-bold text-gray-900">{{ currencyFormation(false, true) }}{{ number_format($dispute->amount, 2) }} <span class="text-xs font-normal text-gray-400">{{ strtoupper($dispute->currency) }}</span></div>
              </div>
            </div>
          </div>
        </div>

        {{-- ── Associated Booking ── --}}
        @if($dispute->booking)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13 2H3a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1V3a1 1 0 00-1-1z" stroke="#7C3AED" stroke-width="1.2"/><path d="M5 2v3l3-1.5L11 5V2M8 9a2 2 0 100-4 2 2 0 000 4z" stroke="#7C3AED" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <h2 class="text-sm font-semibold text-gray-900">Associated Booking</h2>
            </div>
            <a href="{{ route(routePrefix().'.booking.show', ['booking' => $dispute->booking->id]) }}"
               class="text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 transition-colors">
              View Booking
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2.5 6h7m-3-3l3 3-3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
          </div>

          {{-- Customer header --}}
          <div class="px-5 py-4 border-b border-gray-50 bg-gray-50/50 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0 text-purple-700 font-bold text-sm">
              {{ strtoupper(substr($dispute->booking->name ?? 'U', 0, 1)) }}
            </div>
            <div>
              <div class="text-sm font-semibold text-gray-900">{{ $dispute->booking->name ?? 'Unknown Customer' }}</div>
              <div class="text-xs text-gray-400">Booking #{{ $dispute->booking->id }}</div>
            </div>
            <div class="ml-auto">
              <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                {{ $dispute->booking->status ?? 'N/A' }}
              </span>
            </div>
          </div>

          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-8">
            @if($dispute->booking->email)
            <div class="flex gap-3 items-start">
              <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1.5" y="3" width="11" height="8" rx="1.5" stroke="#9CA3AF" stroke-width="1.2"/><path d="M1.5 5l5.5 3.5L12.5 5" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round"/></svg>
              </div>
              <div>
                <div class="text-xs text-gray-400 mb-0.5">Email</div>
                <div class="text-sm text-gray-900">{{ $dispute->booking->email }}</div>
              </div>
            </div>
            @endif

            @if($dispute->booking->phone)
            <div class="flex gap-3 items-start">
              <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M4 2h2.5l1 2.5-1.5 1c.5 1 1.5 2 2.5 2.5l1-1.5L12 7.5V10C10.5 11.5 3.5 7.5 2 4L4 2z" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <div>
                <div class="text-xs text-gray-400 mb-0.5">Phone</div>
                <div class="text-sm text-gray-900">{{ $dispute->booking->phone }}</div>
              </div>
            </div>
            @endif

            <div class="flex gap-3 items-start">
              <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1.5" y="2.5" width="11" height="10" rx="1.5" stroke="#9CA3AF" stroke-width="1.2"/><path d="M4.5 1v3M9.5 1v3M1.5 6h11" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round"/><circle cx="7" cy="9" r="1" fill="#9CA3AF"/></svg>
              </div>
              <div>
                <div class="text-xs text-gray-400 mb-0.5">Tour Date</div>
                <div class="text-sm font-medium text-gray-900">
                  {{ $dispute->booking->tourSlotTime?->date ? \Carbon\Carbon::parse($dispute->booking->tourSlotTime->date)->format('M j, Y') : 'N/A' }}
                </div>
                <div class="text-xs text-gray-500">
                  @if($dispute->booking->tourSlotTime?->time_from)
                    {{ \Carbon\Carbon::parse($dispute->booking->tourSlotTime->time_from)->format('g:i A') }}
                    @if($dispute->booking->tourSlotTime->time_to)
                      – {{ \Carbon\Carbon::parse($dispute->booking->tourSlotTime->time_to)->format('g:i A') }}
                    @endif
                  @else
                    Time N/A
                  @endif
                </div>
              </div>
            </div>

            <div class="flex gap-3 items-start">
              <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="#9CA3AF" stroke-width="1.2"/><path d="M4.5 7l2 2 3-3" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <div>
                <div class="text-xs text-gray-400 mb-0.5">Booking Status</div>
                <div class="text-sm font-medium text-gray-900">{{ $dispute->booking->status ?? 'N/A' }}</div>
              </div>
            </div>
          </div>
        </div>
        @endif

        {{-- ── Evidence Files ── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M9 2H4a1.5 1.5 0 00-1.5 1.5v9A1.5 1.5 0 004 14h8a1.5 1.5 0 001.5-1.5V6L9 2z" stroke="#4F46E5" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M9 2v4h4M6 9h4M6 11.5h2.5" stroke="#4F46E5" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </div>
              <div>
                <h2 class="text-sm font-semibold text-gray-900">Evidence Files</h2>
                <p class="text-xs text-gray-400">{{ $evidence->count() }} {{ Str::plural('file', $evidence->count()) }} uploaded</p>
              </div>
            </div>
            @if(in_array($dispute->status, ['warning_needs_response', 'under_review']))
              <button id="uploadEvidenceBtn2"
                      class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-lg transition-colors">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                  <path d="M6.5 1v9M3.5 4L6.5 1 9.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M1 9.5V12a.5.5 0 00.5.5h10a.5.5 0 00.5-.5V9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Add Evidence
              </button>
            @endif
          </div>

          @if($evidence->count() > 0)
            <div class="divide-y divide-gray-50">
              @foreach($evidence as $evidenceFile)
              <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50/60 transition-colors group">
                {{-- File type icon --}}
                <div class="w-10 h-10 rounded-xl {{ $evidenceFile->isSubmittedToStripe() ? 'bg-green-50' : 'bg-orange-50' }} flex items-center justify-center flex-shrink-0">
                  <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M10 2H4.5A1.5 1.5 0 003 3.5v11A1.5 1.5 0 004.5 16h9A1.5 1.5 0 0015 14.5V7L10 2z"
                          stroke="{{ $evidenceFile->isSubmittedToStripe() ? '#16A34A' : '#D97706' }}" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M10 2v5h5" stroke="{{ $evidenceFile->isSubmittedToStripe() ? '#16A34A' : '#D97706' }}" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>

                {{-- File info --}}
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-gray-900 truncate">{{ $evidenceFile->file_name }}</div>
                  <div class="flex flex-wrap items-center gap-2 mt-0.5">
                    <span class="text-xs text-gray-400">{{ $evidenceFile->type_label }}</span>
                    @if($evidenceFile->file_size_human)
                      <span class="text-gray-200">·</span>
                      <span class="text-xs text-gray-400">{{ $evidenceFile->file_size_human }}</span>
                    @endif
                    <span class="text-gray-200">·</span>
                    @if($evidenceFile->isSubmittedToStripe())
                      <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="4" fill="#D1FAE5"/><path d="M3 5l1.5 1.5L7 3.5" stroke="#16A34A" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Submitted to Stripe
                      </span>
                    @else
                      <span class="inline-flex items-center gap-1 text-xs font-medium text-orange-500">
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="4" fill="#FEF3C7"/><path d="M5 3v2.5M5 7h.01" stroke="#D97706" stroke-width="1.1" stroke-linecap="round"/></svg>
                        Pending submission
                      </span>
                    @endif
                  </div>
                  @if($evidenceFile->submitted_at)
                    <div class="text-xs text-gray-400 mt-0.5">{{ $evidenceFile->submitted_at->format('M j, Y g:i A') }}</div>
                  @endif
                </div>

                {{-- Download --}}
                @if($evidenceFile->fileExists())
                  <a href="{{ $evidenceFile->getDownloadUrl() }}"
                     class="flex-shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 opacity-0 group-hover:opacity-100 transition-opacity bg-blue-50 hover:bg-blue-100 px-2.5 py-1.5 rounded-lg">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v7M3 5l3 3 3-3M1 9v1.5A.5.5 0 001.5 11h9a.5.5 0 00.5-.5V9" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Download
                  </a>
                @endif
              </div>
              @endforeach
            </div>
          @else
            <div class="flex flex-col items-center justify-center py-12 px-5 text-center">
              <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                  <path d="M20 4H8a3 3 0 00-3 3v18a3 3 0 003 3h16a3 3 0 003-3V12L20 4z" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M20 4v8h8M13 17h6M13 21h4" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
              </div>
              <p class="text-sm font-semibold text-gray-700 mb-1">No evidence files yet</p>
              <p class="text-xs text-gray-400 mb-4">Upload supporting documents to strengthen your dispute response.</p>
              @if(in_array($dispute->status, ['warning_needs_response', 'under_review']))
                <button id="emptyStateUploadBtn"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v9M4 4l3-3 3 3M1 10v1.5A1.5 1.5 0 002.5 13h9a1.5 1.5 0 001.5-1.5V10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Upload Evidence
                </button>
              @endif
            </div>
          @endif
        </div>

      </div>

      {{-- ────────────────────────────────────────────────────────
           RIGHT COLUMN / SIDEBAR  (1/3)
      ─────────────────────────────────────────────────────────── --}}
      <div class="space-y-5">

        {{-- ── Activity Timeline ── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="#D97706" stroke-width="1.2"/><path d="M8 5v3.5l2 1.5" stroke="#D97706" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-900">Activity Timeline</h2>
          </div>

          @if($timeline->count() > 0)
            <div class="px-5 py-4">
              <div class="relative">
                {{-- Connector line --}}
                @if($timeline->count() > 1)
                  <div class="absolute left-3.5 top-4 bottom-4 w-px bg-gray-100"></div>
                @endif

                <div class="space-y-5">
                  @foreach($timeline as $loop_index => $event)
                  <div class="relative flex gap-4">
                    {{-- Dot --}}
                    <div class="relative flex-shrink-0 z-10">
                      <div class="w-7 h-7 rounded-full {{ $event->event_css_class }} flex items-center justify-center ring-2 ring-white">
                        <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                          @switch($event->event_icon)
                            @case('exclamation-triangle')
                              <path d="M5.5 1L10 9.5H1L5.5 1z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                              <path d="M5.5 4V6" stroke="currentColor" stroke-width="1" stroke-linecap="round"/>
                              <path d="M5.5 7.5h.01" stroke="currentColor" stroke-width="1" stroke-linecap="round"/>
                              @break
                            @case('upload')
                              <path d="M5.5 1v6.5M3 4L5.5 1 8 4" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                              <path d="M1 8v1.5a.5.5 0 00.5.5h8a.5.5 0 00.5-.5V8" stroke="currentColor" stroke-width="1" stroke-linecap="round"/>
                              @break
                            @case('check-circle')
                              <circle cx="5.5" cy="5.5" r="4.5" stroke="currentColor" stroke-width="1"/>
                              <path d="M3.5 5.5l1.5 1.5 2.5-2.5" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                              @break
                            @default
                              <circle cx="5.5" cy="5.5" r="2" fill="currentColor"/>
                          @endswitch
                        </svg>
                      </div>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0 pb-1">
                      <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold text-gray-900 leading-tight">{{ $event->event_label }}</p>
                        <time class="text-xs text-gray-400 flex-shrink-0 mt-0.5">{{ $event->created_at->format('M j') }}</time>
                      </div>
                      <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $event->description }}</p>
                      <p class="text-xs text-gray-400 mt-0.5">{{ $event->created_at->format('g:i A') }}</p>

                      @if($event->event_data && is_array($event->event_data) && count($event->event_data) > 0)
                        <button type="button"
                                class="toggle-details mt-1.5 text-xs text-blue-600 hover:text-blue-800 font-medium"
                                data-target="event-{{ $event->id }}">
                          Show Details
                        </button>
                        <div id="event-{{ $event->id }}" class="hidden mt-2 p-2.5 bg-gray-50 rounded-lg border border-gray-100 text-xs">
                          <pre class="text-gray-500 whitespace-pre-wrap break-all">{{ json_encode($event->event_data, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                      @endif
                    </div>
                  </div>
                  @endforeach
                </div>
              </div>
            </div>
          @else
            <div class="flex flex-col items-center justify-center py-10 px-5 text-center">
              <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mb-3">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="11" cy="11" r="8" stroke="#D1D5DB" stroke-width="1.5"/><path d="M11 7v4.5l2.5 2" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              <p class="text-sm font-medium text-gray-500">No events yet</p>
              <p class="text-xs text-gray-400 mt-1">Activity will appear here as the dispute progresses.</p>
            </div>
          @endif
        </div>

      </div>
    </div>
  </div>
</div>

@if(in_array($dispute->status, ['warning_needs_response', 'under_review']))
<!-- Evidence Submission Modal -->
<div id="evidenceModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
  <div id="evidenceModalBackdrop" class="min-h-full flex items-start justify-center py-10 px-4" style="background:rgba(15,23,42,0.45);">
    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden">

      <!-- Modal Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-white">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M11.5 2H6a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7l-4.5-5z" stroke="#2563EB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M11 2v5h5M7 10h6M7 13h4" stroke="#2563EB" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div>
            <h3 class="text-base font-semibold text-gray-900 leading-tight">Submit Evidence</h3>
            <p class="text-xs text-gray-400 mt-0.5">Dispute: {{ $dispute->stripe_dispute_id }}</p>
          </div>
        </div>
        <button type="button" id="closeEvidenceModal"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <form id="evidenceForm" enctype="multipart/form-data" class="divide-y divide-gray-100">
        <input type="hidden" id="evidenceDisputeId" name="dispute_id" value="{{ $dispute->id }}">

        <!-- ── Response Templates ─────────────────────────────── -->
        <div id="templateSection" class="px-6 py-5">
          <div class="rounded-xl border border-blue-100 bg-gradient-to-br from-blue-50 to-indigo-50 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-blue-100">
              <div class="flex items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="text-blue-600">
                  <path d="M2 4h12M2 8h8M2 12h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span class="text-sm font-semibold text-blue-900">Response Templates</span>
              </div>
              <button type="button" id="loadTemplates"
                      class="text-xs font-semibold text-blue-700 bg-white border border-blue-200 hover:bg-blue-50 px-3 py-1 rounded-full transition-colors shadow-sm">
                Load Templates
              </button>
            </div>

            <div class="px-4 py-3">
              <div id="templatesContainer" class="hidden space-y-3">
                <div>
                  <label class="block text-xs font-medium text-blue-700 mb-1.5">
                    Available for <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', $dispute->reason)) }}</span>
                  </label>
                  <select id="templateSelect"
                          class="w-full px-3 py-2 text-sm border border-blue-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition-all">
                    <option value="">Choose a template…</option>
                  </select>
                </div>
                <div class="flex gap-2">
                  <button type="button" id="applyTemplate"
                          class="px-3 py-1.5 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-300 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                          disabled>
                    Apply Template
                  </button>
                  <button type="button" id="previewTemplate"
                          class="px-3 py-1.5 text-sm font-medium bg-white border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-gray-200 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                          disabled>
                    Preview
                  </button>
                </div>
              </div>

              <div id="templatesLoading" class="hidden py-3 flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                </svg>
                <span class="text-sm text-blue-600">Loading templates…</span>
              </div>

              <div id="templatesError" class="hidden py-2">
                <p class="text-sm text-red-500">Failed to load templates. Please try again.</p>
              </div>

              <div id="noTemplates" class="hidden py-2">
                <p class="text-sm text-gray-500">No templates available for this dispute reason.</p>
                @can('view-payment-dispute')
                <a href="{{ route(prefixedRouteName('dispute-templates.create')) }}?dispute_reason={{ $dispute->reason }}"
                   class="text-sm text-blue-600 hover:underline font-medium"
                   target="_blank">
                  Create a template for {{ ucfirst(str_replace('_', ' ', $dispute->reason)) }} disputes →
                </a>
                @endcan
              </div>
            </div>
          </div>
        </div>

        <!-- ── Evidence Documents ─────────────────────────────── -->
        <div class="px-6 py-5">
          <div class="flex items-start justify-between mb-3">
            <div>
              <label class="text-sm font-semibold text-gray-800">Evidence Documents</label>
              <p class="text-xs text-gray-400 mt-0.5">PDF, JPG, PNG, DOC, DOCX · max 10 MB per file</p>
            </div>
            <button type="button" id="addFileRow"
                    class="flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-lg transition-colors flex-shrink-0 ml-4">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                <path d="M6.5 1v11M1 6.5h11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
              </svg>
              Add Document
            </button>
          </div>
          <div id="fileRowsContainer" class="space-y-2.5">
            <!-- rows injected by JS -->
          </div>
        </div>

        <!-- ── Evidence Text ──────────────────────────────────── -->
        <div class="px-6 py-5">
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-sm font-semibold text-gray-800">
              Evidence Text
              <span class="font-normal text-gray-400 ml-1">optional</span>
            </label>
            <span id="evidenceTextCount" class="text-xs text-gray-400 tabular-nums">0 / 5000</span>
          </div>
          <p class="text-xs text-gray-400 mb-2">Provide a written explanation or additional context for this dispute.</p>
          <textarea name="evidence_text" id="evidenceTextarea" rows="4"
                    class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none transition-all resize-none"
                    placeholder="Describe the evidence or provide context about the transaction…" maxlength="5000"></textarea>
        </div>

        <!-- ── Description ───────────────────────────────────── -->
        <div class="px-6 py-5">
          <label class="text-sm font-semibold text-gray-800 block mb-1.5">
            Description
            <span class="font-normal text-gray-400 ml-1">optional</span>
          </label>
          <input type="text" name="description"
                 class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none transition-all"
                 placeholder="Brief description of this submission">
        </div>

        <!-- ── Footer ────────────────────────────────────────── -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-100">
          <button type="button" id="cancelEvidenceSubmission"
                  class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button type="submit" id="submitEvidenceButton"
                  class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 focus:ring-2 focus:ring-blue-300 focus:outline-none transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M14 2L7 9M14 2L9.5 14 7 9 2 6.5 14 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Submit Evidence
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

<!-- Status Update Modal -->
@can('view-payment-dispute')
<div id="statusModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
  <div class="min-h-full flex items-start justify-center py-10 px-4" style="background:rgba(15,23,42,0.45);">
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">

      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-white">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
              <path d="M14 1.5l2.5 2.5-10 10-4 1 1-4 10-10z" stroke="#D97706" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div>
            <h3 class="text-base font-semibold text-gray-900 leading-tight">Update Status</h3>
            <p class="text-xs text-gray-400 mt-0.5">Current: <span class="font-medium text-gray-600">{{ $dispute->status_label }}</span></p>
          </div>
        </div>
        <button type="button" id="closeStatusModal"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form id="statusForm" class="divide-y divide-gray-100">

        <div class="px-6 py-5 space-y-4">
          <!-- Status picker -->
          <div>
            <label class="text-sm font-semibold text-gray-800 block mb-2">New Status</label>
            <div class="grid grid-cols-1 gap-2">
              @foreach([
                ['warning_needs_response', 'Needs Response',   'bg-yellow-50 border-yellow-200 text-yellow-800'],
                ['under_review',           'Under Review',      'bg-blue-50   border-blue-200   text-blue-800'],
                ['charge_refunded',        'Charge Refunded',   'bg-purple-50 border-purple-200 text-purple-800'],
                ['lost',                   'Lost',              'bg-red-50    border-red-200    text-red-800'],
                ['won',                    'Won',               'bg-green-50  border-green-200  text-green-800'],
              ] as [$val, $label, $cls])
              <label class="flex items-center gap-3 px-3.5 py-2.5 border rounded-xl cursor-pointer transition-all
                            {{ $dispute->status === $val ? $cls.' ring-2 ring-offset-1 '.str_replace('border-', 'ring-', explode(' ', $cls)[1]) : 'border-gray-200 hover:bg-gray-50' }}">
                <input type="radio" name="status" value="{{ $val }}" class="sr-only"
                       {{ $dispute->status === $val ? 'checked' : '' }}>
                <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0
                             {{ $dispute->status === $val ? 'border-current' : 'border-gray-300' }}">
                  @if($dispute->status === $val)
                    <div class="w-2 h-2 rounded-full bg-current"></div>
                  @endif
                </div>
                <span class="text-sm font-medium {{ $dispute->status === $val ? '' : 'text-gray-700' }}">{{ $label }}</span>
                @if($dispute->status === $val)
                  <span class="ml-auto text-xs font-medium opacity-60">Current</span>
                @endif
              </label>
              @endforeach
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="text-sm font-semibold text-gray-800 block mb-1.5">
              Notes
              <span class="font-normal text-gray-400 ml-1">optional</span>
            </label>
            <textarea name="notes" rows="3"
                      class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none transition-all resize-none"
                      placeholder="Add notes about this status change…"></textarea>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-100">
          <button type="button" id="cancelStatusUpdate"
                  class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
            Cancel
          </button>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-amber-500 rounded-xl hover:bg-amber-600 focus:ring-2 focus:ring-amber-300 focus:outline-none transition-colors">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
              <path d="M11 1.5l1.5 1.5-7 7-3 1 1-3 7-7z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Update Status
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endcan

@endsection

@section('js-custom')
<script>
$(document).ready(function() {
    // Evidence submission modal handlers
    $('#uploadEvidenceBtn, #uploadEvidenceBtn2, #emptyStateUploadBtn, #alertSubmitBtn').on('click', function() {
        $('#evidenceModal').removeClass('hidden');
        $('body').addClass('overflow-hidden');
    });
    
    $('#closeEvidenceModal, #cancelEvidenceSubmission').on('click', function() {
        closeEvidenceModal();
    });
    
    // Status update modal handlers
    $('#updateStatusBtn').on('click', function() {
        $('#statusModal').removeClass('hidden');
        $('body').addClass('overflow-hidden');
    });
    
    $('#closeStatusModal, #cancelStatusUpdate').on('click', function() {
        closeStatusModal();
    });
    
    // Status radio button visual highlight
    $(document).on('change', '#statusForm input[name="status"]', function() {
        const colorMap = {
            warning_needs_response: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            under_review:           'bg-blue-50   border-blue-200   text-blue-800',
            charge_refunded:        'bg-purple-50 border-purple-200 text-purple-800',
            lost:                   'bg-red-50    border-red-200    text-red-800',
            won:                    'bg-green-50  border-green-200  text-green-800',
        };
        const activeBase = 'border-gray-200 hover:bg-gray-50';
        $('#statusForm label').each(function() {
            const radio = $(this).find('input[type="radio"]');
            const val = radio.val();
            const isSelected = radio.is(':checked');
            $(this).removeClass(Object.values(colorMap).join(' ') + ' ' + activeBase);
            $(this).addClass(isSelected ? (colorMap[val] || activeBase) : activeBase);
            const dot = $(this).find('> div.rounded-full');
            dot.toggleClass('border-gray-300', !isSelected);
            const innerDot = dot.find('div');
            if (isSelected) {
                if (!innerDot.length) dot.append('<div class="w-2 h-2 rounded-full bg-current"></div>');
            } else {
                innerDot.remove();
            }
        });
    });

    // Close modals when clicking outside
    $('#evidenceModal, #statusModal').on('click', function(e) {
        const isBackdrop = e.target === this || e.target.id === 'evidenceModalBackdrop';
        if (isBackdrop) {
            if (this.id === 'evidenceModal') {
                closeEvidenceModal();
            } else {
                closeStatusModal();
            }
        }
    });
    
    // --- Multi-file repeater ---
    const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    const MAX_FILE_SIZE = 10 * 1024 * 1024;
    const TYPE_OPTIONS = `
        <option value="">Select type</option>
        <option value="receipt">Receipt</option>
        <option value="customer_communication">Customer Communication</option>
        <option value="service_documentation">Service Documentation</option>
        <option value="shipping_documentation">Shipping Documentation</option>
        <option value="duplicate_charge_documentation">Duplicate Charge Documentation</option>
        <option value="refund_policy">Refund Policy</option>
        <option value="cancellation_policy">Cancellation Policy</option>
        <option value="customer_signature">Customer Signature</option>
        <option value="uncategorized_file">Other Documentation</option>
    `;

    function buildFileRow(index, removable) {
        const removeBtn = removable
            ? `<button type="button" class="remove-file-row flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors mt-5" title="Remove">
                 <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                   <path d="M10.5 3.5L3.5 10.5M3.5 3.5L10.5 10.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                 </svg>
               </button>`
            : '<div class="w-8 flex-shrink-0 mt-5"></div>';

        return $(`
            <div class="file-row border border-gray-200 rounded-xl p-3 bg-white hover:border-blue-200 hover:shadow-sm transition-all" data-index="${index}">
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-52">
                  <label class="block text-xs font-medium text-gray-500 mb-1.5">Document Type</label>
                  <select name="evidence_file_types[]"
                          class="w-full px-2.5 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none transition-all">
                    ${TYPE_OPTIONS}
                  </select>
                </div>
                <div class="flex-1 min-w-0">
                  <label class="block text-xs font-medium text-gray-500 mb-1.5">File</label>
                  <label class="file-upload-label flex items-center gap-2.5 px-3 py-2 border border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 bg-gray-50 transition-all group">
                    <svg class="flex-shrink-0 text-gray-400 group-hover:text-blue-500 transition-colors" width="16" height="16" viewBox="0 0 16 16" fill="none">
                      <path d="M8 10V3M8 3L5.5 5.5M8 3L10.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M2.5 11.5V12.5A1.5 1.5 0 004 14h8a1.5 1.5 0 001.5-1.5v-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="file-name-display text-sm text-gray-400 truncate group-hover:text-blue-600 transition-colors">Choose a file…</span>
                    <input type="file" name="evidence_files[]" class="evidence-file-input sr-only" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                  </label>
                  <span class="file-error text-xs text-red-500 hidden mt-1 block"></span>
                </div>
                ${removeBtn}
              </div>
            </div>
        `);
    }

    function addFileRow() {
        const index = $('#fileRowsContainer .file-row').length;
        $('#fileRowsContainer').append(buildFileRow(index, index > 0));
        refreshTypeSelects();
    }

    // Seed first row
    addFileRow();

    // Disable already-chosen types in every other row's dropdown
    function refreshTypeSelects() {
        const chosen = [];
        $('#fileRowsContainer .file-row select[name="evidence_file_types[]"]').each(function() {
            const v = $(this).val();
            if (v) chosen.push(v);
        });

        $('#fileRowsContainer .file-row select[name="evidence_file_types[]"]').each(function() {
            const $sel = $(this);
            const current = $sel.val();
            $sel.find('option').each(function() {
                const v = $(this).val();
                // Disable if taken by another row (but not by this row itself)
                $(this).prop('disabled', v && chosen.includes(v) && v !== current);
            });
        });
    }

    $(document).on('change', 'select[name="evidence_file_types[]"]', function() {
        refreshTypeSelects();
    });

    $('#addFileRow').on('click', function() {
        if ($('#fileRowsContainer .file-row').length >= 9) {
            showNotification('All 9 evidence types already added', 'error');
            return;
        }
        addFileRow();
    });

    $(document).on('click', '.remove-file-row', function() {
        $(this).closest('.file-row').remove();
        refreshTypeSelects();
    });

    $(document).on('change', '.evidence-file-input', function() {
        const file = this.files[0];
        const $label = $(this).closest('.file-upload-label');
        const $display = $label.find('.file-name-display');
        const $row = $(this).closest('.file-row');
        const $error = $row.find('.file-error');

        if (!file) {
            $display.text('Choose a file…').removeClass('text-gray-700 font-medium').addClass('text-gray-400');
            $label.removeClass('border-blue-400 bg-blue-50').addClass('border-gray-300 bg-gray-50');
            $error.addClass('hidden').text('');
            return;
        }

        const ext = file.name.split('.').pop().toLowerCase();
        if (!ALLOWED_EXTENSIONS.includes(ext)) {
            $error.removeClass('hidden').text('Unsupported file type.');
            $display.text('Choose a file…').removeClass('text-gray-700 font-medium').addClass('text-gray-400');
            this.value = '';
            return;
        }
        if (file.size > MAX_FILE_SIZE) {
            $error.removeClass('hidden').text('File exceeds 10 MB limit.');
            $display.text('Choose a file…').removeClass('text-gray-700 font-medium').addClass('text-gray-400');
            this.value = '';
            return;
        }

        $error.addClass('hidden').text('');
        $display.text(file.name).removeClass('text-gray-400').addClass('text-gray-700 font-medium');
        $label.removeClass('border-gray-300 bg-gray-50').addClass('border-blue-400 bg-blue-50');
    });

    // Character counter for evidence text
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
    
    // Template functionality
    let availableTemplates = [];

    // Load templates for dispute reason
    $('#loadTemplates').on('click', function() {
        const $button = $(this);
        const $container = $('#templatesContainer');
        const $loading = $('#templatesLoading');
        const $error = $('#templatesError');
        const $noTemplates = $('#noTemplates');
        const $select = $('#templateSelect');

        $container.addClass('hidden');
        $error.addClass('hidden');
        $noTemplates.addClass('hidden');
        $loading.removeClass('hidden');
        $button.prop('disabled', true);

        $.ajax({
            url: `{{ route(prefixedRouteName('disputes.templates'), ':disputeId') }}`.replace(':disputeId', '{{ $dispute->id }}'),
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $loading.addClass('hidden');
                $button.prop('disabled', false);

                if (response.success && response.data.templates.length > 0) {
                    availableTemplates = response.data.templates;
                    $select.empty().append('<option value="">Select a template...</option>');
                    response.data.templates.forEach(function(template) {
                        $select.append(`<option value="${template.id}">${template.name}</option>`);
                    });
                    $container.removeClass('hidden');
                } else {
                    $noTemplates.removeClass('hidden');
                }
            },
            error: function() {
                $loading.addClass('hidden');
                $error.removeClass('hidden');
                $button.prop('disabled', false);
            }
        });
    });

    // Template selection change
    $('#templateSelect').on('change', function() {
        const templateId = $(this).val();
        $('#applyTemplate, #previewTemplate').prop('disabled', !templateId);
    });

    // Apply template
    $('#applyTemplate').on('click', function() {
        const templateId = $('#templateSelect').val();
        if (!templateId) return;

        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('Applying...');

        $.ajax({
            url: `{{ route(prefixedRouteName('disputes.apply-template'), ':disputeId') }}`.replace(':disputeId', '{{ $dispute->id }}'),
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({ template_id: parseInt(templateId) }),
            success: function(response) {
                if (response.success) {
                    $('textarea[name="evidence_text"]').val(response.data.applied_content);
                    showNotification('Template applied successfully', 'success');
                } else {
                    showNotification(response.message || 'Failed to apply template', 'error');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showNotification(response?.message || 'Failed to apply template', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Preview template
    $('#previewTemplate').on('click', function() {
        const templateId = $('#templateSelect').val();
        if (!templateId) return;

        const template = availableTemplates.find(t => t.id == templateId);
        if (!template) return;

        const previewModal = $(`
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="templatePreviewModal">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Template Preview: ${template.name}</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600" onclick="$('#templatePreviewModal').remove()">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 max-h-96 overflow-y-auto">
                        <pre class="text-sm text-gray-700 whitespace-pre-wrap font-mono">${template.template_content}</pre>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400" onclick="$('#templatePreviewModal').remove()">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(previewModal);
    });

    // Evidence form submission
    $('#evidenceForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const $submitBtn = $('#submitEvidenceButton');
        const originalText = $submitBtn.text();

        // Validate: at least one file or some text
        const hasFile = Array.from(formData.getAll('evidence_files[]')).some(f => f && f.size > 0);
        const hasText = (formData.get('evidence_text') || '').trim().length > 0;
        if (!hasFile && !hasText) {
            showNotification('Please provide at least one file or some evidence text.', 'error');
            return;
        }
        
        // Show loading state
        $submitBtn.prop('disabled', true).text('Submitting...');
        
        $.ajax({
            url: `{{ route(prefixedRouteName('disputes.submit-evidence'), $dispute->id) }}`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Evidence submitted successfully!', 'success');
                    closeEvidenceModal();
                    
                    // Refresh the page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification('Error: ' + (response.message || 'Failed to submit evidence'), 'error');
                }
            },
            error: function(xhr) {
                let message = 'Failed to submit evidence';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = Object.values(xhr.responseJSON.errors).flat();
                    message = errors.join('\n');
                }
                showNotification('Error: ' + message, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Status form submission
    $('#statusForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.text();
        
        // Show loading state
        $submitBtn.prop('disabled', true).text('Updating...');
        
        $.ajax({
            url: `{{ route(prefixedRouteName('disputes.update-status'), $dispute->id) }}`,
            method: 'PUT',
            data: {
                status: formData.get('status'),
                notes: formData.get('notes'),
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Status updated successfully!', 'success');
                    closeStatusModal();
                    
                    // Refresh the page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification('Error: ' + (response.message || 'Failed to update status'), 'error');
                }
            },
            error: function(xhr) {
                let message = 'Failed to update status';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showNotification('Error: ' + message, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Sync with Stripe
    $('#syncWithStripe, #quickSyncBtn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Syncing...');
        
        $.ajax({
            url: `{{ route(prefixedRouteName('disputes.sync-stripe'), $dispute->id) }}`,
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Dispute synchronized successfully!', 'success');
                    
                    // Refresh the page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification('Error: ' + (response.message || 'Failed to sync'), 'error');
                }
            },
            error: function(xhr) {
                let message = 'Failed to sync with Stripe';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showNotification('Error: ' + message, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Timeline details toggle
    $('.toggle-details').on('click', function() {
        const target = $(this).data('target');
        const $details = $('#' + target);
        
        if ($details.hasClass('hidden')) {
            $details.removeClass('hidden');
            $(this).text('Hide Details');
        } else {
            $details.addClass('hidden');
            $(this).text('Show Details');
        }
    });
    
    // Helper functions
    function closeEvidenceModal() {
        $('#evidenceModal').addClass('hidden');
        $('#evidenceForm')[0].reset();
        $('#fileRowsContainer').empty();
        addFileRow();
        $('#evidenceTextCount').text('0 / 5000').removeClass('text-orange-500').addClass('text-gray-400');
        $('body').removeClass('overflow-hidden');
    }
    
    function closeStatusModal() {
        $('#statusModal').addClass('hidden');
        $('#statusForm')[0].reset();
        $('body').removeClass('overflow-hidden');
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Notification system
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        $('.notification').remove();
        
        const bgColor = type === 'success' ? 'bg-green-500' : 
                       type === 'error' ? 'bg-red-500' : 'bg-blue-500';
        
        const notification = $(`
            <div class="notification fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 max-w-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">${message}</span>
                    <button class="ml-3 text-white hover:text-gray-200" onclick="$(this).parent().parent().remove()">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </div>
        `);
        
        $('body').append(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Escape key closes modals
        if (e.key === 'Escape') {
            if ($('#evidenceModal').is(':visible')) {
                closeEvidenceModal();
            } else if ($('#statusModal').is(':visible')) {
                closeStatusModal();
            }
        }
    });
});
</script>
@endsection