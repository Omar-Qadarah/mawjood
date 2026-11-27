export function setupCustomers(api, showNotice, loadSummary, textCell, showDetails) {
    const rows = document.querySelector('#customer-rows');
    const search = document.querySelector('#customer-search');
    const filter = document.querySelector('#customer-status');
    const previous = document.querySelector('#customer-previous');
    const next = document.querySelector('#customer-next');
    const dialog = document.querySelector('#customer-dialog');
    const form = document.querySelector('#customer-form');
    const errorBox = document.querySelector('#customer-error');
    let currentPage = 1;
    let customerId;
    let searchTimer;
    let requestNumber = 0;

    function actionButton(label, handler, danger = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'button button-small ' + (danger ? 'button-danger' : 'button-secondary');
        button.textContent = label;
        button.addEventListener('click', handler);
        return button;
    }

    async function load() {
        const request = ++requestNumber;
        try {
            const params = new URLSearchParams({ page: currentPage });
            if (search.value.trim()) params.set('search', search.value.trim());
            if (filter.value) params.set('status', filter.value);

            const { data, meta } = await api('/admin/customers?' + params);
            if (request !== requestNumber) return;

            if (currentPage > meta.last_page) {
                currentPage = meta.last_page;
                return load();
            }

            rows.replaceChildren();
            if (!data.length) {
                const row = rows.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 5;
                cell.className = 'empty-state';
                cell.textContent = 'No customers match these filters.';
            }
            for (const customer of data) {
                const row = rows.insertRow();
                row.append(
                    textCell(customer.name), textCell(customer.email), textCell(customer.is_active ? 'Active' : 'Inactive'),
                    textCell(String(customer.memberships_count))
                );
                const actions = row.insertCell();
                actions.className = 'row-actions';
                actions.append(
                    actionButton('View', () => view(customer.id)),
                    actionButton('Edit', () => open(customer)),
                    actionButton('Delete', () => remove(customer), true)
                );
            }

            document.querySelector('#customer-page').textContent = 'Page ' + meta.current_page + ' of ' + meta.last_page;
            previous.disabled = currentPage <= 1;
            next.disabled = currentPage >= meta.last_page;
        } catch (error) {
            if (request === requestNumber) showNotice(error.message, true);
        }
    }

    async function view(id) {
        try {
            const { data } = await api('/admin/customers/' + id);
            showDetails('customer', data.name, {
                Email: data.email,
                Status: data.is_active ? 'Active' : 'Inactive',
                Joined: new Date(data.created_at).toLocaleDateString(),
                'Pharmacy memberships': data.memberships.map(({ pharmacy, role, is_active }) =>
                    pharmacy + ' · ' + role + (is_active ? '' : ' (inactive)')
                ).join('\n'),
            });
        } catch (error) {
            showNotice(error.message, true);
        }
    }

    function open(customer = null) {
        form.reset();
        customerId = customer?.id;
        errorBox.hidden = true;
        document.querySelector('#customer-title').textContent = customer ? 'Edit customer' : 'Add customer';
        form.elements.name.value = customer?.name ?? '';
        form.elements.email.value = customer?.email ?? '';
        form.elements.password.required = !customer;
        form.elements.password.placeholder = customer ? 'Leave blank to keep current password' : '';
        form.elements.is_active.checked = customer?.is_active ?? true;
        dialog.showModal();
    }

    async function remove(customer) {
        if (!window.confirm('Delete the customer account for ' + customer.name + '?')) return;
        try {
            await api('/admin/customers/' + customer.id, { method: 'DELETE' });
            showNotice('Customer deleted.');
            await Promise.all([load(), loadSummary()]);
        } catch (error) {
            showNotice(error.message, true);
        }
    }

    document.querySelector('#add-customer').addEventListener('click', () => open());
    document.querySelector('#cancel-customer').addEventListener('click', () => dialog.close());
    previous.addEventListener('click', () => { currentPage--; load(); });
    next.addEventListener('click', () => { currentPage++; load(); });

    function filterCustomers() {
        currentPage = 1;
        load();
    }

    filter.addEventListener('change', filterCustomers);
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(filterCustomers, 250);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorBox.hidden = true;
        const body = Object.fromEntries(new FormData(form));
        body.is_active = form.elements.is_active.checked;
        if (!body.password) delete body.password;

        try {
            await api(customerId ? '/admin/customers/' + customerId : '/admin/customers', {
                method: customerId ? 'PATCH' : 'POST', body,
            });
            dialog.close();
            showNotice(customerId ? 'Customer updated.' : 'Customer created.');
            if (!customerId) currentPage = 1;
            await Promise.all([load(), loadSummary()]);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
        }
    });

    return load;
}
