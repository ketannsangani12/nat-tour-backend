// used for individual forms of age/rate group in edit page,
// for validating characters in age/rate group title when its form is submitted
var ageRateGrpFormNum = 0;

$(function () {
	if (action == 'Add') {
		//addPckgRateGrp();
	} else {
		fillPckgRates();
		$('#table-affiliate-assignment').DataTable();
		var url = window.location.href;
		var parameters = url.substring(url.lastIndexOf('/') + 1);
		$(".set-year").val(parameters);
	}
});

function addPckgRateGrp() {
	var tmpl = $('#tmpl-pckg-rate-grp')[0].cloneNode(true);
	tmpl.id = '';

	if (action == 'Update')
		tmpl.getElementsByTagName('form')[0].id = 'form-age-rate-grp-' + (ageRateGrpFormNum++);

	txts = tmpl.getElementsByClassName('form-control');

	for (var i = 0; i < txts.length; i++) {
		txts[i].setAttribute('name', txts[i].getAttribute('data-name'));

		if (txts[i].className.indexOf('txt-required') != -1)
			txts[i].setAttribute('required', 1);
	}

	$('#tbody-pckg-rate-grps').append(tmpl);
	return tmpl;
}

function fillPckgRates() {
	for (var i = 0; i < packageRates.length; i++) {
		var tmpl = addPckgRateGrp();
		tmpl.getElementsByClassName('hid-rate-id')[0].value = packageRates[i].id;
		tmpl.getElementsByClassName('txt-rate-for')[0].value = packageRates[i].rate_for;
		tmpl.getElementsByClassName('txt-age-from')[0].value = packageRates[i].age_from;
		tmpl.getElementsByClassName('txt-age-to')[0].value = packageRates[i].age_to;
		tmpl.getElementsByClassName('txt-rate')[0].value = packageRates[i].rate;
		tmpl.getElementsByClassName('txt-adnl-chrg')[0].value = packageRates[i].additional_charge;
		tmpl.getElementsByClassName('txt-min-pax-allowed')[0].value = packageRates[i].min_pax_allowed;
		tmpl.getElementsByClassName('txt-max-pax-allowed')[0].value = packageRates[i].max_pax_allowed;
		if (typeof packageRates[i].affiliate_id !== 'object' && packageRates[i].affiliate_id != 0) {
			tmpl.getElementsByClassName('txt-margin')[0].value = packageRates[i].margin;
		}
		tmpl.getElementsByClassName('txt-desc')[0].value = packageRates[i].description;
		tmpl.getElementsByClassName('txt-permit-fee')[0].value = packageRates[i].permit_fee;
		tmpl.getElementsByClassName('txt-orderby')[0].value = packageRates[i].orderby;

		var selectMappingKey = tmpl.getElementsByClassName('select-mapping-key')[0];
		for (var j = 0; j < selectMappingKey.options.length; j++) {
			if (selectMappingKey.options[j].value == packageRates[i].mapping_key) {
				selectMappingKey.options[j].selected = 1;
				break;
			}
		}

		var selStatus = tmpl.getElementsByClassName('sel-status')[0];
		for (var j = 0; j < selStatus.options.length; j++) {
			if (selStatus.options[j].text == packageRates[i].status) {
				selStatus.options[j].selected = 1;
				break;
			}
		}

		var selectGlobalRate = tmpl.getElementsByClassName('select-global-rate')[0];
		for (var j = 0; j < selectGlobalRate.options.length; j++) {
			if (selectGlobalRate.options[j].value == packageRates[i].global_rate) {
				selectGlobalRate.options[j].selected = 1;
				break;
			}
		}

		if (packageRates[i].is_group_rate == '1' || (typeof isGroupRateEnabled !== "undefined" && isGroupRateEnabled == '1')) {
			tmpl.getElementsByClassName('txt-min-group-size')[0].value = packageRates[i].min_group_size;
			tmpl.getElementsByClassName('txt-max-group-size')[0].value = packageRates[i].max_group_size;
		}
	}

	if (typeof isGroupRateEnabled !== "undefined" && isGroupRateEnabled == '1') {
		$('.grouprateth').show();
		$('.groupratetd').show();
		$('.descth').attr('colspan', 3);
	}
}

