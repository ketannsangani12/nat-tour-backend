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
                    data: 'name',
                    name: 'name',
                    className: 'td-custom-css'
                },
                {
                    data: 'alias',
                    name: 'alias',
                    className: 'td-custom-css'
                },
                {
                    data: 'class',
                    name: 'class',
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
                    className: 'td-custom-css whitespace-nowrap text-center',
                    orderable: false,
                    searchable: false
                },
            ]
        });
    });
}

if (document.currentScript.dataset.tags) {
    // These bodies are table-based HTML emails, so the editor must not normalise
    // the markup away. Jodit keeps it intact; the explicit sync on submit puts the
    // edited HTML back on the textarea, which is what the form actually posts.
    var editor = Jodit.make('#editor', {
        height: Math.max(520, window.innerHeight - 300),
        toolbarAdaptive: false,
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        defaultActionOnPaste: 'insert_as_html'
    });
    $('#editor').closest('form').on('submit', function () {
        $('#editor').val(editor.value);
    });
    $('#available-tags').text('Please select a class to get the available tags.')
    var availableTags = JSON.parse(document.currentScript.dataset.tags)
    if (document.currentScript.dataset.class && availableTags[JSON.parse(document.currentScript.dataset.class)]) {
        $('#available-tags').text(availableTags[JSON.parse(document.currentScript.dataset.class)].join(', '))
    }

    $(function () {
        $('#class').on('change', function () {
            var classNamespace = $(this).val();
            if (classNamespace && availableTags[classNamespace]) {
                $('#available-tags').text(availableTags[classNamespace].join(', '))
            } else {
                $('#available-tags').text('The tags aren\'t available for this class.')
            }
        })
    });
}

// if (document.currentScript.dataset.tags || document.currentScript.dataset.bodyText) {
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

//     if (document.currentScript.dataset.body) {
//         $('#hiddenArea').val(JSON.parse(document.currentScript.dataset.body))
//         const delta = quill.clipboard.convert(JSON.parse(document.currentScript.dataset.body))
//         quill.setContents(delta, 'silent')
//     } else {
//         var defaultTemplates = JSON.parse(document.currentScript.dataset.template)

//         $('#alias').on('change', function () {
//             var alias = $(this).val();
//             if (alias && defaultTemplates[alias]) {
//                 $('#subject').val(defaultTemplates[alias]['subject'])
//                 const delta = quill.clipboard.convert(defaultTemplates[alias]['body'])
//                 quill.setContents(delta, 'silent')
//             }
//         })
//     }

//     $('#available-tags').text('Please select a class to get the available tags.')

//     var availableTags = JSON.parse(document.currentScript.dataset.tags)
//     if (document.currentScript.dataset.class && availableTags[JSON.parse(document.currentScript.dataset.class)]) {
//         $('#available-tags').text(availableTags[JSON.parse(document.currentScript.dataset.class)].join(', '))
//     }

//     $(function () {
//         $('#class').on('change', function () {
//             var classNamespace = $(this).val();
//             if (classNamespace && availableTags[classNamespace]) {
//                 $('#available-tags').text(availableTags[classNamespace].join(', '))
//             } else {
//                 $('#available-tags').text('The tags aren\'t available for this class.')
//             }
//         })
//     });

//     quill.on('text-change', function (delta, oldDelta, source) {
//         $('#hiddenArea').val(quill.container.firstChild.innerHTML)
//     });
// }
