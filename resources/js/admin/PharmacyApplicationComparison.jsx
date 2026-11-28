import { ResetSortButton, sortRows, TableHead, useTableSort } from './TableSort';
import { useEffect, useRef, useState } from 'react';
import { api } from './api';
import { Notice, Status } from './ui';

const fields = [
    ['English name', 'name_en', 'same_english_name'],
    ['Arabic name', 'name_ar', 'same_arabic_name'],
    ['Address', 'address', 'same_address'],
    ['City', 'city'],
    ['Governorate', 'governorate'],
    ['License number', 'license_no'],
    ['Status', 'status'],
];

function fieldValue(field, value) {
    if (!value) return '—';
    if (field === 'status') return <Status status={value} />;
    if (field === 'name_ar') return <span lang="ar" dir="rtl">{value}</span>;

    return value;
}

export function PharmacyApplicationComparison({ application, match, headingRef, onClose, onApproved }) {
    const sort = useTableSort('pharmacy-comparison');
    const [confirming, setConfirming] = useState(false);
    const [approving, setApproving] = useState(false);
    const [error, setError] = useState('');
    const approveButton = useRef(null);
    const cancelButton = useRef(null);

    useEffect(() => {
        if (confirming) cancelButton.current?.focus();
    }, [confirming]);

    function cancelApproval() {
        setConfirming(false);
        setError('');
        requestAnimationFrame(() => approveButton.current?.focus());
    }

    async function approve() {
        setApproving(true);
        setError('');
        try {
            await api('/admin/pharmacies/' + application.id + '/approve', { method: 'POST' });
            onApproved(application);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setApproving(false);
        }
    }

    return <section className="pharmacy-comparison" aria-labelledby="pharmacy-comparison-heading">
        <header className="pharmacy-comparison-header">
            <h3 id="pharmacy-comparison-heading" ref={headingRef} tabIndex={-1}>Compare pharmacies</h3>
            <div className="table-controls">
                <ResetSortButton sort={sort} small />
                <button className="button small secondary" type="button" disabled={approving} onClick={onClose}>Back to matches</button>
            </div>
        </header>
        <div className="table-scroll">
            <table aria-label="Side-by-side pharmacy comparison">
                <TableHead columns={[["Field","label"],["Pending application","application"],["Matching pharmacy","match"]]} sort={sort} />
                <tbody>{sortRows(fields, sort, ([label, field], key) => key === 'label' ? label : (key === 'application' ? application : match)[field]).map(([label, field, flag]) => {
                    const matching = flag ? match[flag] : ['city', 'governorate'].includes(field)
                        && Boolean(application[field]) && application[field] === match[field];
                    return <tr key={field} className={matching ? 'comparison-match' : undefined}>
                        <th scope="row">{label}{matching && <span className="comparison-badge">Match</span>}</th>
                        <td className={field === 'city' ? 'table-cell-centered' : undefined}>{fieldValue(field, application[field])}</td>
                        <td className={field === 'city' ? 'table-cell-centered' : undefined}>{fieldValue(field, match[field])}</td>
                    </tr>;
                })}</tbody>
            </table>
        </div>
        {!confirming && <div className="comparison-actions">
            <button ref={approveButton} className="button primary" type="button" onClick={() => setConfirming(true)}>
                Approve pending pharmacy
            </button>
        </div>}
        {confirming && <section className="comparison-confirmation" aria-labelledby="confirm-pharmacy-approval">
            <h3 id="confirm-pharmacy-approval">Confirm pharmacy approval</h3>
            <p>Activate <strong>{application.name_en}</strong> ({application.license_no}) and make it available in public search?</p>
            <Notice error>{error}</Notice>
            <footer className="form-actions">
                <button ref={cancelButton} className="button secondary" type="button" disabled={approving} onClick={cancelApproval}>Cancel</button>
                <button className="button primary" type="button" disabled={approving} onClick={approve}>
                    {approving ? 'Activating…' : 'Confirm and activate'}
                </button>
            </footer>
        </section>}
    </section>;
}