function checkHeaderOneImgAndShowPreview(elFile) {
	if (!chkFileExt(elFile)) {
		$('#img-header-one').attr('src', '');
		return;
	}

	if (elFile.value)
		previewMedia(elFile.files[0], $('#img-header-one')[0]);
	else
		$('#img-header-one').attr('src', '');
}

function checkHeaderTwoImgAndShowPreview(elFile) {
	if (!chkFileExt(elFile)) {
		$('#img-header-two').attr('src', '');
		return;
	}

	if (elFile.value)
		previewMedia(elFile.files[0], $('#img-header-two')[0]);
	else
		$('#img-header-two').attr('src', '');
}

function checkTimeDateSectionAndShowPreview(elFile) {
	if (!chkFileExt(elFile)) {
		$('#img-date-time-section-image').attr('src', '');
		return;
	}

	if (elFile.value)
		previewMedia(elFile.files[0], $('#img-date-time-section-image')[0]);
	else
		$('#img-date-time-section-image').attr('src', '');
}

function checkFrontendPackageImageAndShowPreview(elFile) {
	if (!chkFileExt(elFile)) {
		$('#img-frontend-package-image').attr('src', '');
		return;
	}

	if (elFile.value)
		previewMedia(elFile.files[0], $('#img-frontend-package-image')[0]);
	else
		$('#img-frontend-package-image').attr('src', '');
}

function checkEmailTemplateImageAndShowPreview(elFile) {
	if (!chkFileExt(elFile)) {
		$('#img-email-template-image').attr('src', '');
		return;
	}

	if (elFile.value)
		previewMedia(elFile.files[0], $('#img-email-template-image')[0]);
	else
		$('#img-email-template-image').attr('src', '');
}

function checkHeaderImgAndShowPreview(elFile) {
	if (!chkFileExt(elFile)) {
		$('#img-header').attr('src', '');
		return;
	}

	if (elFile.value)
		previewMedia(elFile.files[0], $('#img-header')[0]);
	else
		$('#img-header').attr('src', '');
}

function checkFilesAndShowPreview(elFile) {
	var tmpl = $('#tmpl-img-pckg')[0];
	var container = tmpl.parentNode;

	for (var i = container.children.length - 1; i > 0; i--)
		container.removeChild(container.children[i]);

	if (!chkFileExt(elFile))
		return;

	for (var i = 0; i < elFile.files.length; i++) {
		var tmpl2 = tmpl.cloneNode(true);
		tmpl2.id = "";
		var img = tmpl2.getElementsByClassName('img-pckg')[0];
		tmpl.parentNode.appendChild(tmpl2);
		$(tmpl2).removeClass('hidden');
		previewMedia(elFile.files[i], img);
	}
}

function validateAddPckgForm() {
	if ($('#tbody-pckg-rate-grps')[0].children.length == 0) {
		swal('At least one rate group is required', '', 'error');
		return false;
	}

	if (!validateCharacters('#name, .txt-rate-for'))
		return false;

	return true;
}

