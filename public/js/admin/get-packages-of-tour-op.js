function getPackages(selTO) {
	var toId = selTO.options[selTO.selectedIndex].value;

	if(toId == '') {
		$('#tour_package').html('<option value="">Select a Tour Operator</option>');
		return;
	}
	
	$.getJSON(getPackagesUrl + toId)
		.done(function(packages) {
			var arPackages = [ '<option value="">Select Tour Package</option>' ];

			for(var i = 0; i < packages.length; i++)
				arPackages.push('<option value="' + packages[i].id + '">' + packages[i].name + '</option>');
		
			$('#tour_package').html(arPackages.join(''));
		})
		.fail(function() {
			swal('Error occurred', 'Unable to get fetch tour packages of the selected tour operator from server.', 'error');
		});
}
