/* delete record*/
$(function () {
	$(document).on("click", ".btn-delete", function() {

		var $this = $(this);
		var $elToRemove = null;
		var delurl = $this.data('url');

		var confirmTitle = "Delete?";
		var confirmText = "Are you sure to delete the record?";
		var delSuccessMsg = "The record has been deleted.";
		var reloadPage = false;
		var callOnSuccess = '';

		if($this.data('confirm-title'))
			confirmTitle = $this.data('confirm-title');

		if($this.data('confirm-text'))
			confirmText = $this.data('confirm-text');

		if($this.data('success-msg'))
			delSuccessMsg = $this.data('success-msg');

		if($this.data('tag-to-remove'))
			$elToRemove = $this.parents($this.data('tag-to-remove'));
		else
			$elToRemove = $this.parents('tr');

		if ($this.data('reload-on-success'))
			reloadPage = true;

		if($this.data('call-on-success'))
			callOnSuccess = $this.data('call-on-success');

		if($this.data('operatorname')){
			var confirmText = "Are you sure to delete "+$this.data('operatorname')+"?";
		}
		swal({
			title: confirmTitle,
			text: confirmText,
			icon: "warning",
			buttons: {
				no: {
					text: 'No',
					value: false,
				},
				yes: {
					text: 'Yes',
					className: 'btn-danger',
				}
			}
		}).then(function(result) {
			if(result) {
				showLoading();

				$.ajax({
					type: "post",
					url: delurl,
					data: {
						_method: 'DELETE',
					},
					success: function(data) {
						swal(delSuccessMsg, '', 'success');

						// if element to be removed is descendent of a DataTable
						if($elToRemove.closest('.dataTable').length && typeof dtbl != 'undefined')
							dtbl.row($elToRemove).remove().draw();
						else
							$elToRemove.remove();

						if(callOnSuccess)
							window[callOnSuccess]($this);

						if(reloadPage)
							window.location.reload();
						else
							hideLoading();
					},
					error: function(data) {
						hideLoading();
						showSubmissionError(data);
					}
				});
			}
		});
	});
});
