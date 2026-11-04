let selectedUsers = {};

document.addEventListener("DOMContentLoaded", function () {

    // Validate before form submit
    const form = document.getElementById("assign-users-form");
    if (!form) {
        console.warn("Form not found");
        return;
    }

    form.addEventListener("submit", function (e) {
        const hiddenInputs = document.querySelectorAll('input[name^="tenant_users["]');
        let filledCount = 0;

        hiddenInputs.forEach(input => {
            try {
                const value = JSON.parse(input.value);
                if (value && value.id && value.type) {
                    filledCount++;
                }
            } catch (err) {
                // ignore empty/invalid JSON
            }
        });

        showLoading();
        if (filledCount < 2) {
            hideLoading()
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Incomplete Selection',
                text: 'Minimum two users required.',
                confirmButtonText: 'OK'
            });
        }
    });
    
    const container = document.getElementById('tenant-sections');
    const assignBtn = document.querySelector('button[type="submit"]');
    const assignedUsersList = document.getElementById('assigned-users-list');
    buildTenantTable(container, tenantData);
    attachRoleClickHandler();
    attachSearchBox();
    attachModalUserSelectHandler();
    attachTenantSearch();

    // If tenant data is empty
    if (!Object.keys(tenantData).length) {
        container.innerHTML = `
            <div class="w-full max-w-full px-3">
                <div class="alert alert-warning text-center" role="alert">
                    <strong>No tenants found.</strong> Please add the tenants first.
                </div>
            </div>
        `;
        if (assignBtn) {
            assignBtn.style.display = 'none';
        }

        if (assignedUsersList) {
            assignedUsersList.style.display = 'none';
        }
        return;
    }
});    

/*────────────────────────────────────────────────────────────────*/
/*   Tenant's list tabel                                          */
/*────────────────────────────────────────────────────────────────*/
function buildTenantTable(container, tenantData) {
  // Clear any existing content
  container.innerHTML = '';

  // Create the table and header
  const table = document.createElement('table');
  table.className = 'table table-bordered align-middle w-full';

  const thead = document.createElement('thead');
  thead.innerHTML = `
    <tr class="table-light">
      <th class="th-custom-css w-1/2 text-left">Tenant ID</th>
      <th class="th-custom-css w-1/2 text-left">Action</th>
    </tr>
  `;

  const tbody = document.createElement('tbody');

  let idx = 0;
  for (const tenantKey in tenantData) {
    const tr = document.createElement('tr');

    // Tenant name/ID column
    const tdName = document.createElement('td');
    tdName.className = 'td-custom-css';
    tdName.innerHTML = `<span>${tenantKey}</span>`;

    // Action dropdown column
    const tdAction = document.createElement('td');
    tdAction.className = 'td-custom-css';
    const ddWrap = document.createElement('div');
    ddWrap.className = 'dropdown relative';

    const ddBtn = document.createElement('button');
    ddBtn.className = 'btn btn-primary dropdown-toggle outline-none shadow-none border border-[#E2E8F0] rounded-lg px-3 py-2 h-[34px] text-xs leading-[14px] text-[#020817] focus:border-[#ed9c45] focus:shadow-[0_0_2px_0_rgba(249,115,22,1)]';
    ddBtn.type = 'button';
    ddBtn.id = `dd_${idx}`;
    ddBtn.dataset.bsToggle = 'dropdown';
    ddBtn.innerHTML = `
      Select user
      <svg class="inline-block ms-1" width="14" height="15" viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path class="group-hover:!stroke-[#F97316]" 
              d="M1.4001 4.70019L7.0001 10.3002L12.6001 4.7002" 
              stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          </path>
      </svg>
    `;
    

    const ddMenu = document.createElement('ul');
    ddMenu.className = 'dropdown-menu absolute left-1/2 -translate-x-1/2 mt-2 w-64 bg-white shadow-lg z-50 rounded border text-sm text-left hidden';

    const roles = tenantData[tenantKey];
    for (const role in roles) {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = '#';
      a.className = 'dropdown-item role-select block px-4 py-2 text-[#4B5563] hover:text-[#F97316]';
      a.textContent = role;

      a.dataset.role = role;
      a.dataset.tenant = tenantKey;
      a.dataset.btnId = ddBtn.id;
      a.dataset.target = `tenant_${idx}_user`;

      li.appendChild(a);
      ddMenu.appendChild(li);
    }

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = `tenant_users[${tenantKey}]`;
    hidden.id = `tenant_${idx}_user`;

    ddWrap.appendChild(ddBtn);
    ddWrap.appendChild(ddMenu);
    tdAction.append(ddWrap, hidden);

    tr.append(tdName, tdAction);
    tbody.appendChild(tr);

    idx++;
  }

  table.append(thead, tbody);
  container.appendChild(table);
}

