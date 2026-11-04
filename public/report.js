const { info } = require("laravel-mix/src/Log");

function getTourPackages(selTourOp) {
	var tourOpId = selTourOp.options[selTourOp.selectedIndex].value;
	var $selTourPackage = $('#tour_package');
	var $selBookedByStaff = $('#booked_by_staff');
	var pkgDefaultOpt = '<option value="">Select Tour Package</option>';
	var staffDefaultOpt = '<option value="">Select booked by person name</option>';
	$('#slot_time').html('<option value="">Select Time Slot</option>').trigger('change');

	$.getJSON(getTourOperatorDataUrl + tourOpId)
		.done(function(data) {
			$selTourPackage.html(pkgDefaultOpt);
			let html = '<a class="dropdown-item" data-value="" href="#">All Tour Packages</a>';
			let name = null;
			let id = null;
			for(var i = 0; i < data.tourPackages.length; i++){
				html += `<a class="dropdown-item" href="#" data-value="${id}">${name}</a>`;
				name = data.tourPackages[i].name;
				id = data.tourPackages[i].id;
				$selTourPackage.append(new Option(name, id, false, false));
			}

			$("#tourpackage-dropdown-menu-list").html(html);
			$selTourPackage.trigger('change');
			$selBookedByStaff.html(staffDefaultOpt);

			for(var i = 0; i < data.staff.length; i++){
				$selBookedByStaff.append(new Option(data.staff[i].first_name + ' ' + data.staff[i].last_name, data.staff[i].id, false, false));
			}
		})

		.always(hideLoading);
}

function updateReportInfo() {
	var dateFrom = $('#fromDate').val();
	var dateTo = $('#toDate').val();
	var selectedReport = $('#select-report').val();
	$("#reportHeadingPrint, #reportHeading").text(selectedReport);
	$('#sectionForDisplay, #summaryreport').removeClass('hidden');
	$('#mainTableDiv').empty();
	$('#datatableForBookingDetails').css('display','none');
	$('#datatableFortipDetails').css('display','none');
	$('#datatableBookingReport').css('display','none');
	$('#bookingTableBody').empty();


	switch(selectedReport) {
		case 'Summary Report':
		case 'Booking Details Report':
			$('#fromDateValue').val('From Date: ' + $('#fromDate').val());
			$('#toDateValue').val('To Date: ' + $('#toDate').val());
			break;
		case 'Today Availability Report':
		case 'Today Availability Details Report':

			$('#fromDateValue').val('From Date: ' + $('#fromDate').val());
			$('#toDateValue').val('');
			break;

		case 'Tax Collection Report':
		case 'Payments Report':
		case "Software Fee Report":
			if(dateFrom == dateTo) {
				$('#fromDateValue').text('Date: ' + dateFrom);
				$('#toDateValue').text('');
			} else {
				$('#fromDateValue').text('From Date: ' + dateFrom);
				$('#toDateValue').text('To Date: ' + dateTo);
			}
	}
}

