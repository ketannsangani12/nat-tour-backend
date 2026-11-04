// CountrySwitcher.js

class CountrySwitcher {
    constructor(config) {
        this.allCountries = config.allCountries;
        this.activeCountry = config.activeCountry;
        this.setActiveCountryUrl = config.setActiveCountryUrl;
        this.csrfToken = config.csrfToken;

        this.countryDropdown = document.getElementById('id');
        this.init();
    }

    init() {
        if (this.countryDropdown) {
            this.countryDropdown.addEventListener('change', this.handleCountryChange.bind(this));
        }
    }

    handleCountryChange(event) {
        const selectedId = event.target.value;

        if (!selectedId) return;

        const selectedCountry = this.allCountries.find(country => country.id == selectedId);

        if (selectedCountry) {
            swal({
                title: 'Are you sure?',
                text: `Do you want to activate ${selectedCountry.name}?`,
                icon: 'warning',
                buttons: {
                    no: {
                        text: 'No',
                        value: false,
                    },
                    yes: {
                        text: 'Yes',
                        className: 'btn-danger',
                        value: true,
                    }
                }
            }).then((result) => {
                if (result) {
                    this.updateInputFields(selectedCountry);
                    this.setActiveCountry(selectedId);
                } else {
                    this.countryDropdown.value = this.activeCountry.id;
                }
            });
        }
    }

    updateInputFields(country) {
        document.getElementById('country_code').value = country.code;
        document.getElementById('currency_code').value = country.currency;
        document.getElementById('currency_symbol').value = country.currency_symbol;
        document.getElementById('phone_code').value = country.phone_code ?? '';
        document.getElementById('locale').value = country.locale ?? '';
    }

    setActiveCountry(id) {
        fetch(this.setActiveCountryUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken
            },
            body: JSON.stringify({ id })
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    swal('Activated!', 'Active country has been updated successfully.', 'success');

                    // ✅ Update activeCountry to the new one after success
                    const newActive = this.allCountries.find(c => c.id == id);
                    if (newActive) {
                        this.activeCountry = newActive;
                    }
                } else {
                    swal('Failed!', data.message || 'Failed to update active country.', 'error');
                    this.countryDropdown.value = this.activeCountry.id;
                }
            })
            .catch(error => {
                swal('Error!', 'Something went wrong. Please try again later.', 'error');
                this.countryDropdown.value = this.activeCountry.id;
            });
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function () {
    new CountrySwitcher(window.countrySwitcherData);
});