/*────────────────────────────────────────────────────────────────*/
/*   Open modal when a role is clicked                            */
/*────────────────────────────────────────────────────────────────*/
function attachRoleClickHandler() {
  document.addEventListener('click', e => {
    const roleLink = e.target.closest('.role-select');
    if (!roleLink) return;
    e.preventDefault();

    const { role, tenant, btnId, target } = roleLink.dataset;

    const modalEl = document.getElementById('userSelectModal');
    modalEl.dataset.btnId  = btnId;      // for later
    modalEl.dataset.target = target;
    modalEl.dataset.role   = role;       // store type
    modalEl.dataset.tenant = tenant;

    /* users for this role */
    const users = tenantData[tenant][role] || [];
    modalEl.users = users;               // keep original array

    document.getElementById('modalRoleLabel').textContent = role;
    document.getElementById('userSearchInput').value = '';

    renderUserRows(users, role);
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });
}

/*────────────────────────────────────────────────────────────────*/
/*   Search box for tenant's user                                 */
/*────────────────────────────────────────────────────────────────*/
function attachSearchBox() {
  const input = document.getElementById('userSearchInput');
  let timer;

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const q = input.value.trim().toLowerCase();

      const modalEl  = document.getElementById('userSelectModal');
      const allUsers = modalEl.users || [];
      const role     = modalEl.dataset.role;

      const filtered = !q ? allUsers : allUsers.filter(u =>
        (u.name  && u.name.toLowerCase().includes(q)) ||
        (u.email && u.email.toLowerCase().includes(q))
      );
      renderUserRows(filtered, role);
    }, 200);
  });
}

