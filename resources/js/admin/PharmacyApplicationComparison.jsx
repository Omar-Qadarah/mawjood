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
            <button className="button small secondary" type="button" disabled={approving} onClick={onClose}>Close comparison</button>
        </header>
        <div className="table-scroll">
            <table aria-label="Side-by-side pharmacy comparison">
                <thead><tr>
                    <th scope="col">Field</th>
                    <th scope="col">Pending application</th>
                    <th scope="col">Matching pharmacy</th>
                </tr></thead>
                <tbody>{fields.map(([label, field, flag]) => {
                    const matching = flag ? match[flag] : ['city', 'governorate'].includes(field)
                        && Boolean(application[field]) && application[field] === match[field];
                    return <tr key={field} className={matching ? 'comparison-match' : undefined}>
                        <th scope="row">{label}{matching && <span className="comparison-badge">Match</span>}</th>
                        <td>{fieldValue(field, application[field])}</td>
                        <td>{fieldValue(field, match[field])}</td>
                    </tr>;
                })}</tbody>
            </table>
        </div>
        <div className="form-actions">
            <button ref={approveButton} className="button primary" type="button" disabled={confirming} onClick={() => setConfirming(true)}>
                Approve pending pharmacy
            </button>
        </div>
        {confirming && <section className="catalog-fieldset" aria-labelledby="confirm-pharmacy-approval">
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
