function convertTo24Time(timeStr) {
    if (!timeStr) return '';

    timeStr = timeStr.toUpperCase().trim();   // e.g. "10:00 AM"

    const [time, modifier] = timeStr.split(' '); // ["10:00", "AM"]
    let [hours, minutes] = time.split(':').map(Number);

    if (modifier === 'PM' && hours < 12) {
        hours += 12;
    }
    if (modifier === 'AM' && hours === 12) {
        hours = 0;
    }

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:00`;
}

function updatePdfButton() {
    let dateText = $('#guideDate').text().trim();  // "Tuesday, December 09, 2025"
    let timeText = $('#guideTime').text().trim();  // "10:00 AM"

    console.log("Raw:", dateText, timeText);

    if (!dateText || !timeText) return;

    // ---- DATE: "Tuesday, December 09, 2025" → "2025-12-09"
    const parsedDate = new Date(dateText);
    if (isNaN(parsedDate.getTime())) {
        console.warn("Invalid date:", dateText);
        return;
    }

    const year  = parsedDate.getFullYear();
    const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
    const day   = String(parsedDate.getDate()).padStart(2, '0');
    const formattedDate = `${year}-${month}-${day}`;

    // ---- TIME: "10:00 AM" → "10:00:00"
    const formattedTime = convertTo24Time(timeText);

    console.log("Converted:", formattedDate, formattedTime);

    const url = `${adminBoardPdfUrl}?date=${encodeURIComponent(formattedDate)}&time_from=${encodeURIComponent(formattedTime)}`;

    document.getElementById('downloadPdfBtn').setAttribute('href', url);
}

document.getElementById("downloadPdfBtn").addEventListener("click", function (event) {
    updatePdfButton();
});


