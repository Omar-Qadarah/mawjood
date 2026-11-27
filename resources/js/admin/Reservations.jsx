import { useEffect, useState } from 'react';
import { api, places } from './api';
import { useTableLoader } from './useTableLoader';
import { TableFilters } from './TableFilters';
import { Details, Field, Modal, Notice, Status } from './ui';

const endpoint = '/admin/reservations';
const statuses = ['pending', 'confirmed', 'rejected', 'cancelled', 'completed', 'expired'];
const ammanTime = new Intl.DateTimeFormat('en-JO', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Amman',
});

function formatDeadline(value) {
    return value ? <time dateTime={value}>{ammanTime.format(new Date(value))}</time> : '—';
}

function ReservationDetails({ id, code, onClose, onReviewed }) {
    const [reservation, setReservation] = useState(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [busy, setBusy] = useState(false);
    const [rejecting, setRejecting] = useState(false);
    const [cancelling, setCancelling] = useState(false);

    useEffect(() => {
        let current = true;
        api(endpoint + '/' + id)
            .then(({ data }) => { if (current) setReservation(data); })
            .catch((exception) => { if (current) setError(exception.message); });
        return () => { current = false; };
    }, [id]);

    async function review(status, value) {
        setBusy(true);
        setError('');
        try {
            const body = { status };
            const path = endpoint + '/' + id;
            if (status === 'rejected' && value) body.rejection_reason = value;
            let request;
            if (status === 'completed') {
                request = api(path + '/complete', { method: 'POST', body: { pickup_code: value } });
            } else if (status === 'cancelled') {
                request = api(path + '/cancel', { method: 'POST' });
            } else {
                request = api(path, { method: 'PATCH', body });
            }
            const { data } = await request;
            setReservation(data);
            setRejecting(false);
            setCancelling(false);
            setNotice({
                confirmed: 'Reservation confirmed. Pickup deadline set.',
                rejected: 'Reservation rejected. Held stock released.',
                completed: 'Reservation completed. Dispense recorded.',
                cancelled: 'Reservation cancelled. Held stock released.',
            }[status]);
            onReviewed();
        } catch (exception) {
            setError(exception.message);
            const latest = await api(endpoint + '/' + id).catch(() => null);
            if (latest) {
                setReservation(latest.data);
                onReviewed();
            }
        } finally {
            setBusy(false);
        }
    }

    const canCancel = ['pending', 'confirmed'].includes(reservation?.status);
    const confirmingCancellation = canCancel && cancelling;

    return <Modal title={'Reservation ' + code} onClose={() => { if (!busy) onClose(); }} wide>
        <Notice error>{error}</Notice>
        <Notice>{notice}</Notice>
        {!reservation && !error && <p role="status">Loading reservation details…</p>}
        {reservation && <>
            <Details values={{
                Pharmacy: reservation.pharmacy.name_en,
                Status: <Status status={reservation.status} />,
                'Held packages': String(reservation.held_quantity),
                'Response deadline (Jordan time)': formatDeadline(reservation.response_deadline),
                'Pickup deadline (Jordan time)': formatDeadline(reservation.pickup_deadline),
                'Status last changed (Jordan time)': formatDeadline(reservation.status_changed_at),
                ...(reservation.rejection_reason ? { 'Rejection reason': reservation.rejection_reason } : {}),
            }} />
            <Notice error>{reservation.attention_reason === 'insufficient_eligible_stock' &&
                'This reservation needs attention. There are not enough eligible packages to fulfill it.'}</Notice>
            <h3>Medicine and batch allocations</h3>
            <div className="table-scroll"><table>
                <thead><tr>{['Medicine', 'Requested', 'Batch', 'Allocated', 'Allocation status'].map((heading) =>
                    <th key={heading} scope="col">{heading}</th>)}</tr></thead>
                <tbody>{reservation.items.map((item) => item.allocations.length ?
                    item.allocations.map((allocation) => <tr key={allocation.id}>
                        <td>{item.medicine_name_snapshot}</td><td>{item.quantity}</td><td>{allocation.batch_no}</td>
                        <td>{allocation.quantity}</td><td><Status status={allocation.status} /></td>
                    </tr>) : <tr key={item.id}>
                        <td>{item.medicine_name_snapshot}</td><td>{item.quantity}</td>
                        <td colSpan="3">No batch allocations</td>
                    </tr>
                )}</tbody>
            </table></div>
        </>}
        {reservation?.status === 'pending' && rejecting &&
            <form className="catalog-fieldset" onSubmit={(event) => {
                event.preventDefault();
                review('rejected', new FormData(event.currentTarget).get('reason'));
            }}>
                <h3>Reject reservation</h3>
                <p>Rejecting releases the held packages back to inventory.</p>
                <Field label="Reason (optional)"><input name="reason" maxLength={255} disabled={busy} /></Field>
                <footer className="form-actions">
                    <button className="button secondary" type="button" disabled={busy} onClick={() => setRejecting(false)}>Back</button>
                    <button className="button danger" disabled={busy}>{busy ? 'Rejecting…' : 'Release stock and reject'}</button>
                </footer>
            </form>}
        {reservation?.status === 'confirmed' && !confirmingCancellation && <form className="catalog-fieldset" onSubmit={(event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const pickupCode = new FormData(form).get('pickup_code');
            form.reset();
            review('completed', pickupCode);
        }}>
            <h3>Collect reservation</h3>
            <Field label="Pickup code">
                <input name="pickup_code" inputMode="numeric" pattern="[0-9]{6}" maxLength={6}
                    autoComplete="off" required disabled={busy} />
            </Field>
            <div className="form-actions">
                <button className="button primary" disabled={busy}>{busy ? 'Recording…' : 'Complete and dispense'}</button>
            </div>
        </form>}
        {confirmingCancellation && <div className="catalog-fieldset">
            <h3>Cancel this reservation?</h3>
            <p>Held packages will return to inventory and any pickup code will stop working.</p>
            <div className="form-actions">
                <button className="button secondary" type="button" disabled={busy} onClick={() => setCancelling(false)}>Back</button>
                <button className="button danger" type="button" disabled={busy} onClick={() => review('cancelled')}>
                    {busy ? 'Cancelling…' : 'Release stock and cancel'}
                </button>
            </div>
        </div>}
        {!confirmingCancellation && (!rejecting || reservation?.status !== 'pending') && <footer className="form-actions">
            <button className="button secondary" type="button" disabled={busy} onClick={onClose}>Done</button>
            {canCancel && <button className="button danger" type="button" disabled={busy} onClick={() => setCancelling(true)}>Cancel reservation</button>}
            {reservation?.status === 'pending' && <>
                <button className="button danger" type="button" disabled={busy} onClick={() => setRejecting(true)}>Reject</button>
                <button className="button primary" type="button" disabled={busy} onClick={() => review('confirmed')}>
                    {busy ? 'Confirming…' : 'Confirm'}
                </button>
            </>}
        </footer>}
    </Modal>;
}

export function Reservations({ initialStatus = '', initialGovernorate = '', initialDeadline = '', onClearRoute }) {
    const [filter, setFilter] = useState({
        search: '',
        status: statuses.includes(initialStatus) ? initialStatus : '',
        governorate: Object.hasOwn(places, initialGovernorate) ? initialGovernorate : '',
        deadline: initialDeadline,
        page: 1,
    });
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    const [selected, setSelected] = useState(null);

    const loadTable = useTableLoader();
    useEffect(() => {
        let current = true;
        const cancel = loadTable(filter.search, () => {
            const params = new URLSearchParams({ page: filter.page });
            if (filter.status) params.set('status', filter.status);
            if (filter.deadline) params.set('deadline', filter.deadline);
            if (filter.governorate) params.set('governorate', filter.governorate);
            if (filter.search.trim()) params.set('search', filter.search.trim());

            api(endpoint + '?' + params)
                .then((response) => { if (current) setResult(response); })
                .catch((exception) => { if (current) setError(exception.message); })
                .finally(() => { if (current) setLoading(false); });
        });
        return () => { current = false; cancel?.(); };
    }, [loadTable, filter]);

    function changeFilter(changes) {
        setLoading(true);
        setError('');
        setFilter((previous) => ({ ...previous, page: 1, ...changes }));
    }

    const rows = result?.data ?? [];
    let emptyText = 'No reservations match these filters.';
    if (loading) emptyText = 'Loading reservations…';
    else if (error) emptyText = 'Reservation results are unavailable.';
    else if (!filter.deadline && !filter.status && !filter.governorate && !filter.search.trim()) emptyText = 'No reservations have been placed yet.';

    return <section aria-labelledby="reservations-heading">
        <div className="page-heading"><div><h1 id="reservations-heading">Reservations</h1>
            <p>Review held stock and reservation deadlines in Jordan time.</p></div></div>
        <Notice error>{error}</Notice>
        <div className="panel directory" aria-busy={loading}>
            <TableFilters label="Search reservations" search={filter.search} onSearch={(search) => changeFilter({ search })}
                onClear={() => {
                    changeFilter({ search: '', governorate: '', status: '', deadline: '' });
                    onClearRoute();
                }}
                placeholder="Reservation code or pharmacy name">
                <Field label="Governorate"><select value={filter.governorate} onChange={(event) => changeFilter({ governorate: event.target.value })}>
                    <option value="">All governorates</option>
                    {Object.keys(places).map((name) => <option key={name}>{name}</option>)}
                </select></Field>
                <Field label="Status"><select value={filter.status} onChange={(event) => changeFilter({ status: event.target.value })}>
                    <option value="">All statuses</option>
                    {statuses.map((status) => <option key={status} value={status}>{status[0].toUpperCase() + status.slice(1)}</option>)}
                </select></Field>
                <Field label="Deadline"><select value={filter.deadline} onChange={event => changeFilter({ deadline: event.target.value })}>
                    <option value="">All deadlines</option>
                    <option value="upcoming">Due within 60 minutes</option>
                </select></Field>
            </TableFilters>
            <div className="table-scroll"><table>
                <thead><tr>{['Reservation', 'Pharmacy', 'Status', 'Held packages', 'Expiry deadline', 'Details'].map((heading) =>
                    <th key={heading} scope="col">{heading}</th>)}</tr></thead>
                <tbody>{!loading && !error && rows.length ? rows.map((reservation) => {
                    const isResponseDeadline = reservation.status === 'pending' ||
                        (reservation.status === 'expired' && !reservation.pickup_deadline);
                    const deadline = isResponseDeadline ? reservation.response_deadline :
                        ['confirmed', 'expired'].includes(reservation.status) ? reservation.pickup_deadline : null;
                    return <tr key={reservation.id}>
                        <td><strong>{reservation.code}</strong></td><td>{reservation.pharmacy.name_en}</td>
                        <td><Status status={reservation.status} /></td><td>{reservation.held_quantity}</td>
                        <td>{formatDeadline(deadline)}
                            {deadline && <small className="table-secondary">{isResponseDeadline ? 'Response' : 'Pickup'}</small>}
                        </td>
                        <td><button className="button small secondary" type="button" onClick={() => setSelected(reservation)}
                            aria-label={'View reservation ' + reservation.code}>View details</button></td>
                    </tr>;
                }) : <tr><td className="empty" colSpan="6">{emptyText}</td></tr>}</tbody>
            </table></div>
            {!loading && !error && result?.meta.total > 0 && <footer className="pagination">
                <button className="button secondary small" type="button" onClick={() => changeFilter({ page: filter.page - 1 })} disabled={filter.page <= 1}>Previous</button>
                <span>Page {result.meta.current_page} of {result.meta.last_page} · {result.meta.total} {result.meta.total === 1 ? 'reservation' : 'reservations'}</span>
                <button className="button secondary small" type="button" onClick={() => changeFilter({ page: filter.page + 1 })} disabled={filter.page >= result.meta.last_page}>Next</button>
            </footer>}
        </div>
        {selected && <ReservationDetails id={selected.id} code={selected.code} onClose={() => setSelected(null)}
            onReviewed={() => changeFilter({})} />}
    </section>;
}
