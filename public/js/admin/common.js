$(function() {
	$('#tour_operator').on('change', function() {
		if($(this).val())
			document.cookie = 'tourOpId=' + $(this).val() + '; path=/; max-age=' + (60 * 60 * 24 * 30);
	})
});
