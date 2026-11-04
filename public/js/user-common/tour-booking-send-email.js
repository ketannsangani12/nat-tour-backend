CKEDITOR.stylesSet.add('default', [
    { name: 'Font Size 140%', element: 'span', styles: { 'font-size': '140%' } },
    { name: 'Font Size 120%', element: 'span', styles: { 'font-size': '120%' } },
    { name: 'Font Size 100%', element: 'span', styles: { 'font-size': '100%' } },
    { name: 'Font Size 80%', element: 'span', styles: { 'font-size': '80%' } },
    { name: 'Font Size 60%', element: 'span', styles: { 'font-size': '60%' } },
    { name: 'Serif Font', element: 'span', styles: { 'font-family': 'serif' } },
    { name: 'Sans-Serif Font', element: 'span', styles: { 'font-family': 'sans-serif' } },
    { name: 'Monospace Font', element: 'span', styles: { 'font-family': 'monospace' } }
]);

CKEDITOR.on('dialogDefinition', function(event) {
	if(event.data.name == 'table') {
		// setting default formatting values for table dialog
		var infoTab = event.data.definition.getContents('info');
		infoTab.get('txtWidth').default = '100%';
		infoTab.get('txtCellSpace').default = '0';
		infoTab.get('txtCellPad').default = 5;
	}
});

CKEDITOR.replace($('#email_content')[0], {
	customConfig: '',
	colorButton_colors: 'Black/000,Dark Gray/404040,Gray/808080,Light Gray/c0c0c0,White/fff,Red/f00,Blue/00f,Green/008000,Yellow/ff0,Purple/f0f,Cyan/0ff,Sky-Blue/80c0ff,Orange/ffaa00,Brown/a52a2a,Lime/00ff00',
	toolbarGroups: [

		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'styles', groups: [ 'styles' ] },
		{ name: 'paragraph', groups: [ 'list', 'indent', 'align' ] },
		{ name: 'links', groups: [ 'links' ] },
		{ name: 'insert', groups: [ 'insert' ] },
		{ name: 'colors', groups: [ 'colors' ] },
		{ name: 'clipboard', groups: [ 'clipboard', 'undo' ] },

	],
	removeButtons: 'Save,Cut,Copy,PasteText,Paste,PasteFromWord,Subscript,Superscript,Anchor,Image'
});

function validateAndSubmitForm(form) {
	var emailContent = CKEDITOR.instances.email_content.getData();

	if(emailContent == '') {
		swal('', 'Please enter email content.', 'warning');
		return false;
	}

	$('#email_content').val(emailContent);
	return submitForm(form);
}
