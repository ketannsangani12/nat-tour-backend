var UploadURL = $("#UploadURL").val();

// Release notes are plain-ish HTML (not the table-based emails the other
// editors deal with), but this one also needs in-editor image uploads, which
// RichTextEditor wired up via `images_upload_url`. Jodit's uploader expects
// its own request/response contract, so `process` below adapts the existing
// ReleaseController::storeImage response ({fileName, uploaded, url}) into the
// shape Jodit's default success handler expects ({files, isImages, baseurl}).
var editor = Jodit.make('#editor', {
    height: Math.max(400, window.innerHeight - 360),
    toolbarAdaptive: false,
    askBeforePasteHTML: false,
    askBeforePasteFromWord: false,
    defaultActionOnPaste: 'insert_as_html',
    uploader: {
        url: UploadURL,
        filesVariableName: function () {
            return 'upload';
        },
        isSuccess: function (resp) {
            return !!resp.uploaded;
        },
        getMessage: function (resp) {
            return resp.message || '';
        },
        process: function (resp) {
            return {
                files: resp.url ? [resp.url] : [],
                isImages: [true],
                baseurl: '',
                error: resp.error || {},
                msg: resp.message || ''
            };
        }
    }
});

$('#editor').closest('form').on('submit', function () {
    $('#editor').val(editor.value);
});

function checkHeaderOneImgAndShowPreview(elFile) {
    if (!chkFileExt(elFile)) {
        $('#img-header-one').attr('src', '');
        return;
    }

    if (elFile.value) {
        previewMedia(elFile.files[0], $('#img-header-one')[0]);
        document.getElementById("img-header-one").style.marginBottom = "14px";
    }
    else {
        $('#img-header-one').attr('src', '');
        document.getElementById("img-header-one").style.marginBottom = "0px";
    }
}
