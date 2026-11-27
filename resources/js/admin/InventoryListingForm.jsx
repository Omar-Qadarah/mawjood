import { useEffect, useState } from 'react';
import { api } from './api';
import { Field, Modal, Notice } from './ui';

export function InventoryListingForm({ item, pharmacies, pharmacy, onClose, onSaved }) {
    const [search, setSearch] = useState('');
    const [medicines, setMedicines] = useState(null);
    const [lookupError, setLookupError] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState(false);

    useEffect(() => {
        if (item) return;
        let current = true;
        const timer = setTimeout(() => {
            api('/admin/medicines?' + new URLSearchParams({ search }))
                .then(({ data }) => { if (current) setMedicines(data); })
                .catch((exception) => { if (current) setLookupError(exception.message); });
        }, 150);
        return () => { current = false; clearTimeout(timer); };
    }, [search, item]);

    async function save(event) {
        event.preventDefault();
        setBusy(true);
        setError('');
        const form = new FormData(event.currentTarget);
        const endpoint = '/admin/inventory-items' + (item ? '/' + item.id : '');
        try {
            if (removing) {
                await api(endpoint, { method: 'DELETE' });
                onSaved('Stock listing deleted.');
                return;
            }
            const body = { selling_price: form.get('selling_price'), is_listed: form.has('is_listed') };
            if (!item) {
                body.pharmacy_id = form.get('pharmacy_id');
                body.medicine_id = form.get('medicine_id');
            }
            await api(endpoint, { method: item ? 'PATCH' : 'POST', body });
            onSaved(item ? 'Stock listing updated.' : 'Stock listing created.');
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <Modal title={item ? 'Edit stock listing' : 'Add stock listing'} onClose={() => { if (!busy) onClose(); }}>
        <Notice error>{error}</Notice><Notice error>{lookupError}</Notice>
        <form aria-label="Stock listing" onSubmit={save}>
            {removing ? <p>Delete this stock listing? Listings with stock or reservation history can be unlisted instead.</p> :
                <div className="form-grid">
                    {item ? <p className="wide">{item.medicine.name_en} · {item.pharmacy.name_en}</p> : <>
                        <Field label="Pharmacy" wide><select name="pharmacy_id" defaultValue={pharmacy} required disabled={busy}>
                            <option value="">Choose a pharmacy</option>
                            {pharmacies.map((site) => <option key={site.id} value={site.id}>{site.name_en} · {site.city}</option>)}
                        </select></Field>
                        <Field label="Find a medicine" wide><input type="search" value={search} maxLength={150} disabled={busy}
                            onChange={(event) => {
                                setMedicines(null);
                                setLookupError('');
                                setSearch(event.target.value);
                            }} /></Field>
                        <Field label="Medicine package" wide><select name="medicine_id" required disabled={busy || !medicines?.length}>
                            <option value="">{medicines ? (medicines.length ? 'Choose a package' : 'No packages found') : 'Loading packages…'}</option>
                            {medicines?.map((medicine) => <option key={medicine.id} value={medicine.id}>
                                {medicine.name_en} · {medicine.strength_text}
                            </option>)}
                        </select></Field>
                    </>}
                    <Field label={'Selling price (' + (item?.medicine.currency ?? 'JOD') + ')'}>
                        <input name="selling_price" type="number" min="0" max="9999999.999" step="0.001"
                            defaultValue={item?.selling_price ?? ''} required disabled={busy} />
                    </Field>
                    <label className="checkbox"><input name="is_listed" type="checkbox" defaultChecked={item?.is_listed ?? true} disabled={busy} /> Listed</label>
                </div>}
            <footer className="form-actions">
                {item && !removing && <button className="button danger" type="button" disabled={busy}
                    onClick={() => { setError(''); setRemoving(true); }}>Delete listing</button>}
                <button className="button secondary" type="button" disabled={busy}
                    onClick={() => { if (removing) { setError(''); setRemoving(false); } else onClose(); }}>{removing ? 'Back' : 'Cancel'}</button>
                <button className={'button ' + (removing ? 'danger' : 'primary')}
                    disabled={busy || (!item && !medicines?.length)}>{removing ? 'Delete stock listing' : 'Save listing'}</button>
            </footer>
        </form>
    </Modal>;
}
