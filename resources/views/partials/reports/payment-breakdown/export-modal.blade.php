{{-- Payment Breakdown Report - Export Modal --}}

<!-- Export Modal - MUST be outside the overflow section -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 hidden" id="pbExportModal" tabindex="-1" aria-labelledby="pbExportModalLabel" aria-hidden="true" style="background-color: rgba(0, 0, 0, 0.75);">
	<div class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
			<div class="modal-header border-b border-gray-200 px-6 py-4 flex items-center justify-between">
				<h5 class="modal-title text-lg font-semibold text-gray-900 m-0" id="pbExportModalLabel">Export Payment Breakdown Report</h5>
				<button type="button" class="text-gray-400 hover:text-gray-600 text-2xl leading-none ml-auto" data-bs-dismiss="modal" aria-label="Close">&times;</button>
			</div>
			<div class="modal-body px-6 py-4">
				<form id="pbExportForm">
					<div class="mb-4">
						<label for="pbExportFormat" class="block text-sm font-medium text-gray-700 mb-2">Export Format</label>
						<select class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500" id="pbExportFormat" name="format" required>
							<option value="csv">CSV (Comma Separated Values)</option>
							<!-- <option value="excel">Excel (XLSX)</option> -->
							<option value="pdf">PDF (Portable Document Format)</option>
						</select>
					</div>
					<div class="mb-4">
						<div class="flex items-center">
							<input type="checkbox" class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" id="pbIncludeCharts" name="includeCharts" checked>
							<label class="ml-2 text-sm text-gray-700" for="pbIncludeCharts">Include summary sections</label>
						</div>
					</div>
					<div class="mb-4">
						<div class="flex items-center">
							<input type="checkbox" class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" id="pbIncludeFilters" name="includeFilters" checked>
							<label class="ml-2 text-sm text-gray-700" for="pbIncludeFilters">Include applied filters</label>
						</div>
					</div>
					<div class="mb-4" id="pbIncludeBookingDetailsWrapper">
						<div class="flex items-center">
							<input type="checkbox" class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" id="pbIncludeBookingDetails" name="includeBookingDetails">
							<label class="ml-2 text-sm text-gray-700" for="pbIncludeBookingDetails">Include booking details (CSV only)</label>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-end gap-2">
				<button type="button" class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-md transition-colors" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="px-4 py-2 text-sm bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-md transition-colors" id="pbConfirmExportBtn">
					<i class="fas fa-download mr-1"></i>
					Export Report
				</button>
			</div>
		</div>
	</div>
</div>
