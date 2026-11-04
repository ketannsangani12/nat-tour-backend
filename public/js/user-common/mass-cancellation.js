/**
 * Mass Cancellation page.
 *
 * Loads the bookings of a date / package / time, lets the user pick which to
 * cancel and posts them with one refund option. The server cancels each
 * booking through the regular single booking cancel flow and returns a result
 * per booking, which is shown in the "Result" column.
 */
(function ($) {
  'use strict';

  var cfg = window.MASS_CANCEL_CONFIG;
  var bookings = [];
  var submitting = false;

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function money(value) {
    return '$' + Number(value || 0).toFixed(2);
  }

  function errorMessage(xhr, fallback) {
    var errors = xhr && xhr.responseJSON && xhr.responseJSON.errors;
    if (errors) {
      var first = Object.values(errors)[0];
      return Array.isArray(first) ? first[0] : first;
    }
    return fallback;
  }

  function showLoading(text) {
    $('#mcLoadingText').text(text);
    $('#mcLoading').removeClass('hidden').addClass('flex');
  }

  function hideLoading() {
    $('#mcLoading').removeClass('flex').addClass('hidden');
  }

  function filters() {
    return {
      date: $('#mcDate').val(),
      tour_package_id: $('#mcPackage').val() || ''
    };
  }

  function selectedTime() {
    return $('#mcTime').val() || '';
  }

  function slotLabel(slot) {
    return slot.label + ' (' + slot.bookings_count + ' bookings / ' + slot.pax + ' pax' +
      (slot.cancelled_count ? ', ' + slot.cancelled_count + ' cancelled' : '') + ')';
  }

  // Only one time slot can be cancelled at a time
  function setTimeOptions(placeholder, slots, selected) {
    var $select = $('#mcTime').empty();
    $('<option>', { value: '', text: placeholder, disabled: true, selected: !selected }).appendTo($select);
    (slots || []).forEach(function (slot) {
      $('<option>', { value: slot.time_from, text: slotLabel(slot), selected: slot.time_from === selected }).appendTo($select);
    });
    $select.prop('disabled', !(slots && slots.length));
  }

  // Refresh the booking counts shown in the time dropdown, keeping the selection
  function refreshTimeSlotCounts() {
    var params = filters();
    var selected = selectedTime();
    $.get(cfg.timeSlotsUrl, params).done(function (response) {
      setTimeOptions('Please select time', response.timeSlots || [], selected);
    });
  }

  function selectedIds() {
    return $('.mc-booking:checked').map(function () { return parseInt(this.value, 10); }).get();
  }

  function setTableMessage(message) {
    bookings = [];
    $('#mcBookings').html('<tr><td colspan="10" class="py-6 text-center text-[#94a3b8]">' + escapeHtml(message) + '</td></tr>');
    refreshSelection();
  }

  function loadTimeSlots() {
    var params = filters();
    $('#mcSummary').addClass('hidden').empty();

    if (!params.date || !params.tour_package_id) {
      setTimeOptions('Select a date and tour package first');
      setTableMessage('Select a date, tour package and time to load bookings');
      return;
    }

    setTimeOptions('Loading...');
    $.get(cfg.timeSlotsUrl, params)
      .done(function (response) {
        var slots = response.timeSlots || [];
        if (!slots.length) {
          setTimeOptions('No bookings on this date');
          setTableMessage('No active bookings found');
          return;
        }

        if (slots.length === 1) {
          setTimeOptions('Please select time', slots, slots[0].time_from);
          loadBookings();
        } else {
          setTimeOptions('Please select time', slots);
          setTableMessage('Select a time to load bookings');
        }
      })
      .fail(function (xhr) {
        setTimeOptions('Unable to load time slots');
        setTableMessage(errorMessage(xhr, 'Unable to load time slots'));
      });
  }

  function loadBookings() {
    var params = filters();
    var time = selectedTime();

    if (!time) {
      setTableMessage('Select a time to load bookings');
      return;
    }

    params.time = time;
    stopPolling();
    $('#mcSummary').addClass('hidden').empty();
    showLoading('Loading bookings...');

    $.get(cfg.bookingsUrl, params)
      .done(function (response) {
        bookings = response.bookings || [];
        renderBookings();

        // A mass cancellation is still running for this slot: show its progress
        if (response.activeRun) {
          trackRun(response.activeRun);
        }
      })
      .fail(function (xhr) {
        setTableMessage(errorMessage(xhr, 'Unable to load bookings'));
      })
      .always(hideLoading);
  }

  function renderBookings() {
    if (!bookings.length) {
      setTableMessage('No active bookings found');
      return;
    }

    var html = bookings.map(function (b) {
      var url = cfg.bookingUrl.replace('__ID__', b.id);
      var checkbox = b.blocked_reason
        ? '<input type="checkbox" class="w-4 h-4" disabled title="' + escapeHtml(b.blocked_reason) + '">'
        : '<input type="checkbox" class="mc-booking w-4 h-4" value="' + b.id + '">';
      var result = '';
      if (b.is_cancelled) {
        result = '<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-[#fee2e2] text-[#b91c1c]">Cancelled</span>';
      } else if (b.blocked_reason) {
        result = '<span class="text-xs text-[#b45309]">' + escapeHtml(b.blocked_reason) + '</span>';
      }

      return '<tr class="border-b border-[#f1f5f9]' + (b.is_cancelled ? ' bg-[#f8fafc] text-[#94a3b8]' : '') + '" data-id="' + b.id + '">' +
        '<td class="py-2 pr-2">' + checkbox + '</td>' +
        '<td class="py-2 pr-3"><a href="' + url + '" target="_blank" class="text-[#1e3a5c] font-semibold hover:underline">#' + b.id + '</a></td>' +
        '<td class="py-2 pr-3">' + escapeHtml(b.name) + '<div class="text-xs text-[#64748b]">' + escapeHtml(b.email) + '</div></td>' +
        '<td class="py-2 pr-3">' + escapeHtml(b.package_name) + '</td>' +
        '<td class="py-2 pr-3 whitespace-nowrap">' + escapeHtml(b.time) + '</td>' +
        '<td class="py-2 pr-3 text-right">' + b.pax + '</td>' +
        '<td class="py-2 pr-3 text-right">' + money(b.total) + '</td>' +
        '<td class="py-2 pr-3 text-right">' + money(b.paid) + '</td>' +
        '<td class="py-2 pr-3">' + escapeHtml(b.via) + (b.is_octo ? ' <span class="text-xs text-[#64748b]">(OCTO)</span>' : '') + '</td>' +
        '<td class="py-2 mc-result">' + result + '</td>' +
        '</tr>';
    }).join('');

    $('#mcBookings').html(html);
    $('#mcSelectAll').prop('checked', false);
    refreshSelection();
  }

  function refreshSelection() {
    var eligible = $('.mc-booking').length;
    var selected = selectedIds().length;
    $('#mcEligibleCount').text(eligible);
    $('#mcSelectedCount').text(selected);
    $('#mcSelectAll').prop('checked', eligible > 0 && selected === eligible);
    $('#mcSubmit').prop('disabled', submitting || selected === 0);
  }

  var CANCELLED_BADGE = '<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-[#fee2e2] text-[#b91c1c]">Cancelled</span>';
  var POLL_INTERVAL = 2000;
  var pollTimer = null;

  function resultHtml(r) {
    switch (r.status) {
      case 'cancelled':
        // Cancelled, but a post-cancel step (email / board update) failed
        if (r.message && r.message !== 'Cancelled') {
          return CANCELLED_BADGE + '<div class="text-xs text-[#b45309] mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>' + escapeHtml(r.message) + '</div>';
        }
        return CANCELLED_BADGE;
      case 'failed':
        return '<span class="text-xs font-semibold text-[#b91c1c]"><i class="fas fa-exclamation-circle mr-1"></i>' + escapeHtml(r.message) + '</span>';
      case 'processing':
        return '<span class="text-xs font-semibold text-[#1e3a5c]"><i class="fas fa-spinner fa-spin mr-1"></i>Processing...</span>';
      case 'queued':
        return '<span class="text-xs text-[#64748b]"><i class="far fa-clock mr-1"></i>Queued</span>';
      default:
        return '<span class="text-xs text-[#64748b]">' + escapeHtml(r.message) + '</span>';
    }
  }

  // Show the per booking result in the "Result" column
  function applyResults(results) {
    (results || []).forEach(function (r) {
      var $row = $('#mcBookings tr[data-id="' + r.id + '"]');
      if (r.status === 'cancelled') {
        $row.addClass('bg-[#f8fafc] text-[#94a3b8]');
        $row.find('.mc-booking').prop('checked', false).prop('disabled', true).removeClass('mc-booking');
      }
      $row.find('.mc-result').html(resultHtml(r));
    });
  }

  function showSummary(run) {
    var running = run.status === 'queued' || run.status === 'processing';
    var color = running ? '#1e3a5c' : (run.status === 'completed' ? '#15803d' : '#b91c1c');
    var html = '<div class="font-semibold" style="color:' + color + '">' + escapeHtml(run.message) + '</div>';

    if (running && run.total) {
      var percent = Math.round((run.cancelled / run.total) * 100);
      html += '<div class="mt-2 h-2 w-full rounded-full overflow-hidden" style="background:#e2e8f0">' +
        '<div class="h-2 rounded-full" style="width:' + percent + '%;background:#1e3a5c;transition:width .3s"></div></div>' +
        '<div class="text-xs text-[#64748b] mt-1">' + run.cancelled + ' of ' + run.total + ' cancelled — you can leave this page, the cancellation continues in the background.</div>';
    }

    $('#mcSummary').removeClass('hidden').html(html);
  }

  // Lock the filters and the selection while a run is in progress
  function setLocked(locked) {
    submitting = locked;
    $('#mcPackage, #mcDateDisplay, #mcTime, #mcSelectAll, .mc-booking').prop('disabled', locked);
    $('#mcSubmit').html(locked ? '<i class="fas fa-spinner fa-spin mr-2"></i>Cancelling...' : 'Cancel Selected Bookings');
    refreshSelection();
  }

  function stopPolling() {
    clearTimeout(pollTimer);
    pollTimer = null;
  }

  function pollRun(runId) {
    stopPolling();
    pollTimer = setTimeout(function () {
      $.get(cfg.statusUrl.replace('__ID__', runId))
        .done(trackRun)
        .fail(function () {
          // Network hiccup: keep trying, the queue keeps working regardless
          pollRun(runId);
        });
    }, POLL_INTERVAL);
  }

  // Follow a queued run until it is finished
  function trackRun(run) {
    applyResults(run.results);
    showSummary(run);

    if (run.status === 'queued' || run.status === 'processing') {
      setLocked(true);
      pollRun(run.id);
      return;
    }

    stopPolling();
    setLocked(false);

    if (run.cancelled > 0) {
      refreshTimeSlotCounts();
    }

    if (run.status === 'completed') {
      swal('Bookings cancelled', run.message, 'success');
    } else {
      showIssues('Mass cancellation stopped', run);
    }
  }

  // List every booking that has an issue
  function showIssues(title, response) {
    var list = (response.results || [])
      .filter(function (r) { return r.status === 'failed'; })
      .map(function (r) { return '#' + r.id + ' — ' + r.message; })
      .join('\n');

    swal({ title: title, text: response.message + '\n\n' + list, icon: 'error' });
  }

  function submit(event) {
    event.preventDefault();
    if (submitting) {
      return;
    }

    var ids = selectedIds();
    var refundType = $('input[name="refund_type"]:checked');
    var comment = $.trim($('#mcComment').val());

    if (!ids.length) {
      swal('No bookings selected', 'Please select at least one booking to cancel.', 'warning');
      return;
    }
    if (ids.length > cfg.maxBookings) {
      swal('Too many bookings', 'Please cancel at most ' + cfg.maxBookings + ' bookings at a time.', 'warning');
      return;
    }
    if (!refundType.length) {
      swal('Refund type required', 'Please select a refund type before cancelling the bookings.', 'warning');
      return;
    }
    if (!comment) {
      swal('Note required', 'Please specify the reason to cancel the bookings.', 'warning');
      $('#mcComment').focus();
      return;
    }

    var refundLabel = $.trim(refundType.parent().text());

    var plural = ids.length > 1;
    var sendMail = $('input[name="send_cancel_mail"]').is(':checked');

    swal({
      title: 'Cancel ' + ids.length + ' booking' + (plural ? 's' : '') + '?',
      text: 'Refund option: ' + refundLabel + ' (original payment method)\n\n' +
        (sendMail ? 'Guests will receive a cancellation email.\n' : 'No cancellation email will be sent to guests.\n') +
        'This action cannot be reversed when performed in bulk.',
      icon: 'warning',
      buttons: ['No, go back', 'Yes, cancel ' + (plural ? 'bookings' : 'booking')],
      dangerMode: true
    }).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      setLocked(true);
      $('#mcSummary').addClass('hidden').empty();

      $.ajax({
        url: cfg.cancelUrl,
        method: 'POST',
        data: {
          date: $('#mcDate').val(),
          tour_package_id: $('#mcPackage').val(),
          time: selectedTime(),
          booking_ids: ids,
          refund_type: refundType.val(),
          comment: comment,
          send_cancel_mail: sendMail ? 1 : 0
        }
      })
        .done(function (response) {
          if (response.status === 'validation_failed') {
            // Nothing was queued
            setLocked(false);
            applyResults(response.results);
            showSummary(response);
            showIssues('No bookings were cancelled', response);
            return;
          }
          trackRun(response);
        })
        .fail(function (xhr) {
          setLocked(false);
          swal('Error', errorMessage(xhr, 'Unable to cancel bookings.'), 'error');
        });
    });
  }

  $(function () {
    // Displayed as mm/dd/yyyy, sent to the server as yyyy-mm-dd
    $('#mcDateDisplay').datepicker({
      dateFormat: 'mm/dd/yy',
      altField: '#mcDate',
      altFormat: 'yy-mm-dd',
      changeMonth: true,
      changeYear: true,
      onSelect: loadTimeSlots
    });
    // Default to today (tour operator timezone)
    $('#mcDateDisplay').datepicker('setDate', $.datepicker.parseDate('yy-mm-dd', cfg.today));
    $('#mcPackage').on('change', loadTimeSlots);

    $('#mcTime').on('change', loadBookings);

    $('#mcSelectAll').on('change', function () {
      $('.mc-booking').prop('checked', this.checked);
      refreshSelection();
    });
    $('#mcBookings').on('change', '.mc-booking', refreshSelection);

    $('#mcForm').on('submit', submit);
  });
})(jQuery);
