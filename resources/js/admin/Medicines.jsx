import { ResetSortButton, sortRows, TableHead, useTableSort } from './TableSort';
import { useEffect, useState } from 'react';
import { api } from './api';
import { IngredientManager } from './IngredientManager';
import { MedicineEditor } from './MedicineEditor';
import { useTableLoader } from './useTableLoader';
import { useSavedTableFilters } from './useSavedTableFilters';
import { TableFilters } from './TableFilters';
import { Details, Field, Modal, Notice, RowActions, Status, TableAction } from './ui';

const endpoint = '/admin/medicines';
const numberText = (value) => value == null ? '' : String(value).replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '');
const packageSize = (medicine) => numberText(medicine.pack_size) + ' ' + medicine.pack_unit;

function unitBasis(component) {
    if (component.per_value == null && !component.per_unit) return '—';

    return [numberText(component.per_value), component.per_unit].filter(Boolean).join(' ');
}

export function MedicineDetails({ summary, onClose }) {
    const ingredientSort = useTableSort('medicine-ingredients');
    const [medicine, setMedicine] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let current = true;
        api(endpoint + '/' + summary.id)
            .then(({ data }) => { if (current) setMedicine(data); })
            .catch((exception) => { if (current) setError(exception.message); });
        return () => { current = false; };
    }, [summary.id]);

    return <Modal title={summary.name_en} onClose={onClose} wide>
        <Notice error>{error}</Notice>
        {!medicine && !error && <p role="status">Loading medicine details…</p>}
        {medicine && <>
            <Details values={{
                'Arabic name': medicine.name_ar,
                'Generic name': medicine.generic_name_en,
                'Arabic generic name': medicine.generic_name_ar,
                'Dosage form': medicine.dosage_form,
                Route: medicine.route,
                'Package size': packageSize(medicine),
                Manufacturer: medicine.manufacturer_name,
                Brand: medicine.brand_name,
                Strength: medicine.strength_text,
                'Dispensing class': medicine.dispensing_class,
                'Catalog status': medicine.catalog_status,
                Barcode: medicine.barcode,
                'JFDA registration number': medicine.jfda_registration_no,
                'Registration status': medicine.registration_status,
                'Registration expiry': medicine.registration_expiry,
                'Official price': medicine.official_price == null ? null : medicine.official_price + ' ' + medicine.currency,
                'Price effective date': medicine.official_price_effective_date,
            }} />
            <div className="table-section-heading"><h3>Active ingredients</h3>
                <ResetSortButton sort={ingredientSort} small />
            </div>
            <div className="table-scroll">
                <table>
                    <TableHead columns={[["Ingredient","name_en"],["Strength","strength_value"],["Per","per_value"]]} sort={ingredientSort} />
                    <tbody>{medicine.ingredients.length ? sortRows(medicine.ingredients, ingredientSort, (row, key) => key.endsWith('_value') && row[key] != null ? Number(row[key]) : row[key]).map((component) => <tr key={component.id}>
                        <td><strong>{component.name_en}</strong>{component.name_ar && <small className="table-secondary" lang="ar">{component.name_ar}</small>}</td>
                        <td>{numberText(component.strength_value)} {component.strength_unit}</td>
                        <td>{unitBasis(component)}</td>
                    </tr>) : <tr><td className="empty" colSpan="3">No active ingredients have been recorded.</td></tr>}</tbody>
                </table>
            </div>
        </>}
        <footer className="form-actions"><button className="button secondary" type="button" onClick={onClose}>Done</button></footer>
    </Modal>;
}

