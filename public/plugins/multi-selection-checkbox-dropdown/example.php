<?php
$recs = [
	[
		'id' => 1,
		'name' => 'Item 1'
	],
	[
		'id' => 2,
		'name' => 'Item 2'
	],
	[
		'id' => 3,
		'name' => 'Item 3'
	],
	[
		'id' => 4,
		'name' => 'Item 4'
	]
];
?>
<!doctype html>
<html>
<head>
	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
	<link href="multi-selection-checkbox-dropdown.css" rel="stylesheet">
</head>
<body>
<?php
if(isset($_POST['item'])) {
	echo 'Posted data: <pre>';
	print_r($_POST['item']);
	echo '</pre>';
}
?>
	<form method="post">
		<div class="form-control multi-selection-container" tabindex="0">
			<div class="multi-sel-placeholder"><span class="default-placeholder">Select Items(s)</span></div>

			<div class="multi-sel-checkboxes">
<?php foreach($recs as $rec) { ?>
				<label>
					<input type="checkbox" name="item[]" id="item-<?= $rec['id'] ?>" value="<?= $rec['id'] ?>">
					<?= $rec['name'] ?>
				</label>
<?php } ?>
			</div>

			<i class="fas fa-angle-down fa-fw multi-sel-opener"></i>
		</div>

		<div class="mt-3"><button class="btn btn-primary">Submit</button></div>
	</form>

	<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
	<script src="multi-selection-checkbox-dropdown.js"></script>
</body>
</html>
