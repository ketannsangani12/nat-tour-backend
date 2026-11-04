const configureModal = document.getElementById('configureModal');

/*────────────────────────────────────────────────────────────────*/
/*  Rendering modal data                                          */
/*────────────────────────────────────────────────────────────────*/ 
configureModal.addEventListener('show.bs.modal', function (event) {
  const button = event.relatedTarget;
  const tenantId = button.getAttribute('data-tenant-id');
  const networkId = button.getAttribute('data-network-id');

  document.getElementById('modalTenantId').value = tenantId;
  document.getElementById('modalNetworkId').value = networkId;

  document.getElementById('calendarSwitch').checked = false;
  document.getElementById('packageCheckboxes').innerHTML = '<p>Loading packages...</p>';

  // Construct URL with query params
  const fetchUrl = `${getTenantTourPackage}?tenant_id=${tenantId}&network_id=${networkId}`;

 fetch(fetchUrl)
    .then(res => res.json())
    .then(data => {
      const checkboxContainer = document.getElementById('packageCheckboxes');
      checkboxContainer.innerHTML = '';

      // Set switch state
      document.getElementById('calendarSwitch').checked = !!data.calendar_switch;

      // Inject checkboxes
      if (data.packages && data.packages.length > 0) {
        data.packages.forEach(pkg => {
          const checked = data.selected_package_ids.includes(String(pkg.id)) ? 'checked' : '';
          checkboxContainer.innerHTML += `
            <div class="w-full sm:w-1/2 max-w-full px-3">
              <div class="form-check flex items-center mb-1">
                <input class="form-check-input w-4 h-4 mr-2" type="checkbox" name="package_assignments[]" value="${pkg.id}" id="pkg-${pkg.id}" ${checked}>
                <label class="form-check-label form-custom-label !mb-0" for="pkg-${pkg.id}">
                  ${pkg.name}
                </label>
              </div>
            </div>
          `;
        });
      } else {
        checkboxContainer.innerHTML = '<p>No packages found.</p>';
      }
      // Event delegation to ensure buttons work after dynamic content load
      document.getElementById('selectAllPackages').addEventListener('click', function () {
        document.querySelectorAll('#packageCheckboxes input[type="checkbox"]').forEach(cb => cb.checked = true);
      });

      document.getElementById('deselectAllPackages').addEventListener('click', function () {
        document.querySelectorAll('#packageCheckboxes input[type="checkbox"]').forEach(cb => cb.checked = false);
      });

    });

});

/*────────────────────────────────────────────────────────────────*/
/*  Message handling                                              */
/*────────────────────────────────────────────────────────────────*/ 
window.addEventListener('DOMContentLoaded', () => {
    const alertBox = document.getElementById('success-alert');
    if (alertBox) {
        setTimeout(() => {
            alertBox.classList.remove('show');
            alertBox.classList.add('fade');
            alertBox.style.display = 'none';
        }, 5000); // 5 seconds
    }
});

/*────────────────────────────────────────────────────────────────*/
/*  Form submit with show loading                                 */
/*────────────────────────────────────────────────────────────────*/ 
document.getElementById('configurationForm').addEventListener('submit', function (e) {
    const selectedPackages = document.querySelectorAll('#packageCheckboxes input[type="checkbox"]:checked');

    if (selectedPackages.length === 0) {
        e.preventDefault(); // Prevent form submission

        Swal.fire({
            icon: 'warning',
            title: 'Select at least one package',
            text: 'Please select at least one tour package.',
            confirmButtonText: 'OK',
            customClass: {
                confirmButton: 'btn btn-warning'
            },
            buttonsStyling: false
        });

        return false;
    }

    // Show loading only if validation passes
    showLoading();
});
