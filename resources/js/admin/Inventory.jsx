import { useEffect, useState } from 'react';
import { api } from './api';
import { StockMovementForm } from './StockMovementForm';
import { Details, Field, Modal, Notice, Status } from './ui';

const endpoint = '/admin/inventory-items';

function StockDetails({ item, onClose, onChanged }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [form, setForm] = useState(null);
    const [busy, setBusy] = useState(false);
    const [revision, setRevision] = useState(0);

    useEffect(() => {
        let current = true;
        api(endpoint + '/' + item.id)
            .then((response) => { if (current) setData(response.data); })
            .catch((exception) => { if (current) setError(exception.message); });
        return () => { current = false; };
    }, [item.id, revision]);

    function saved(message) {
        setForm(null);
        setNotice(message);
        setError('');
        setRevision((value) => value + 1);
        onChanged();
    }

    function openForm(batch = null) {
        setNotice('');
        setForm({ batch });
    }

    return <Modal title={item.medicine.name_en + ' stock'} onClose={() => { if (!busy) onClose(); }} wide>
        <Notice error>{error}</Notice>
        <Notice>{notice}</Notice>
        {!data && !error && <p role="status">Loading stock batches…</p>}
        {data && <>
            <Details values={{
                Pharmacy: data.pharmacy.name_en,
                City: data.pharmacy.city,
                Strength: data.medicine.strength_text,
                Price: data.selling_price + ' ' + data.medicine.currency,
                Listing: data.is_listed ? 'Listed' : 'Unlisted',
                'Available packages': String(data.available_quantity),
                'Last stock verification': data.stock_verified_at ? new Date(data.stock_verified_at).toLocaleString() : null,
            }} />
            {form ? <StockMovementForm key={form.batch?.id ?? 'receipt'} item={item} batch={form.batch}
                onCancel={() => setForm(null)} onSaved={saved} onBusyChange={setBusy} />
                : <button className="button primary" type="button" onClick={() => openForm()}>Receive stock</button>}
            <h3>Stock batches</h3>
            <div className="table-scroll"><table>
                <thead><tr>{['Batch', 'Expiry date', 'Status', 'On hand', 'Held', 'Available', 'Actions'].map((heading) =>
                    <th key={heading} scope="col">{heading}</th>)}</tr></thead>
                <tbody>{data.batches.length ? data.batches.map((batch) => <tr key={batch.id}>
                    <td>{batch.batch_no}</td><td>{batch.expiry_date}</td><td><Status status={batch.status} /></td>
                    <td>{batch.quantity_on_hand}</td><td>{batch.quantity_held}</td><td>{batch.available_quantity}</td>
                    <td><button className="button secondary small" type="button" disabled={busy}
                        aria-label={'Adjust ' + batch.batch_no} onClick={() => openForm(batch)}>Adjust</button></td>
                </tr>) : <tr><td className="empty" colSpan="7">No stock batches have been recorded.</td></tr>}</tbody>
            </table></div>
            <h3>Recent stock movements</h3>
            <div className="table-scroll"><table>
                <thead><tr>{['When', 'Batch', 'Movement', 'Change', 'Reason'].map((heading) =>
                    <th key={heading} scope="col">{heading}</th>)}</tr></thead>
                <tbody>{data.movements.length ? data.movements.map((movement) => <tr key={movement.id}>
                    <td>{new Date(movement.created_at).toLocaleString()}</td><td>{movement.batch_no}</td>
                    <td>{movement.type}</td><td>{movement.quantity > 0 ? '+' : ''}{movement.quantity}</td>
                    <td>{movement.reason || '—'}</td>
                </tr>) : <tr><td className="empty" colSpan="5">No stock movements have been recorded.</td></tr>}</tbody>
            </table></div>
        </>}
        <footer className="form-actions"><button className="button secondary" type="button" disabled={busy} onClick={onClose}>Done</button></footer>
    </Modal>;
}

