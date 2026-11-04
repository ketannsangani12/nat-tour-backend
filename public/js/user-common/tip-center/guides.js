class Guides {
    constructor(props) {
        this.init(props);
    }

    init(props) {
        this.searchGuideUrl = props.searchGuideUrl;
        this.editGuideUrl = props.editGuideUrl;
        this.bindEvents();
    }

    bindEvents() {
        $(document).off('click', '#searchTourGuideBtn').on('click', '#searchTourGuideBtn', (evt) => {
            this.searchTourGuide();
        });
        $(document).off('click', '#resetTourGuideBtn').on('click', '#resetTourGuideBtn', (evt) => {
            $('#searchTourGuideInput').val('');
            this.searchTourGuide();
        });
        $(document).off('change', '#profilePicture').on('change', '#profilePicture', (evt) => {
            this.validateImage(evt);
        });
    }

    searchTourGuide() {
        var searchQuery = $('#searchTourGuideInput').val().trim();
        if (searchQuery.length > 0 && searchQuery.length < 3) {
            swal('Invalid Search', 'Please enter at least 3 characters to search.', 'warning');
            return;
        }
        showLoading();
        $.ajax({
            url: this.searchGuideUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                searchTerm: searchQuery
            }),
            success: this.renderTourGuides.bind(this),
        }).always(hideLoading)
            .fail(function (jqXHR) {
                hideLoading();
                showSubmissionError(jqXHR);
            });
    }

    renderTourGuides(data) {
        var tourGuides = data.guides;
        if (tourGuides.length === 0) {
            swal('No Results', 'No tour guides found matching your search.', 'info');
            return;
        }

        var html = ``;
        var editGuideUrl = this.editGuideUrl;

        tourGuides.forEach(tourGuide => {
            var url = editGuideUrl.replace(':id', tourGuide.id);

            html += `<div class="card guide-card" style="flex: 0 0 calc(33.33% - 0.5rem);">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="guide-avatar me-3">`;
            if (tourGuide.profile_picture) {
                html += `<img src="${tourGuide.profile_picture}" alt="${tourGuide.name}" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">`;
            } else {
                html += `<i class="fas fa-user text-white"></i>`;
            }
            html += `</div>
                                            <div>
                                                <h6 class="mb-0">${tourGuide.name}</h6>
                                                <small class="text-muted">${tourGuide.email}</small>
                                            </div>
                                        </div>
                                        <span class="badge bg-success d-none">Active</span>
<a href="${url}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">Capacity:</span>
                                            <span class="fw-semibold">${tourGuide.total_tourists}/${tourGuide.capacity} guests</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: ${(tourGuide.capacity > 0) ? (tourGuide.total_tourists / tourGuide.capacity * 100) : 0}%; transition: width 1s ease-in-out;"></div>
                                        </div>
                                    </div>

                                </div>
                     </div>`;
        });

        $('#guidesListDiv').html(html);
    }
    validateImage(evt) {
        var file = evt.target.files[0];
        if (file) {
            var fileType = file.type;
            var validImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validImageTypes.includes(fileType)) {
                swal('Invalid File selected', 'Please select a valid image file (JPEG, PNG, GIF).', 'error');
                $('#profilePicture').val('');
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#profilePicturePreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    }
    showLoading() {
        document.getElementById('loading').style.display = 'block';
        document.getElementById('bookings-container').style.display = 'none';
    }

    hideLoading() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('bookings-container').style.display = 'block';
    }
}