function sofrtwarefeeReport(){
	var params2 = params();


	if(params2 == false)
		return;

	showLoading();

	$.post(sofrtwarefeeReportUrl, params2)
	.done(function(data) {
		updateReportInfo();

				$('#datatableForBookingDetails').css('display','none');
				$('#datatableFortipDetails').css('display','none');
				$('#datatableForRefundDetails').css('display','none');
		$('#mainTableDiv').empty();
		$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    	$('#datatableForTransaction').css('display','none');
		$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForTransactionSummary').css('display','none');
         $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		$('#datatableForaffiliateSeatsReport').css('display','none');
		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForRefundDetails').css('display','none');
        $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForAddonsPackageReport').css('display','none');
        $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForAddonsPackageDetailsReport').css('display','none');
		$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		$('#datatableForActivityLogsReport').css('display','none');
			if(data.totals.additionalTax !="0.00" || data.totals.all !="0.00" || data.totals.permitFee !="0.00" || data.totals.stateTax !="0.00" || data.totals.softwarefee !="0.00" )
			{$('#tblTaxCollection').empty();
				$('#mainTableDiv').append(
						'<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered w-full border-collapse text-left"><thead>' +
						'<tr><th class="th-custom-css">Date</th><th class="th-custom-css">Permit Fee</th><th class="th-custom-css">State Tax</th><th class="th-custom-css">Additional Tax</th><th class="th-custom-css">Software Fees</th><th class="th-custom-css">Processing Fee</th><th class="th-custom-css">Total</th></tr>' +
						'</thead><tbody id="tbl-tax-collection"><tr></tr></tbody><tfoot><tr><th class="th-custom-css">Total</th><th class="th-custom-css">' + currency(data.totals.permitFee).format() + '</th><th class="th-custom-css">' +
						currency(data.totals.stateTax).format() + '</th><th class="th-custom-css">' +currency(data.totals.additionalTax).format() + '</th><th class="th-custom-css">' +currency(data.totals.softwarefee).format() +'</th><th class="th-custom-css">' +currency(data.totals.processing_charge_percentage).format()+'</th><th class="th-custom-css">' + currency(data.totals.all).format() +
						'</th></tr></tfoot></table></div>'
					);
					$tblTaxCollection = $('#tbl-tax-collection');

					var tablerow = '';

					for(var row in data.individual) {
						tablerow +='<tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out"><td class="td-custom-css">' + row +
						'</td><td class="td-custom-css">' + currency(data.individual[row].permitFee).format() +
						'</td><td class="td-custom-css">' + currency(data.individual[row].stateTax).format() +
						'</td><td class="td-custom-css">' + currency(data.individual[row].additionalTax).format() +
						'</td><td class="td-custom-css">' + currency(data.individual[row].softwarefee).format() +
						'</td><td class="td-custom-css">' + currency(data.individual[row].processing_charge_percentage).format() +
						'</td><td class="td-custom-css">' + currency(data.individual[row].total).format() +
						'</td></tr>';
					}

					$tblTaxCollection.append(tablerow);

			}
			else{
				$('#tblTaxCollection').empty();
				$('#mainTableDiv').empty();

					$('#mainTableDiv').append(
								'<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered w-full border-collapse text-left"><thead>' +
								'<tr><th class="th-custom-css">Date</th><th class="th-custom-css">Permit Fee</th><th class="th-custom-css">State Tax</th><th class="th-custom-css">Additional Tax</th><th class="th-custom-css">Software Fees</th><th class="th-custom-css">Processing Fee</th><th class="th-custom-css">Total</th></tr>' +
								'</thead><tbody id="tbl-tax-collection"><tr class="odd"><td valign="top" colspan="10" class="td-custom-css dataTables_empty"><center>No data available in table</center></td></tr></tbody></table></div>'
							);
			}

	})
	.fail(showSubmissionError)
	.always(hideLoading);
}

function taxCollectionReport() {
	var params2 = params();


	if(params2 == false)
		return;

	showLoading();

	$.post(taxCollectionReportUrl, params2)
		.done(function(data) {
			updateReportInfo();

				$('#datatableFortipDetails').css('display','none');
				$('#datatableForBookingDetails').css('display','none');
				$('#datatableForRefundDetails').css('display','none');
			$('#mainTableDiv').empty();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForaffiliateSeatsReport').css('display','none');
            $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
				if(data.totals.additionalTax !="0.00" || data.totals.all !="0.00" || data.totals.permitFee !="0.00" || data.totals.stateTax !="0.00" )
				{$('#tblTaxCollection').empty();
					$('#mainTableDiv').append(
							'<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered w-full border-collapse text-left"><thead>' +
							'<tr><th class="th-custom-css">Date</th><th class="th-custom-css">Permit Fee</th><th class="th-custom-css">State Tax</th><th class="th-custom-css">Additional Tax</th><th class="th-custom-css">Processing Fee</th><th class="th-custom-css">Total</th></tr>' +
							'</thead><tbody id="tbl-tax-collection"><tr></tr></tbody><tfoot><tr><th class="th-custom-css">Total</th><th class="th-custom-css">' + currency(data.totals.permitFee).format() + '</th><th class="th-custom-css">' +
							currency(data.totals.stateTax).format() + '</th><th class="th-custom-css">' +currency(data.totals.additionalTax).format() + '</th> <th class="th-custom-css">' +currency(data.totals.processing_charge_percentage).format()+'</th><th class="th-custom-css">' + currency(data.totals.all).format() +
							'</th></tr></tfoot></table></div>'
						);
						$tblTaxCollection = $('#tbl-tax-collection');

						var tablerow = '';

						for(var row in data.individual) {
							tablerow +='<tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out"><td class="td-custom-css">' + row +
							'</td><td class="td-custom-css">' + currency(data.individual[row].permitFee).format() +
							'</td><td class="td-custom-css">' + currency(data.individual[row].stateTax).format() +
							'</td><td class="td-custom-css">' + currency(data.individual[row].additionalTax).format() +
							'</td><td class="td-custom-css">' + currency(data.individual[row].processing_charge_percentage).format() +
							'</td><td class="td-custom-css">' + currency(data.individual[row].total).format() +
							'</td></tr>';
						}

						$tblTaxCollection.append(tablerow);

				}
				else{
					$('#tblTaxCollection').empty();
						$('#mainTableDiv').append(
									'<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered w-full border-collapse text-left"><thead>' +
									'<tr><th class="th-custom-css">Date</th><th class="th-custom-css">Permit Fee</th><th class="th-custom-css">State Tax</th><th class="th-custom-css">Additional Tax</th><th class="th-custom-css">Processing Fee</th><th class="th-custom-css">Total</th></tr>' +
									'</thead><tbody id="tbl-tax-collection"><tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out odd"><td valign="top" colspan="10" class="td-custom-css dataTables_empty"><center>No data available in table</center></td></tr></tbody></table></div>'
								);
				}

		})
		.fail(showSubmissionError)
		.always(hideLoading);
}