export function Inventory() {
    const [pharmacies, setPharmacies] = useState(null);
    const [pharmacyError, setPharmacyError] = useState('');
    const [filter, setFilter] = useState({ pharmacy: '', search: '', page: 1 });
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    const [selected, setSelected] = useState(null);
    const [revision, setRevision] = useState(0);

    useEffect(() => {
        let current = true;
        api('/admin/pharmacy-sites')
            .then(({ data }) => { if (current) setPharmacies(data); })
            .catch((exception) => { if (current) setPharmacyError('Could not load the pharmacy filter. ' + exception.message); });
        return () => { current = false; };
    }, []);

    useEffect(() => {
        let current = true;
        const timer = setTimeout(async () => {
            const params = new URLSearchParams({ page: filter.page });
            if (filter.pharmacy) params.set('pharmacy_id', filter.pharmacy);
            if (filter.search.trim()) params.set('search', filter.search.trim());

            try {
                const response = await api(endpoint + '?' + params);
                if (current) setResult(response);
            } catch (exception) {
                if (current) setError(exception.message);
            } finally {
                if (current) setLoading(false);
            }
        }, 150);
        return () => { current = false; clearTimeout(timer); };
    }, [filter, revision]);

    function changeFilter(changes) {
        setLoading(true);
        setError('');
        setFilter((previous) => ({ ...previous, page: 1, ...changes }));
    }

    const rows = result?.data ?? [];
    let emptyText = 'No stock listings match these filters.';
    if (loading) emptyText = 'Loading inventory…';
    else if (error) emptyText = 'Inventory results are unavailable.';

    return <section aria-labelledby="inventory-heading">
        <div className="page-heading"><div><h1 id="inventory-heading">Inventory</h1>
            <p>Browse pharmacy stock. Availability counts active, unexpired batches after held quantities.</p></div></div>
        <Notice error>{error}</Notice><Notice error>{pharmacyError}</Notice>
        <div className="panel directory" aria-busy={loading}>
            <div className="table-filters">
                <Field label="Pharmacy"><select value={filter.pharmacy} disabled={!pharmacies}
                    onChange={(event) => changeFilter({ pharmacy: event.target.value })}>
                    <option value="">All pharmacies</option>
                    {(pharmacies ?? []).map((pharmacy) => <option key={pharmacy.id} value={pharmacy.id}>
                        {pharmacy.name_en} · {pharmacy.city}
                    </option>)}
                </select></Field>
                <Field label="Search inventory"><input type="search" value={filter.search} maxLength={150}
                    onChange={(event) => changeFilter({ search: event.target.value })}
                    placeholder="Pharmacy, medicine, or batch number" /></Field>
            </div>
            <div className="table-scroll"><table>
                <thead><tr>{['Pharmacy', 'Medicine', 'Selling price', 'Listing', 'Available', 'Batches', 'Details'].map((heading) =>
                    <th key={heading} scope="col">{heading}</th>)}</tr></thead>
                <tbody>{!loading && !error && rows.length ? rows.map((item) => <tr key={item.id}>
                    <td><strong>{item.pharmacy.name_en}</strong><small className="table-secondary">{item.pharmacy.city}</small></td>
                    <td><strong>{item.medicine.name_en}</strong><small className="table-secondary">{item.medicine.strength_text}</small></td>
                    <td>{item.selling_price} {item.medicine.currency}</td>
                    <td><Status status={item.is_listed ? 'listed' : 'unlisted'} /></td>
                    <td>{item.available_quantity}</td><td>{item.batches_count}</td>
                    <td><button className="button small secondary" type="button" onClick={() => setSelected(item)}
                        aria-label={'View batches for ' + item.medicine.name_en + ' at ' + item.pharmacy.name_en}>View batches</button></td>
                </tr>) : <tr><td className="empty" colSpan="7">{emptyText}</td></tr>}</tbody>
            </table></div>
            {!loading && !error && result?.meta && <footer className="pagination">
                <button className="button secondary small" onClick={() => changeFilter({ page: filter.page - 1 })} disabled={filter.page <= 1}>Previous</button>
                <span>Page {result.meta.current_page} of {result.meta.last_page} · {result.meta.total} {result.meta.total === 1 ? 'listing' : 'listings'}</span>
                <button className="button secondary small" onClick={() => changeFilter({ page: filter.page + 1 })} disabled={filter.page >= result.meta.last_page}>Next</button>
            </footer>}
        </div>
        {selected && <StockDetails item={selected} onClose={() => setSelected(null)}
            onChanged={() => setRevision((value) => value + 1)} />}
    </section>;
}
