let count = 0;

$(document).ready(function () {
    SetValues();
    $(window).scroll(function () {
        SetValues();
        if ($(window).scrollTop() == ($(document).height() - window.innerHeight)) {
            setTimeout("appendContent()", 1000);
        }
    });
});

let appendContent = function () {
    count++;
    $('#content').append(fetchData());
};

let SetValues = function () {
    $('table tr:first-child td:nth-child(4)').html($(document).height());
};

function fetchData() {
    var start = Number($('#start').val());
    var allcount = Number($('#totalrecords').val());
    var rowperpage = Number($('#rowperpage').val());
    var url = $('#url').val();
    start = start + rowperpage;

    if (start <= allcount) {
        $('#start').val(start);
        $.ajax({
            url: url,
            data: { start: start },
            dataType: 'json',
            success: function (response) {
                $(".post:last").after(response.html).show().fadeIn("slow");
            }
        });
    }
}

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

if (document.currentScript.dataset.url) {
    const url = document.currentScript.dataset.url

    var dataTable = null

    $(function () {
        dataTable = $('#datatable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                method: 'POST',
                url: url,
            },
            order: [
                [0, 'asc'],
                [1, 'asc'],
                [2, 'asc']
            ],
            columns: [
                {
                    data: 'title',
                    name: 'title'
                },
                {
                    data: 'operation_by_user_type',
                    name: 'operation_by_user_type'
                },
                {
                    data: 'action',
                    className: 'text-center',
                    orderable: false,
                    searchable: false
                },
            ]
        });
    });
}

function checkHeaderOneImgAndShowPreview(elFile) {
    if (!chkFileExt(elFile)) {
        $('#img-header-one').attr('src', '');
        return;
    }

    if (elFile.value)
        previewMedia(elFile.files[0], $('#img-header-one')[0]);
    else
        $('#img-header-one').attr('src', '');
}

// Note: release/index.blade.php (the only view that loads this script with an
// `#editor` on the page) never passes `data-tags`, doesn't load any editor
// assets, and has no `#editor` element - a RichTextEditor init block used to
// sit here but was dead code, never reached. Removed rather than migrated to
// Jodit. See public/js/admin/release-add.js for the live editor on the
// release create/edit forms.

function checkHeaderOneImgAndShowPreview(elFile) {
    if (!chkFileExt(elFile)) {
        $('#img-header-one').attr('src', '');
        return;
    }

    if (elFile.value)
        previewMedia(elFile.files[0], $('#img-header-one')[0]);
    else
        $('#img-header-one').attr('src', '');
}

$(document).on('click', '.btn-send-emails', function(e) {
    e.preventDefault();

    var url = $(this).data('url');

    swal({
        title: 'Are you sure?',
        text: "Notify all tenant users by email?",
        icon: 'warning',
        buttons: {
            no: {
                text: 'No',
                value: false,
                className: 'btn-danger',
            },
            yes: {
                text: 'Yes, send them!',
                value: true,
            }
        }
    }).then((result) => {
        if (result) {
            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    from: 'all',
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    swal('Success!', response.message, 'success');
                },
                error: function(xhr) {
                    swal('Error!', 'Unable to send notifications. Please try again.', 'error');
                }
            });
        }
    });
});
