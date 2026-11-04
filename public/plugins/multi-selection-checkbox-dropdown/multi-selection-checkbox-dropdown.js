$(function() {
	var clickListner = function(e) {
		var container = $(".multi-selection-container");

		// if the target of the click isn't the container nor a descendant of the container
		if(!container.is(e.target) && container.has(e.target).length === 0) {
			$(document).unbind('mouseup', clickListner);
			$('.multi-sel-checkboxes').hide();
			return;
		}
	}

	$('.multi-selection-container').on('click keyup', function(e) {
	//$('.multi-selection-container').on('click keyup', function(e) {
		$eTarget = $(e.target);
		if(! ($eTarget.hasClass('multi-selection-container') || $eTarget.hasClass('multi-sel-placeholder') || $eTarget.hasClass('default-placeholder') || $eTarget.hasClass('multi-sel-opener') ) )
			return; // do not handle events on the elements other than the specified in the 'if'

		if($eTarget.hasClass('multi-sel-placeholder') || $eTarget.hasClass('default-placeholder'))
			$eTarget = $eTarget.parents('.multi-selection-container');

		if(! (e.type == 'click' || e.code == 'Enter' || e.code == 'ArrowDown'))
			return;

		var chkDiv = $(this).find('.multi-sel-checkboxes');

		if(chkDiv.css('display') == 'none') {
			if(typeof $eTarget.data('id-prefix') == 'undefined') {
				$eTarget.data('id-prefix', (new Date).getTime());
				var $placeholder = $eTarget.find('.multi-sel-placeholder');
				$placeholder.data('placeholder', $placeholder.html());
			}

			$(document).on('mouseup', clickListner);
			chkDiv.show();
		} else {
			if(e.code == 'ArrowDown')
				return;

			chkDiv.hide();
		}
	});

	$('.multi-selection-container').on('blur', function(e) {
		//$(this).find('.multi-sel-checkboxes').hide();
	});

	$('.multi-selection-container input[type=checkbox], .multi-selection-container label').on('click', function(e) {


		e.stopPropagation();
		var checkbox = e.target;

		if(e.target.tagName == 'label')
			checkbox = $(e.target).find('input[type=checkbox]')[0];

		var $multiSelContainer = $(e.target).parents('.multi-selection-container');
		// re-display the hidden checkboxes due to blur event of .multi-selection-container
		$multiSelContainer.find('.multi-sel-checkboxes').show();
		var idPrefix = $multiSelContainer.data('id-prefix');
		var selItemId = 'multi-sel-' + idPrefix + '-_' + checkbox.id;
		var $placeholder = $multiSelContainer.find('.multi-sel-placeholder');

		if(checkbox.checked) {

			var selItem = document.createElement('span');
			selItem.id = selItemId;
			selItem.className = 'multi-sel-item-container';

			selItem.innerHTML = '<span class="multi-sel-item">' +
				$(checkbox).parents('label').text() +
				'</span> <span class="multi-sel-remove">X</span>';

			if($placeholder.html() == $placeholder.data('placeholder'))
				$placeholder.html('');

			$placeholder.append(selItem);
		} else {


			$placeholder.find('#' + selItemId).remove();

			if($placeholder.html() == '')
				$placeholder.html($placeholder.data('placeholder'));
		}
	});

	$(document).on('click', '.multi-sel-remove', function(e) {
		$selItem = $(e.target).parent();
		var selItemId = $selItem.attr('id');
		chkId = selItemId.substr(selItemId.lastIndexOf('-_') + 2);
		$('#' + chkId)[0].checked = false;
		$placeholder = $selItem.parents('.multi-sel-placeholder')
		$selItem.remove();

		if($placeholder.html() == '')
			$placeholder.html($placeholder.data('placeholder'));
	});
});
