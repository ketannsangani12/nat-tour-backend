@extends('layouts.default-2')
@php
    $routePrefix = routePrefix();
@endphp

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-83px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Tenants</h2>
							</div>
						</div>
                        @if (isPermissionGranted())
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.tenant.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
							</div>
						</div>
                        @endif
					</div>

                    <div class="main-content">
                        @if($errors->any())
                            @include('layouts._errors')
                        @endif

                        @if(session('message'))
                            @include('layouts._message')
                        @endif
                        <div class="w-full overflow-y-auto">
                            <table class="table table-bordered table-hover w-full border-collapse" id="datatable">
                                <thead>
                                    <tr>
                                        <th class="th-custom-css">ID</th>
                                        <th class="th-custom-css">Name</th>
                                        <th class="th-custom-css">Domain</th>
                                        <th class="th-custom-css">Timezone</th>
                                        <th class="th-custom-css" width="15%">Action</th>
                                    </tr>
                                </thead>
                                
                                <tbody></tbody>
                            </table>
                        </div>
                    </div> {{-- /.main-content --}}
                </div>
            </div>
        </div>
    </div>
</div> {{-- /.page-content --}}
{{-- OTA Keys Modal (Tailwind) --}}
<div id="otaKeysModal" class="hidden fixed inset-0 z-[1055] overflow-y-auto">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black/75 z-[1050]"></div>

    {{-- Dialog --}}
    <div class="relative z-[1060] flex items-center justify-center min-h-full p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl" id="otaModalContent">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#E5E7EB]">
                <div>
                    <h5 class="text-base font-semibold text-gray-800" id="otaKeysModalLabel">OTA Keys</h5>
                    <p class="text-xs text-gray-400 mt-0.5 mb-0">Manage bearer tokens for OTA resellers accessing this tenant's OCTO API.</p>
                </div>
                <button type="button" id="otaModalCloseBtn" class="text-gray-400 hover:text-gray-600 text-lg leading-none ml-4">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5">

                {{-- Panel 1: Key list --}}
                <div id="otaPanel-list">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-gray-500 mb-0">Active keys grant API access. Revoke immediately if a key is compromised.</p>
                        <button class="btn-warning-small !flex items-center" id="btnShowIssueForm">
                            <i class="fas fa-plus mr-2"></i> Issue New Key
                        </button>
                    </div>
                    <div id="otaKeysList">
                        <p class="text-center text-gray-400 py-6">Loading...</p>
                    </div>
                </div>

                {{-- Panel 2: Issue new key form --}}
                <div id="otaPanel-issue" style="display:none;">
                    <div class="flex items-center gap-2 mb-4">
                        <button class="text-gray-400 hover:text-gray-600 text-sm" id="btnCancelIssue">
                            <i class="fas fa-arrow-left mr-1"></i> Back
                        </button>
                        <span class="text-gray-300">|</span>
                        <span class="text-sm font-medium text-gray-700">Issue New Key</span>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">Letters and numbers only — spaces and capitals are fine. The name will be normalised into the key automatically.</p>
                    <div class="mb-5">
                        <label class="form-custom-label">Reseller Name <span class="text-red-500">*</span></label>
                        <input type="text" id="otaResellerInput" class="form-custom-input" placeholder="e.g. Headout, GetYourGuide, Viator, National Park Express CHD">
                        <p class="text-xs text-gray-400 mt-1.5">
                            Preview: <code class="bg-gray-100 px-1 rounded text-xs" id="otaKeyPreview">...-headout-test</code>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn-light-small" id="btnCancelIssue2">Cancel</button>
                        <button class="btn-primary-small" id="btnGenerateKey">
                            <i class="fas fa-key mr-2"></i> Generate & Issue Key
                        </button>
                    </div>
                </div>

                {{-- Panel 3: Key revealed (shown once) --}}
                <div id="otaPanel-reveal" style="display:none;">
                    <div class="flex gap-3 bg-[#fff3cd] border border-[#ffecb5] text-[#664d03] rounded-lg px-4 py-3 mb-5">
                        <i class="fas fa-exclamation-triangle text-base mt-0.5 flex-shrink-0"></i>
                        <div class="text-sm">
                            <strong class="block mb-1">This key will never be shown again.</strong>
                            Copy it now and send it securely to the reseller. If lost, you must revoke and issue a new one.
                        </div>
                    </div>
                    <label class="form-custom-label">API Key (read-only)</label>
                    <div class="flex gap-2 mb-5">
                        <input type="text" id="otaRevealedKey" class="form-custom-input font-mono flex-1 bg-gray-50 select-all" readonly>
                        <button class="btn-secondary-small whitespace-nowrap" id="btnCopyKey" type="button">
                            <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                    </div>
                    <label class="flex items-start gap-3 text-sm text-gray-700 cursor-pointer mb-5 p-3 bg-gray-50 rounded-lg border border-[#E5E7EB]">
                        <input type="checkbox" id="otaCopiedCheck" class="mt-0.5 flex-shrink-0">
                        <span>I have copied and saved this key. I understand it <strong>cannot be retrieved again</strong> — if lost, I must revoke and issue a new one.</span>
                    </label>
                    <button class="btn-primary-small opacity-50 cursor-not-allowed" id="btnRevealDone" disabled>
                        <i class="fas fa-check mr-2"></i> Done — Close
                    </button>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex justify-end px-6 py-4 border-t border-[#E5E7EB] bg-gray-50 rounded-b-lg">
                <button class="btn-light-small" id="otaFooterCloseBtn">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>
@endsection

@section('css-libraries')
	@if(App::environment('local'))
        <link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
	@else
        <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
	@endif
@endsection

@section('js-libraries')
	@if(App::environment('local'))
        <script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
	@else
        <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
	@endif
@endsection

@section('js-custom')
<script>
    var dtbl = null;

    $(function() {
        dtbl = $('#datatable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                method: 'POST',
                url: "{{ route($routePrefix . '.tenant.index-ahr') }}"
            },
            order: [ [0, 'desc'] ],
            columns: [
                {
                    data: 'id',
                    className: 'td-custom-css fw-bold'
                },
                {
                    data: 'name',
                    className: 'td-custom-css fw-bold'
                },
                {
                    data: 'domain',
                    className: 'td-custom-css fw-bold'
                },
                {
                    data: 'timezone',
                    className: 'td-custom-css fw-bold'
                },
                {
                    data: 'action',
                    className: 'td-custom-css text-center',
                    orderable: false,
                    searchable: false
                }
            ]
        });
    });
</script>

<x-delete-record-js-file />
<x-octo-keys-js-file />
@endsection
