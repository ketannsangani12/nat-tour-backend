@if($activeDispute)
@php
    $dc = match($activeDispute->status) {
        'warning_needs_response', 'lost' => ['border'=>'border-red-300','header'=>'bg-red-100','body'=>'bg-red-50','title'=>'text-red-800','badge'=>'bg-red-200 text-red-800','icon'=>'text-red-500','divider'=>'border-red-200'],
        'under_review'    => ['border'=>'border-yellow-300','header'=>'bg-yellow-100','body'=>'bg-yellow-50','title'=>'text-yellow-800','badge'=>'bg-yellow-200 text-yellow-800','icon'=>'text-yellow-500','divider'=>'border-yellow-200'],
        'won'             => ['border'=>'border-green-300','header'=>'bg-green-100','body'=>'bg-green-50','title'=>'text-green-800','badge'=>'bg-green-200 text-green-800','icon'=>'text-green-500','divider'=>'border-green-200'],
        'charge_refunded' => ['border'=>'border-blue-300','header'=>'bg-blue-100','body'=>'bg-blue-50','title'=>'text-blue-800','badge'=>'bg-blue-200 text-blue-800','icon'=>'text-blue-500','divider'=>'border-blue-200'],
        default           => ['border'=>'border-gray-300','header'=>'bg-gray-100','body'=>'bg-gray-50','title'=>'text-gray-800','badge'=>'bg-gray-200 text-gray-800','icon'=>'text-gray-500','divider'=>'border-gray-200'],
    };
@endphp
<div class="dispute-info-section border {{ $dc['border'] }} rounded-xl overflow-hidden shadow-sm mx-4 mt-4">

    {{-- Header --}}
    <div class="{{ $dc['header'] }} px-4 py-2.5 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $dc['icon'] }} flex-shrink-0">
                <path d="M7 1L13 12H1L7 1Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M7 5V7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M7 10H7.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span class="text-xs font-semibold {{ $dc['title'] }} uppercase tracking-wide">Payment Dispute Active</span>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-medium px-2.5 py-0.5 rounded-full {{ $dc['badge'] }}">
                {{ $activeDispute->status_label }}
            </span>
            @can('view-payment-dispute')
                <a href="{{ route(prefixedRouteName('disputes.show'), $activeDispute->id) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border {{ $dc['border'] }} {{ $dc['title'] }} text-xs font-medium rounded-md hover:{{ $dc['header'] }} transition-colors">
                    View Dispute
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 5H8M5.5 2.5L8 5L5.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            @endcan
        </div>
    </div>

    {{-- Body --}}
    <div class="{{ $dc['body'] }} px-4 py-3">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Dispute ID</p>
                <p class="text-xs font-semibold text-gray-800 truncate" title="{{ $activeDispute->stripe_dispute_id }}">
                    {{ $activeDispute->stripe_dispute_id }}
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Amount</p>
                <p class="text-xs font-semibold text-gray-800">
                    {{ currencyFormation(false, true) }}{{ number_format($activeDispute->amount, 2) }} {{ strtoupper($activeDispute->currency) }}
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Reason</p>
                <p class="text-xs font-semibold text-gray-800">{{ $activeDispute->reason_label }}</p>
            </div>
            @if($activeDispute->evidence_due_by)
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Evidence Due</p>
                @php
                    $days = $activeDispute->getDaysUntilDeadline();
                    $deadlineColor = $activeDispute->isOverdue() ? 'text-red-700' : ($days !== null && $days <= 3 ? 'text-orange-700' : 'text-gray-800');
                @endphp
                <p class="text-xs font-semibold {{ $deadlineColor }}">
                    {{ $activeDispute->evidence_due_by->format('M j, Y') }}
                    @if($days !== null)
                        <span class="block font-normal text-xs">
                            {{ $activeDispute->isOverdue() ? 'Overdue' : $days . ' day(s) left' }}
                        </span>
                    @endif
                </p>
            </div>
            @endif
        </div>

        @if($activeDispute->requiresAttention())
        <div class="mt-3 flex items-center gap-2 bg-red-100 border border-red-200 rounded-lg px-3 py-2">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-red-600 flex-shrink-0">
                <path d="M6 1L11 10H1L6 1Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M6 4.5V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M6 8H6.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span class="text-xs font-semibold text-red-700">Requires Immediate Attention — Evidence submission deadline is near</span>
        </div>
        @endif
    </div>

</div>
@endif

@if($disputes && $disputes->count() > 0 && !$activeDispute)
<div class="dispute-history-section border border-gray-200 rounded-xl overflow-hidden shadow-sm mx-4 mt-4">

    {{-- Header --}}
    <div class="bg-gray-100 px-4 py-2.5 flex items-center gap-2">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-gray-500">
            <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
            <path d="M7 4V7.5L9 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Dispute History</span>
    </div>

    {{-- Body --}}
    <div class="bg-gray-50 px-4 py-3 space-y-2">
        @foreach($disputes as $dispute)
        <div class="flex items-center justify-between py-1.5 {{ !$loop->last ? 'border-b border-gray-200' : '' }}">
            <div class="flex items-center gap-3">
                <span class="text-xs font-medium text-gray-700 truncate max-w-[160px]" title="{{ $dispute->stripe_dispute_id }}">
                    {{ $dispute->stripe_dispute_id }}
                </span>
                <span class="text-xs font-semibold text-gray-800">{{ currencyFormation(false, true) }}{{ number_format($dispute->amount, 2) }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $dispute->status_css_class }}">
                    {{ $dispute->status_label }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500">{{ $dispute->created_at->format('M j, Y') }}</span>
                @can('view-payment-dispute')
                    <a href="{{ route(prefixedRouteName('disputes.show'), $dispute->id) }}"
                       class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                        View
                    </a>
                @endcan
            </div>
        </div>
        @endforeach
    </div>

</div>
@endif
