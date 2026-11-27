import { useEffect, useState } from 'react';
import { api } from './api';
import { Details, Field, Modal, Notice, Status } from './ui';

const endpoint = '/admin/medicines';
const numberText = (value) => value == null ? '' : String(value).replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '');
const packageSize = (medicine) => numberText(medicine.pack_size) + ' ' + medicine.pack_unit;

function unitBasis(component) {
    if (component.per_value == null && !component.per_unit) return '—';

    return [numberText(component.per_value), component.per_unit].filter(Boolean).join(' ');
}

function MedicineDetails({ summary, onClose }) {
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
            <h3>Active ingredients</h3>
            <div className="table-scroll">
                <table>
                    <thead><tr><th scope="col">Ingredient</th><th scope="col">Strength</th><th scope="col">Per</th></tr></thead>
                    <tbody>{medicine.ingredients.length ? medicine.ingredients.map((component) => <tr key={component.id}>
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
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [selection, setSelection] = useState(null);

    useEffect(() => {
        let current = true;
        const timer = setTimeout(async () => {
            try {
                const params = new URLSearchParams({ page });
                if (search.trim()) params.set('search', search.trim());
                const catalog = await api(endpoint + '?' + params);
                if (current) setResult(catalog);
            } catch (exception) {
                if (current) setError(exception.message);
            } finally {
                if (current) setLoading(false);
            }
        }, 150);

        return () => { current = false; clearTimeout(timer); };
    }, [search, page]);

    function startLoading() {
        setLoading(true);
        setError('');
    }

    function searchCatalog(value) {
        if (value === search) return;
        startLoading();
        setSearch(value);
        setPage(1);
    }

    function changePage(next) {
        startLoading();
        setPage(next);
    }

    const rows = result?.data ?? [];

    return <section aria-labelledby="medicines-heading">
        <div className="page-heading"><div><h1 id="medicines-heading">Medicines</h1><p>Browse medicine packages and review their active ingredient strengths.</p></div></div>
        <Notice error>{error}</Notice>
        <div className="panel directory" aria-busy={loading}>
            <div className="table-filters">
                <Field label="Search medicines">
                    <input type="search" value={search} maxLength={150}
                        onChange={(event) => searchCatalog(event.target.value)}
                        placeholder="Name, ingredient, barcode, or JFDA number" />
                </Field>
            </div>
            <div className="table-scroll">
                <table>
                    <thead><tr>{['Medicine', 'Strength', 'Package', 'Status', 'Details'].map((heading) =>
                        <th key={heading} scope="col">{heading}</th>)}</tr></thead>
                    <tbody>{!loading && !error && rows.length ? rows.map((medicine) => <tr key={medicine.id}>
                        <td><strong>{medicine.name_en}</strong>{medicine.name_ar && <small className="table-secondary" lang="ar">{medicine.name_ar}</small>}
                            <small className="table-secondary">{medicine.manufacturer_name}</small></td>
                        <td>{medicine.strength_text}</td><td>{packageSize(medicine)}</td><td><Status status={medicine.catalog_status} /></td>
                        <td><button className="button small secondary" type="button" onClick={() => setSelection(medicine)}
                            aria-label={'View ' + medicine.name_en}>View</button></td>
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
    </section>;
}
