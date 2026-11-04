@extends('layouts.default-2')

@section('main-content')
    @php
        if(Route::currentRouteName() == 'admin.importview')
            $action = 'Add';
    @endphp
<div class="page-content">
    <div class="content-title">
        <h2><i class="fas fa-user-secret title-icon"></i> Import Data</h2>
    </div>
    <div class="sub-nav">
        <a href="javascript:void(0)" {!! $action == 'Add' ? 'class="active"' : '' !!}><i class="fas fa-plus sub-nav-icon"></i> Add New</a>
    </div>
    <div class="main-content">
        <div class="successfully-title" id="successcsv"></div>
        <form class="form" id="import-contacts" enctype="multipart/form-data" method="POST" action="{{ $formSubmitRoute }}" data-redirect-on-success="{{ route('admin.importview') }}" data-on-success-msg= @if($action == 'Add') "data added" @endif >
            @if($action == 'Add')
                {{csrf_field()}}
            @endif
            <div class="row">
                <div class="col-4 col-md-4 col-lg-4">
                    <label for="txt-date" class="form-label">Date <req>*</req></label>
                    <input name="txt-date" id="txt-date" class="form-control" value="{{ $date }}" placeholder="Enter tour date" readonly required>
                    <input type="hidden" name="date" id="hid-date" value="{{ $hiddenDate }}">
                </div>
                <div class="col-8 col-md-8  col-lg-8 mb-3">
                    <label for="name" class="form-label">Import <req>*</req></label>
                    <input type="file" class="form-control" id="contacts_import" name="contacts_import" accept="text/csv" required /> 
                    <span id="errormsgcsv" style="color:red"></span>
                    <span style="color:red" id="csverror"></span>
                </div>
            </div>
            <div class="mt-4 text-end">
                <button type="button" class="btn btn-primary" id="importeCsvbtn"><span>Import</span></button>
            </div> 
        </form>
    </div>
</div>
@endsection

@section('css-libraries')
    @if(App::environment('local'))
<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
    @else
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
    @endif
@endsection

@section('js-libraries')
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
@endsection

@section('js-custom')
<script type="text/javascript">
$(document).ready(function () {
    $('#txt-date').datepicker({
        altField: '#hid-date',
        altFormat: 'yy-mm-dd',
        dateFormat: 'mm/dd/yy',
        minDate: 0,
    });
    // $('#importeCsvbtn').on('click', function () {
    //     var formData = new FormData(document.getElementById("import-contacts"));
    //     let contacts_import_ = $('#contacts_import').val();
    //     let txt_date = $('#txt-date').val();
    //     if(txt_date == "" || txt_date == null){
    //         $('#dateerror').text('Date field is required.');
    //         return false;
    //     }else{
    //         $('#dateerror').text('');
    //     }
    //     if(contacts_import_ == "" || contacts_import_ == null){
    //         $('#csverror').text('Import field is required.');
    //         return false;
    //     }else{
    //         $('#csverror').text('');
    //     }
    //     $.ajax({
    //         url: "import-sheet-data",
    //         type: 'POST',
    //         mimeType: "multipart/form-data",
    //         dataType: 'json',
    //         cache: false,
    //         data: formData,
    //         contentType: false,
    //         processData: false,
    //         success: function (response) {
    //             if (response.success) {
    //                 $('input[name="contacts_import"]').val('');
    //                 $("#successcsv").show();
    //                 $('#successcsv').html("<div class='alert alert-success alert-dismissible fade show' role='alert'>"+ response.message +"<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>");
    //                 $("#successcsv").delay(4000).fadeOut(300);
    //                 location.reload();
    //             }
    //         },
    //         error: function (response) {
    //             $('#csverror').text(response.responseJSON.errors.contacts_import);
    //         }
    //     });
    // });

    $('#importeCsvbtn').on('click', function () {
        var formData = new FormData(document.getElementById("import-contacts"));
        let txt_date = $('#txt-date').val();
        let contacts_import_ = $('#contacts_import').val();
        if(txt_date == "" || txt_date == null){
            $('#dateerror').text('Date field is required.');
            return false;
        }else{
            $('#dateerror').text('');
        }
        if(contacts_import_ == "" || contacts_import_ == null){
            $('#csverror').text('Import field is required.');
            return false;
        }else{
            $('#csverror').text('');
        }

        var Url = $(this).parents('form').attr('action');
        var Method = $(this).parents('form').attr('method');
        var Enctype = $(this).parents('form').attr('enctype');
        
        $.ajax({
            url: Url,
            type: Method,
            mimeType: Enctype,
            dataType: 'json',
            cache: false,
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response.success) {
                    $('input[name="contacts_import"]').val('');
                    $("#successcsv").show();
                    $('#successcsv').html("<div class='alert alert-success alert-dismissible fade show' role='alert'>"+ response.message +"<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>");
                    $("#successcsv").delay(4000).fadeOut(300);
                    location.reload();
                }
            },
            error: function (response) {
                $('#csverror').text(response.responseJSON.errors.contacts_import);
            }
        });
    });
});
</script> 


@endsection

