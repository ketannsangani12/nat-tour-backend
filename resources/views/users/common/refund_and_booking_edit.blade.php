@extends('layouts.default-2')

@php
	$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')
<style type="text/css">
	.cash-set{color: #fff; font-weight: 800}
</style>
<div class="row default-table-design">
		<div class="col-12">
			<h2 class="font-semibold text-lg max-[1350px]:text-base text-[#111827] mb-4 max-[1350px]:mb-3">Please select group of people:</h2>

			<div class="scroll-table">
				<div class="table-responsive">
					<table class="table mb-1 tbl-rate-info">
						<thead>
							<tr id="headings-rate-group">
								<th>Age Group</th>
								<th>Number of People</th>
								<th>Rate</th>
								<th>Permit Fee</th>
								<th>Processing <br>Fee</th>
								<!-- <th>State Tax</th> -->
								<th>Adnl. Tax %</th>
								<th>Tax</th>
								<th>Final Cost</th>
							</tr>
						</thead>

						<tbody class="hidden" id="tmpl-rate-group">
							<tr>
								<td class="age"></td>
								<td class="group">
									<input type="hidden" class="hid-rate-group" data-name="rate_group[]">
									<select class="form-select tourists" onchange="updateRatesTable()" data-name="tourists[]">
										<option>0</option>
									</select>
								</td>
								<td class="price"><span class="tag">{{ moneyFormat(0.00) }}</span></td>
								<td class="normal permit-fee">{{ moneyFormat(0.00) }}</td>
								<td class="normal proc-fee">{{ moneyFormat(0.00) }}</td>
								<td class="normal adnl-tax">{{ moneyFormat(0.00) }}</td>
								<td class="normal adnl-charge">{{ moneyFormat(0.00) }}</td>
								<td class="final">{{ moneyFormat(0.00) }}</td>
							</tr>
						</tbody>

						<tbody id="table-rate-groups"></tbody>

						<tfoot>
						<tr>
								<td class="cost whitespace-nowrap">Total Cost:</td>
								<td class="total" colspan="7">
									<span class="total-before-service-commission-and-discount">{{ moneyFormat(0.00) }}</span>
									<span class="taxes">(inclusive all taxes)</span>
								</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>
</div>
@endsection