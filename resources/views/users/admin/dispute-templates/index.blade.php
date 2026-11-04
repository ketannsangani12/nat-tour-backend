@extends('layouts.default-2')

@section('main-content')

{{-- ── Page Header ── --}}
<div class="bg-white border-b border-gray-100 shadow-sm">
  <div class="w-full container">
    <div class="flex items-center justify-between py-4 gap-4 flex-wrap">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 2h9l4 4v10H3V2z" stroke="#F97316" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 7h6M6 10h6M6 13h4" stroke="#F97316" stroke-width="1.2" stroke-linecap="round"/></svg>
        </div>
        <div>
          <div class="flex items-center gap-1.5 text-xs text-gray-400 mb-0.5">
            <a href="{{ route(prefixedRouteName('disputes.index')) }}" class="hover:text-orange-600 transition-colors">Disputes</a>
            <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3.5 2l3 3-3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="text-gray-600">Templates</span>
          </div>
          <h1 class="text-base font-bold text-gray-900 leading-tight">Dispute Response Templates</h1>
        </div>
      </div>
      @can('view-payment-dispute')
      <a href="{{ route(prefixedRouteName('dispute-templates.create')) }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition-colors shadow-sm">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        New Template
      </a>
      @endcan
    </div>
  </div>
</div>

