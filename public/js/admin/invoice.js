function printPage() {
    var printHtml = window.open('', 'PRINT', 'height=400,width=600');
    printHtml.document.write('<html><head>');
    printHtml.document.write(document.getElementById("printableId").innerHTML);
    printHtml.document.write('</body></html>');
    printHtml.document.close();
    printHtml.focus();
    printHtml.print();
    printHtml.close();
    return true;
}

Inputmask("email", {
    separator: ", ",
    quantifier: 5
}).mask("email");


// How often to poll for the finished PDF, and a generous backstop so the
// dialog cannot spin forever if the job silently dies (the PDF would never
// appear on S3, so existence-polling alone can't detect that).
const INVOICE_PDF_POLL_MS = 3000;
const INVOICE_PDF_MAX_ATTEMPTS = 100; // ~5 min of polling

// Inject the spinner styles once (kept here so this file is self-contained).
function injectInvoicePdfSpinnerStyle() {
    if (document.getElementById('invoice-pdf-spinner-style')) {
        return;
    }
    const style = document.createElement('style');
    style.id = 'invoice-pdf-spinner-style';
    style.textContent =
        '.invoice-pdf-wait{display:flex;flex-direction:column;align-items:center;gap:14px;padding:6px 0;}' +
        '.invoice-pdf-spinner{width:44px;height:44px;border-radius:50%;border:4px solid #e2e8f0;' +
        'border-top-color:#60BEBD;animation:invoicePdfSpin 0.9s linear infinite;}' +
        '.invoice-pdf-status{margin:0;color:#475569;font-size:14px;}' +
        '@keyframes invoicePdfSpin{to{transform:rotate(360deg);}}';
    document.head.appendChild(style);
}

const generatePDFAsync = (event) => {
    event.preventDefault();
    
    // event.target may be the icon inside the anchor; walk up to the anchor.
    const ele = event.target.closest('a');
    if (!ele) {
        return false;
    }

    const tag = $(ele);
    // Remember the original label so we can restore it when done.
    if (tag.data('label') === undefined) {
        tag.data('label', tag.text().trim());
    }
    const label = tag.data('label');

    const setBusy = (text) => {
        tag.text(text);
        tag.css('pointer-events', 'none').addClass('opacity-60');
    };
    const reset = () => {
        tag.text(label);
        tag.css('pointer-events', '').removeClass('opacity-60');
        $("#pdf-not-generated").remove();
    };

    setBusy('Starting...');

    $.ajax({ url: ele.href, dataType: 'json' })
        .done((data) => {
            if (!data || !data.success) {
                reset();
                swal('PDF creation', (data && data.message) || 'Could not start PDF generation.', 'error');
                return;
            }
            pollInvoicePdf(data.status_url, data.previous_last_modified, setBusy, reset);
        })
        .fail((xhr) => {
            reset();
            const message = (xhr.responseJSON && xhr.responseJSON.message)
                || 'Could not start PDF generation. Please try again.';
            swal('PDF creation', message, 'error');
        });

    return false;
};

// Show a waiting dialog with a spinner that stays open and polls the status
// endpoint until a PDF newer than `baseline` appears, then swaps itself for a
// download prompt. The dialog is not dismissible by click/escape, but a
// "Run in background" button lets the user stop watching (generation keeps
// going server-side) so they are never trapped if something stalls.
function pollInvoicePdf(statusUrl, baseline, setBusy, reset) {
    if (!statusUrl) {
        reset();
        swal('PDF creation', 'Generation started, but the status could not be tracked. Please check back shortly.', 'info');
        return;
    }

    injectInvoicePdfSpinnerStyle();

    // finished: we resolved successfully / with a terminal message and are
    //           swapping the dialog ourselves - not a user cancel.
    // cancelled: the user clicked "Run in background" - stop polling.
    let finished = false;
    let cancelled = false;
    let attempt = 0;
    const startedAt = Date.now();

    const content = document.createElement('div');
    content.className = 'invoice-pdf-wait';
    content.innerHTML =
        '<div class="invoice-pdf-spinner"></div>' +
        '<p class="invoice-pdf-status">Starting the PDF generation...</p>';
    const statusText = content.querySelector('.invoice-pdf-status');

    swal({
        title: 'Generating invoice PDF',
        content: content,
        closeOnClickOutside: false,
        closeOnEsc: false,
        buttons: {
            background: { text: 'Run in background', value: null },
        },
    }).then(() => {
        // Fires when the dialog closes. If we did not close it ourselves, the
        // user chose to stop watching.
        if (!finished) {
            cancelled = true;
            reset();
        }
    });

    const showReady = (status) => {
        finished = true;
        reset();
        swal({
            title: 'Invoice PDF ready',
            text: 'Your PDF has finished generating.',
            icon: 'success',
            buttons: {
                download: { text: 'Download PDF', value: 'download' },
                close: { text: 'Close', value: null },
            },
        }).then((choice) => {
            if (choice === 'download' && status.url) {
                window.open(status.url, '_blank');
            }
        });
    };

    const showStalled = () => {
        finished = true;
        reset();
        swal('PDF creation', 'This is taking longer than expected. The PDF is still being generated in the background and will be ready shortly - please try again in a moment.', 'info');
    };

    const tick = () => {
        if (cancelled) {
            return;
        }

        attempt += 1;
        const seconds = Math.round((Date.now() - startedAt) / 1000);
        if (statusText) {
            statusText.textContent = `Working on your PDF... ${seconds}s elapsed. Please keep this window open.`;
        }

        $.ajax({ url: statusUrl, dataType: 'json' })
            .done((status) => {
                if (cancelled) {
                    return;
                }

                // A newly generated PDF has a last_modified different from the
                // one that existed before we kicked off generation.
                const isFresh = status && status.ready
                    && (baseline === null || baseline === undefined || status.last_modified !== baseline);

                if (isFresh) {
                    showReady(status);
                    return;
                }

                if (attempt >= INVOICE_PDF_MAX_ATTEMPTS) {
                    showStalled();
                    return;
                }

                setTimeout(tick, INVOICE_PDF_POLL_MS);
            })
            .fail(() => {
                if (cancelled) {
                    return;
                }

                if (attempt >= INVOICE_PDF_MAX_ATTEMPTS) {
                    finished = true;
                    reset();
                    swal('PDF creation', 'Could not confirm the PDF status. Please refresh the page and try again.', 'error');
                    return;
                }

                setTimeout(tick, INVOICE_PDF_POLL_MS);
            });
    };

    setTimeout(tick, INVOICE_PDF_POLL_MS);
}
