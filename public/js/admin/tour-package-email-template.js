$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

const dataset = document.currentScript.dataset;

if (dataset.url) {
    const url = dataset.url

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
                    data: 'name',
                    name: 'name',
                    className: 'td-custom-css'
                },
                {
                    data: 'subject',
                    name: 'subject',
                    className: 'td-custom-css'
                },
                {
                    data: 'created_by',
                    name: 'created_by',
                    className: 'td-custom-css'
                },
                {
                    data: 'created_from',
                    name: 'created_from',
                    className: 'td-custom-css'
                },
                {
                    data: 'action',
                    className: 'td-custom-css text-center',
                    orderable: false,
                    searchable: false,
                },
            ]
        });
    });
}

if (dataset.tags) {
    // These bodies are table-based HTML emails, so the editor must not normalise
    // the markup away. Jodit keeps it intact; the explicit sync on submit puts the
    // edited HTML back on the textarea, which is what the form actually posts.
    var editor = Jodit.make('#editor', {
        // Fill the viewport instead of a fixed box: these bodies run to 45KB and
        // scrolling a short window is painful. The 600px-wide email inside is the
        // template's own max-width, not something the editor imposes.
        height: Math.max(520, window.innerHeight - 300),
        toolbarAdaptive: false,
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        defaultActionOnPaste: 'insert_as_html'
    });
    $('#editor').closest('form').on('submit', function () {
        $('#editor').val(editor.value);
    });
    var availableTags = JSON.parse(dataset.tags)
    var defaultTemplates = JSON.parse(dataset.templates)
    $('#available-tags').text('Please select a master email template to get the available tags.')
    if (dataset.id) {
        $('#available-tags').text(availableTags[defaultTemplates[dataset.id]['class']].join(', '))
    }
    $('#email_template_id').on('change', function () {
        var emailTemplateId = $(this).val();
        if (emailTemplateId && defaultTemplates[emailTemplateId]) {
            $('#subject').val(defaultTemplates[emailTemplateId]['subject'])
            $('#available-tags').text(availableTags[defaultTemplates[emailTemplateId]['class']].join(', '))
        } else {
            $('#subject').val('');
            $('#available-tags').text('Please select a master email template to get the available tags.')
        }
    })
}

// if (dataset.templates || dataset.body) {
//     Quill.register("modules/htmlEditButton", htmlEditButton);
//     var quill = new Quill('#editor', {
//         modules: {
//             toolbar: [
//                 [{ header: [1, 2, false] }],
//                 ['bold', 'italic', 'underline'],
//                 ['image', 'code-block']
//             ],
//             htmlEditButton: {
//                 syntax: true,
//             }
//         },
//         placeholder: 'Compose an epic...',
//         theme: 'snow'  // or 'bubble'
//     });

//     var availableTags = JSON.parse(dataset.tags)
//     var defaultTemplates = JSON.parse(dataset.templates)

//     if (dataset.body) {
//         $('#hiddenArea').val(JSON.parse(dataset.body))
//         var delta = quill.clipboard.convert(JSON.parse(dataset.body))
//         quill.setContents(delta, 'silent')
//         $('#available-tags').text(availableTags[defaultTemplates[dataset.id]['class']].join(', '))
//     } else {
//         $('#available-tags').text('Please select a master email template to get the available tags.')
//     }

//     $('#email_template_id').on('change', function () {
//         var emailTemplateId = $(this).val();
//         if (emailTemplateId && defaultTemplates[emailTemplateId]) {
//             $('#subject').val(defaultTemplates[emailTemplateId]['subject'])
//             if (dataset.id == emailTemplateId) {
//                 var delta = quill.clipboard.convert(JSON.parse(dataset.body))
//             } else {
//                 var delta = quill.clipboard.convert(defaultTemplates[emailTemplateId]['body'])
//             }
//             quill.setContents(delta, 'silent')
//             $('#hiddenArea').val(quill.container.firstChild.innerHTML)
//             $('#available-tags').text(availableTags[defaultTemplates[emailTemplateId]['class']].join(', '))
//         } else {
//             $('#subject').val('');
//             quill.setContents('', 'silent')
//             $('#hiddenArea').val('')
//             $('#available-tags').text('Please select a master email template to get the available tags.')
//         }
//     })

//     quill.on('text-change', function (delta, oldDelta, source) {
//         $('#hiddenArea').val(quill.container.firstChild.innerHTML)
//     });
// }