/*function paymentsReport() {
	var params2 = params();
	if(params2 == false)
		return;

	showLoading();

	$.post(paymentsReportUrl, params2)
		.done(function(data) {
			updateReportInfo();
			$('#mainTableDiv').empty();
				$('#datatableFortipDetails').css('display','none');
				$('#datatableForBookingDetails').css('display','none');
				$('#datatableForRefundDetails').css('display','none');
			$("#reportHeadingPrint, #reportHeading").text($('#select-report').val());
			$('#sectionForDisplay, #summaryreport').removeClass('d-none');

			if(data !=0){
				$('#mainTableDiv').append(
				'<table class="table table-bordered table-striped table-hover w-full border-collapse text-left"><thead>' +
					'<tr><th class='th-custom-css'>Booking </th>' +'<th class='th-custom-css'>Date Time</th>'+'<th class='th-custom-css'>Via</th>'+
					'<th class='th-custom-css'>Amount</th>'+'<th class='th-custom-css'>Customer Name</th>'+'<th class='th-custom-css'>Phone</th>'+'<th class='th-custom-css'>Email</th>'+
					(params2.tourPackage ? '' : '<th class='th-custom-css'>Tour Package</th>') +

					'</thead><tbody id="tbl-transactions"></tbody></table>');

				$mainTableDiv = $('#mainTableDiv');
				$tblTransactions = $('#tbl-transactions');
				var dateTime = [], row = '';
				for(var a = 0; a < data.length; a++) {
					dateTime = data[a].created_at.split(' ');
					var Status = '';
						if( data[a].via == 'Cash'){
							Status = 'Cash'
						}else{
							Status = 'Card'
						}

					row = '<tr><td class='td-custom-css'><a href="' + bookingInfoUrl + data[a].tour_booking_id + '" target="_blank">' + data[a].tour_booking_id +
						'</a></td><td class='td-custom-css'>' + formatDate(dateTime[0]) + ' ' + formatTime(dateTime[1]) +'</td><td class='td-custom-css'>' + Status +
						'</td><td class='td-custom-css'>' + data[a].amount +
						'</td><td class='td-custom-css'>' + encLT(data[a].customer) +'</td><td class='td-custom-css'>' + data[a].phone_number +
						'</td><td class='td-custom-css'>' + data[a].email+
						(params2.tourPackage ? '' : '</td></td><td class='td-custom-css'>' + encLT(data[a].tour_package)) +

						'</td></tr>';

					$tblTransactions.append(row);
				}
			}else{
				$('#tblTaxCollection').empty();
				$('#mainTableDiv').empty();
				$('#mainTableDiv').append("<table class=table table-bordered table-striped table-hover w-full border-collapse text-left><thead> <tr><th class='th-custom-css'>Booking </th><th class='th-custom-css'>Date Time</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Customer Name</th> <th class='th-custom-css'>Phone</th> <th class='th-custom-css'>Email</th></thead><tbody id=tbl-transactions></tbody><tr class=odd><td valign=top colspan=10 class=dataTables_empty><center>No data available in table</center></td></tr></table>");
			}

		})
		.fail(showSubmissionError)
		.always(hideLoading);
}*/