/*────────────────────────────────────────────────────────────────*/
/*   Render header + user rows                                    */
/*────────────────────────────────────────────────────────────────*/
function renderUserRows(users, role) {
  const list = document.getElementById('modalUserList');
  list.innerHTML = '';

  const modalEl = document.getElementById('userSelectModal');
  const tenant = modalEl.dataset.tenant;
  const selectedKey = `${tenant}__${role}`;
  const selectedUserId = selectedUsers?.[selectedKey]?.id;

  /* Header */
  const header = document.createElement('li');
  header.className = 'list-group-item bg-light fw-semibold w-full min-w-[1024px]';
  header.innerHTML =
    '<span style="width:25%;" class="px-4 py-2.5 inline-block bg-gray-50 text-black text-sm border border-[#E5E7EB] !shadow-none whitespace-nowrap font-medium">Name</span>' +
    '<span style="width:25%;" class="px-4 py-2.5 inline-block bg-gray-50 text-black text-sm border border-[#E5E7EB] !shadow-none whitespace-nowrap font-medium">Email</span>' +
    '<span style="width:25%;" class="px-4 py-2.5 inline-block bg-gray-50 text-black text-sm border border-[#E5E7EB] !shadow-none whitespace-nowrap font-medium text-center">Status</span>' +
    '<span style="width:25%;" class="px-4 py-2.5 inline-block bg-gray-50 text-black text-sm border border-[#E5E7EB] !shadow-none whitespace-nowrap font-medium text-center">Action</span>';
  list.appendChild(header);

  /* No Data */
  if (!users.length) {
    const li = document.createElement('li');
    li.className = 'list-group-item text-center text-muted py-3';
    li.textContent = 'No users found';
    list.appendChild(li);
    return;
  }

  const badgeFor = s => {
    s = (s || '').toLowerCase();
    if (s === 'active') return 'bg-success border border-[#198754] bg-[#19875430] text-[#198754] rounded-full px-4 py-2 text-center';
    if (s === 'inactive') return 'bg-secondary border bg-white rounded-full px-4 py-2 text-center';
    return 'bg-warning border border-[#ffc107] bg-orange-50 text-[#ffc107] rounded-full px-4 py-2 text-center';
  };

  users.forEach(u => {
    const isSelected = u.id == selectedUserId;

    const link = document.createElement('a');
    link.href = '#';
    link.className = 'modal-user-select flex justify-between items-stretch text-decoration-none w-full';

    link.dataset.userId = u.id;
    link.dataset.userName = u.name;
    link.dataset.userRole = role;

    link.innerHTML =
      `<span style="width:25%;" class="flex-1 flex items-center justify-center px-4 py-2.5 inline-block bg-gray-50 text-black text-sm border border-[#E5E7EB] !shadow-none">
         <span class="${isSelected ? 'bg-success text-white rounded px-2 py-1' : ''}">${u.name}</span>
       </span>` +
      `<span style="width:25%;" class="flex-1 flex items-center justify-center text-muted small px-4 py-2.5 inline-block bg-gray-50 text-black text-sm border border-[#E5E7EB] !shadow-none">${u.email}</span>` +
      `<span style="width:25%;" class="flex-1 flex items-center justify-center px-4 py-2.5 inline-block bg-gray-50 text-black text-sm border border-[#E5E7EB] !shadow-none text-center">
         <span class="badge ${badgeFor(u.status)}">${u.status}</span>
       </span>` +
      `<span style="width:25%;" class="flex-1 flex items-center justify-center text-primary fw-semibold px-4 py-2.5 inline-block bg-gray-50 text-black text-sm border border-[#E5E7EB] !shadow-none text-center"><span class="btn-primary-small">Select</span>`;

    const li = document.createElement('li');
    li.className = 'list-group-item p-0 w-full min-w-[1024px]';
    li.appendChild(link);
    list.appendChild(li);
  });
}

/*────────────────────────────────────────────────────────────────*/
/*   When a user is picked, save {id,type} to hidden input        */
/*────────────────────────────────────────────────────────────────*/
function attachModalUserSelectHandler() {
  document.getElementById('modalUserList').addEventListener('click', e => {
    const link = e.target.closest('.modal-user-select');
    if (!link) return;
    e.preventDefault();

    const { userId, userName, userRole } = link.dataset;

    const modalEl = document.getElementById('userSelectModal');
    const target = modalEl.dataset.target;
    const btnId = modalEl.dataset.btnId;
    const tenant = modalEl.dataset.tenant;

    // Call checkUserExists before saving the selection
    showLoading();
    checkUserExists(userId, userRole, tenant, function (exists, groupId) {
      if (exists) {
        hideLoading();
        return;
      }

      // Save selected user to global object
      selectedUsers[`${tenant}__${userRole}`] = {
        id: userId,
        name: userName
      };

      // Update hidden input value
      const input = document.getElementById(target);
      if (input) {
        input.value = JSON.stringify({ id: userId, type: userRole });
      }

      // Update dropdown button label
      const btn = document.getElementById(btnId);
      if (btn) {
        btn.textContent = `${userName} (${userRole})`;
        btn.classList.add('bg-success', 'text-white');
      }

      // Hide modal
      bootstrap.Modal.getInstance(modalEl).hide();
      hideLoading();
    });
  });
}

/*────────────────────────────────────────────────────────────────*/
/*   Validation check the exist user                              */
/*────────────────────────────────────────────────────────────────*/
function checkUserExists(userId, userType, tenantId, callback) {
  fetch(urlcheckUserExists, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      user_id: userId,
      user_type: userType,
      tenant_id: tenantId
    })
  })
    .then(response => response.json())
    .then(data => {
      if (data.exists) {
       Swal.fire({
          icon: 'warning',
          title: 'User Already Assigned',
          html: `<span style="color: green; font-weight: bold;">${data.name}</span> is already assigned to Group ID: <strong>${data.group_id}</strong>`,
          confirmButtonText: 'OK',
          customClass: {
            confirmButton: 'btn btn-warning'
          },
          buttonsStyling: false
        });
        callback(true, data.group_id); // you can still return this for logic handling
      } else {
        callback(false, null);
      }
    })
    .catch(error => {
      console.error('Error checking user existence:', error);
      callback(false, null); // fallback
    });
}

