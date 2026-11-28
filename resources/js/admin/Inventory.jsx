import { sortRows, TableHead, useTableSort } from './TableSort';
import { useEffect, useState } from 'react';
import { api } from './api';
import { ExpiringBatchesTable } from './ExpiringBatchesTable';
import { InventoryListingForm } from './InventoryListingForm';
import { StockMovementForm } from './StockMovementForm';
import { useTableLoader } from './useTableLoader';
import { TableFilters } from './TableFilters';
import { Details, Field, Modal, Notice, Status, TableAction } from './ui';

const endpoint = '/admin/inventory-items';
const expiringEndpoint = '/admin/inventory-batches/expiring';

function StockDetails({ item, onClose, onChanged }) {
    const batchSort = useTableSort('expiry_date');
    const movementSort = useTableSort();
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
                <TableHead columns={[["Batch","batch_no"],["Expiry date","expiry_date"],["Status","status"],["On hand","quantity_on_hand"],["Held","quantity_held"],["Available","available_quantity"],["Actions"]]} sort={batchSort} />
                <tbody>{data.batches.length ? sortRows(data.batches, batchSort, (row, key) => row[key]).map((batch) => <tr key={batch.id}>
                    <td>{batch.batch_no}</td><td>{batch.expiry_date}</td><td><Status status={batch.status} /></td>
                    <td>{batch.quantity_on_hand}</td><td>{batch.quantity_held}</td><td>{batch.available_quantity}</td>
                    <td><TableAction icon="adjust" disabled={busy}
                        label={'Adjust ' + batch.batch_no} onClick={() => openForm(batch)} /></td>
                </tr>) : <tr><td className="empty" colSpan="7">No stock batches have been recorded.</td></tr>}</tbody>
            </table></div>
            <h3>Recent stock movements</h3>
            <div className="table-scroll"><table>
                <TableHead columns={[["When","created_at"],["Batch","batch_no"],["Movement","type"],["Change","quantity"],["Reason","reason"]]} sort={movementSort} />
                <tbody>{data.movements.length ? sortRows(data.movements, movementSort, (row, key) => row[key]).map((movement) => <tr key={movement.id}>
                    <td>{new Date(movement.created_at).toLocaleString()}</td><td>{movement.batch_no}</td>
                    <td>{movement.type}</td><td>{movement.quantity > 0 ? '+' : ''}{movement.quantity}</td>
                    <td>{movement.reason || '—'}</td>
                </tr>) : <tr><td className="empty" colSpan="5">No stock movements have been recorded.</td></tr>}</tbody>
            </table></div>
        </>}
        <footer className="form-actions"><button className="button secondary" type="button" disabled={busy} onClick={onClose}>Done</button></footer>
    </Modal>;
}

