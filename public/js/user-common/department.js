var dtbl = null;
if (departmentIndexUrl) {
	$(function () {
		dtbl = $('#datatable').DataTable({
			processing: true,
			serverSide: true,
			responsive: true,
			ajax: {
				method: 'POST',
				url: departmentIndexUrl
			},
			order: [[0, 'asc'], [1, 'asc']],
			columns: [
				{
					data: 'name',
					name: 'name',
					className: 'td-custom-css'
				},
				{
					data: 'status',
					name: 'status',
					className: 'td-custom-css text-center'
				},
				{
					data: 'action',
					className: 'td-custom-css text-center',
					orderable: false,
					searchable: false
				},
			]
		});
	});	
}

if (isPackageAssigned == 1) {
	$("#packageButtonsContainer").removeClass('hidden');
}

// JavaScript to toggle the visibility of the package buttons
function togglePackageButtons() {
	const isChecked = document.getElementById('is_package_assigned').checked;
	const packageContainer = document.getElementById('packageButtonsContainer');
	if (isChecked) {
		packageContainer.classList.remove('hidden'); // Show package buttons
	} else {
		packageContainer.classList.add('hidden'); // Hide package buttons
		resetPackageSelection(); // Reset package selection
	}
}

function resetPackageSelection() {
	// Deselect all checkboxes and reset button styles
	document.querySelectorAll('.package-btn').forEach(button => {
		button.classList.remove('btn-primary');
		button.classList.add('btn-outline-primary');
	});
	document.querySelectorAll('.package-checkbox').forEach(checkbox => {
		checkbox.checked = false;
	});
}

// JavaScript to handle package selection
function togglePackageSelection(button) {
	const checkbox = button.nextElementSibling;
	if (checkbox.checked) {
		// Deselect the package
		checkbox.checked = false;
		button.classList.remove('btn-primary');
		button.classList.add('btn-outline-primary');
	} else {
		// Select the package
		checkbox.checked = true;
		button.classList.remove('btn-outline-primary');
		button.classList.add('btn-primary');
	}
}