export function Medicines() {
    const [{ search, status }, updateFilters, resetFilters] = useSavedTableFilters('medicines', { search: '', status: '' });
    const sort = useTableSort('medicines', 'name');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [selection, setSelection] = useState(null);
    const [editor, setEditor] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [manageIngredients, setManageIngredients] = useState(false);
    const [revision, setRevision] = useState(0);
    const [notice, setNotice] = useState('');
    const [deleteError, setDeleteError] = useState('');
    const [deletingBusy, setDeletingBusy] = useState(false);

    const loadTable = useTableLoader();
    useEffect(() => {
        let current = true;
        const cancel = loadTable(search, async () => {
            try {
                const params = new URLSearchParams({ page, ...sort.params });
                if (search.trim()) params.set('search', search.trim());
                if (status) params.set('catalog_status', status);
                const catalog = await api(endpoint + '?' + params);
                if (current) {
                    if (page > catalog.meta.last_page) setPage(catalog.meta.last_page);
                    else setResult(catalog);
                }
            } catch (exception) {
                if (current) setError(exception.message);
            } finally {
                if (current) setLoading(false);
            }
        });

        return () => { current = false; cancel?.(); };
    }, [loadTable, search, status, page, revision, sort.column, sort.direction, sort.params.sort]);

    function startLoading() {
        setLoading(true);
        setError('');
    }

    function searchCatalog(value) {
        if (value === search) return;
        startLoading();
        updateFilters({ search: value });
        setPage(1);
    }

    function filterStatus(value) {
        startLoading();
        updateFilters({ status: value });
        setPage(1);
    }

    function clearFilters() {
        if (!search && !status && page === 1) return;
        startLoading();
        resetFilters();
        setPage(1);
    }

    function changePage(next) {
        startLoading();
        setPage(next);
    }

    function refresh(message) {
        if (message) setNotice(message);
        startLoading();
        setRevision((value) => value + 1);
    }

    async function removeMedicine() {
        setDeletingBusy(true);
        setDeleteError('');
        try {
            await api(endpoint + '/' + deleting.id, { method: 'DELETE' });
            setDeleting(null);
            refresh('Medicine deleted.');
        } catch (exception) {
            setDeleteError(exception.message);
        } finally {
            setDeletingBusy(false);
        }
    }

    const rows = result?.data ?? [];

    return <section aria-labelledby="medicines-heading">
        <div className="page-heading"><div><h1 id="medicines-heading">Medicines</h1><p>Manage medicine packages and active ingredient strengths.</p></div>
            <div className="row-actions">
                <button className="button secondary" type="button" onClick={() => setManageIngredients(true)}>Manage ingredients</button>
                <button className="button primary" type="button" onClick={() => setEditor({})}>Add medicine</button>
            </div>
        </div>
        <Notice error>{error}</Notice><Notice>{notice}</Notice>
        <div className="panel directory" aria-busy={loading}>
            <TableFilters onClear={clearFilters} sort={sort} onSortReset={() => changePage(1)} label="Search medicines" search={search} onSearch={searchCatalog}
                placeholder="Name, ingredient, barcode, or JFDA number">
                <Field label="Catalog status"><select value={status} onChange={(event) => filterStatus(event.target.value)}>
                    <option value="">All statuses</option>
                    {(result?.meta.catalog_statuses ?? []).map((option) => <option key={option}>{option}</option>)}
                </select></Field>
            </TableFilters>
            <div className="table-scroll">
                <table>
                    <TableHead columns={[["Medicine","name"],["Strength","strength"],["Package","package","table-cell-centered"],["Status","status","table-cell-centered"],["Actions",null,"table-action-column"]]} sort={sort} onSort={() => changePage(1)} />
                    <tbody>{!loading && !error && rows.length ? rows.map((medicine) => <tr key={medicine.id}>
                        <td><strong>{medicine.name_en}</strong>{medicine.name_ar && <small className="table-secondary" lang="ar">{medicine.name_ar}</small>}
                            <small className="table-secondary">{medicine.manufacturer_name}</small></td>
                        <td>{medicine.strength_text}</td><td className="table-cell-centered">{packageSize(medicine)}</td><td><Status status={medicine.catalog_status} /></td>
                        <td className="table-action-column"><RowActions rowName={medicine.name_en}>
                            <TableAction icon="view" onClick={() => setSelection(medicine)} label={'View ' + medicine.name_en} />
                            <TableAction icon="edit" onClick={() => setEditor(medicine)} label={'Edit ' + medicine.name_en} />
                            <TableAction icon="delete" onClick={() => { setDeleteError(''); setDeleting(medicine); }} label={'Delete ' + medicine.name_en} />
                        </RowActions></td>
                    </tr>) : <tr><td className="empty" colSpan="5">{loading ? 'Loading medicines…'
                        : error ? 'Medicine results are unavailable.' : 'No medicines found.'}</td></tr>}</tbody>
                </table>
            </div>
            {!loading && !error && result?.meta && <footer className="pagination">
                <button className="button secondary small" onClick={() => changePage(page - 1)} disabled={page <= 1}>Previous</button>
                <span>Page {result.meta.current_page} of {result.meta.last_page} · {result.meta.total} {result.meta.total === 1 ? 'package' : 'packages'}</span>
                <button className="button secondary small" onClick={() => changePage(page + 1)} disabled={page >= result.meta.last_page}>Next</button>
            </footer>}
        </div>
        {selection && <MedicineDetails summary={selection} onClose={() => setSelection(null)} />}
        {editor && <MedicineEditor id={editor.id} onClose={() => setEditor(null)} onSaved={(message) => {
            if (!editor.id) { resetFilters(); setPage(1); }
            setEditor(null);
            refresh(message);
        }} />}
        {manageIngredients && <IngredientManager onClose={() => { setManageIngredients(false); refresh(); }} />}
        {deleting && <Modal title="Delete medicine?" onClose={() => { if (!deletingBusy) setDeleting(null); }}>
            <Notice error>{deleteError}</Notice>
            <p>Delete {deleting.name_en} and its ingredient strengths? Shared ingredient records will be kept.</p>
            <footer className="form-actions">
                <button className="button secondary" type="button" disabled={deletingBusy} onClick={() => setDeleting(null)}>Cancel</button>
                <button className="button danger" type="button" disabled={deletingBusy} onClick={removeMedicine}>Confirm delete medicine</button>
            </footer>
        </Modal>}
    </section>;
}