function updatePhotoStatus(chkPhotoStatus) {
	showLoading();

	$.post(updatePhotoStatusUrl + chkPhotoStatus.value, { status: chkPhotoStatus.checked ? 1 : 0 })
		.done(function () {
			swal('Status of the photo updated', '', 'success');
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function pckgRateGrpSaved(form, response) {
	if (typeof response.tour_package_rate_id != 'undefined')
		form.getElementsByClassName('hid-rate-id')[0].value = response.tour_package_rate_id;
}

function pckgRelationGrpSaved(form, response) {
	return true;
}

function pckgHotelGrpSaved(form, response) {
	return true;
}

function deletePckgRateGrp(btn) {
	var rateId = $(btn).parents('form').children('.hid-rate-id').val();

	if (rateId) {
		swal({
			title: 'Are you sure to delete the rate group?',
			text: '',
			icon: "warning",
			buttons: {
				no: {
					text: 'No, cancel!',
					value: false,
				},
				yes: {
					text: 'Yes, delete!',
					className: 'btn-danger',
				}
			}
		}).then(function (result) {
			if (!result)
				return;

			showLoading();

			$.ajax({
				type: 'POST',
				url: deletePckgRateGrpUrl + rateId,
				data: { _method: 'DELETE' }
			})
				.done(function () {
					swal('Rate group deleted', '', 'success');
					$(btn).parents('.pckg-rate-grp').remove();
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		});

		return;
	} else
		$(btn).parents('.pckg-rate-grp').remove();
}

function updateUrlForYear(selectedYear) {
	var url = window.location.href;
	var parameters = url.split(/\?|&/);
	url = parameters[0].slice(0, -4) + selectedYear.value
	window.location.href = url
}

function setTime(sel) {
	$sel = $(sel);
	var $td = $sel.parents('div');
	var $h = $td.find('.sel-time-hour');
	var $m = $td.find('.sel-time-minute');
	var $ap = $td.find('.sel-time-am-pm');
	var h = $h.val();
	var m = $m.val();
	var ap = $ap.val();

	if (h !== '')
		h = parseInt(h);

	if ($sel.hasClass('sel-time-hour')) { // hour changed
		if (h !== '') {
			if (m === '')
				$m.val('00');

			if (ap === '')
				$ap.val('am');
		}
	}
	else if ($sel.hasClass('sel-time-minute')) { // minute changed
		if (m !== '') {
			if (h === '')
				$h.val(0);

			if (ap === '')
				$ap.val('am');
		}
	}
	else { // if($sel.hasClass('sel-time-am-pm')) { // am-pm changed
		if (ap !== '') {
			if (h === '')
				$h.val(0);

			if (m === '')
				$m.val('00');
		}
	}

	if (ap == 'pm' && h !== '')
		h += 12;

	if (h === '' || m === '' || ap === '')
		$td.find('.hid-time').val('');
	else
		$td.find('.hid-time').val((h < 10 ? '0' : '') + h + ':' + m + ':00');
}
function changeRefundPercent() {
	var refundBasis = $("#refund_basis").val();
	if (refundBasis == 'no_refund') {
		$('#refund_percent').val(0);
		$('#refund_percent').attr('readonly', 'true');
	} else {
		$('#refund_percent').val(0);
		$('#refund_percent').removeAttr("readonly");
	}
}

function displayGroupRatePaxNumberSection() {
	var val = $('#is_group_rate_enabled option:selected').val();
	console.log(val);
	if (val == '1') {
		$('.grouprateth').show();
		$('.groupratetd').show();
	} else {
		$('.grouprateth').hide();
		$('.groupratetd').hide();
	}
}

function pckgBundleRelationSaved(form, response) {
	return true;
}

$('.document-panel .setting-categorized').on('change', function () {
	var inputName = $(this).attr('name');
	$('.document-panel .setting-categorized').not(this).filter(`[name=${inputName}]`).prop('checked', false);
	var value = $('.document-panel .setting-categorized:checked').filter(`[name=${inputName}]`).val();
	if (value != '') {
		var dataObj = {};
		dataObj[inputName] = value;
		settingUpdated(dataObj);
	}
});

$('.document-panel .setting').on('change', function () {
	var inputName = $(this).attr('name');
	if (inputName == 'status') {
		var value = ($(this).is(":checked")) ? 'Active' : 'Inactive';
	} else {
		var value = ($(this).is(":checked")) ? 1 : 0;
	}

	var dataObj = {};
	dataObj[inputName] = value;
	settingUpdated(dataObj);
});

function settingUpdated(dataObj) {
	showLoading();
	$.post(updateSettingUrl, dataObj)
		.done(function () {
			if (Object.hasOwnProperty.call(dataObj, 'checkin_type')) {
				if (dataObj.checkin_type == 1) {
					$('#booking_expiration').show();
				} else {
					$('#booking_expiration').hide();
				}

			}
			$.notify({
				// options
				title: '<strong>Success</strong>',
				message: "<br>Setting updated successfully",
				icon: 'glyphicon glyphicon-ok'
			}, {
				// settings
				element: 'body',
				type: "success",
				allow_dismiss: true,
				showProgressbar: false,
				placement: {
					from: "top",
					align: "right"
				},
				offset: 20,
				spacing: 10,
				z_index: 1031,
				delay: 3300,
				timer: 1000,
				animate: {
					enter: 'animated fadeInDown',
					exit: 'animated fadeOutRight'
				}
			});
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function assignedAffiliateUpdated(dataObj) {
	$.post(assignAffiliateUrl, dataObj)
		.done(function () {
			console.log('here');
			$.notify({
				// options
				title: '<strong>Success</strong>',
				message: "<br>Details updated successfully",
				icon: 'glyphicon glyphicon-ok'
			}, {
				// settings
				element: 'body',
				type: "success",
				allow_dismiss: true,
				showProgressbar: false,
				placement: {
					from: "top",
					align: "right"
				},
				offset: 20,
				spacing: 10,
				z_index: 1031,
				delay: 3300,
				timer: 1000,
				animate: {
					enter: 'animated fadeInDown',
					exit: 'animated fadeOutRight'
				}
			});
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

$('.document-panel .assigned_affiliate').on('change', function () {
	var affiliateId = $(this).val();
	var value = ($(this).is(":checked")) ? 1 : 0;
	var dataObj = { affiliateId: affiliateId, isAssigned: value };
	if (value == 0) {
		$('#' + affiliateId).val(0);
	}
	assignedAffiliateUpdated(dataObj);
});

$('.document-panel .show_all_package_bookings_package').on('change', function () {
	var affiliateId = $(this).attr('id');
	var value = $(this).val();
	if (!$('#affiliate_' + affiliateId).is(":checked")) {
		swal('Please assign the affiliate first.', '', 'error');
		$(this).val(0);
		return false;
	}
	var dataObj = { affiliateId: affiliateId, showAllPackageBookings: value };
	assignedAffiliateUpdated(dataObj);
});

$('.secondary_package').on('change', function () {
	var iframeUrl = $('#iframe_combo_url').text();
	var array = [];
	$(".secondary_package:checked").each(function () {
		array.push($(this).val());
	});
	var cids = array.join();
	if (array.length > 0) {
		cids = isCurrentTourPackage + "," + cids;
	} else {
		cids = isCurrentTourPackage;
	}
	var url = new URL(iframeUrl);
	url.searchParams.set("cids", cids);
	var newUrl = url.href;
	$('#iframe_combo_url').text(newUrl.replace(/%2C/g, ","));
	$('#iframe_combo').attr('src', newUrl.replace(/%2C/g, ","));
});
$('#deleteWaiverPdf').on('click', function () {

	swal({
		title: 'Are you sure to delete the waiver PDF?',
		text: '',
		icon: "warning",
		buttons: {
			no: {
				text: 'No, cancel!',
				value: false,
			},
			yes: {
				text: 'Yes, delete!',
				className: 'btn-danger',
			}
		}
	}).then(function (result) {
		if (!result)
			return;

		showLoading();

		$.ajax({
			type: 'GET',
			url: deleteWaiverPdfUrl,
		})
			.done(function () {
				swal('Waiver PDF deleted', '', 'success');
				$('#divWaiverPdf').remove();
			})
			.fail(showSubmissionError)
			.always(hideLoading);
	});

	return;
});