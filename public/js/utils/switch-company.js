 const tenants = Array.isArray(window.switchTenants)
    ? window.switchTenants
    : [];


// Show the <li> only if tenant data exists
const switchCompanyWrapper = document.getElementById('switch-company-wrapper');
    if (tenants.length > 0 && switchCompanyWrapper) {
        switchCompanyWrapper.classList.remove('hidden');

    }

document.addEventListener('click', (e) => {
    const wrapper = document.getElementById('switch-company-wrapper');
    const dropdown = document.getElementById('tenant-dropdown');

    if (!wrapper || !dropdown) return;

    if (!wrapper.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

function showTenantDropdown() {
    const dropdown = document.getElementById('tenant-dropdown');
    
    // Check if dropdown exists before proceeding
    if (!dropdown) {
        return;
    }
    
    dropdown.innerHTML = ''; // Clear old items

    tenants.forEach(t => {
        const li = document.createElement('li');
        const a = document.createElement('a');

        a.href = 'javascript:void(0);';
        a.className = 'dropdown-item block px-4 py-2 text-[#4B5563] hover:text-[#F97316] hover:bg-gray-50';
        a.textContent = t.tenant_name || t.tenant_id; // Use tenant_name if available, fallback to 

        a.addEventListener('click', () => {
            switchTenant(t);
        });

        li.appendChild(a);
        dropdown.appendChild(li);
    });

    dropdown.classList.remove('hidden');
}

function hideTenantDropdown() {
    const dropdown = document.getElementById('tenant-dropdown');
    const switchCompanyWrapper = document.getElementById('switch-company-wrapper');
    
    // Add a small delay to allow clicking on dropdown items
    setTimeout(() => {
        if (dropdown && switchCompanyWrapper) {
            if (!dropdown.matches(':hover') && !switchCompanyWrapper.matches(':hover')) {
                dropdown.classList.add('hidden');
            }
        }
    }, 100);
}

function switchTenant(tenant) {
    
    showLoading();
    
    $.get(url, { tenant: tenant })
        .done(function (data) {
            window.location = data.url; // redirect or handle as needed
        })
    .fail(showSubmissionError)
    .always(hideLoading);
}