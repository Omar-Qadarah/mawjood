import { TableHead, useTableSort } from './TableSort';
import { useEffect, useState } from 'react';
import { api, places } from './api';
import { useTableLoader } from './useTableLoader';
import { useSavedTableFilters } from './useSavedTableFilters';
import { TableFilters } from './TableFilters';
import { Field, Notice, Status } from './ui';

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

export function Reservations({ initialStatus = '', initialGovernorate = '', initialDeadline = '', onClearRoute }) {
    const routeFilters = {};
    if (statuses.includes(initialStatus)) routeFilters.status = initialStatus;
    if (Object.hasOwn(places, initialGovernorate)) routeFilters.governorate = initialGovernorate;
    if (initialDeadline) routeFilters.deadline = initialDeadline;
    const [filter, updateFilters, clearFilters] = useSavedTableFilters('reservations', {
        search: '', status: '', governorate: '', deadline: '', page: 1,
    }, routeFilters);
    const sort = useTableSort('reservations', filter.deadline ? 'deadline' : undefined);
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    const loadTable = useTableLoader();
    useEffect(() => {
        let current = true;
        const cancel = loadTable(filter.search, () => {
            const params = new URLSearchParams({ page: filter.page, ...sort.params });
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
    }, [loadTable, filter, sort.column, sort.direction, sort.params.sort]);

    function changeFilter(changes) {
        setLoading(true);
        setError('');
        updateFilters({ page: 1, ...changes });
        if (Object.keys(changes).some((key) => Object.hasOwn(routeFilters, key))) onClearRoute();
    }

    const rows = result?.data ?? [];
    let emptyText = 'No reservations match these filters.';
    if (loading) emptyText = 'Loading reservations…';
    else if (error) emptyText = 'Reservation results are unavailable.';
    else if (!filter.deadline && !filter.status && !filter.governorate && !filter.search.trim()) emptyText = 'No reservations have been placed yet.';

    return <section aria-labelledby="reservations-heading">
        <div className="page-heading"><div><h1 id="reservations-heading">Reservations</h1>
            <p>Monitor reservation statuses and deadlines in Jordan time.</p></div></div>
        <Notice error>{error}</Notice>
        <div className="panel directory" aria-busy={loading}>
            <TableFilters sort={sort} onSortReset={() => changeFilter({})} label="Search reservations" search={filter.search} onSearch={(search) => changeFilter({ search })}
                onClear={() => {
                    setLoading(true);
                    setError('');
                    clearFilters();
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
                <TableHead columns={[["Reservation","code"],["Pharmacy","pharmacy"],["Status","status"],["Expiry deadline","deadline"]]} sort={sort} onSort={() => changeFilter({})} />
                <tbody>{!loading && !error && rows.length ? rows.map((reservation) => {
                    const isResponseDeadline = reservation.status === 'pending' ||
                        (reservation.status === 'expired' && !reservation.pickup_deadline);
                    const deadline = isResponseDeadline ? reservation.response_deadline :
                        ['confirmed', 'expired'].includes(reservation.status) ? reservation.pickup_deadline : null;
                    return <tr key={reservation.id}>
                        <td><strong>{reservation.code}</strong></td><td>{reservation.pharmacy.name_en}</td>
                        <td><Status status={reservation.status} /></td>
                        <td>{formatDeadline(deadline)}
                            {deadline && <small className="table-secondary">{isResponseDeadline ? 'Response' : 'Pickup'}</small>}
                        </td>
                    </tr>;
                }) : <tr><td className="empty" colSpan="4">{emptyText}</td></tr>}</tbody>
            </table></div>
            {!loading && !error && result?.meta.total > 0 && <footer className="pagination">
                <button className="button secondary small" type="button" onClick={() => changeFilter({ page: filter.page - 1 })} disabled={filter.page <= 1}>Previous</button>
                <span>Page {result.meta.current_page} of {result.meta.last_page} · {result.meta.total} {result.meta.total === 1 ? 'reservation' : 'reservations'}</span>
                <button className="button secondary small" type="button" onClick={() => changeFilter({ page: filter.page + 1 })} disabled={filter.page >= result.meta.last_page}>Next</button>
            </footer>}
        </div>
    </section>;
}
