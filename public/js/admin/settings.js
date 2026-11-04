const triggerTabList = document.querySelectorAll('#myTab button')
triggerTabList.forEach(triggerEl => {
  const tabTrigger = new bootstrap.Tab(triggerEl)

  triggerEl.addEventListener('click', event => {
    event.preventDefault()
    tabTrigger.show()
  })
})

$(function () {
  // Initialize DataTable
  let table = $('#table-surcharge').DataTable();

  // Filter button click event
  $('#filter-button').on('click', function (e) {
    e.preventDefault();

    // Get filter values
    let customDateName = $('#custom-date-name-input').val();
    let packageId = $('#tour-package-id-input').find(':selected').data('package-name');
    let startDate = $('#date-range-start-input').val();
    let endDate = $('#date-range-end-input').val();
    let applyOnSlots = $('#apply-on-slots-input').val();

    // Apply filters on each column
    table.column(0).search(customDateName);
    table.column(1).search(packageId);
    table.column(2).search(startDate);
    table.column(3).search(endDate);
    table.column(4).search(applyOnSlots === "1" ? "Yes" : applyOnSlots === "0" ? "No" : "");

    // Redraw the table with new filters
    table.draw();
  });

  // Clear button click event
  $('#clear-button').on('click', function (e) {
    e.preventDefault();

    // Clear form fields
    $('#custom-date-name-input').val('');
    $('#tour-package-id-input').val('');
    $('#date-range-start-input').val('');
    $('#date-range-end-input').val('');
    $('#apply-on-slots-input').val('');

    // Clear DataTable filters
    table.columns().search('');

    // Redraw the table
    table.draw();
  });

  // Get URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  const tabParam = urlParams.get('tab');

  // Check if 'tab' parameter is 'standard-rate-dates' and activate the corresponding tab
  if (tabParam === 'standard-rate-dates') {
    document.getElementById('v-pills-settings-tab').classList.remove('active');
    document.getElementById('v-pills-settings').classList.remove('show', 'active');

    document.getElementById('v-pills-surcharge-date-tab').classList.add('active');
    document.getElementById('v-pills-surcharge-date').classList.add('show', 'active');
  }
});

$('.document-panel .setting').on('change', function () {
  var inputName = $(this).attr('name');
  var settingId = $(this).attr('id');
  var settingKey = $(this).data('setting-key'); // optional: store 'PAYMENT_METHODS' in data attribute
  var value = ($(this).is(":checked")) ? 1 : 0;
  var dataObj = {};

  var value = $(this).is(':checked') ? 1 : 0;
  dataObj[inputName] = value;

  settingUpdated(dataObj);
});

$('.document-panel .setting-categorized').on('change', function () {
  var inputName = $(this).attr('name');
  $('.document-panel .setting-categorized').not(this).filter(`[name=${inputName}]`).prop('checked', false);
  var value = $('.document-panel .setting-categorized:checked').filter(`[name=${inputName}]`).val();

  // START - This line is to store the standard date rates global setting
  if (value != 1 && ($(this).data('setting-name') == 'CDR_PACKAGES' || $(this).data('setting-name') == 'CDR_AFFILIATES')) {
    value = 0;
  }
  // END

  if (value != '' || value == 0) {
    var dataObj = {};
    dataObj[inputName] = value;
    settingUpdated(dataObj);
  }
});

function settingUpdated(dataObj) {
  showLoading();
  $.post(updateSettingUrl, dataObj)
    .done(function () {
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

$('#editSurchargeModal').on('show.bs.modal', function (event) {
  var button = $(event.relatedTarget); // Button that triggered the modal
  var id = button.data('id');
  var name = button.data('name');
  var start = button.data('start');
  var end = button.data('end');
  var apply = button.data('apply');

  var modal = $(this);
  modal.find('#surchargeId').val(id);
  modal.find('#customDateName').val(name);
  modal.find('#dateRangeStart').val(start);
  modal.find('#dateRangeEnd').val(end);
  modal.find('#applyOnSlots').val(apply ? '1' : '0');
});