/*────────────────────────────────────────────────────────────────*/
/*   Search the tenant id                                         */
/*────────────────────────────────────────────────────────────────*/
function attachTenantSearch() {
  const input = document.getElementById('tenantTableSearch');
  const tableContainer = document.getElementById('tenant-sections');

  input.addEventListener('input', () => {
    const query = input.value.trim().toLowerCase();

    // Rebuild the table from tenantData
    const filteredData = {};

    for (const tenantKey in tenantData) {
      if (tenantKey.toLowerCase().includes(query)) {
        filteredData[tenantKey] = tenantData[tenantKey];
      }
    }

    buildTenantTable(tableContainer, filteredData);
  });
}

/*────────────────────────────────────────────────────────────────*/
/*  Edit‑group buttons: re‑populate the form                      */
/*────────────────────────────────────────────────────────────────*/
document.querySelectorAll('.edit-group-btn').forEach(button => {
  button.addEventListener('click', function () {
    const groupId = this.dataset.groupId;
    const groupUsers = JSON.parse(this.dataset.groupUsers || '[]'); // array of { tenant, user_type, user_id, user_name }

    document.getElementById('group_id').value = groupId;

    // Clear previous selectedUsers
    if (typeof selectedUsers !== 'object') {
      window.selectedUsers = {};
    } else {
      Object.keys(selectedUsers).forEach(key => delete selectedUsers[key]);
    }

    // Step 1: Reset all tenant-role buttons
    document.querySelectorAll('[data-tenant][data-role]').forEach(dropdownLink => {
      const btnId = dropdownLink.dataset.btnId;
      const tenant = dropdownLink.dataset.tenant;
      const role = dropdownLink.dataset.role;
      const btn = document.getElementById(btnId);
      const hiddenInput = document.querySelector(`input[name="tenant_users[${tenant}]"]`);

      if (btn) {
        btn.textContent = 'Select user';
        btn.classList.remove('bg-success', 'text-white');
      }

      if (hiddenInput) {
        hiddenInput.value = '';
      }
    });

    // Step 2: Apply group users
    groupUsers.forEach(({ tenant, user_type, user_id, user_name }) => {
      const key = `${tenant}__${user_type}`;
      selectedUsers[key] = { id: user_id, name: user_name };

      // Update hidden input
      const hiddenInput = document.querySelector(`input[name="tenant_users[${tenant}]"]`);
      if (hiddenInput) {
        hiddenInput.value = JSON.stringify({ id: user_id, type: user_type });
      }

      // Update button label
      const dropdownLink = document.querySelector(`[data-tenant="${tenant}"][data-role="${user_type}"]`);
      if (dropdownLink) {
        const btnId = dropdownLink.dataset.btnId;
        const btn = document.getElementById(btnId);
        if (btn) {
          btn.textContent = `${user_name} (${user_type})`;
          btn.classList.add('bg-success', 'text-white');
        }
      }
    });

    // Optional: scroll into view
    document.getElementById('tenant-sections')?.scrollIntoView({ behavior: 'smooth' });
  });
});

/*────────────────────────────────────────────────────────────────*/
/*  Delete function                                               */
/*────────────────────────────────────────────────────────────────*/
document.querySelectorAll('.delete-group-form').forEach(form => {
    const button = form.querySelector('button');
    const hiddenInput = form.querySelector('.delete-group-id');

    button.addEventListener('click', function (e) {
        e.preventDefault(); // prevent form from submitting immediately

        const groupId = form.dataset.groupId;

        Swal.fire({
            title: 'Are you sure?',
            text: 'This will permanently delete the assigned group!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                if (groupId) {
                    showLoading();
                    hiddenInput.value = groupId;
                    form.submit(); // Submit after confirmation
                } else {
                    console.warn('Group ID not found.');
                }
            }
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
