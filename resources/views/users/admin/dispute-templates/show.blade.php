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
            <a href="{{ route(prefixedRouteName('dispute-templates.index')) }}" class="hover:text-orange-600 transition-colors">Templates</a>
            <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3.5 2l3 3-3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="text-gray-600 truncate max-w-[200px]">{{ $template->name }}</span>
          </div>
          <div class="flex items-center gap-2">
            <h1 class="text-base font-bold text-gray-900 leading-tight">{{ $template->name }}</h1>
            @if($template->is_active)
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active
              </span>
            @else
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactive
              </span>
            @endif
          </div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" id="previewTemplate"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M7.5 3C4.5 3 2 5 1 7.5c1 2.5 3.5 4.5 6.5 4.5s5.5-2 6.5-4.5C13 5 10.5 3 7.5 3z" stroke="currentColor" stroke-width="1.2"/><circle cx="7.5" cy="7.5" r="2" stroke="currentColor" stroke-width="1.2"/></svg>
          Preview
        </button>
        @can('view-payment-dispute')
        <a href="{{ route(prefixedRouteName('dispute-templates.edit'), $template->id) }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition-colors shadow-sm">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9.5 1.5l3 3-7.5 7.5-3.5 1 1-3.5 7-7z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Edit
        </a>
        @endcan
        <a href="{{ route(prefixedRouteName('dispute-templates.index')) }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9 2.5L4.5 7 9 11.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Back
        </a>
      </div>
    </div>
  </div>
</div>

