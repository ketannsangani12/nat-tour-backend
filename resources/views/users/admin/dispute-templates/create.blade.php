@extends('layouts.default-2')

@section('main-content')

{{-- ── Page Header ── --}}
<div class="bg-white border-b border-gray-100 shadow-sm">
  <div class="w-full container">
    <div class="flex items-center justify-between py-4 gap-4 flex-wrap">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 2h9l4 4v10H3V2z" stroke="#F97316" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 6v6M6 9h6" stroke="#F97316" stroke-width="1.3" stroke-linecap="round"/></svg>
        </div>
        <div>
          <div class="flex items-center gap-1.5 text-xs text-gray-400 mb-0.5">
            <a href="{{ route(prefixedRouteName('dispute-templates.index')) }}" class="hover:text-orange-600 transition-colors">Templates</a>
            <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3.5 2l3 3-3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="text-gray-600">Create</span>
          </div>
          <h1 class="text-base font-bold text-gray-900 leading-tight">Create Dispute Response Template</h1>
        </div>
      </div>
      <a href="{{ route(prefixedRouteName('dispute-templates.index')) }}"
         class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9 2.5L4.5 7 9 11.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Back
      </a>
    </div>
  </div>
</div>

{{-- ── Form ── --}}
<div class="bg-[#F8F9FB] min-h-screen">
  <div class="w-full container py-5">
    <div class="max-w-4xl mx-auto">
      <form id="templateForm" action="{{ route(prefixedRouteName('dispute-templates.store')) }}" method="POST" class="space-y-4">
        @csrf

        {{-- Basic Info --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-50">
            <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><circle cx="7.5" cy="7.5" r="6" stroke="#F97316" stroke-width="1.2"/><path d="M7.5 5v4M7.5 10v.5" stroke="#F97316" stroke-width="1.4" stroke-linecap="round"/></svg>
            </div>
            <div>
              <h2 class="text-sm font-semibold text-gray-900">Basic Information</h2>
              <p class="text-xs text-gray-400 mt-0.5">Basic details for the dispute response template</p>
            </div>
          </div>
          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
            {{-- Name --}}
            <div class="sm:col-span-2">
              <label for="name" class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1.5">
                Template Name <span class="text-red-500">*</span>
              </label>
              <input type="text" id="name" name="name"
                     class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white transition-colors @error('name') border-red-400 @enderror"
                     placeholder="e.g., Fraudulent Charge Response"
                     value="{{ old('name') }}" required>
              @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Reason --}}
            <div>
              <label for="dispute_reason" class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1.5">
                Dispute Reason <span class="text-red-500">*</span>
              </label>
              <select id="dispute_reason" name="dispute_reason"
                      class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white transition-colors @error('dispute_reason') border-red-400 @enderror"
                      required>
                <option value="">Select a dispute reason</option>
                @foreach($availableReasons as $reason => $label)
                  <option value="{{ $reason }}" {{ old('dispute_reason') === $reason ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
              @error('dispute_reason')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Status --}}
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1.5">Status</label>
              <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 border border-gray-200 rounded-xl hover:border-orange-300 transition-colors">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       class="w-4 h-4 rounded border-gray-300 text-orange-500 focus:ring-orange-400"
                       {{ old('is_active', true) ? 'checked' : '' }}>
                <span class="text-sm text-gray-700">Active — available for use in disputes</span>
              </label>
            </div>
          </div>
        </div>

        {{-- Template Content --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2.5 2h7.5l3 3v8H2.5V2z" stroke="#16A34A" stroke-width="1.2"/><path d="M5 6h5M5 8.5h5M5 11h3" stroke="#16A34A" stroke-width="1.1" stroke-linecap="round"/></svg>
              </div>
              <div>
                <h2 class="text-sm font-semibold text-gray-900">Template Content</h2>
                <p class="text-xs text-gray-400 mt-0.5">Use <code class="font-mono text-green-700 bg-green-50 px-1 rounded">@{{variable}}</code> placeholders</p>
              </div>
            </div>
            <button type="button" id="toggleVariables"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="6.5" cy="6.5" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M6.5 4v3.5M6.5 9v.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
              Variables
            </button>
          </div>
          <div class="p-5 space-y-4">
            {{-- Variables Panel --}}
            <div id="variablesPanel" class="hidden bg-green-50 border border-green-200 rounded-xl p-4">
              <h4 class="text-xs font-semibold text-green-800 uppercase tracking-wide mb-3">Available Variables — click + to insert</h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($availableVariables as $variable => $description)
                <div class="flex items-center justify-between p-2 bg-white border border-green-100 rounded-lg hover:border-green-300 transition-colors">
                  <div class="min-w-0 flex-1">
                    <div class="text-xs font-mono font-semibold text-green-700 truncate">{{ '{'.'{'. $variable .'}'. '}' }}</div>
                    <div class="text-xs text-gray-400 truncate">{{ $description }}</div>
                  </div>
                  <button type="button" class="insert-variable ml-2 w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-green-700 hover:bg-green-100 transition-colors flex-shrink-0" data-variable="{{ $variable }}">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                  </button>
                </div>
                @endforeach
              </div>
            </div>

            {{-- Textarea --}}
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label for="template_content" class="text-xs font-semibold text-gray-700 uppercase tracking-wide">
                  Content <span class="text-red-500">*</span>
                </label>
                <span id="contentLength" class="text-xs text-gray-400">0 characters</span>
              </div>
              <textarea id="template_content" name="template_content" rows="12"
                        class="w-full px-3.5 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 placeholder-gray-400 font-mono focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white resize-y transition-colors @error('template_content') border-red-400 @enderror"
                        placeholder="Enter your dispute response template content…" required>{{ old('template_content') }}</textarea>
              @error('template_content')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
          </div>
        </div>

        {{-- Preview --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M7.5 3C4.5 3 2 5 1 7.5c1 2.5 3.5 4.5 6.5 4.5s5.5-2 6.5-4.5C13 5 10.5 3 7.5 3z" stroke="#3B82F6" stroke-width="1.2"/><circle cx="7.5" cy="7.5" r="2" stroke="#3B82F6" stroke-width="1.2"/></svg>
              </div>
              <div>
                <h2 class="text-sm font-semibold text-gray-900">Preview</h2>
                <p class="text-xs text-gray-400 mt-0.5">See how the template looks with sample data</p>
              </div>
            </div>
            <button type="button" id="generatePreview"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M6.5 2C4 2 1.8 3.6 1 5.8c.8 2.2 3 3.7 5.5 3.7s4.7-1.5 5.5-3.7C11.2 3.6 9 2 6.5 2z" stroke="currentColor" stroke-width="1.2"/><circle cx="6.5" cy="5.8" r="1.8" stroke="currentColor" stroke-width="1.2"/></svg>
              Generate Preview
            </button>
          </div>
          <div class="p-5">
            <div id="previewContent" class="min-h-[100px] p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-400 font-mono">
              Enter template content above and click "Generate Preview" to see it with sample data.
            </div>
          </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2 pb-6">
          <a href="{{ route(prefixedRouteName('dispute-templates.index')) }}"
             class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
            Cancel
          </a>
          <button type="submit" id="submitBtn"
                  class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition-colors shadow-sm">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M12 3.5l-7 7-3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Create Template
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

@endsection

@section('js-custom')
@include('users.admin.dispute-templates._form_js', ['formId' => 'templateForm', 'submitLabel' => 'Create Template', 'submitLabelLoading' => 'Creating…'])
@endsection
