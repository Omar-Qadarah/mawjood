import { setupCustomers } from './admin-customers.js';
import { openPharmacyEditor, pharmacyPayload, populateCities, populateGovernors, showPharmacyError } from './admin-pharmacy-form.js';

const root = document.querySelector('#admin-app');

if (root) {
    const apiBase = root.dataset.apiBase;
    const loginPanel = document.querySelector('#login-panel');
    const workspace = document.querySelector('#workspace');
    const navigation = [...document.querySelectorAll('[data-admin-page]')];
    const governorateFilter = document.querySelector('#governorate-filter');
    const cityFilter = document.querySelector('#city-filter');
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
    let pharmacyRequest = 0;
    const loadCustomers = setupCustomers(api, showNotice, loadSummary, textCell, showDetails);
    populateGovernors(governorateFilter, 'All governorates');

    function showPage() {
        if (workspace.hidden) return;

        const requested = window.location.hash.slice(1);
        const page = navigation.some((link) => link.dataset.adminPage === requested) ? requested : 'overview';
        for (const link of navigation) {
            const selected = link.dataset.adminPage === page;
            document.getElementById(link.dataset.adminPage).hidden = !selected;
            if (selected) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        }
        if (page === 'pharmacies') loadPharmacies();
        if (page === 'customers') loadCustomers();
    }

    window.addEventListener('hashchange', showPage);

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
        workspace.hidden = true;
        pharmacyRequest++;
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
        workspace.hidden = false;
        logoutButton.hidden = false;
        showNotice('');
        showPage();
        await loadSummary();
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

            document.querySelector('#summary-customers').textContent = summary.customers_count;
            document.querySelector('#summary-owners').textContent = summary.owners_count;
            renderRecent('#recent-pharmacies', summary.recent_pharmacies, 'created_at', 'No pharmacies have been added yet.');
            renderRecent('#recent-activity', summary.recent_activity, 'updated_at', 'No pharmacy updates yet.');
            summaryData.hidden = false;
            message.hidden = true;
        } catch {
            if (request !== summaryRequest) return;

            summaryData.hidden = true;
            message.textContent = 'The pharmacy overview could not be loaded.';
            message.hidden = false;
        }
    }

    function renderRecent(selector, pharmacies, dateField, emptyMessage) {
        const entries = pharmacies.map((pharmacy) => {
            const item = document.createElement('li');
            const name = document.createElement('strong');
            name.textContent = pharmacy.name_en;
            const detail = document.createElement('span');
            detail.textContent = new Date(pharmacy[dateField]).toLocaleString() + ' · ' + pharmacy.status;
            item.append(name, detail);
            return item;
        });
        if (!entries.length) {
            const empty = document.createElement('li');
            empty.textContent = emptyMessage;
            entries.push(empty);
        }
        document.querySelector(selector).replaceChildren(...entries);
    }

    async function loadPharmacies() {
        const request = ++pharmacyRequest;
        const params = new URLSearchParams({ page: String(currentPage) });
        if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
        if (statusFilter.value) params.set('status', statusFilter.value);
        if (governorateFilter.value) params.set('governorate', governorateFilter.value);
        if (cityFilter.value) params.set('city', cityFilter.value);

        try {
            const result = await api(`/admin/pharmacies?${params}`);
            if (request !== pharmacyRequest) return;

            if (currentPage > result.meta.last_page) {
                currentPage = result.meta.last_page;
                return loadPharmacies();
            }
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
            edit.addEventListener('click', () => openPharmacyEditor(pharmacy));
            const view = document.createElement('button');
            view.type = 'button';
            view.className = 'button button-small button-secondary';
            view.textContent = 'View';
            view.addEventListener('click', () => viewPharmacy(pharmacy.id));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button button-small button-danger';
            remove.textContent = 'Delete';
            remove.addEventListener('click', () => deletePharmacy(pharmacy));
            actions.append(view, edit, remove);
            row.append(actions);
            rows.append(row);
        });
    }

    function textCell(value) {
        const cell = document.createElement('td');
        cell.textContent = value || '—';
        return cell;
    }

    function showDetails(entity, heading, fields) {
        const list = document.querySelector('#' + entity + '-details-fields');
        list.replaceChildren();
        document.querySelector('#' + entity + '-details').querySelector('h2').textContent = heading;
        for (const [label, value] of Object.entries(fields)) {
            const row = document.createElement('div');
            const term = document.createElement('dt');
            const description = document.createElement('dd');
            term.textContent = label;
            description.textContent = value || '—';
            description.style.whiteSpace = 'pre-line';
            row.append(term, description);
            list.append(row);
        }
        document.querySelector('#' + entity + '-details').showModal();
    }

    async function viewPharmacy(id) {
        try {
            const { data } = await api('/admin/pharmacies/' + id);
            const hours = Object.entries(data.opening_hours ?? {}).map(([key, slot]) =>
                slot?.date ? slot.date + ' · ' + slot.opens_at + '–' + slot.closes_at : key + ' · ' + String(slot)
            );
            showDetails('pharmacy', data.name_en, {
                'Arabic name': data.name_ar,
                License: data.license_no,
                Status: data.status,
                Phone: data.phone,
                Email: data.email,
                Governorate: data.governorate,
                City: data.city,
                Address: data.address,
                'Opening hours': hours.join('\n'),
                Memberships: data.memberships.map(({ name, role, is_active }) =>
                    name + ' · ' + role + (is_active ? '' : ' (inactive)')
                ).join('\n'),
            });
        } catch (error) {
            showNotice(error.message, true);
        }
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
            const csrf = await fetch(root.dataset.csrfUrl, { credentials: 'same-origin' });
            if (!csrf.ok) throw new Error('Could not start the sign-in session.');
            const data = new FormData(loginForm);
            const result = await api('/auth/login', {
                method: 'POST',
                body: { email: data.get('email'), password: data.get('password'), remember: data.has('remember') },
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

    document.querySelector('#create-button').addEventListener('click', () => openPharmacyEditor());
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
    function filterPharmacies() {
        currentPage = 1;
        loadPharmacies();
    }

    governorateFilter.addEventListener('change', () => {
        populateCities(cityFilter, governorateFilter.value, 'All cities');
        filterPharmacies();
    });
    cityFilter.addEventListener('change', filterPharmacies);
    statusFilter.addEventListener('change', filterPharmacies);

    pharmacyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        showPharmacyError();
        const id = pharmacyForm.elements.id.value;

        try {
            await api(id ? `/admin/pharmacies/${id}` : '/admin/pharmacies', {
                method: id ? 'PATCH' : 'POST',
                body: pharmacyPayload(),
            });
            pharmacyDialog.close();
            showNotice(id ? 'Pharmacy updated.' : 'Pharmacy created.');
            currentPage = 1;
            await Promise.all([loadPharmacies(), loadSummary()]);
        } catch (error) {
            showPharmacyError(error.message);
        }
    });

    api('/auth/me')
        .then((result) => result.data.platform_role === 'admin'
            ? showPharmacies()
            : showLogin('This account does not have administrator access.'))
        .catch(() => showLogin());
}