{{-- ── Main Content ── --}}
<div class="bg-[#F8F9FB] min-h-screen">
  <div class="w-full container py-5 space-y-4">

    {{-- ── Stats Strip ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 2h9l4 4v10H3V2z" stroke="#F97316" stroke-width="1.3"/><path d="M6 7h6M6 10h6" stroke="#F97316" stroke-width="1.2" stroke-linecap="round"/></svg>
        </div>
        <div>
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Total</p>
          <p class="text-2xl font-bold text-gray-900 leading-tight">{{ $templates->total() }}</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="#16A34A" stroke-width="1.3"/><path d="M5.5 9l2.5 2.5 4.5-5" stroke="#16A34A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <p class="text-xs font-medium text-green-500 uppercase tracking-wide">Active</p>
          <p class="text-2xl font-bold text-green-600 leading-tight">{{ collect($reasonStats)->sum('active_count') }}</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 1.5L2.25 4.5v4.5C2.25 12.9 5.175 16.425 9 17.25c3.825-.825 6.75-4.35 6.75-8.25V4.5L9 1.5z" stroke="#3B82F6" stroke-width="1.3"/></svg>
        </div>
        <div>
          <p class="text-xs font-medium text-blue-400 uppercase tracking-wide">Reasons</p>
          <p class="text-2xl font-bold text-blue-600 leading-tight">{{ count(array_filter($reasonStats, fn($stat) => $stat['total_count'] > 0)) }}</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="#9CA3AF" stroke-width="1.3"/><path d="M6.5 6.5l5 5M11.5 6.5l-5 5" stroke="#9CA3AF" stroke-width="1.3" stroke-linecap="round"/></svg>
        </div>
        <div>
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Inactive</p>
          <p class="text-2xl font-bold text-gray-500 leading-tight">{{ collect($reasonStats)->sum('total_count') - collect($reasonStats)->sum('active_count') }}</p>
        </div>
      </div>

    </div>

    {{-- ── Filters + Table Card ── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

      <form id="clearFiltersForm" method="POST" action="{{ route(prefixedRouteName('dispute-templates.filters.clear')) }}">@csrf</form>

      {{-- Filters --}}
      <div class="px-5 py-4 border-b border-gray-50">
        <form id="filtersForm" method="POST" action="{{ route(prefixedRouteName('dispute-templates.filters')) }}">
          @csrf

          {{-- Desktop --}}
          <div class="hidden sm:flex sm:flex-wrap sm:items-center sm:gap-3">
            <div class="flex-1 min-w-[200px] max-w-[280px] relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.3"/><path d="M9.5 9.5l3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
              <input type="text" name="search" placeholder="Search by name or content…"
                     class="w-full pl-8 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white transition-colors"
                     value="{{ session('dispute_template_filters.search', '') }}">
            </div>

            <select name="dispute_reason" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white transition-colors">
              <option value="">All Reasons</option>
              @foreach($availableReasons as $reason => $label)
                <option value="{{ $reason }}" {{ session('dispute_template_filters.dispute_reason') == $reason ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>

            <select name="is_active" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white transition-colors">
              <option value="">All Statuses</option>
              <option value="1" {{ session('dispute_template_filters.is_active') === '1' ? 'selected' : '' }}>Active</option>
              <option value="0" {{ session('dispute_template_filters.is_active') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>

            <div class="flex gap-2 ml-auto">
              <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 transition-colors">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="5.5" cy="5.5" r="4" stroke="currentColor" stroke-width="1.3"/><path d="M8.5 8.5l3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                Filter
              </button>
              <button type="submit" form="clearFiltersForm" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                Clear
              </button>
            </div>
          </div>

          {{-- Mobile --}}
          <div class="sm:hidden space-y-3">
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.3"/><path d="M9.5 9.5l3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
              <input type="text" name="search" placeholder="Search templates…"
                     class="w-full pl-8 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                     value="{{ session('dispute_template_filters.search', '') }}">
            </div>
            <select name="dispute_reason" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
              <option value="">All Reasons</option>
              @foreach($availableReasons as $reason => $label)
                <option value="{{ $reason }}" {{ session('dispute_template_filters.dispute_reason') == $reason ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
            <select name="is_active" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
              <option value="">All Statuses</option>
              <option value="1" {{ session('dispute_template_filters.is_active') === '1' ? 'selected' : '' }}>Active</option>
              <option value="0" {{ session('dispute_template_filters.is_active') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>
            <div class="flex gap-2">
              <button type="submit" class="flex-1 px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 transition-colors">Apply</button>
              <button type="submit" form="clearFiltersForm" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">Clear</button>
            </div>
          </div>
        </form>
      </div>

      {{-- Toolbar --}}
      <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50 bg-gray-50/50">
        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-500">Sort:</span>
          <select id="sortBy" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400">
            <option value="name" {{ $sortBy === 'name' ? 'selected' : '' }}>Name</option>
            <option value="dispute_reason" {{ $sortBy === 'dispute_reason' ? 'selected' : '' }}>Reason</option>
            <option value="is_active" {{ $sortBy === 'is_active' ? 'selected' : '' }}>Status</option>
            <option value="created_at" {{ $sortBy === 'created_at' ? 'selected' : '' }}>Created</option>
            <option value="updated_at" {{ $sortBy === 'updated_at' ? 'selected' : '' }}>Updated</option>
          </select>
          <select id="sortDirection" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400">
            <option value="asc" {{ $sortDirection === 'asc' ? 'selected' : '' }}>Asc</option>
            <option value="desc" {{ $sortDirection === 'desc' ? 'selected' : '' }}>Desc</option>
          </select>
        </div>
        <span class="text-xs text-gray-400">{{ $templates->total() }} templates</span>
      </div>

      {{-- Desktop Table --}}
      <div class="hidden lg:block overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-5">Template</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Reason</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Status</th>
              <th class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-4">Created</th>
              <th class="text-right text-xs font-semibold text-gray-400 uppercase tracking-wide py-3 px-5">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            @forelse($templates as $template)
            <tr class="hover:bg-orange-50/20 transition-colors group">
              <td class="py-3.5 px-5">
                <a href="{{ route(prefixedRouteName('dispute-templates.show'), $template->id) }}"
                   class="text-sm font-semibold text-gray-900 hover:text-orange-600 transition-colors">
                  {{ $template->name }}
                </a>
                <div class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ Str::limit($template->template_content, 90) }}</div>
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                  {{ $template->reason_label }}
                </span>
              </td>
              <td class="py-3.5 px-4">
                @if($template->is_active)
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                    Active
                  </span>
                @else
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span>
                    Inactive
                  </span>
                @endif
              </td>
              <td class="py-3.5 px-4">
                <div class="text-sm text-gray-700">{{ $template->created_at->format('M j, Y') }}</div>
                <div class="text-xs text-gray-400">{{ $template->created_at->format('g:i A') }}</div>
              </td>
              <td class="py-3.5 px-5">
                <div class="flex items-center justify-end gap-1.5">
                  <a href="{{ route(prefixedRouteName('dispute-templates.show'), $template->id) }}"
                     class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="View">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 3C4 3 1.6 4.8 1 7c.6 2.2 3 4 6 4s5.4-1.8 6-4c-.6-2.2-3-4-6-4z" stroke="currentColor" stroke-width="1.2"/><circle cx="7" cy="7" r="2" stroke="currentColor" stroke-width="1.2"/></svg>
                  </a>
                  @can('view-payment-dispute')
                  <a href="{{ route(prefixedRouteName('dispute-templates.edit'), $template->id) }}"
                     class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-orange-600 hover:bg-orange-50 transition-colors" title="Edit">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9.5 1.5l3 3-7.5 7.5-3.5 1 1-3.5 7-7z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </a>

                  @if($template->is_active)
                  <button type="button"
                          class="template-deactivate w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                          data-template-id="{{ $template->id }}" title="Deactivate">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 4.5l5 5M9.5 4.5l-5 5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                  </button>
                  @else
                  <button type="button"
                          class="template-activate w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors"
                          data-template-id="{{ $template->id }}" title="Activate">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 7l2 2 3.5-3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  @endif

                  <button type="button"
                          class="template-delete w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                          data-template-id="{{ $template->id }}"
                          data-template-name="{{ $template->name }}" title="Delete">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1.5 3.5h11M5 3.5V2.5a.5.5 0 01.5-.5h3a.5.5 0 01.5.5v1M3 3.5l.75 8a.5.5 0 00.5.5h5.5a.5.5 0 00.5-.5L11 3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                  </button>
                  @endcan
                </div>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                  <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center">
                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M4 3h11l4 4v12H4V3z" stroke="#D1D5DB" stroke-width="1.5"/><path d="M7 9h8M7 12h8M7 15h5" stroke="#D1D5DB" stroke-width="1.3" stroke-linecap="round"/></svg>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-gray-500">No templates found</p>
                    <p class="text-xs text-gray-400 mt-0.5">Create your first dispute response template.</p>
                  </div>
                  @can('view-payment-dispute')
                  <a href="{{ route(prefixedRouteName('dispute-templates.create')) }}"
                     class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-500 text-white text-sm font-medium rounded-xl hover:bg-orange-600 transition-colors">
                    Create Template
                  </a>
                  @endcan
                </div>
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Mobile Cards --}}
      <div class="lg:hidden divide-y divide-gray-50">
        @forelse($templates as $template)
        <div class="p-4 hover:bg-gray-50/50 transition-colors">
          <div class="flex items-start justify-between mb-2">
            <a href="{{ route(prefixedRouteName('dispute-templates.show'), $template->id) }}"
               class="text-sm font-semibold text-gray-900 hover:text-orange-600 transition-colors">
              {{ $template->name }}
            </a>
            @if($template->is_active)
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 ml-2 flex-shrink-0">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active
              </span>
            @else
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 ml-2 flex-shrink-0">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactive
              </span>
            @endif
          </div>
          <div class="text-xs text-gray-400 mb-2 line-clamp-2">{{ Str::limit($template->template_content, 100) }}</div>
          <div class="flex items-center justify-between">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ $template->reason_label }}</span>
            <div class="flex items-center gap-1">
              <a href="{{ route(prefixedRouteName('dispute-templates.show'), $template->id) }}"
                 class="px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg hover:bg-gray-700 transition-colors">View</a>
              @can('view-payment-dispute')
              <a href="{{ route(prefixedRouteName('dispute-templates.edit'), $template->id) }}"
                 class="px-2.5 py-1.5 bg-orange-50 text-orange-700 border border-orange-200 text-xs font-medium rounded-lg hover:bg-orange-100 transition-colors">Edit</a>
              <button type="button"
                      class="template-delete px-2.5 py-1.5 bg-red-50 text-red-600 border border-red-200 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors"
                      data-template-id="{{ $template->id }}" data-template-name="{{ $template->name }}">Delete</button>
              @endcan
            </div>
          </div>
        </div>
        @empty
        <div class="py-16 text-center">
          <p class="text-sm font-semibold text-gray-500">No templates found</p>
          <p class="text-xs text-gray-400 mt-1">Try adjusting your filters.</p>
        </div>
        @endforelse
      </div>

      {{-- Pagination --}}
      @if($templates->hasPages())
      <div class="px-5 py-4 border-t border-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="text-xs text-gray-400">{{ $templates->total() }} total templates</div>
        <div>{{ $templates->appends(request()->query())->links() }}</div>
      </div>
      @endif

    </div>

  </div>
</div>

{{-- ── Delete Modal ── --}}
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
  <div id="deleteModalBackdrop" class="flex min-h-full items-center justify-center p-4" style="background:rgba(15,23,42,0.45);">
    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
      <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
        <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
          <svg width="17" height="17" viewBox="0 0 17 17" fill="none"><path d="M1.5 4h14M6 4V2.5a.5.5 0 01.5-.5h4a.5.5 0 01.5.5V4M3.5 4l.875 9.333A.5.5 0 004.873 14h7.254a.5.5 0 00.498-.667L13.5 4" stroke="#EF4444" stroke-width="1.3" stroke-linecap="round"/></svg>
        </div>
        <h3 class="text-sm font-bold text-gray-900">Delete Template</h3>
      </div>
      <div class="px-6 py-5">
        <p class="text-sm text-gray-600">Are you sure you want to delete <span class="font-semibold text-gray-900" id="deleteTemplateName"></span>? This action cannot be undone.</p>
      </div>
      <div class="flex items-center justify-end gap-2.5 px-6 py-4 bg-gray-50 border-t border-gray-100">
        <button type="button" id="cancelDelete" class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">Cancel</button>
        <button type="button" id="confirmDelete" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-xl hover:bg-red-700 transition-colors">Delete</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('js-custom')
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Sort
    const sortBy = document.getElementById('sortBy');
    const sortDirection = document.getElementById('sortDirection');
    function updateSort() {
        const url = new URL(window.location);
        url.searchParams.set('sort_by', sortBy.value);
        url.searchParams.set('sort_direction', sortDirection.value);
        window.location.href = url.toString();
    }
    if (sortBy) sortBy.addEventListener('change', updateSort);
    if (sortDirection) sortDirection.addEventListener('change', updateSort);

    // Activate / deactivate
    document.querySelectorAll('.template-activate, .template-deactivate').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.templateId;
            const isActivate = this.classList.contains('template-activate');
            const url = isActivate
                ? `{{ route(prefixedRouteName('dispute-templates.activate'), ':id') }}`.replace(':id', id)
                : `{{ route(prefixedRouteName('dispute-templates.deactivate'), ':id') }}`.replace(':id', id);
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            })
            .then(r => r.json())
            .then(data => { if (data.success) location.reload(); else alert(data.message); })
            .catch(() => alert('Request failed'));
        });
    });

    // Delete modal
    let templateToDelete = null;
    const deleteModal = document.getElementById('deleteModal');
    const deleteModalBackdrop = document.getElementById('deleteModalBackdrop');

    document.querySelectorAll('.template-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            templateToDelete = this.dataset.templateId;
            document.getElementById('deleteTemplateName').textContent = this.dataset.templateName;
            deleteModal.classList.remove('hidden');
        });
    });

    function closeDeleteModal() {
        deleteModal.classList.add('hidden');
        templateToDelete = null;
    }

    document.getElementById('cancelDelete').addEventListener('click', closeDeleteModal);
    deleteModalBackdrop.addEventListener('click', function(e) { if (e.target === this) closeDeleteModal(); });

    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (!templateToDelete) return;
        fetch(`{{ route(prefixedRouteName('dispute-templates.destroy'), ':id') }}`.replace(':id', templateToDelete), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(data => { if (data.success) location.reload(); else alert(data.message); })
        .catch(() => alert('Delete failed'));
        closeDeleteModal();
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDeleteModal(); });
});
</script>
@endsection
