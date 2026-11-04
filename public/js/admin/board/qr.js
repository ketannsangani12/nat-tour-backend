let html5QrCode;

document.getElementById("qr-btn").addEventListener("click", function () {
    let qrModal = new bootstrap.Modal(document.getElementById("qrModal"));
    qrModal.show();

    // Start QR Code scanner (camera only, no file option)
    html5QrCode = new Html5Qrcode("qr-reader");

    html5QrCode.start(
        { facingMode: "environment" }, // use back camera
        { fps: 10, qrbox: 250 },
        function onScanSuccess(decodedText, decodedResult) {
            console.log("QR Raw Result:", decodedText);

            // Keep only numbers
            let cleanedValue = decodedText.replace(/\D+/g, "");

            // Put into search input & trigger search
            document.getElementById("bookingSearchInput").value = cleanedValue;
            document.getElementById("bookingSearchBtn").click();

            // Stop scanner & close modal
            html5QrCode.stop().then(() => {
                qrModal.hide();
            }).catch(err => console.error("Stop failed", err));
        },
        function onScanFailure(errorMessage) {
            // Fail silently
        }
    ).catch(err => {
        console.error("Camera start failed", err);
        Swal.fire("Camera Error", "Unable to access the camera. Please allow permissions and try again.", "error");
    });
});

// Optional: stop camera if modal closed manually
document.getElementById("qrModal").addEventListener("hidden.bs.modal", function () {
    if (html5QrCode) {
        html5QrCode.stop().catch(() => {});
    }
});