function paymentsReport() {
	var params2 = params();
	if(params2 == false)
		return;

	showLoading();

	$.post(paymentsReportUrl, params2)
	.done(function(data) {
		updateReportInfo();
		$('#mainTableDiv').empty();
			$('#mainTableDiv').css('overflow','auto');
		$('#datatableFortipDetails').css('display','none');
		$('#datatableForBookingDetails').css('display','none');
		$('#datatableForRefundDetails').css('display','none');
		$("#reportHeadingPrint, #reportHeading").text($('#select-report').val());
		// $("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary'><i class='fa fa-download'></i></button>");
		$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary' onclick='downloadReport()'><i class='fa fa-download'></i></button>");
		$('#sectionForDisplay, #summaryreport').removeClass('d-none');
		$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForTransaction').css('display','none');
        $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForTransactionSummary').css('display','none');
        $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    	$('#datatableForaffiliateSeatsReport').css('display','none');
    	$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForRefundDetails').css('display','none');
        $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForAddonsPackageReport').css('display','none');
        $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
        $('#datatableForAddonsPackageDetailsReport').css('display','none');
		$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		$('#datatableForActivityLogsReport').css('display','none');


		if(data !=0){
			var arraydata = [];
			var arrayKeysInternal = [];
			for (var main in data) {

				arraydata = data[main].data;
				arrayKeysInternal = data[main].name;

				var html = "";
				var i = 0;
				var amountTotal = 0;
				for(var children in arraydata) {
					amountTotal += parseFloat(arraydata[children].amount);
				}
				html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport' style='margin-bottom: 10px;'>";

				html+="<br/><span>Tour Operator Staff Name - "+arrayKeysInternal+'</span><br/><span> Total Amount - '+amountTotal.toFixed(2)+"</span><thead><tr><th class='th-custom-css'>Booking</th><th class='th-custom-css'>Date</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Customer Name</th><th class='th-custom-css'>Phone</th><th class='th-custom-css'>Email</th></thead>";

				html += "<tbody>"

				for(var children in arraydata) {
					html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='th-custom-css'><a target=_blank href="+ bookingInfoUrl + arraydata[children].tour_booking_id + ">"+ arraydata[children].tour_booking_id +"</a></td><td class='th-custom-css'>"+ formatDate(arraydata[children].created_at) +"</td><td class='th-custom-css'>"+arraydata[children].via+"</td><td class='th-custom-css'>" + arraydata[children].amount + "</td><td class='th-custom-css'>" + arraydata[children].customer +"</td><td class='th-custom-css'>" + arraydata[children].phone_number + "</td><td class='th-custom-css'>" + arraydata[children].email + "</td></tr>";
				}
				html += "</tbody>"
				i++;
				html += "</table></div>";
				$('#mainTableDiv').append(html);
			}
		}else{
			$('#tblTaxCollection').empty();
			$('#mainTableDiv').empty();
			$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover w-full border-collapse text-left'><thead> <tr><th class='th-custom-css'>Booking </th><th class='th-custom-css'>Date</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Customer Name</th> <th class='th-custom-css'>Phone</th> <th class='th-custom-css'>Email</th></thead><tbody id=tbl-transactions></tbody><tr class=odd><td valign=top colspan=10 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></table></div>");
		}

	})
	.fail(showSubmissionError)
	.always(hideLoading);
}

function CardCollectionReport() {
	var params2 = params();
	if(params2 == false)
		return;

	showLoading();

	$.post(CardCollectionReportUrl, params2)
	.done(function(data) {
		updateReportInfo();
		$('#mainTableDiv').empty();
		$('#datatableFortipDetails').css('display','none');
		$('#datatableForBookingDetails').css('display','none');
		$('#datatableForRefundDetails').css('display','none');
		$("#reportHeadingPrint, #reportHeading").text($('#select-report').val());
		$('#sectionForDisplay, #summaryreport').removeClass('d-none');

		if(data !=0){
			var arraydata = [];
			var arrayKeysInternal = [];
			for (var main in data) {

				arraydata = data[main].data;
				arrayKeysInternal = data[main].name;

				var html = "";
				var i = 0;
				var amountTotal = 0;
				for(var children in arraydata) {
					amountTotal += parseFloat(arraydata[children].amount);
				}
				html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport' style='margin-bottom: 10px;'>";

				html+="<br/><span>Tour Operator Staff Name - "+arrayKeysInternal+'</span><br/><span> Total Amount - '+amountTotal.toFixed(2)+"</span><thead><tr><th class='th-custom-css'>Booking</th><th class='th-custom-css'>Date</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Customer Name</th><th class='th-custom-css'>Phone</th><th class='th-custom-css'>Email</th></thead>";

				html += "<tbody>"

				for(var children in arraydata) {
					html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + arraydata[children].tour_booking_id + ">"+ arraydata[children].tour_booking_id +"</a></td><td class='td-custom-css'>"+ formatDate(arraydata[children].created_at) +"</td><td class='td-custom-css'>"+arraydata[children].via+"</td><td class='td-custom-css'>" + arraydata[children].amount + "</td><td class='td-custom-css'>" + arraydata[children].customer +"</td><td class='td-custom-css'>" + arraydata[children].phone_number + "</td><td class='td-custom-css'>" + arraydata[children].email + "</td></tr>";
				}
				html += "</tbody>"
				i++;
				html += "</table></div>";
				$('#mainTableDiv').append(html);
			}
		}else{
			$('#tblTaxCollection').empty();
			$('#mainTableDiv').empty();
			$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover w-full border-collapse text-left'><thead> <tr><th class='th-custom-css'>Booking </th><th class='th-custom-css'>Date</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Customer Name</th> <th class='th-custom-css'>Phone</th> <th class='th-custom-css'>Email</th></thead><tbody id=tbl-transactions></tbody><tr class=odd><td valign=top colspan=10 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></table></div>");
		}

	})
	.fail(showSubmissionError)
	.always(hideLoading);
}


