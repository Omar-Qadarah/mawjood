const root = document.querySelector('#admin-app');

if (root) {
    const apiBase = root.dataset.apiBase;
    const loginPanel = document.querySelector('#login-panel');
    const pharmacyPanel = document.querySelector('#pharmacy-panel');
    const notice = document.querySelector('#notice');
    const logoutButton = document.querySelector('#logout-button');
    const loginForm = document.querySelector('#login-form');
    const pharmacyForm = document.querySelector('#pharmacy-form');
    const pharmacyDialog = document.querySelector('#pharmacy-dialog');
    const rows = document.querySelector('#pharmacy-rows');
    const searchInput = document.querySelector('#search-input');
    const statusFilter = document.querySelector('#status-filter');
    const previousButton = document.querySelector('#previous-page');
    const nextButton = document.querySelector('#next-page');
    let currentPage = 1;
    let lastPage = 1;
    let searchTimer;
    let summaryRequest = 0;

    function showNotice(message, isError = false) {
        notice.textContent = message;
        notice.classList.toggle('notice-error', isError);
        notice.hidden = !message;
    }

    function csrfToken() {
        const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));
        return cookie ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length)) : '';
    }

    async function api(path, options = {}) {
        const headers = {
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            ...options.headers,
        };
        if (options.body && typeof options.body !== 'string') {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const response = await fetch(`${apiBase}${path}`, {
            credentials: 'same-origin',
            ...options,
            headers,
        });
        const payload = response.status === 204 ? null : await response.json().catch(() => null);

        if (!response.ok) {
            const validationMessages = payload?.errors
                ? Object.values(payload.errors).flat().join(' ')
                : '';
            throw new Error(validationMessages || payload?.message || 'The request could not be completed.');
        }

        return payload;
    }

    function showLogin(message = '') {
        loginPanel.hidden = false;
        pharmacyPanel.hidden = true;
        summaryRequest++;
        document.querySelector('#summary-data').hidden = true;
        const summaryMessage = document.querySelector('#summary-message');
        summaryMessage.textContent = 'Loading overview…';
        summaryMessage.hidden = false;
        logoutButton.hidden = true;
        if (message) showNotice(message, true);
    }

    async function showPharmacies() {
        loginPanel.hidden = true;
        pharmacyPanel.hidden = false;
        logoutButton.hidden = false;
        showNotice('');
        await Promise.all([loadPharmacies(), loadSummary()]);
    }

    async function loadSummary() {
        const request = ++summaryRequest;
        const message = document.querySelector('#summary-message');
        const summaryData = document.querySelector('#summary-data');
        try {
            const summary = await api('/admin/dashboard/summary');
            if (request !== summaryRequest) return;

            for (const element of summaryData.querySelectorAll('[data-summary-count]')) {
                element.textContent = summary.counts[element.dataset.summaryCount];
            }

            const entries = summary.recent_pharmacies.map((pharmacy) => {
                const item = document.createElement('li');
                const name = document.createElement('strong');
                name.textContent = pharmacy.name_en;
                const detail = document.createElement('span');
                detail.textContent = new Date(pharmacy.created_at).toLocaleDateString() + ' · ' + pharmacy.status;
                item.append(name, detail);
                return item;
            });

            if (!entries.length) {
                const empty = document.createElement('li');
                empty.textContent = 'No pharmacies have been added yet.';
                entries.push(empty);
            }

            document.querySelector('#recent-pharmacies').replaceChildren(...entries);
            summaryData.hidden = false;
            message.hidden = true;
        } catch {
            if (request !== summaryRequest) return;

            summaryData.hidden = true;
            message.textContent = 'The pharmacy overview could not be loaded.';
            message.hidden = false;
        }
    }

    async function loadPharmacies() {
        const params = new URLSearchParams({ page: String(currentPage) });
        if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
        if (statusFilter.value) params.set('status', statusFilter.value);

        try {
            const result = await api(`/admin/pharmacies?${params}`);
            renderRows(result.data);
            lastPage = result.meta.last_page;
            document.querySelector('#page-label').textContent = `Page ${result.meta.current_page} of ${lastPage}`;
            previousButton.disabled = currentPage <= 1;
            nextButton.disabled = currentPage >= lastPage;
        } catch (error) {
            showNotice(error.message, true);
        }
    }

    function renderRows(pharmacies) {
        rows.replaceChildren();
        if (!pharmacies.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.className = 'empty-state';
            cell.textContent = 'No pharmacies match these filters.';
            row.append(cell);
            rows.append(row);
            return;
        }

        pharmacies.forEach((pharmacy) => {
            const row = document.createElement('tr');
            const name = document.createElement('td');
            const primaryName = document.createElement('strong');
            const secondaryName = document.createElement('span');
            primaryName.textContent = pharmacy.name_en;
            secondaryName.textContent = pharmacy.name_ar;
            secondaryName.className = 'secondary-text';
            name.append(primaryName, secondaryName);
            row.append(name);
            row.append(textCell(pharmacy.license_no));
            row.append(textCell(pharmacy.city));

            const statusCell = document.createElement('td');
            const status = document.createElement('span');
            status.className = `status-badge status-${pharmacy.status}`;
            status.textContent = pharmacy.status;
            statusCell.append(status);
            row.append(statusCell, textCell(String(pharmacy.memberships_count ?? 0)));

            const actions = document.createElement('td');
            actions.className = 'row-actions';
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'button button-small button-secondary';
            edit.textContent = 'Edit';
            edit.addEventListener('click', () => openEditor(pharmacy));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button button-small button-danger';
            remove.textContent = 'Delete';
            remove.addEventListener('click', () => deletePharmacy(pharmacy));
            actions.append(edit, remove);
            row.append(actions);
            rows.append(row);
        });
    }

    function textCell(value) {
        const cell = document.createElement('td');
        cell.textContent = value || '—';
        return cell;
    }

    function openEditor(pharmacy = null) {
        pharmacyForm.reset();
        pharmacyForm.elements.id.value = pharmacy?.id ?? '';
        document.querySelector('#form-title').textContent = pharmacy ? 'Edit pharmacy' : 'Add pharmacy';
        document.querySelector('#save-pharmacy').textContent = pharmacy ? 'Save changes' : 'Save pharmacy';

        if (pharmacy) {
            for (const field of ['name_ar', 'name_en', 'license_no', 'phone', 'email', 'city', 'address', 'latitude', 'longitude', 'status']) {
                pharmacyForm.elements[field].value = pharmacy[field] ?? '';
            }
            pharmacyForm.elements.opening_hours.value = pharmacy.opening_hours
                ? JSON.stringify(pharmacy.opening_hours, null, 2)
                : '';
        }

        pharmacyDialog.showModal();
    }

    async function deletePharmacy(pharmacy) {
        if (!window.confirm(`Delete ${pharmacy.name_en} and its ${pharmacy.memberships_count} membership(s)?`)) return;

        try {
            await api(`/admin/pharmacies/${pharmacy.id}`, { method: 'DELETE' });
            showNotice('Pharmacy deleted.');
            await Promise.all([loadPharmacies(), loadSummary()]);
        } catch (error) {
            showNotice(error.message, true);
        }
    }

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showNotice('');
        try {
            await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
            const data = new FormData(loginForm);
            const result = await api('/auth/login', {
                method: 'POST',
                body: { email: data.get('email'), password: data.get('password') },
            });
            if (result.data.platform_role !== 'admin') {
                await api('/auth/logout', { method: 'POST' }).catch(() => {});
                showLogin('This account does not have administrator access.');
                return;
            }
            loginForm.reset();
            await showPharmacies();
        } catch (error) {
            showLogin(error.message);
        }
    });

    logoutButton.addEventListener('click', async () => {
        try {
            await api('/auth/logout', { method: 'POST' });
            showLogin('You have been signed out.');
        } catch (error) {
            showNotice(error.message, true);
        }
    });

    document.querySelector('#create-button').addEventListener('click', () => openEditor());
    document.querySelector('#close-dialog').addEventListener('click', () => pharmacyDialog.close());
    document.querySelector('#cancel-dialog').addEventListener('click', () => pharmacyDialog.close());
    previousButton.addEventListener('click', () => {
        currentPage = Math.max(1, currentPage - 1);
        loadPharmacies();
    });
    nextButton.addEventListener('click', () => {
        currentPage = Math.min(lastPage, currentPage + 1);
        loadPharmacies();
    });
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentPage = 1;
            loadPharmacies();
        }, 250);
    });
    statusFilter.addEventListener('change', () => {
        currentPage = 1;
        loadPharmacies();
    });

    pharmacyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(pharmacyForm);
        let openingHours = null;
        if (formData.get('opening_hours').trim()) {
            try {
                openingHours = JSON.parse(formData.get('opening_hours'));
                if (!openingHours || typeof openingHours !== 'object' || Array.isArray(openingHours)) {
                    throw new Error('Opening hours must be a JSON object.');
                }
            } catch (error) {
                showNotice(error.message || 'Opening hours must contain valid JSON.', true);
                return;
            }
        }

        const id = formData.get('id');
        const payload = Object.fromEntries(
            ['name_ar', 'name_en', 'license_no', 'phone', 'email', 'city', 'address', 'latitude', 'longitude', 'status']
                .map((name) => [name, formData.get(name)])
        );
        payload.opening_hours = openingHours;

        try {
            await api(id ? `/admin/pharmacies/${id}` : '/admin/pharmacies', {
                method: id ? 'PATCH' : 'POST',
                body: payload,
            });
            pharmacyDialog.close();
            showNotice(id ? 'Pharmacy updated.' : 'Pharmacy created.');
            currentPage = 1;
            await Promise.all([loadPharmacies(), loadSummary()]);
        } catch (error) {
            showNotice(error.message, true);
        }
    });

    api('/auth/me')
        .then((result) => result.data.platform_role === 'admin'
            ? showPharmacies()
            : showLogin('This account does not have administrator access.'))
        .catch(() => showLogin());
}
