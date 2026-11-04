

function getTourPackages(selTourOp, selectObj=true) {
	
	const $createLIEle = ( text, value, className='nat-list-item block px-3 py-1 text-[#4B5563] hover:text-[#F97316] cursor-pointer' ) => {
		const node = document.createElement("li");
		const textnode = document.createTextNode(text.trim());
		node.append(textnode);
		$(node).attr("data-value", value);
		node.className = className;
		return node;
	};
	
	
	let tourOpId = ( selectObj === true ) ? selTourOp.options[selTourOp.selectedIndex].value : selTourOp;
	var $selTourPackage = $('#tour_package');
	var $selBookedByStaff = $('#booked_by_staff');
	var $selReport = $('#select-report');
	var pkgDefaultOpt = '<option value="">All Tour Packages</option>';
	var staffDefaultOpt = '<option value="">Select booked by person name</option>';
	$('#slot_time').html('<option value="">Select Time Slot</option>').trigger('change');

	$.getJSON(getTourOperatorDataUrl + tourOpId)
		.done(function(data) {
			$selTourPackage.html(pkgDefaultOpt);

			let html = '<a class="dropdown-item tour-package-update block px-4 py-2 text-sm text-[#4B5563] hover:text-[#F97316]" href="#" data-value="" >All Tour Packages</a>';
			let name = null;
			let id = null;

			for(var i = 0; i < data.tourPackages.length; i++){
				name = data.tourPackages[i].name;
				id = data.tourPackages[i].id;
				html += `<a class="dropdown-item tour-package-update block px-4 py-2 text-sm text-[#4B5563] hover:text-[#F97316]" href="#" data-value="${id}">${name}</a>`;
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


	$.getJSON(getTourOperatorReportUrl + tourOpId)
		.done(function(data) {
			const el = $("#report-list");
			el.html('');
			if(data.data.reports != null){
				for(var i = 0; i < data.data.reports.length; i++){
					el.append( $createLIEle(data.data.reports[i], data.data.reports[i]) );				
					//$selReport.append(new Option(data.data.reports[i], data.data.reports[i], false, false));
				}
			}
		})
	.always(hideLoading);
}

function updateReportInfo(title=null, dateDisplay=null, viewBy=null) {
	// var dateFrom = $('#fromDate').val();
	// var dateTo = $('#toDate').val();
	let dateRange = $('#reportrange').data('daterangepicker');
	var dateFrom = dateRange.startDate.format('YYYY-MM-DD');
	var dateTo = dateRange.endDate.format('YYYY-MM-DD');
	var selectedReport = $('#select-report').val();
	var selectedPackage = $('#tour_package :selected').text();

	var heading = ( title !==null ) ? title : selectedReport;
	if(heading=='Card Collection Report' && isCashFees=='1'){
		var type = $('#reportType :selected').val();
		if(type=='Cash'){
			heading = 'Cash Collection Report';
		}else{
			heading = 'Card Collection Report';
		}
	}
	$("#reportHeadingPrint, #reportHeading, #reportHeadingPrintDiv").text(heading);
	$('#sectionForDisplay, #summaryreport').removeClass('hidden');
	$('#mainTableDiv').empty();
	$('#datatableForBookingDetails').css('display','none');
	$('#datatableFortipDetails').css('display','none');
	$('#datatableBookingReport').css('display','none');
	$('#bookingTableBody').empty();

	if(!dateDisplay){
		dateDisplay = {from: dateRange.startDate.format('MM/DD/YYYY'), to: dateRange.endDate.format('MM/DD/YYYY')};
	}

	if(!viewBy) {
		viewBy = $("#viewby-select :selected").text();
	}

    $('#printDates').html('From Date: '+dateDisplay.from+' : '+' To Date: '+dateDisplay.to + '<br>' + 'View By: ' + viewBy);
	var textfortitle = '';
	if(isAdmin){
		var selectedCompany = $('#tour_operator :selected').text();
		textfortitle += 'Tour Operator: '+selectedCompany;
		if ($('#tour_package').val() != '') {
			textfortitle += '<br>Tour Package: '+selectedPackage;
		}
	} else {
		textfortitle += 'Tour Operator: '+tourOpearatorName;
		if($('#tour_package').val()!=''){
			textfortitle += '<br>Tour Package: '+selectedPackage;
		}
	}

	$('#printPackage').html(textfortitle);
	switch(selectedReport) {
		case 'Summary Report':
		case 'Booking Details Report':
			$('#fromDateValue').val('From Date: ' + $('#fromDate').val());
			$('#toDateValue').val('To Date: ' + $('#toDate').val());
			break;
		case 'Today Availability Report':
		case 'Closure Emails Report':
		case 'Today Availability Details Report':
			$('#fromDateValue').val('From Date: ' + $('#fromDate').val());
			$('#toDateValue').val('');
			break;
		case 'Tax Collection Report':
		case 'Payments Report':
		case "Software Fee Report":
			if (dateFrom == dateTo) {
				$('#fromDateValue').text('Date: ' + dateFrom);
				$('#toDateValue').text('');
			} else {
				$('#fromDateValue').text('From Date: ' + dateFrom);
				$('#toDateValue').text('To Date: ' + dateTo);
			}
	}
}

/**
 * @description Update the onclick event of a given action button on the screen.
 * @returns void
 */
const updateActionBtn = (callback=()=>console.log("no function bound"), disabled=false, id="#btnDownload") => {
	let ele = $(id);
	ele.unbind('click');
	ele.click(callback);
	ele.css('display', 'block');
	ele.attr("disabled", disabled);

}

/**
 * @description Reset the report view elements
 * @returns void
 */
const updateReportView = () => {
	$('#mainTableDiv').empty();
	$('#bookingTableBody').empty();
	$('#datatableFortipDetails').css('display','none');
	$('#datatableForBookingDetails').css('display','none');
	$('#datatableForRefundDetails').css('display','none');
	$('#datatableForFarHarborDetails').css('display','none');
	$('#affiliateInvoiceTabs').addClass('hidden');
}

const renderDataNotFound = () => {
	$('#datatableFortipDetails').empty();
	$('#datatableBookingReport').empty();
	$('#datatableForRefundDetails').empty();
	$('#datatableForFarHarborDetails').empty();
	$('#mainTableDiv').empty();
	$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left daily-activity-report' id='dailyReportHeader' style='margin-bottom: 10px;'><thead><tr><th class='th-custom-css'>Payment Type</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Total Bookings</th><th class='th-custom-css'>Total Amount</th><th class='th-custom-css'>Total Software Fee</th></tr></thead><tbody><tr class=odd><td valign=top colspan=8 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></tbody></table></div>");
}

function sofrtwarefeeReport(){
	// var params2 = params();
	var params2 = dateRangeParams();

	if(params2 == false)
		return;

	showLoading();

	$.post(sofrtwarefeeReportUrl, params2)
	.done(function(data) {
		updateReportInfo();
		$('#datatableForBookingDetails').css('display','none');
		$('#datatableFortipDetails').css('display','none');
		$('#datatableForRefundDetails').css('display','none');
		$('#datatableForFarHarborDetails').css('display','none');
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
		{
			$('#tblTaxCollection').empty();
			$('#mainTableDiv').append(
				'<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered w-full border-collapse text-left"><thead>' +
				'<tr><th class="th-custom-css">Date</th><th class="th-custom-css">Permit Fee</th><th class="th-custom-css">State Tax</th><th class="th-custom-css">Additional Tax</th><th class="th-custom-css">Software Fees</th><th class="th-custom-css">Processing Fee</th><th class="th-custom-css">Total</th></tr>' +
				'</thead><tbody id="tbl-tax-collection"><tr></tr></tbody><tfoot><tr><th class="th-custom-css">Total</th><th class="th-custom-css">' + currency(data.totals.permitFee).format() + '</th><th class="th-custom-css">$' +
				data.totals.stateTax + '</th><th class="th-custom-css">' +currency(data.totals.additionalTax).format() + '</th><th class="th-custom-css">'+currency(data.totals.softwarefee).format() +'</th><th class="th-custom-css">'+currency(data.totals.processing_charge_percentage).format()+'</th><th class="th-custom-css">' + currency(data.totals.all).format() +
				'</th></tr></tfoot></table></div>'
			);

			$tblTaxCollection = $('#tbl-tax-collection');

			var tablerow = '';
			for(var row in data.individual) {
				tablerow +='<tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out"><td class="td-custom-css">' + moment(row).format('MM-DD-YYYY') +
				'</td><td class="td-custom-css">' + currency(data.individual[row].permitFee).format() +
				'</td><td class="td-custom-css">' + currency(data.individual[row].stateTax).format() +
				'</td><td class="td-custom-css">' + currency(data.individual[row].additionalTax).format() +
				'</td><td class="td-custom-css">' + currency(data.individual[row].softwarefee).format() +
				'</td><td class="td-custom-css">' + currency(data.individual[row].processing_charge_percentage).format() +
				'</td><td class="td-custom-css">' + currency(data.individual[row].total).format() +
				'</td></tr>';
			}
			$tblTaxCollection.append(tablerow);
		} else {
			$('#tblTaxCollection').empty();
			$('#mainTableDiv').empty();
			$('#mainTableDiv').append(
				'<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered w-full border-collapse text-left"><thead>' +
				'<tr><th class="th-custom-css">Date</th><th class="th-custom-css">Permit Fee</th><th class="th-custom-css">State Tax</th><th class="th-custom-css">Additional Tax</th><th class="th-custom-css">Software Fees</th><th class="th-custom-css">Processing Fee</th><th class="th-custom-css">Total</th></tr>' +
				'</thead><tbody id="tbl-tax-collection"><tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out odd"><td valign="top" colspan="10" class="td-custom-css dataTables_empty"><center>No data available in table</center></td></tr></tbody></table></div>'
			);
		}
	})
	.fail(showSubmissionError)
	.always(hideLoading);
}

function getFinancialReport(){
	// var params2 = params();
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
	var params2 = dateRangeParams();

	if(params2 == false)
		return;

	showLoading();

	$.post(financialReportUrl, params2)
	.done(function(data) {
		updateReportInfo();
		updateActionBtn(downloadReport, (data.length===0));
		$('#datatableForBookingDetails').css('display','none');
		$('#datatableFortipDetails').css('display','none');
		$('#datatableForRefundDetails').css('display','none');
		$('#datatableForFarHarborDetails').css('display','none');
		$('#mainTableDiv').empty();

		if(data.length != 0) {
			$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()'><i class='fa fa-download'></i></button>");

			var overallTotalBookedAmount = 0;
			let overallPaidAmount = 0
			let overallRefundAmount = 0
			let overallTouristTotal = 0
			let overallCancellationTotal = 0
			let overallUnpaidTotal = 0
			$('#mainTableDiv').empty();
			$('#datatableForBookingDetails').css('display','none');
			$('#datatableForRefundDetails').css('display','none');
			$('#bookingTableBody').empty();

			$.each(data, function(key, value){
				overallTotalBookedAmount += parseFloat((value.amountTotal).toFixed(2));
				overallPaidAmount += parseFloat((value.paidAmountTotal).toFixed(2));
				overallRefundAmount += parseFloat((value.refundTotal).toFixed(2));
				overallTouristTotal += parseFloat((value.touristsTotal).toFixed(2));
				overallCancellationTotal += parseFloat((value.cancelledBookingAmount).toFixed(2));
				overallUnpaidTotal += parseFloat((value.unpaidAmountTotal).toFixed(2));
			});

			var html = "";

			html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader' style='margin-bottom: 10px;'>";
			html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Created</th><th class='th-custom-css'>Tourists Total</th><th class='th-custom-css'>Booking Amount</th><th class='th-custom-css'>Payment Amount</th><th class='th-custom-css'>Unpaid Amount</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Cancellation Amount</th></tr></thead>";

			html += "<tbody>";
			html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(params2.dateFrom).format('MM/DD/YYYY') + " - " + moment(params2.dateTo).format('MM/DD/YYYY') + "</td><td class='td-custom-css'>" + moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css'>" + overallTouristTotal.toLocaleString('en-US') + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(overallTotalBookedAmount.toFixed(2)).format() + "</td> <td class='td-custom-css info-text-color fw-normal'>" + currency(overallPaidAmount.toFixed(2)).format() + "</td> <td class='td-custom-css alert-text-color fw-normal'>" + currency(overallUnpaidTotal.toFixed(2)).format() + "</td> <td class='td-custom-css info-text-color fw-normal'>" + currency(overallRefundAmount.toFixed(2)).format() + "</td> <td class='td-custom-css alert-text-color fw-normal'>" + currency(overallCancellationTotal.toFixed(2)).format() + "</td> </tr>";
			html += "</tbody></table></div>";
			$('#mainTableDiv').append(html);

			var arrayKeys = [];
			for (var main in data) {
				arrayKeys = Object.keys(data);
			}

			var html = "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable w-full border-collapse text-left' id='datatableBookingReport' style='margin-bottom: 10px;'><thead><tr><th class='th-custom-css'>Date</th><th class='th-custom-css'>Total tourists</th><th class='th-custom-css'>Amount Total</th><th class='th-custom-css'>paid Amount</th><th class='th-custom-css'>unpaid Amount</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Cancelled Amount</th><th class='th-custom-css'>persons</th></tr></thead>";
			var arraykeyCount = 0;
			var particularTotalBookedAmount = 0;
			var particularPaidAmount = 0;
			var particularRefundAmount = 0;
			for(var main in data) {
				rates = data[main].CustomerRateValue;

				length = Object.keys(rates);

		 	    let ratsarr = [];

				if(rates.infant !== "" && rates.infant != undefined){
					ratsarr.push(rates.infant);
				}
				if(rates.EightToTwelveChildren !== "" && rates.EightToTwelveChildren != undefined){
					ratsarr.push(rates.EightToTwelveChildren);
				}
				if(rates.FourtoSevenChildren !== "" && rates.FourtoSevenChildren != undefined){
					ratsarr.push(rates.FourtoSevenChildren);
				}
				if(rates.Adult !== "" && rates.Adult != undefined){
					ratsarr.push(rates.Adult);
				}
				if(rates.Teenager !== "" && rates.Teenager != undefined){
					ratsarr.push(rates.Teenager);
				}
				if(rates.SeniorCitizen !== "" && rates.SeniorCitizen != undefined){
					ratsarr.push(rates.SeniorCitizen);
				}
				if(rates.TLC !== "" && rates.TLC != undefined){
					ratsarr.push(rates.TLC);
				}
				if(rates.Locals !== "" && rates.Locals != undefined){
					ratsarr.push(rates.Locals);
				}
				if(rates.Guides !== "" && rates.Guides != undefined){
					ratsarr.push(rates.Guides);
				}
				if(rates.americans !== "" && rates.americans != undefined){
					ratsarr.push(rates.americans);
				}
				if(rates.AdultMilitryTeachers !== "" && rates.AdultMilitryTeachers != undefined){
					ratsarr.push(rates.AdultMilitryTeachers);
				}
				if(rates.EightToTwelveChildrenMilitryTeachers !== "" && rates.EightToTwelveChildrenMilitryTeachers != undefined){
					ratsarr.push(rates.EightToTwelveChildrenMilitryTeachers);
				}
				if(rates.FourToSevenChildrenMilitryTeachers !== "" && rates.FourToSevenChildrenMilitryTeachers != undefined){
					ratsarr.push(rates.FourToSevenChildrenMilitryTeachers);
				}
				particularTotalBookedAmount += parseFloat(data[main].amountTotal.toFixed(2));
				particularPaidAmount+= parseFloat(data[main].paidAmountTotal.toFixed(2));
				particularRefundAmount+= parseFloat(data[main].refundTotal.toFixed(2));

				html += "<tbody><tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>"+data[main].bookingdate+"</td><td class='td-custom-css'>"+data[main].touristsTotal.toLocaleString('en-US')+"</td><td class='td-custom-css'>"+(currency(data[main].amountTotal.toFixed(2)).format())+"</td><td class='td-custom-css'>"+currency(data[main].paidAmountTotal.toFixed(2)).format()+"</td><td class='td-custom-css'>"+currency(data[main].unpaidAmountTotal.toFixed(2)).format()+"</td><td class='td-custom-css'>"+currency(data[main].refundTotal.toFixed(2)).format()+"</td><td class='td-custom-css'>"+currency(data[main].cancelledBookingAmount.toFixed(2)).format()+"</td><td class='td-custom-css'>"+ratsarr.join('<br />')+"</td></tr></tbody>";
			};
			html += "</table></div>";
				$('#mainTableDiv').append(html);
		} else {
			$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()' disabled><i class='fa fa-download'></i></button>");
			$('#datatableBookingReport').empty();
			$('#datatableFortipDetails').empty();
			$('#mainTableDiv').empty();
			$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' ><thead><tr><th class='th-custom-css'>Date</th><th class='th-custom-css'>Amount Total</th><th class='th-custom-css'>Total tourists</th><th class='th-custom-css'>paid Amount</th><th class='th-custom-css'>unpaid Amount</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>persons</th></tr></thead><tbody><tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out odd'><td valign=top colspan=8 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></tbody></table></div>");
		}
	})
	.fail(showSubmissionError)
	.always(hideLoading);
}


function taxCollectionReport() {
	// var params2 = params();
	var params2 = dateRangeParams();

	if(params2 == false)
		return;

	showLoading();

	$.post(taxCollectionReportUrl, params2)
		.done(function(data) {
			updateReportInfo();
			updateActionBtn(downloadReport, (data.length===0));
				$('#datatableFortipDetails').css('display','none');
				$('#datatableForBookingDetails').css('display','none');
				$('#datatableForRefundDetails').css('display','none');
			$('#datatableForFarHarborDetails').css('display','none');
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
			if(data.totals.additionalTax !="0.00" || data.totals.all !="0.00" || data.totals.permitFee !="0.00" || data.totals.stateTax !="0.00" ) {
				$('#tblTaxCollection').empty();
				$('#mainTableDiv').append(
						'<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered w-full border-collapse text-left"><thead>' +
						'<tr><th class="th-custom-css">Date</th><th class="th-custom-css">State Tax</th></tr>' +
						'</thead><tbody id="tbl-tax-collection"><tr></tr></tbody><tfoot><tr><th class="th-custom-css"></th><th class="th-custom-css">' + currency(data.totals.stateTax).format() +
						'</th></tr></tfoot></table></div>'
					);
					$tblTaxCollection = $('#tbl-tax-collection');

					var tablerow = '';

					for(var row in data.individual) {
						tablerow +='<tr><td class="td-custom-css">' + row +
						'</td>';
						tablerow +='<td class="td-custom-css">' + currency(data.individual[row].stateTax).format()+"<br/>";
						touristsCountStateTax = data.individual[row].touristsCountStateTax;
						tablerow += touristsCountStateTax.join('<br />');
						tablerow += '</td>';
						tablerow += '</tr>';
					}

					$tblTaxCollection.append(tablerow);
				$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()'><i class='fa fa-download'></i></button>");
			} else {
				$('#tblTaxCollection').empty();
				$('#mainTableDiv').append(
					'<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered w-full border-collapse text-left"><thead>' +
					'<tr><th class="th-custom-css">Date</th><th class="th-custom-css">State Tax</th></tr>' +
					'</thead><tbody id="tbl-tax-collection"><tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out odd"><td valign="top" colspan="10" class="td-custom-css dataTables_empty"><center>No data available in table</center></td></tr></tbody></table></div>'
				);
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function paymentsReport() {

	// var params2 = params();
	var params2 = dateRangeParams();
	var viewBy = $("#viewby-select").val();
	params2.viewDate = viewBy;

	if(viewBy == "purchasedate") {
		params2.selectedReport = "Stripe Payment Report";
	} else {
		params2.selectedReport = "Card Collection Report";
	}
	if(params2 == false)
		return;

	showLoading();

	$.post(paymentsReportUrl, params2)
		.done(function(data) {
			updateReportInfo();
			updateActionBtn(downloadReport, (data.length===0));
			$('#mainTableDiv').empty();
			$('#mainTableDiv').css('overflow','auto');
			$('#datatableFortipDetails').css('display','none');
			$('#datatableForBookingDetails').css('display','none');
			$('#datatableForRefundDetails').css('display','none');
			$('#datatableForFarHarborDetails').css('display','none');
			$("#reportHeadingPrint, #reportHeading").text($('#select-report').val());
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

			if(typeof data[0].data != 'undefined'){
				// var currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
				// var toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
				var dateRange = reportDateRange();
				var arraydata = [];
				var html = "";

				html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left card-report' id='affiliateReportHeader' style='margin-bottom: 10px;'>";
				html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>"
					+"Created</th><th class='th-custom-css'>"
					+"Ticket Cost</th><th class='th-custom-css'>"
					+"Ticket Refund</th><th class='th-custom-css'>"
					+"Ticket Net</th><th class='th-custom-css'>"
					// +"Processing Fees</th><th class='th-custom-css'>"
					// +"Processing Fees Refund</th><th class='th-custom-css'>"
					// +"Processing Net</th><th class='th-custom-css'>"
					+"Platform Fees</th><th class='th-custom-css'>" +
					"Platform Fees Refund</th><th class='th-custom-css'>" +
					"Platform Fees Net</th><th class='th-custom-css'>" +
					"Discount</th><th class='th-custom-css'>" +
					"Payment Total</th><th class='th-custom-css'>" +
					"Total Refund</th><th class='th-custom-css'>" +
					"Total Net</th></tr></thead>";

				html += "<tbody>";
				html += "<tr id='cardtotals'></tr>";

				html += "</tbody></table></div>";

				$('#mainTableDiv').append(html);
				html1 = '';
				html1 += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left card-report' id='datatableBookingReport' style='margin-bottom: 10px;'>";
				html1 += "<tbody><thead><tr><th class='th-custom-css'>"
					+"ID</th><th class='th-custom-css'>"
					+"Booking Date</th><th class='th-custom-css'>"
					+"Created Date</th><th class='th-custom-css'>"
					+"Customer Name</th><th class='th-custom-css' colspan='3' style='text-align: center;'>"
					+"Ticket Cost</th>"
					// +"<th class='th-custom-css' colspan='3' style='text-align: center;'>"
					// +"Processing Fees</th>"
					+"<th class='th-custom-css' colspan='3' style='text-align: center;'>Platform Fees</th>"
					+"<th class='th-custom-css'>Discount</th>"
					+"<th class='th-custom-css'>Payment Total</th>"
					+"<th class='th-custom-css'>Refund Total</th>"
					+"<th class='th-custom-css'>Total Net</th></thead>";
				html1 += "<tr><th class='th-custom-css'>"
					+"</th><th class='th-custom-css'>"
					+"</th><th class='th-custom-css'>"
					+"</th><th class='th-custom-css'>"
					+"</th><th class='th-custom-css'>"
					+"Total</th><th class='th-custom-css'>"
					+"Refund</th><th class='th-custom-css'>"
					+"Net</th><th class='th-custom-css'>"
					// +"Total</th><th class='th-custom-css'>"
					// +"Refund</th><th class='th-custom-css'>"
					// +"Net</th><th class='th-custom-css'>"
					+"Total</th><th class='th-custom-css'>"
					+"Refund</th><th class='th-custom-css'>"
					+"Net</th>"
					+"<th class='th-custom-css'></th>"
					+"<th class='th-custom-css'></th>"
					+"<th class='th-custom-css'></th>"
					+"<th class='th-custom-css'></th>"

				var paymentamount = 0;
				for (var main in data) {
					arraydata = data[0].data;
				}
				var ticket_net_amount = 0;
				var processing_fees_net = 0;
				var service_commission_net = 0;
				var total_net = 0;
				var farharbor_total_net = 0;
				var farharbor_total_net_total = 0;

				for(var children in arraydata) {
					ticket_net_amount = arraydata[children].payment_cost - arraydata[children].refund_cost;
					processing_fees_net = arraydata[children].payment_processing_fees - arraydata[children].refund_processing_fees;
					service_commission_net = arraydata[children].payment_platform_fees - arraydata[children].refund_platform_fees;

					total_net = parseFloat(ticket_net_amount);
					var comments = '';
					comments = (typeof arraydata[children].comments !== 'undefined')?arraydata[children].comments:'';

					html1 +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css' style='min-width: 100px;max-width: 100px;'><a target=_blank href="+ bookingInfoUrl + arraydata[children].tour_booking_id + ">"+ arraydata[children].tour_booking_id +"</a></td><td class='td-custom-css'>"
						+ formatDate(arraydata[children].date)+" @ "+arraydata[children].time_from+"</td><td class='td-custom-css'>"
						+ formatDate(arraydata[children].created_at)+"</td><td class='td-custom-css' style='max-width: 30px;min-width: 30px;'>"
						+ arraydata[children].customer +"</td><td class='td-custom-css'>"
						+ currency(arraydata[children].payment_cost).format()  + "</td><td class='td-custom-css'>"
						+ currency(arraydata[children].refund_cost).format() +"</td><td class='td-custom-css'>"
						+ currency(parseFloat(ticket_net_amount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
						// + currency(arraydata[children].payment_processing_fees).format() + "</td><td class='td-custom-css'>"
						// + currency(arraydata[children].refund_processing_fees).format() + "</td><td class='td-custom-css'>"
						// + currency(parseFloat(processing_fees_net).toFixed(2)).format() + "</td><td class='td-custom-css'>"
						+ currency(arraydata[children].payment_platform_fees).format()  + "</td><td class='td-custom-css'>"
						+ currency(arraydata[children].refund_platform_fees).format() + "</td><td class='td-custom-css'>"
						+ currency(parseFloat(service_commission_net).toFixed(2)).format() + "</td><td class='td-custom-css'>"
						+ currency(arraydata[children].discount).format() + "</td><td class='td-custom-css'>"
						+ currency(arraydata[children].payment_total).format() + "</td><td class='td-custom-css'>"
						+ currency(arraydata[children].refund_total).format() + "</td><td class='td-custom-css'>"
						+ currency(parseFloat(total_net).toFixed(2)).format() + "</td></tr>";
				}

				html1 += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'><b>"+currency(data[0].ticketcost.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(data[0].ticketcostrefund.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(data[0].ticketnetamount.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"

					// +currency(data[0].processingfee.toFixed(2)).format()+"</b></td>"   +
					// "<td class='td-custom-css'><b>"+currency(data[0].processingfeerefund.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(data[0].processingfeenetamount.toFixed(2)).format()+
					// "</b></td><td class='td-custom-css'><b>"

					+currency(data[0].platformfee.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(data[0].platformfeerefund.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(data[0].platformfeenetamount.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(data[0].discountamount.toFixed(2)).format() +"</b></td><td class='td-custom-css'><b>"+currency(data[0].paymentamount.toFixed(2)).format()+"</b></td>"+"</b></td><td class='td-custom-css'><b>"+currency(data[0].refund_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(data[0].netamount.toFixed(2)).format()+"</b></td></tr>";
				html1 += "</tbody>";
				html1 += "</table></div>";

				$('#cardtotals').html("<td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " + moment(dateRange.toDate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>"
					+ moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].ticketcost.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].ticketcostrefund.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].ticketnetamount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
					// + currency(data[0].processingfee.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
					// + currency(data[0].processingfeerefund.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
					// + currency(data[0].processingfeenetamount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].platformfee.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].platformfeerefund.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].platformfeenetamount.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].discountamount.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].paymentamount.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].refund_total.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
					+ currency(data[0].netamount.toFixed(2)).format() + "</td>");
				$('#mainTableDiv').append(html1);
				$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()'><i class='fa fa-download'></i></button>");
			} else {
				$('#tblTaxCollection').empty();
				$('#mainTableDiv').empty();
				$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()' disabled><i class='fa fa-download'></i></button>");
				$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover w-full border-collapse text-left'><thead> <tr><th class='th-custom-css'>Booking </th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css min-w-32'>Time</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Customer Name</th> <th class='th-custom-css'>Phone</th> <th class='th-custom-css'>Email</th></thead><tbody id=tbl-transactions></tbody><tr class=odd><td valign=top colspan=10 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></table></div>");
			}

			if(typeof data[1].farharbor_data != 'undefined'){
				var dateRange = reportDateRange();
				// var farharbor_currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
				// var farharbor_toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
				// var yearcheck = moment($('#toDate').datepicker('getDate')).format('YYYY');
				var farharbor_arraydata = [];
				var farharbor_html = "";

				if (dateRange.yearcheck != "2023") {
					var fh_html = "";

					fh_html += '<div class="w-full overflow-y-auto mt-2"><table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="dailyReportHeader">'+
						'<thead><tr><td class="px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm" align="center">Fareharbor Card Collection</td></tr></thead><tbody></tbody></table></div>';

					farharbor_html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left card-report' id='affiliateReportHeader' style='margin-bottom: 10px;'>";
					farharbor_html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>"
						+"Created</th><th class='th-custom-css'>"
						+"Ticket Cost</th><th class='th-custom-css'>"
						+"Ticket Refund</th><th class='th-custom-css'>"
						+"Ticket Net</th><th class='th-custom-css'>"
						+"Processing Fees</th><th class='th-custom-css'>"
						+"Processing Fees Refund</th><th class='th-custom-css'>"
						+"Processing Net</th><th class='th-custom-css'>"
						+"Platform Fees</th><th class='th-custom-css'>"
						+"Platform Fees Refund</th><th class='th-custom-css'>"
						+"Platform Fees Net</th><th class='th-custom-css'>"
						+"Discount</th><th class='th-custom-css'>"
						+"Payment Total</th><th class='th-custom-css'>"
						+"Total Net</th><th class='th-custom-css'>"
						+"Total Collection</th><th class='th-custom-css'>"
						+"Total Refund</th></tr></thead>";

					farharbor_html += "<tbody>";
					farharbor_html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out' id='farharbor_cardtotals'></tr>";

					farharbor_html += "</tbody></table></div>";

					$('#mainTableDiv').append(fh_html,farharbor_html);
					farharbor_html1 = '';
					farharbor_html1 += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left card-report' id='datatableBookingReport' style='margin-bottom: 10px;'>";
					farharbor_html1 += "<tbody><thead><tr><th class='th-custom-css'>"
						+"ID</th><th class='th-custom-css'>"
						+"Booking Date</th><th class='th-custom-css'>"
						+"Created Date</th><th class='th-custom-css'>"
						+"Customer Name</th><th class='th-custom-css' colspan='3' style='text-align: center;'>"
						+"Ticket Cost</th>"
						+"<th class='th-custom-css' colspan='3' style='text-align: center;'>Processing Fees</th>"
						+"<th class='th-custom-css' colspan='3' style='text-align: center;'>Platform Fees</th>"
						+"<th class='th-custom-css'>Discount</th>"
						+"<th class='th-custom-css'>Payment Total</th>"
						+"<th class='th-custom-css'>Total Net</th></thead>";

					farharbor_html1 += "<tr><th class='th-custom-css'>"
						+"</th><th class='th-custom-css'>"
						+"</th><th class='th-custom-css'>"
						+"</th><th class='th-custom-css'>"
						+"</th><th class='th-custom-css'>"
						+"Total</th><th class='th-custom-css'>"
						+"Refund</th><th class='th-custom-css'>"
						+"Net</th><th class='th-custom-css'>"
						+"Total</th><th class='th-custom-css'>"
						+"Refund</th><th class='th-custom-css'>"
						+"Net</th><th class='th-custom-css'>"
						+"Total</th><th class='th-custom-css'>"
						+"Refund</th><th class='th-custom-css'>"
						+"Net</th>"
						+"<th class='th-custom-css'></th>"
						+"<th class='th-custom-css'></th>"
						+"<th class='th-custom-css'></th>"
						+"<th class='th-custom-css'></th>";

					var farharbor_paymentamount = 0;
					for (var main in data) {
						farharbor_arraydata = data[1].farharbor_data;
					}
					var farharbor_completed_amount = 0;
					var farharbor_ticket_cost_refund = 0;
					var farharbor_ticket_net_amount = 0;

					var farharbor_fee_amount = 0;
					var farharbor_processing_fees_refund = 0;
					var farharbor_processing_fees_net = 0;

					var farharbor_service_commission = 0;
					var farharbor_service_commission_refund = 0;
					var farharbor_service_commission_net = 0;

					var farharbor_payment_total = 0;
					var farharbor_refund_total = 0;
					var farharbor_net_total = 0;

					var farharbor_ticket_cost_total=0;
					var farharbor_ticket_cost_refund_total = 0;
					var farharbor_ticket_cost_net_total = 0;
					var farharbor_processing_fees_total = 0;
					var farharbor_processing_fees_refund_total = 0;
					var farharbor_processing_fees_net_total = 0;
					var farharbor_platform_fees_total = 0;
					var farharbor_platform_fees_refund_total = 0;
					var farharbor_platform_fees_net_total = 0;
					var farharbor_payment_total_amount = 0;
					var farharbor_discount_amount = 0;
					var farharbor_discount_total = 0;

					for(var children in farharbor_arraydata) {
						farharbor_paymentamount = parseFloat(farharbor_arraydata[children].payment_cost - farharbor_arraydata[children].refund_cost);
						farharbor_fee_amount = parseFloat(farharbor_arraydata[children].payment_processing_fees).toFixed(2);
						farharbor_completed_amount = parseFloat(farharbor_arraydata[children].payment_cost).toFixed(2);
						farharbor_service_commission = parseFloat(farharbor_arraydata[children].payment_platform_fees).toFixed(2);
						farharbor_processing_fees_refund = (typeof farharbor_arraydata[children].refund_processing_fees !== 'undefined') ? parseFloat(farharbor_arraydata[children].refund_processing_fees).toFixed(2) : 0;
						farharbor_ticket_cost_refund = (typeof farharbor_arraydata[children].refund_cost !== 'undefined') ? parseFloat(farharbor_arraydata[children].refund_cost).toFixed(2) : 0;
						farharbor_service_commission_refund = (typeof farharbor_arraydata[children].refund_platform_fees !== 'undefined') ? parseFloat(farharbor_arraydata[children].refund_platform_fees).toFixed(2) : 0;
						farharbor_ticket_net_amount = farharbor_completed_amount - farharbor_ticket_cost_refund;
						farharbor_processing_fees_net = farharbor_arraydata[children].payment_processing_fees - farharbor_processing_fees_refund;
						farharbor_service_commission_net = farharbor_arraydata[children].payment_platform_fees - farharbor_service_commission_refund;
						farharbor_discount_amount = parseFloat(farharbor_arraydata[children].discount).toFixed(2);
						farharbor_total_net = farharbor_ticket_net_amount - farharbor_processing_fees_refund - farharbor_discount_amount;

						if (farharbor_arraydata[children].status=='Cancelled'){
							farharbor_payment_total = 0.00;
						} else {
							farharbor_payment_total = (parseFloat(farharbor_ticket_net_amount) + parseFloat(farharbor_service_commission_net) + parseFloat(farharbor_processing_fees_net)).toFixed(2) - parseFloat(farharbor_discount_amount).toFixed(2);
						}
						var farharbor_comments = '';
						farharbor_comments = (typeof farharbor_arraydata[children].comments !== 'undefined')?farharbor_arraydata[children].comments:'';

						farharbor_ticket_cost_total += parseFloat(farharbor_completed_amount);
						farharbor_ticket_cost_refund_total += parseFloat(farharbor_ticket_cost_refund);
						farharbor_ticket_cost_net_total += parseFloat(farharbor_ticket_net_amount);
						farharbor_processing_fees_total += parseFloat(farharbor_fee_amount);
						farharbor_processing_fees_refund_total += parseFloat(farharbor_processing_fees_refund);
						farharbor_processing_fees_net_total += parseFloat(farharbor_processing_fees_net);
						farharbor_platform_fees_total += parseFloat(farharbor_service_commission);
						farharbor_platform_fees_refund_total += parseFloat(farharbor_service_commission_refund);
						farharbor_platform_fees_net_total += parseFloat(farharbor_service_commission_net);
						farharbor_payment_total_amount += parseFloat(farharbor_payment_total);
						farharbor_discount_total += parseFloat(farharbor_discount_amount);

						farharbor_html1 +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + farharbor_arraydata[children].tour_booking_id + ">"+ farharbor_arraydata[children].tour_booking_id +"</a></td><td class='td-custom-css'>"
							+ formatDate(farharbor_arraydata[children].date)+" @ "+farharbor_arraydata[children].time_from+"</td><td class='td-custom-css'>"
							+ formatDate(farharbor_arraydata[children].created_at)+"</td><td class='td-custom-css'>"
							+ farharbor_arraydata[children].customer +"</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_completed_amount).toFixed(2)).format()  + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_ticket_cost_refund).toFixed(2)).format() +"</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_ticket_net_amount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_fee_amount).toFixed(2)).format() + "</td><td class='td-custom-css'>"

							+ currency(parseFloat(farharbor_processing_fees_refund).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_processing_fees_net).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_service_commission).toFixed(2)).format()  + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_service_commission_refund).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_service_commission_net).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_discount_amount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_payment_total).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(farharbor_total_net).toFixed(2)).format() + "</td></tr>";
					}

					farharbor_total_net_total = farharbor_ticket_cost_net_total - farharbor_processing_fees_refund_total - farharbor_discount_total;
					farharbor_html1 += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'><b>"+currency(farharbor_ticket_cost_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_ticket_cost_refund_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_ticket_cost_net_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_processing_fees_total.toFixed(2)).format()+"</b></td>" +
						"<td class='td-custom-css'><b>"+currency(farharbor_processing_fees_refund_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_processing_fees_net_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_platform_fees_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_platform_fees_refund_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_platform_fees_net_total.toFixed(2)).format()+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_discount_total.toFixed(2)).format() +"</b></td><td class='td-custom-css'><b>"+currency(farharbor_payment_total_amount.toFixed(2)).format()+"</b></td>"+"</b></td><td class='td-custom-css'><b>"+currency(farharbor_total_net_total.toFixed(2)).format()+"</b></td></tr>";
					farharbor_html1 += "</tbody>";
					farharbor_html1 += "</table></div>";

					var  farharbor_net_total = (typeof data[1].farharbor_net_total != 'undefined')?currency(data[1].farharbor_net_total.toFixed(2)).format():currency(0.00).format();
					var  farharbor_refund_total = (typeof data[1].farharbor_refund_total != 'undefined')?currency(data[1].farharbor_refund_total.toFixed(2)).format():currency(0.00).format();

					$('#farharbor_cardtotals').html("<td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " + moment(dateRange.toDate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>"
						+ moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_ticket_cost_total.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_ticket_cost_refund_total.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_ticket_cost_net_total.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_processing_fees_total.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_processing_fees_refund_total.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_processing_fees_net_total.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_platform_fees_total.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_platform_fees_refund_total.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_platform_fees_net_total.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_discount_total.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_payment_total_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ currency(farharbor_total_net_total.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ farharbor_net_total+ "</td> <td class='td-custom-css success-text-color fw-normal'>"
						+ farharbor_refund_total + "</td>");

					$('#mainTableDiv').append(farharbor_html1);
					$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()'><i class='fa fa-download'></i></button>");
				}
			} else {
				$('#tblTaxCollection').empty();
				$('#mainTableDiv').empty();
				$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()' disabled><i class='fa fa-download'></i></button>");
				$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class=table table-bordered table-striped table-hover w-full border-collapse text-left><thead> <tr><th class='th-custom-css'>Booking </th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css min-w-32'>Time</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Customer Name</th> <th class='th-custom-css'>Phone</th> <th class='th-custom-css'>Email</th></thead><tbody id=tbl-transactions></tbody><tr class=odd><td valign=top colspan=10 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></table></div>");
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function cashCollectionReport() {
	var params2 = dateRangeParams();
	var viewBy = $("#viewby-select").val();
	params2.viewDate = viewBy;
	if(params2 == false)
		return;

	showLoading();

	$.post(cashcollectionReportUrl, params2)
		.done(function(data) {
			updateReportInfo();
			updateActionBtn(downloadReport, (data.length===0));
			$('#mainTableDiv').empty();
			$('#datatableFortipDetails').css('display','none');
			$('#datatableForBookingDetails').css('display','none');
			$('#datatableForRefundDetails').css('display','none');
			$('#datatableForFarHarborDetails').css('display','none');
			$("#reportHeadingPrint, #reportHeading").text($('#select-report').val());
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

			if(data.length != 0){
				// var currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
				// var toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
				var dateRange = reportDateRange();
				var html = "";

				html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader' style='margin-bottom: 10px;'>";
				// var checkYear = moment($('#toDate').datepicker('getDate')).format('YYYY');

				html += "<thead><tr><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Date Range</th><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Created</th><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Payment Total</th>";

				if(isCashFees == '1') {
					html += "<th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Paid Fees</th>";
				}

				if(dateRange.checkYear != "2022"){
					html += "<th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Refund Total</th>";
				}else{
					html += "<th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Fareharbor Total</th><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Refund Total</th>";
				}

				if(isCashFees == '1') {
					html += "<th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Fees Refund</th>";
				}

				html += "<th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Final Total</th></tr></thead><tbody>";

				html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " + moment(dateRange.toDate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>" + moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.alltotalamount.toFixed(2)).format() + "</td>";

				if(isCashFees == '1') {
					html += "<td class='td-custom-css'>" + currency(data.allpaidcashfees.toFixed(2)).format() + "</td>";
				}

				if (dateRange.checkYear != "2022") {
					html += "<td class='td-custom-css info-text-color fw-normal'>" + currency(data.allrefundamount.toFixed(2)).format() + "</td>";
				} else {
					html += "<td class='td-custom-css success-text-color fw-normal'>" + currency(data.allfarharbouramount.toFixed(2)).format() + "</td><td class='td-custom-css info-text-color fw-normal'>" + currency(data.allrefundamount.toFixed(2)).format() + "</td>";
				}

				if(isCashFees == '1') {
					html += "<td class='td-custom-css'>" + currency(data.allrefundcashfees.toFixed(2)).format() + "</td>";
				}

				html += "<td class='td-custom-css info-text-color fw-normal'>" + currency(data.allpaymentamount.toFixed(2)).format() + "</td> </tr>";

				html += "</tbody></table></div>";

				$('#mainTableDiv').append(html);
				var arraydata = [];
				var arrayKeysInternal = [];

				for (var main in data) {
					arraydata = data[main].data;
					arrayKeysInternal = data[main].name;

					var html1 = "";
					var i = 0;
					var amountTotal = 0;
					var refundamountTotal = 0;
					var paymentamountTotal = 0;
					var paidFeesTotal = 0;
					var refundFeesTotal = 0;
					var alltotalamount = 0;

					for(var children in arraydata) {
						amountTotal += parseFloat(arraydata[children].completed_amount);
						refundamountTotal += parseFloat(arraydata[children].refund_amount);
						paymentamountTotal += (parseFloat(arraydata[children].completed_amount) + parseFloat(arraydata[children].paid_cash_fees)) - (parseFloat(arraydata[children].refund_amount) + parseFloat(arraydata[children].refund_cash_fees));
						paidFeesTotal += parseFloat(arraydata[children].paid_cash_fees);
						refundFeesTotal += parseFloat(arraydata[children].refund_cash_fees);
						alltotalamount = arraydata[children].alltotalamount;
					}

					html1 += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport' style='margin-bottom: 10px;'>";

					if (arrayKeysInternal != null) {
						html1+="<br/><span class='text-sm text-[#7C706A]'>Tour Operator Staff Name - "+arrayKeysInternal+'</span><br/><span class="text-sm text-[#7C706A]"> Total Amount - '+currency(amountTotal.toFixed(2)).format()+'</span>';
						
						if(isCashFees == '1') {
							html1 += '<br/><span class="text-sm text-[#7C706A]"> Total Paid Fees - '+currency(paidFeesTotal.toFixed(2)).format()+'</span>';
						}

						html1 += '<br/><span class="text-sm text-[#7C706A]"> Total Refund Amount - '+currency(refundamountTotal.toFixed(2)).format()+'</span>';

						if(isCashFees == '1') {
							html1 += '<br/><span class="text-sm text-[#7C706A]"> Total Fees Refund - '+currency(refundFeesTotal.toFixed(2)).format()+'</span>';
						}
						
						html1 += '<br/><span class="text-sm text-[#7C706A]">Total Payment Amount - '+currency(paymentamountTotal.toFixed(2)).format()+"</span><thead><tr><th class='th-custom-css'>Booking</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Payment Amount</th>";
						
						if(isCashFees == '1') {
							html1 += "<th class='th-custom-css'>Paid Fees</th>";
						}

						html1 += "<th class='th-custom-css'>Refund Amount</th>";

						if(isCashFees == '1') {
							html1 += "<th class='th-custom-css'>Fees Refund</th>";
						}
						
						html1 += "<th class='th-custom-css'>Final Amount</th><th class='th-custom-css'>Customer Name</th><th class='th-custom-css'>Comments</th><th class='th-custom-css'>Status</th></thead><tbody>";
					}

					var cash = "Cash";

					for(var children in arraydata) {

						paymentamount = 0;
						// paymentamount = arraydata[children].completed_amount - arraydata[children].refund_amount;
						paymentamount = (parseFloat(arraydata[children].completed_amount) + parseFloat(arraydata[children].paid_cash_fees)) - (parseFloat(arraydata[children].refund_amount) + parseFloat(arraydata[children].refund_cash_fees));
						paymentamount = (paymentamount==0.00)?0.00:paymentamount.toFixed(2);
						completed_amount = 0;
						if (arraydata[children].completed_amount != "0.00") {
							completed_amount = parseFloat(arraydata[children].completed_amount).toFixed(2);
						} else {
							completed_amount = parseFloat(arraydata[children].completed_amount).toFixed(2);
						}

						var comments = '';
						comments = (typeof arraydata[children].comments !== 'undefined')?arraydata[children].comments:'';
						html1 +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + arraydata[children].tour_booking_id + ">"+ arraydata[children].tour_booking_id +"</a></td><td class='td-custom-css'>"+ formatDate(arraydata[children].date)+" @ "+arraydata[children].time_from+"</td><td class='td-custom-css'>"+ formatDate(arraydata[children].created_at)+"</td><td class='td-custom-css'>"+cash+"</td><td class='td-custom-css'>" + currency(completed_amount).format() + "</td>";
						
						if(isCashFees == '1') {
							html1 += "<td class='td-custom-css'>"+currency(parseFloat(arraydata[children].paid_cash_fees).toFixed(2)).format()+"</td>";
						}
						
						html1 += "<td class='td-custom-css'>"+currency(parseFloat(arraydata[children].refund_amount).toFixed(2)).format()+"</td>";
						
						if(isCashFees == '1') {
							html1 += "<td class='td-custom-css'>"+currency(parseFloat(arraydata[children].refund_cash_fees).toFixed(2)).format()+"</td>";
						}

						html1 += "<td class='td-custom-css'>" + currency(paymentamount).format() + "</td><td class='td-custom-css'>" + arraydata[children].customer +"</td><td class='td-custom-css'>" + comments + "</td><td class='td-custom-css'>" + arraydata[children].status + "</td></tr>";
					}
					html1 += "</tbody>"
					i++;
					html1 += "</table></div>";
					$('#mainTableDiv').append(html1);
				}

				$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()'><i class='fa fa-download'></i></button>");
			} else {
				$('#tblTaxCollection').empty();
				$('#mainTableDiv').empty();
				$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()' disabled><i class='fa fa-download'></i></button>");
				$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover w-full border-collapse text-left'><thead> <tr><th class='th-custom-css'>Booking </th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css min-w-32'>Time</th><th class='th-custom-css'>Via</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Customer Name</th> <th class='th-custom-css'>Phone</th> <th class='th-custom-css'>Email</th></thead><tbody id=tbl-transactions></tbody><tr class='odd'><td valign=top colspan=10 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></table></div>");
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function wireCollectionReport() {
	var params2 = dateRangeParams();
	var viewBy = $("#viewby-select").val();
	params2.viewDate = viewBy;
	if(params2 == false)
		return;

	showLoading();

	$.post(wirecollectionReportUrl, params2)
		.done(function(data) {
			updateReportInfo();
			updateActionBtn(downloadReport, (data.length===0));
			$('#mainTableDiv').empty();
			$('#datatableFortipDetails').css('display','none');
			$('#datatableForBookingDetails').css('display','none');
			$('#datatableForRefundDetails').css('display','none');
			$('#datatableForFarHarborDetails').css('display','none');
			$("#reportHeadingPrint, #reportHeading").text($('#select-report').val());
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

			if(typeof data.data != 'undefined') {
				// var currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
				// var toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
				var dateRange = reportDateRange();
				// var checkYear = moment($('#toDate').datepicker('getDate')).format('YYYY');
				var arrayData = [];
				var html = "";

				html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader' style='margin-bottom: 10px;'>";
				if (dateRange.checkYear != "2022") {
		            html += "<thead><tr><th class='th-custom-css'>"+
					"Date Range</th><th class='th-custom-css'>"+
					"Created</th><th class='th-custom-css'>"+
					"Normal Refund</th><th class='th-custom-css'>"+
					"Total Refund</th></tr></thead>";
		        } else {
		            html += "<thead><tr><th class='th-custom-css'>"+
					"Date Range</th><th class='th-custom-css'>"+
					"Created</th><th class='th-custom-css'>"+
					"Normal Refund</th><th class='th-custom-css'>"+
					"Fareharbor Refund</th><th class='th-custom-css'>"+
					"Total Refund</th></tr></thead>";
		        }

				html += "<tbody>";
				if (dateRange.checkYear != "2022") {
					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" +
					moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " +
					moment(dateRange.toDate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>" +
					moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
					currency(data.totalNormalRefund.toFixed(2)).format() + "</td><td class='td-custom-css info-text-color fw-normal'>" +
					currency(data.totalRefund.toFixed(2)).format() + "</td> </tr>";
				} else {
					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" +
					moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " +
					moment(dateRange.toDate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>" +
					moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
					currency(data.totalNormalRefund.toFixed(2)).format() + "</td><td class='td-custom-css info-text-color fw-normal'>" +
					currency(data.totalFareharborRefund.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" +
					currency(data.totalRefund.toFixed(2)).format() + "</td> </tr>";
				}

				html += "</tbody></table></div>";

				$('#mainTableDiv').append(html);

				html1 = '';
				html1 += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport' style='margin-bottom: 10px;'>";
				if (dateRange.checkYear != "2022") {
					html1 += "<tbody><thead><tr><th class='th-custom-css'>"+
					"Booking</th><th class='th-custom-css'>"+
					"Booking Date</th><th class='th-custom-css'>"+
					"Created Date</th><th class='th-custom-css'>"+
					"Via</th><th class='th-custom-css'>"+
					"Normal Refund</th><th class='th-custom-css'>"+
					"Customer Name</th><th class='th-custom-css'>"+
					"Comments</th><th class='th-custom-css'>"+
					"Status</th></thead>";
				} else {
					html1 += "<tbody><thead><tr><th class='th-custom-css'>"+
					"Booking</th><th class='th-custom-css'>"+
					"Booking Date</th><th class='th-custom-css'>"+
					"Created Date</th><th class='th-custom-css'>"+
					"Via</th><th class='th-custom-css'>"+
					"Normal Refund</th><th class='th-custom-css'>"+
					"Fareharbor Refund</th><th class='th-custom-css'>"+
					"Customer Name</th><th class='th-custom-css'>"+
					"Comments</th><th class='th-custom-css'>"+
					"Status</th></thead>";
				}

				arrayData = data.data;

				for(var children in arrayData) {
					var comments = (typeof arrayData[children].comments !== 'undefined') ? arrayData[children].comments : '';
					if(dateRange.checkYear != "2022"){
						html1 +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+
						bookingInfoUrl + arrayData[children].tour_booking_id + ">"+ arrayData[children].tour_booking_id +"</a></td><td class='td-custom-css'>"+
						formatDate(arrayData[children].date)+" @ "+arrayData[children].time_from+"</td><td class='td-custom-css'>"+
						formatDate(arrayData[children].created_at)+"</td><td class='td-custom-css'>"+
						arrayData[children].via+"</td><td class='td-custom-css'>" +
						 currency(parseFloat(arrayData[children].normal_refund).toFixed(2)).format() + "</td><td class='td-custom-css'>" +
						 arrayData[children].customer +"</td><td class='td-custom-css'>" +
						 comments + "</td><td class='td-custom-css'>" +
						 arrayData[children].status + "</td></tr>";
					} else {
						html1 +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+
						bookingInfoUrl + arrayData[children].tour_booking_id + ">"+ arrayData[children].tour_booking_id +"</a></td><td class='td-custom-css'>"+
						formatDate(arrayData[children].date)+" @ "+arrayData[children].time_from+"</td><td class='td-custom-css'>"+
						formatDate(arrayData[children].created_at)+"</td><td class='td-custom-css'>"+
						arrayData[children].via+"</td><td class='td-custom-css'>" +
						 currency(parseFloat(arrayData[children].normal_refund).toFixed(2)).format() + "</td><td class='td-custom-css'>" +
						 currency(parseFloat(arrayData[children].fareharbor_refund).toFixed(2)).format() + "</td><td class='td-custom-css'>" +
						 arrayData[children].customer +"</td><td class='td-custom-css'>" +
						 comments + "</td><td class='td-custom-css'>" +
						 arrayData[children].status + "</td></tr>";
					}
				}

				html1 += "</tbody>";
				html1 += "</table></div>";

				$('#mainTableDiv').append(html1);

				$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()'><i class='fa fa-download'></i></button>");
			} else {
				$('#tblTaxCollection').empty();
				$('#mainTableDiv').empty();
				$("#reportDownload").html("<button id='btnDownload' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' onclick='downloadReport()' disabled><i class='fa fa-download'></i></button>");
				$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover w-full border-collapse text-left'><thead> <tr><th class='th-custom-css'>"+
					"Booking </th><th class='th-custom-css'>"+
					"Booking Date</th><th class='th-custom-css'>"+
					"Created Date</th><th class='th-custom-css'>"+
					"Via</th><th class='th-custom-css'>"+
					"Normal Amount</th><th class='th-custom-css'>"+
					"Customer Name</th> <th class='th-custom-css'>"+
					"Comments</th> <th class='th-custom-css'>"+
					"Status</th>"+
					"</thead><tbody id=tbl-transactions></tbody><tr class=odd><td valign=top colspan=10 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></table></div>");
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}