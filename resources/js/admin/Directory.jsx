import { useEffect, useState } from 'react';
import { api, places } from './api';
import { PharmacyForm, weekdays } from './PharmacyForm';
import { Details, Field, Icon, Modal, Notice, Status } from './ui';

function CustomerForm({ customer, onClose, onSave }) {
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function submit(event) {
        event.preventDefault();
        const body = Object.fromEntries(new FormData(event.currentTarget));
        body.is_active = Boolean(body.is_active);
        if (!body.password) delete body.password;
        setBusy(true);
        setError('');
        try {
            await onSave(body);
            onClose();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <Modal title={customer ? 'Edit customer' : 'Add customer'} onClose={() => { if (!busy) onClose(); }}>
        <form className="form-grid" onSubmit={submit}>
            <div className="wide"><Notice error>{error}</Notice></div>
            <Field label="Name"><input name="name" defaultValue={customer?.name} required maxLength={150} /></Field>
            <Field label="Email"><input type="email" name="email" defaultValue={customer?.email} required maxLength={190} /></Field>
            <Field label="Password" wide><input type="password" name="password" minLength={10} required={!customer} autoComplete="new-password" placeholder={customer ? 'Leave blank to keep the current password' : undefined} /></Field>
            <label className="checkbox"><input type="checkbox" name="is_active" defaultChecked={customer?.is_active ?? true} />Active account</label>
            <footer className="form-actions wide"><button type="button" className="button secondary" onClick={onClose} disabled={busy}>Cancel</button><button className="button primary" disabled={busy}>{busy ? 'Saving…' : 'Save customer'}</button></footer>
        </form>
    </Modal>;
}

function formatHours(hours) {
    if (!hours) return null;
    if (!Array.isArray(hours)) return Object.entries(hours).map(([day, value]) => day + ' · ' + String(value)).join('\n');
    return hours.map((slot) => (weekdays.find(([day]) => day === slot.day)?.[1] ?? slot.date ?? '') +
        ' · ' + slot.opens_at + '–' + slot.closes_at).join('\n');
}

export function RecordDetails({ entity, record, onClose }) {
    const pharmacy = entity === 'pharmacies';
    const memberships = (record.memberships ?? []).map((membership) => (membership.name ?? membership.pharmacy) +
        ' · ' + membership.role + (membership.is_active ? '' : ' (inactive)')).join('\n');
    const values = pharmacy ? {
        'Arabic name': record.name_ar, License: record.license_no, Status: record.status,
        Phone: record.phone, Email: record.email, Governorate: record.governorate,
        City: record.city, Address: record.address, 'Opening hours': formatHours(record.opening_hours), Memberships: memberships,
    } : {
        Email: record.email, Status: record.is_active ? 'Active' : 'Inactive',
        Joined: new Date(record.created_at).toLocaleDateString(), 'Pharmacy memberships': memberships,
    };

    return <Modal title={pharmacy ? record.name_en : record.name} onClose={onClose}>
        <Details values={values} />
        <footer className="form-actions"><button className="button secondary" type="button" onClick={onClose}>Done</button></footer>
    </Modal>;
}

export function Directory({ entity, onChanged, onDetails }) {
    const pharmacy = entity === 'pharmacies';
    const singular = pharmacy ? 'pharmacy' : 'customer';
    const path = '/admin/' + entity;
    const [filters, setFilters] = useState({ search: '', status: '', governorate: '', city: '' });
    const [page, setPage] = useState(1);
    const [revision, setRevision] = useState(0);
    const [results, setResults] = useState(null);
    const [message, setMessage] = useState(null);
    const [editor, setEditor] = useState(undefined);
    const [deletion, setDeletion] = useState(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let current = true;
        const timer = setTimeout(async () => {
            const query = new URLSearchParams({ page });
            Object.entries(filters).forEach(([key, value]) => { if (value.trim()) query.set(key, value.trim()); });
            try {
                const response = await api(path + '?' + query);
                if (current) {
                    if (page > response.meta.last_page) setPage(response.meta.last_page);
                    else setResults(response);
                }
            } catch (error) {
                if (current) setMessage({ error: true, text: error.message });
            }
        }, 150);

        return () => { current = false; clearTimeout(timer); };
    }, [path, page, filters, revision]);

    function filter(key, value) {
        setPage(1);
        setFilters((previous) => ({ ...previous, [key]: value, ...(key === 'governorate' ? { city: '' } : {}) }));
    }

    async function save(body) {
        await api(editor ? path + '/' + editor.id : path, { method: editor ? 'PATCH' : 'POST', body });
        setMessage({ text: (pharmacy ? 'Pharmacy' : 'Customer') + (editor ? ' updated.' : ' created.') });
        if (!editor) setPage(1);
        setRevision((value) => value + 1);
        onChanged();
    }

    async function remove() {
        setBusy(true);
        try {
            await api(path + '/' + deletion.id, { method: 'DELETE' });
            setDeletion(null);
            setMessage({ text: (pharmacy ? 'Pharmacy' : 'Customer') + ' deleted.' });
            setRevision((value) => value + 1);
            onChanged();
        } catch (error) {
            setDeletion(null);
            setMessage({ error: true, text: error.message });
        } finally {
            setBusy(false);
        }
    }

    const heading = pharmacy ? 'Pharmacies' : 'Customers';
    const TableForm = pharmacy ? PharmacyForm : CustomerForm;
    return <section>
        <div className="page-heading"><div><h1>{heading}</h1><p>{pharmacy ? 'Review and manage pharmacy listings.' : 'Manage customer accounts and review pharmacy memberships.'}</p></div>
            <button type="button" className="button primary" onClick={() => setEditor(null)}><Icon name="plus" />Add {singular}</button>
        </div>
        <Notice error={message?.error}>{message?.text}</Notice>
        <div className="panel directory">
            <div className="table-filters">
                <Field label="Search"><input type="search" value={filters.search} onChange={(event) => filter('search', event.target.value)} placeholder={pharmacy ? 'Name, license, or city' : 'Name or email'} /></Field>
                <Field label="Status"><select value={filters.status} onChange={(event) => filter('status', event.target.value)}>
                    <option value="">All statuses</option>{(pharmacy ? ['pending', 'active', 'suspended'] : ['active', 'inactive']).map((status) =>
                        <option key={status} value={status}>{status[0].toUpperCase() + status.slice(1)}</option>)}</select></Field>
                {pharmacy && <>
                    <Field label="Governorate"><select value={filters.governorate} onChange={(event) => filter('governorate', event.target.value)}>
                        <option value="">All governorates</option>{Object.keys(places).map((name) => <option key={name}>{name}</option>)}</select></Field>
                    <Field label="City"><select disabled={!filters.governorate} value={filters.city} onChange={(event) => filter('city', event.target.value)}>
                        <option value="">All cities</option>{(places[filters.governorate]?.cities ?? []).map((name) => <option key={name}>{name}</option>)}</select></Field>
                </>}
            </div>
            <div className="table-scroll">
                <table>
                    <thead><tr>{(pharmacy ? ['Pharmacy', 'License', 'City', 'Status', 'Members', 'Actions'] : ['Customer', 'Email', 'Status', 'Memberships', 'Actions']).map((column) => <th key={column} scope="col">{column}</th>)}</tr></thead>
                    <tbody>
                        {results?.data.length ? results.data.map((entry) => <tr key={entry.id}>
                            <td><strong>{pharmacy ? entry.name_en : entry.name}</strong>{pharmacy && <small className="table-secondary" lang="ar">{entry.name_ar}</small>}</td>
                            <td>{pharmacy ? entry.license_no : entry.email || '—'}</td>
                            {pharmacy && <td>{entry.city}</td>}
                            <td><Status status={pharmacy ? entry.status : entry.is_active ? 'active' : 'inactive'} /></td>
                            <td>{entry.memberships_count}</td>
                            <td><div className="row-actions">
                                <button className="button small secondary" onClick={() => onDetails(entity, entry.id)}>View</button>
                                <button className="button small secondary" onClick={() => setEditor(entry)}>Edit</button>
                                <button className="button small danger" onClick={() => setDeletion(entry)}>Delete</button>
                            </div></td>
                        </tr>) : <tr><td className="empty" colSpan={pharmacy ? 6 : 5}>{results ? 'No matching ' + entity + '.' : 'Loading ' + entity + '…'}</td></tr>}
                    </tbody>
                </table>
            </div>
            {results && <footer className="pagination">
                <button className="button secondary small" onClick={() => setPage(page - 1)} disabled={page <= 1}>Previous</button>
                <span>Page {results.meta.current_page} of {results.meta.last_page}</span>
                <button className="button secondary small" onClick={() => setPage(page + 1)} disabled={page >= results.meta.last_page}>Next</button>
            </footer>}
        </div>
        {editor !== undefined && <TableForm pharmacy={editor} customer={editor} onClose={() => setEditor(undefined)} onSave={save} />}
        {deletion && <Modal title={'Delete ' + singular + '?'} onClose={() => { if (!busy) setDeletion(null); }}>
            <p>{pharmacy ? deletion.name_en + ' and its pharmacy memberships will be deleted.' : deletion.name + ' will be deleted if the account has no pharmacy memberships.'}</p>
            <footer className="form-actions"><button className="button secondary" onClick={() => setDeletion(null)} disabled={busy}>Cancel</button>
                <button className="button danger" onClick={remove} disabled={busy}>Delete {singular}</button></footer>
        </Modal>}
    </section>;
}