export function Inventory({ initialStock = '', expiring = false, onClearRoute }) {
    const sort = useTableSort(expiring ? 'expiry' : undefined);
    const [pharmacies, setPharmacies] = useState(null);
    const [pharmacyError, setPharmacyError] = useState('');
    const [filter, setFilter] = useState({ pharmacy: '', search: '', listing: '', stock: initialStock, page: 1 });
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    const [selected, setSelected] = useState(null);
    const [listingForm, setListingForm] = useState(null);
    const [notice, setNotice] = useState('');
    const [revision, setRevision] = useState(0);

    useEffect(() => {
        let current = true;
        api('/admin/pharmacy-sites')
            .then(({ data }) => { if (current) setPharmacies(data); })
            .catch((exception) => { if (current) setPharmacyError('Could not load the pharmacy filter. ' + exception.message); });
        return () => { current = false; };
    }, []);

    const loadTable = useTableLoader();
    useEffect(() => {
        let current = true;
        const cancel = loadTable(filter.search, async () => {
            const params = new URLSearchParams({ page: filter.page, ...sort.params });
            if (filter.pharmacy) params.set('pharmacy_id', filter.pharmacy);
            if (filter.listing) params.set('listing', filter.listing);
            if (filter.stock) params.set('stock', filter.stock);
            if (filter.search.trim()) params.set('search', filter.search.trim());

            try {
                const response = await api((expiring ? expiringEndpoint : endpoint) + '?' + params);
                if (current) setResult(response);
            } catch (exception) {
                if (current) setError(exception.message);
            } finally {
                if (current) setLoading(false);
            }
        });
        return () => { current = false; cancel?.(); };
    }, [expiring, loadTable, filter, revision, sort.column, sort.direction, sort.params.sort]);

    function changeFilter(changes) {
        setLoading(true);
        setError('');
        setFilter((previous) => ({ ...previous, page: 1, ...changes }));
    }

    const rows = result?.data ?? [];
    let emptyText = expiring ? 'No expiring batches match these filters.' : 'No stock listings match these filters.';
    if (loading) emptyText = 'Loading inventory…';
    else if (error) emptyText = 'Inventory results are unavailable.';

    return <section aria-labelledby="inventory-heading">
        <div className="page-heading"><div><h1 id="inventory-heading">Inventory</h1>
            <p>{expiring ? 'Batches with stock on hand expiring within 30 days.' : 'Browse pharmacy stock. Availability counts active, unexpired batches after held quantities.'}</p></div>
            {expiring ? <a className="button secondary" href="#inventory">All listings</a>
                : <button className="button primary" type="button" disabled={!pharmacies?.length} onClick={() => setListingForm({})}>Add stock listing</button>}
        </div>
        <Notice>{notice}</Notice>
        <Notice error>{error}</Notice><Notice error>{pharmacyError}</Notice>
        <div className="panel directory" aria-busy={loading}>
            <TableFilters label="Search inventory" search={filter.search} onSearch={(search) => changeFilter({ search })}
                onClear={() => {
                    changeFilter({ search: '', pharmacy: '', listing: '', stock: '' });
                    onClearRoute();
                }}
                placeholder="Pharmacy, medicine, or batch number">
                <Field label="Pharmacy"><select value={filter.pharmacy} disabled={!pharmacies}
                    onChange={(event) => changeFilter({ pharmacy: event.target.value })}>
                    <option value="">All pharmacies</option>
                    {(pharmacies ?? []).map((pharmacy) => <option key={pharmacy.id} value={pharmacy.id}>
                        {pharmacy.name_en} · {pharmacy.city}
                    </option>)}
                </select></Field>
                <Field label="Listing"><select value={filter.listing} onChange={(event) => changeFilter({ listing: event.target.value })}>
                    <option value="">All listings</option>
                    <option value="listed">Listed</option>
                    <option value="unlisted">Unlisted</option>
                </select></Field>
                {!expiring && <Field label="Stock level"><select value={filter.stock} onChange={event => changeFilter({ stock: event.target.value })}>
                    <option value="">All stock levels</option>
                    <option value="low">Low stock</option>
                </select></Field>}
            </TableFilters>
            {expiring ? <ExpiringBatchesTable batches={loading || error ? [] : rows} emptyText={emptyText} onDetails={setSelected} sort={sort} onSort={() => changeFilter({})} />
                : <div className="table-scroll"><table>
                <TableHead columns={[["Pharmacy","pharmacy"],["Medicine","medicine"],["Selling price","price"],["Listing","listing"],["Available","available"],["Batches","batches"],["Details"]]} sort={sort} onSort={() => changeFilter({})} />
                <tbody>{!loading && !error && rows.length ? rows.map((item) => <tr key={item.id}>
                    <td><strong>{item.pharmacy.name_en}</strong><small className="table-secondary">{item.pharmacy.city}</small></td>
                    <td><strong>{item.medicine.name_en}</strong><small className="table-secondary">{item.medicine.strength_text}</small></td>
                    <td>{item.selling_price} {item.medicine.currency}</td>
                    <td><Status status={item.is_listed ? 'listed' : 'unlisted'} /></td>
                    <td>{item.available_quantity}</td><td>{item.batches_count}</td>
                    <td><div className="row-actions"><TableAction icon="inventory" onClick={() => setSelected(item)}
                        label={'View batches for ' + item.medicine.name_en + ' at ' + item.pharmacy.name_en} />
                        <TableAction icon="edit" onClick={() => setListingForm({ item })}
                            label={'Edit listing for ' + item.medicine.name_en + ' at ' + item.pharmacy.name_en} />
                    </div></td>
                </tr>) : <tr><td className="empty" colSpan="7">{emptyText}</td></tr>}</tbody>
            </table></div>}
            {!loading && !error && result?.meta && <footer className="pagination">
                <button className="button secondary small" onClick={() => changeFilter({ page: filter.page - 1 })} disabled={filter.page <= 1}>Previous</button>
                <span>Page {result.meta.current_page} of {result.meta.last_page} · {result.meta.total} {expiring ? (result.meta.total === 1 ? 'batch' : 'batches') : (result.meta.total === 1 ? 'listing' : 'listings')}</span>
                <button className="button secondary small" onClick={() => changeFilter({ page: filter.page + 1 })} disabled={filter.page >= result.meta.last_page}>Next</button>
            </footer>}
        </div>
        {listingForm && <InventoryListingForm item={listingForm.item} pharmacies={pharmacies ?? []} pharmacy={filter.pharmacy}
            onClose={() => setListingForm(null)} onSaved={(message) => {
                setListingForm(null);
                setNotice(message);
                changeFilter({});
            }} />}
        {selected && <StockDetails item={selected} onClose={() => setSelected(null)}
            onChanged={() => setRevision((value) => value + 1)} />}
    </section>;
}
