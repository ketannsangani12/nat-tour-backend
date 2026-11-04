function fillExistingData() {
	$txtNames = $('.txt-name');
	$txtAges = $('.txt-age');

	for(var a = 0; a < liabWaivSign.length; a++) {
		for(var b = 0; b < $txtNames.length; b++) {
			if($txtAges[b].value == '' && $txtNames[b].value=='' ) { //&& liabWaivSign[a][1] >= $txtAges[b].min && liabWaivSign[a][1] <= $txtAges[b].max
				$txtNames[b].value = liabWaivSign[a][0];
				$txtAges[b].value = liabWaivSign[a][1];
					break;
			}
		}
	}
	for(var c = 0; c < $txtAges.length; c++) {
        if($txtAges[c].value==-1){
			$txtAges[c].value = '';
		}
	}
}

function liabWaivSubmitted() {
	swal('Thank you for signing the liability waiver.', 'You will be redirected to the booking information page.', 'success')
		.then(function() {
			showLoading();
			location.href = bookingInfoPageUrl;
		});
}