{{-- ── Main Content ── --}}
<div class="bg-[#F8F9FB] min-h-screen">
  <div class="w-full container py-5">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      {{-- ── Left: 2/3 ── --}}
      <div class="lg:col-span-2 space-y-5">

        {{-- Template Content --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2.5 2h7.5l3 3v8H2.5V2z" stroke="#F97316" stroke-width="1.2"/><path d="M5 6h5M5 8.5h5M5 11h3" stroke="#F97316" stroke-width="1.1" stroke-linecap="round"/></svg>
            </div>
            <div>
              <h2 class="text-sm font-semibold text-gray-900">Template Content</h2>
              <p class="text-xs text-gray-400 mt-0.5">For {{ $template->reason_label }} disputes</p>
            </div>
            <div class="ml-auto flex items-center gap-3 text-xs text-gray-400">
              <span>{{ strlen($template->template_content) }} chars</span>
              <span>{{ str_word_count($template->template_content) }} words</span>
            </div>
          </div>
          <div class="p-5">
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
              <pre class="text-sm text-gray-700 whitespace-pre-wrap font-mono leading-relaxed">{{ $template->template_content }}</pre>
            </div>
          </div>
        </div>

        {{-- Available Variables --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M5 2.5h5M2.5 5h10M2.5 7.5h10M2.5 10h10M5 12.5h5" stroke="#16A34A" stroke-width="1.2" stroke-linecap="round"/></svg>
            </div>
            <div>
              <h2 class="text-sm font-semibold text-gray-900">Available Variables</h2>
              <p class="text-xs text-gray-400 mt-0.5">Variables usable in dispute response templates</p>
            </div>
          </div>
          <div class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              @foreach($availableVariables as $variable => $description)
              <div class="flex items-center justify-between p-3 bg-gray-50 border border-gray-100 rounded-xl hover:border-gray-200 transition-colors">
                <div class="min-w-0 flex-1">
                  <div class="text-xs font-mono font-semibold text-green-700">{{ '{'.'{'. $variable .'}'. '}' }}</div>
                  <div class="text-xs text-gray-400 mt-0.5">{{ $description }}</div>
                </div>
                @if(strpos($template->template_content, '{'.'{'. $variable .'}'. '}') !== false)
                <span class="ml-2 flex-shrink-0 inline-flex items-center px-1.5 py-0.5 rounded-md text-xs font-medium bg-green-100 text-green-700">Used</span>
                @endif
              </div>
              @endforeach
            </div>
          </div>
        </div>

        {{-- Related Templates --}}
        @if($relatedTemplates->count() > 0)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2.5 2h7.5l3 3v8H2.5V2z" stroke="#3B82F6" stroke-width="1.2"/><path d="M5 6h5M5 8.5h3" stroke="#3B82F6" stroke-width="1.1" stroke-linecap="round"/></svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-900">Related Templates</h2>
            <span class="text-xs text-gray-400 ml-1">for {{ $template->reason_label }}</span>
          </div>
          <div class="p-5 space-y-2.5">
            @foreach($relatedTemplates as $related)
            <div class="flex items-center justify-between p-3.5 bg-gray-50 border border-gray-100 rounded-xl hover:border-gray-200 transition-colors">
              <div class="flex-1 min-w-0">
                <a href="{{ route(prefixedRouteName('dispute-templates.show'), $related->id) }}"
                   class="text-sm font-medium text-gray-900 hover:text-orange-600 transition-colors">{{ $related->name }}</a>
                <div class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ Str::limit($related->template_content, 80) }}</div>
              </div>
              @if($related->is_active)
                <span class="ml-3 flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">
                  <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active
                </span>
              @else
                <span class="ml-3 flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">
                  <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactive
                </span>
              @endif
            </div>
            @endforeach
          </div>
        </div>
        @endif

      </div>

      {{-- ── Right: 1/3 ── --}}
      <div class="space-y-5">

        {{-- Template Info --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><circle cx="7.5" cy="7.5" r="6" stroke="#6B7280" stroke-width="1.2"/><path d="M7.5 5v4M7.5 10v.5" stroke="#6B7280" stroke-width="1.4" stroke-linecap="round"/></svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-900">Template Info</h2>
          </div>
          <div class="p-5 space-y-4 divide-y divide-gray-50">
            <div class="pb-3.5">
              <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Dispute Reason</dt>
              <dd class="text-sm text-gray-800">{{ $template->reason_label }}</dd>
            </div>
            <div class="py-3.5">
              <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Status</dt>
              <dd>
                @if($template->is_active)
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactive
                  </span>
                @endif
              </dd>
            </div>
            <div class="py-3.5">
              <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Created By</dt>
              <dd class="text-sm text-gray-800">
                @if($template->creator)
                  {{ $template->creator->name ?? 'Unknown' }}
                  <span class="text-xs text-gray-400">({{ $template->created_by_user_type }})</span>
                @else
                  <span class="text-gray-400">Unknown User</span>
                @endif
              </dd>
            </div>
            <div class="py-3.5">
              <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Created</dt>
              <dd class="text-sm text-gray-800">{{ $template->created_at->format('M j, Y \a\t g:i A') }}</dd>
            </div>
            <div class="pt-3.5">
              <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Last Updated</dt>
              <dd class="text-sm text-gray-800">{{ $template->updated_at->format('M j, Y \a\t g:i A') }}</dd>
            </div>
          </div>
        </div>

        {{-- Usage Stats --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M1.5 11l3-3 2.5 2 3-4 3.5 3" stroke="#3B82F6" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-900">Usage Statistics</h2>
          </div>
          <div class="p-5 space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Total Usage</span>
              <span class="text-2xl font-bold text-gray-900">{{ $usageStats['total_usage'] ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-gray-400">Last 30 days</span>
              <span class="text-lg font-semibold text-gray-700">{{ $usageStats['recent_usage'] ?? 0 }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-400">Last Used</span>
              <p class="text-sm text-gray-700 mt-0.5">
                @if($usageStats['last_used_at'])
                  {{ \Carbon\Carbon::parse($usageStats['last_used_at'])->format('M j, Y') }}
                @else
                  <span class="text-gray-400 italic">Never used</span>
                @endif
              </p>
            </div>
            @if($usageStats['success_rate'] !== null)
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-gray-400">Success Rate</span>
              <span class="text-sm font-semibold text-green-600">{{ $usageStats['success_rate'] }}%</span>
            </div>
            @endif
          </div>
        </div>

        {{-- Actions --}}
        @can('view-payment-dispute')
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M7.5 1l1.3 3.9H13l-3.3 2.4 1.3 3.9-3.5-2.5-3.5 2.5 1.3-3.9L2 4.9h4.2L7.5 1z" stroke="#D97706" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <h2 class="text-sm font-semibold text-gray-900">Actions</h2>
          </div>
          <div class="p-4 space-y-2.5">
            @if($template->is_active)
            <button type="button"
                    class="template-deactivate w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-xl hover:bg-gray-100 transition-colors"
                    data-template-id="{{ $template->id }}">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 4.5l5 5M9.5 4.5l-5 5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
              Deactivate Template
            </button>
            @else
            <button type="button"
                    class="template-activate w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition-colors"
                    data-template-id="{{ $template->id }}">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 7l2 2 3.5-3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Activate Template
            </button>
            @endif
            <button type="button"
                    class="template-delete w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition-colors"
                    data-template-id="{{ $template->id }}"
                    data-template-name="{{ $template->name }}">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1.5 3.5h11M5 3.5V2.5a.5.5 0 01.5-.5h3a.5.5 0 01.5.5v1M3 3.5l.75 8a.5.5 0 00.5.5h5.5a.5.5 0 00.5-.5L11 3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
              Delete Template
            </button>
          </div>
        </div>
        @endcan

      </div>
    </div>
  </div>
</div>

{{-- ── Preview Modal ── --}}
<div id="previewModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
  <div id="previewModalBackdrop" class="flex min-h-full items-center justify-center p-4" style="background:rgba(15,23,42,0.45);">
    <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M7.5 3C4.5 3 2 5 1 7.5c1 2.5 3.5 4.5 6.5 4.5s5.5-2 6.5-4.5C13 5 10.5 3 7.5 3z" stroke="#F97316" stroke-width="1.2"/><circle cx="7.5" cy="7.5" r="2" stroke="#F97316" stroke-width="1.2"/></svg>
          </div>
          <h3 class="text-sm font-bold text-gray-900">Template Preview</h3>
        </div>
        <button type="button" id="closePreview" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M11 4L4 11M4 4l7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
      </div>
      <div class="p-6 overflow-y-auto max-h-[65vh]">
        <div id="previewContent" class="bg-gray-50 border border-gray-200 rounded-xl p-5 min-h-[200px]">
          <div class="flex items-center justify-center h-32">
            <div class="text-center">
              <svg class="animate-spin h-8 w-8 text-orange-400 mx-auto" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <p class="text-sm text-gray-400 mt-2">Generating preview…</p>
            </div>
          </div>
        </div>
      </div>
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

    // Preview
    const previewModal = document.getElementById('previewModal');
    const previewModalBackdrop = document.getElementById('previewModalBackdrop');

    document.getElementById('previewTemplate').addEventListener('click', function() {
        previewModal.classList.remove('hidden');
        fetch(`{{ route(prefixedRouteName('dispute-templates.preview'), $template->id) }}`, {
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(data => {
            const content = data.success
                ? `<div class="text-gray-800 whitespace-pre-wrap text-sm font-mono leading-relaxed">${data.data.preview_content.replace(/\n/g, '<br>')}</div>`
                : `<p class="text-red-500 text-sm">${data.message || 'Preview failed'}</p>`;
            document.getElementById('previewContent').innerHTML = content;
        })
        .catch(() => { document.getElementById('previewContent').innerHTML = '<p class="text-red-500 text-sm">Failed to generate preview.</p>'; });
    });

    function closePreview() { previewModal.classList.add('hidden'); }
    document.getElementById('closePreview').addEventListener('click', closePreview);
    previewModalBackdrop.addEventListener('click', e => { if (e.target === previewModalBackdrop) closePreview(); });

    // Activate/Deactivate
    document.querySelectorAll('.template-activate, .template-deactivate').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.templateId;
            const isActivate = this.classList.contains('template-activate');
            const url = isActivate
                ? `{{ route(prefixedRouteName('dispute-templates.activate'), ':id') }}`.replace(':id', id)
                : `{{ route(prefixedRouteName('dispute-templates.deactivate'), ':id') }}`.replace(':id', id);
            fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
            .then(r => r.json())
            .then(data => { if (data.success) location.reload(); else alert(data.message); });
        });
    });

    // Delete
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

    function closeDeleteModal() { deleteModal.classList.add('hidden'); templateToDelete = null; }
    document.getElementById('cancelDelete').addEventListener('click', closeDeleteModal);
    deleteModalBackdrop.addEventListener('click', e => { if (e.target === deleteModalBackdrop) closeDeleteModal(); });

    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (!templateToDelete) return;
        fetch(`{{ route(prefixedRouteName('dispute-templates.destroy'), ':id') }}`.replace(':id', templateToDelete), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.href = data.data.redirect_url || `{{ route(prefixedRouteName('dispute-templates.index')) }}`;
            else alert(data.message);
        });
        closeDeleteModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closePreview(); closeDeleteModal(); }
    });
});
</script>
@endsection
