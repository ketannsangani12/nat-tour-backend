$(function() {
	$('#tour_operator').on('change', function() {
		if($(this).val())
			document.cookie = 'tourOpId=' + $(this).val() + '; path=/; max-age=' + (60 * 60 * 24 * 30);
	})
});
$(document).ready(function () {
    
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
            }
        });
    });

    $('#customer_phone_number, #cardnumber, #expirydate, #cvv').keypress(function(event) {
        var keycode = event.which;
        if (!(event.shiftKey == false && (keycode == 43 || keycode == 46 || keycode == 8 || keycode == 37 || keycode == 39 || (keycode >= 48 && keycode <= 57)))) {
            event.preventDefault();
        }
    });

    //Input validation text only
    $('#customer_name, #nameoncard').bind('keyup blur', function () {
        var node = $(this);
        node.val(node.val().replace(/[^A-Za-z ']/g, ''));
    });


    $(".btn-make-payment").click(function() {
        var form = $("#payment-form");
        form.validate({
            errorElement: 'span',
            errorClass: 'help-block',
            success: function(label) {
                label.text("ok!").addClass("success");
            },
            rules: {
                customer_name: {
                    required: true,
                },
                customer_phone_number: {
                    required: true,
                    minlength: 9,
                    maxlength: 14,
                },
                customer_email: {
                    required: true,
                    email: true
                },
                cardnumber: {
                    required: true,
                    minlength: 19,
                    maxlength: 19,
                },
                nameoncard: {
                    required: true,
                },
                expiration: {
                    required: true,
                    minlength: 5,
                    maxlength: 5,
                },
                cvv: {
                    required: true,
                    minlength: 3,
                    maxlength: 3,
                },
            },
        });
    });

    $('input[type=radio][name=payment_method]').change(function() {
        alert("123456");
        if(this.value == 'Cash'){
            $('.card-validation-wrp').slideUp();
        }
        if(this.value == 'Card'){
            $('.card-validation-wrp').slideDown();
            $('#cardnumber').mask('0000 0000 0000 0000');
            $('#expiration').mask('00/00');
            $('#cvc').mask('000');

            $(".paypayment-btn").click(function() {
                var form = $("#finalpayout");
                form.validate({
                    errorElement: 'span',
                    errorClass: 'help-block',
                    success: function(label) {
                        label.text("ok!").addClass("success");
                    },
                    rules: {
                        cardnumber: {
                            required: true,
                            minlength: 19,
                            maxlength: 19,
                        },
                        nameoncard: {
                            required: true,
                        },
                        expiration: {
                            required: true,
                            minlength: 5,
                            maxlength: 5,
                        },
                        cvv: {
                            required: true,
                            minlength: 3,
                            maxlength: 3,
                        },
                    },
                    submitHandler: function (form) {

                      var spinner = $('#loader').show();
                      $form.get(0).submit();
                        // $(function() {
                        //   $('form').submit(function(e) {
                        //     spinner.show();
                        //   });
                        // });
                    }
                });
            });

        }
    });
});