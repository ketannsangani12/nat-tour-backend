function printQRCode() {
    let qrElement = document.getElementById("qrCodeSection");

    if (!qrElement) {
        alert("QR Code not found!");
        return;
    }

    let qrContent = qrElement.innerHTML;

    // open a full screen window
    let win = window.open("", "PrintQR", "toolbar=no,location=no,menubar=no,scrollbars=no,resizable=no");
    win.document.write(`
        <html>
            <head>
                <title>Print QR Code</title>
                <style>
                    body {
                        display:flex;
                        justify-content:center;
                        align-items:center;
                        height:100vh;
                        margin:0;
                    }
                </style>
            </head>
            <body>
                ${qrContent}
            </body>
        </html>
    `);
    win.document.close();
    win.focus();
    win.print();
    win.close();
}

function calculateAmounts() {
    let tip = parseFloat($("#tipAmount").val()) || 0;
    let fees = roundout(tip * natServiceCommission / 100, 2);
    let total = tip + fees;

    $("#tipFees").text(currencyFormation.symbol + fees.toFixed(2));
    $("#totalAmount").text(currencyFormation.symbol + total.toFixed(2));
}
function roundout(amount, places = 0) {
    if (places < 0) {
        places = 0;
    }

    let x = Math.pow(10, places);
    return (amount >= 0 ? Math.ceil(amount * x) : Math.floor(amount * x)) / x;
}

$("#tipAmount").on("input", calculateAmounts);
function validateAndSubmitForm(form) {
    initializeStripe(form);
    return false;
}

async function initializeStripe(form) {
    // Show the loader
    showLoading();

    try {
        $('#payment-element').empty();
        // $('#link-authentication-element').empty();
        $('#saved-cards-container').empty().hide();

        // Get CSRF token from meta tag
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        const formData = new FormData(form);
        // Append save_card preference
        formData.append('save_card', $('#save-card-checkbox').is(':checked') ? '1' : '0');

        const intentResponse = await fetch(createPaymentIntentUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData,
        });

        const responseData = await intentResponse.json();

        if (!intentResponse.ok) {
            throw new Error(responseData.message || 'Unknown backend error');
        } else if (responseData.error) {
            throw new Error(responseData.error);
        }

        // Store clientSecret globally
        clientSecret = responseData.clientSecret;

        // Store savedCards from backend
        const savedCards = responseData.savedCards || [];

        // Debug: Log savedCards to verify data
        console.log('Saved Cards from Backend:', JSON.stringify(savedCards, null, 2));

        const appearance = {
            theme: 'stripe',
        };

        const loader = 'auto';

        const options = {
            layout: {
                type: 'auto',
            },
            paymentMethodOrder: ['card', 'apple_pay', 'google_pay'],
            business: { name: 'Native American Tours' },
            wallets: {
                applePay: 'auto',
                googlePay: 'auto'
            },
            defaultValues: {
                billingDetails: {
                    address: {
                        country: countryCode,
                    },
                },
            },
        };

        elements = stripe.elements({ clientSecret, appearance, loader });

        // Create and mount Payment Element
        const paymentElement = elements.create('payment', options);

        // Wait until elements are mounted before hiding the loader
        await new Promise((resolve, reject) => {
            paymentElement.mount('#payment-element');
            paymentElement.on('ready', resolve);

            // linkAuthenticationElement.mount('#link-authentication-element');
            // linkAuthenticationElement.on('ready', resolve);

            setTimeout(() => reject(new Error('Element mounting timed out')), 30000);
        });


        // No saved cards, ensure checkbox is visible and unchecked
        $('#save-card-checkbox').prop('checked', false).closest('.form-check').show();
        $('#payment-element').show(); // Ensure payment element is visible
        // Show the modal only on success
        $('#make-a-payment').modal('show');
    } catch (error) {
        console.error('Error initializing Stripe elements:', error);
        hideLoading();
        swal('Error occurred', error.message || 'Failed to initialize payment elements', 'error');
    } finally {
        hideLoading();
    }
}
async function handleMakePayment() {
    showLoading();

    try {
        var form = $('#payment-form');

        let paymentConfirmation;

        // Use Payment Element for new card
        paymentConfirmation = await stripe.confirmPayment({
            elements,
            redirect: 'if_required'
        });


        const { paymentIntent, error } = paymentConfirmation;

        if (error) {
            swal('Error occurred', error.message, 'error');
            hideLoading();
        } else {
            if ($(form).find('input[name="payment_intent_id"]').length === 0) {
                $(form).append(`<input type="hidden" name="payment_intent_id" value="${paymentIntent.id}">`);
            } else {
                $(form).find('input[name="payment_intent_id"]').val(paymentIntent.id);
            }
            createTip(form);
        }
    } catch (apiError) {
        console.error('API Error:', apiError);
        swal('Error occurred', apiError.message || apiError, 'error');
        hideLoading();
    }
}
function createTip(form) {
    showLoading();
    $.ajax({
        headers: { Accept: 'application/json' },
        dataType: 'json',
        type: 'POST',
        url: $(form).attr('action'),
        data: $(form).serialize(),
    })
        .done(function (response) {
            swal('Tip Payment Done', 'Page will reload', 'success');
            location.reload();

        })
        .fail(function (jqXHR) {
            hideLoading();
            showSubmissionError(jqXHR);
        })
        .always(hideLoading);
}