import { ResetSortButton, sortRows, TableHead, useTableSort } from './TableSort';
import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { api, signIn } from './api';
import { AuthLayout } from './AuthLayout';
import { PasswordField } from './PasswordField';
import { Field, Icon, Notice, Status } from './ui';
import { Directory, RecordDetails } from './Directory';
import { JordanMap } from './JordanMap';
import { MedicineDetails, Medicines } from './Medicines';
import { Reservations } from './Reservations';
import { RegistrationTrend } from './RegistrationTrend';
import { ProfileSettings } from './ProfileSettings';
import { Sidebar, sections } from './Sidebar';
import { TableFilterAccount } from './useSavedTableFilters';
import './admin.css';

const currentHash = () => location.hash.slice(1);

function Login({ onLogin, message }) {
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        setError('');
        setBusy(true);
        try {
            const user = await signIn({ email: form.get('email'), password: form.get('password'), remember: form.has('remember') });
            if (user.platform_role !== 'admin') {
                await api('/auth/logout', { method: 'POST' });
                throw new Error('An administrator account is required.');
            }
            onLogin(user);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <AuthLayout title="Manage the pharmacy network." subtitle="Review listings and coordinate the Mawjood directory.">
        <h2>Administrator sign in</h2>
        <Notice>{message}</Notice><Notice error>{error}</Notice>
        <form onSubmit={submit}>
            <Field label="Email"><input type="email" name="email" autoComplete="username" required /></Field>
            <PasswordField label="Password" name="password" autoComplete="current-password" required />
            <label className="checkbox"><input type="checkbox" name="remember" />Remember me</label>
            <button className="button primary" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}<Icon name="arrow" /></button>
        </form>
    </AuthLayout>;
}

function Overview({ onDetails, refresh }) {
    const recentSort = useTableSort('recent-pharmacies', 'created_at', 'desc');
    const [summary, setSummary] = useState(null);
    const [sites, setSites] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let current = true;
        api('/admin/dashboard/summary').then((next) => { if (current) setSummary(next); })
            .catch((exception) => { if (current) setError(exception.message); });
        api('/admin/pharmacy-sites').then((next) => { if (current) setSites(next.data); })
            .catch((exception) => { if (current) setError(exception.message); });
        return () => { current = false; };
    }, [refresh]);

    const stats = [
        ['Pharmacies', summary?.counts.total, 'pharmacies'],
        ['Customers', summary?.customers_count, 'customers'],
        ['Completed reservations', summary?.completed_reservations_count, 'reservations'],
    ];
    const statuses = ['active', 'pending', 'suspended'];
    const total = summary?.counts.total ?? 0;
    const activeShare = total ? (summary.counts.active / total) * 100 : 0;

    return <section>
        <div className="page-heading"><div><h1>Dashboard</h1><p>Pharmacy network and customer activity</p></div>
            <a className="button secondary" href="#pharmacies">Manage pharmacies <Icon name="arrow" /></a></div>
        <Notice error>{error}</Notice>
        <div className="overview-grid">
            <div className="overview-main">
            <div className="stats-grid">{stats.map(([title, value, icon]) => <a className="stat stat-link" key={title} href={icon === 'reservations' ? '#reservations?status=completed' : '#' + icon}>
                <span className="stat-icon"><Icon name={icon} /></span><div><p>{title}</p><strong>{value ?? '—'}</strong></div>
            </a>)}</div>
            <RegistrationTrend registrations={summary?.registrations} />
        </div>
            <JordanMap sites={sites} />
            <section className="panel recent-panel"><header className="panel-header"><div><h2>Recently added pharmacies</h2><p>Latest records in the directory</p></div>
                <div className="table-controls">
                    <ResetSortButton sort={recentSort} small />
                    <a className="text-button" href="#pharmacies">View all</a>
                </div></header>
                <div className="table-scroll"><table><TableHead columns={[["Pharmacy","name_en"],["Added","created_at"],["Status","status","table-cell-centered"]]} sort={recentSort} />
                    <tbody>{summary?.recent_pharmacies.length ? sortRows(summary.recent_pharmacies, recentSort, (row, key) => row[key]).map((pharmacy) => <tr key={pharmacy.id}>
                        <td><button className="text-button" onClick={() => onDetails('pharmacies', pharmacy.id)}>{pharmacy.name_en}</button></td>
                        <td>{new Date(pharmacy.created_at).toLocaleDateString()}</td><td><Status status={pharmacy.status} /></td>
                    </tr>) : <tr><td className="empty" colSpan="3">{summary ? 'No pharmacies have been added yet.' : 'Loading pharmacies…'}</td></tr>}</tbody>
                </table></div>
            </section>
            <section className="panel status-panel" aria-labelledby="pharmacy-status-heading">
                <header className="panel-header"><h2 id="pharmacy-status-heading">Pharmacy status</h2></header>
                <div className="status-layout">
                    <div className="status-overview">
                        <div className="status-totals">
                            <div><strong>{summary ? total : '—'}</strong><span>Total pharmacies</span></div>
                            <div><strong>{summary && total ? Math.round(activeShare) + '%' : '—'}</strong><span>Active share</span></div>
                        </div>
                        <div className="status-distribution" aria-hidden="true">
                            {statuses.map(status => <span key={status} className={'status-color-' + status}
                                style={{ flexGrow: summary?.counts[status] ?? 0 }} />)}
                        </div>
                    </div>
                    <ul className="status-breakdown">
                        {statuses.map(status => <li key={status}>
                            <a href={'#pharmacies?status=' + status}>
                                <span className={'status-dot status-color-' + status} aria-hidden="true" />
                                <span className="status-name">{status}</span>
                                <strong>{summary ? summary.counts[status] : '—'}</strong>
                                <Icon name="arrow" size={16} />
                            </a>
                        </li>)}
                    </ul>
                </div>
            </section>
            <section className="panel attention-panel" aria-labelledby="attention-heading">
                <header className="panel-header"><h2 id="attention-heading">Needs attention</h2></header>
                <div className="attention-links">
                    <a href="#pharmacies?status=pending"><Icon name="pharmacies" />
                        <div><strong>Pending pharmacy approvals</strong></div>
                        <span className="attention-count">{summary?.counts.pending ?? '—'}</span><Icon name="arrow" size={16} />
                    </a>
                    <a href="#reservations?deadline=upcoming"><Icon name="reservations" />
                        <div><strong>Reservations nearing deadlines</strong><p>Due within 60 minutes</p></div>
                        <span className="attention-count">{summary?.upcoming_reservations_count ?? '—'}</span><Icon name="arrow" size={16} />
                    </a>
                </div>
            </section>
            <section className="panel activity-panel"><header className="panel-header"><div><h2>Recent updates</h2><p>Latest pharmacy changes</p></div></header>
                {summary?.recent_activity.length ? <ul className="activity-list">{summary.recent_activity.map((pharmacy) => <li key={pharmacy.id}>
                    <div><button className="text-button" onClick={() => onDetails('pharmacies', pharmacy.id)}>{pharmacy.name_en}</button><small>{new Date(pharmacy.updated_at).toLocaleString()}</small></div>
                    <Status status={pharmacy.status} />
                </li>)}</ul> : <p className="empty">{summary ? 'No pharmacy updates yet.' : 'Loading activity…'}</p>}
            </section>
        </div>
    </section>;
}

function App() {
    const [user, setUser] = useState(undefined);
    const [route, setRoute] = useState(() => ({ hash: currentHash(), entry: 0 }));
    const [section, query] = route.hash.split('?');
    const tab = Object.hasOwn(sections, section) ? section : 'overview';
    const params = new URLSearchParams(query);
    const status = params.get('status') ?? '';
    const [message, setMessage] = useState(null);
    const [detail, setDetail] = useState(null);
    const [pharmacyRevision, setPharmacyRevision] = useState(0);

    useEffect(() => {
        const navigate = () => {
            const hash = currentHash();
            setRoute((previous) => ({ hash, entry: previous.entry + 1 }));
        };
        window.addEventListener('hashchange', navigate);
        api('/auth/me').then(({ data }) => setUser(data.platform_role === 'admin' ? data : null)).catch(() => setUser(null));
        return () => window.removeEventListener('hashchange', navigate);
    }, []);

    function clearRouteFilters() {
        if (!query) return;
        window.history.replaceState(window.history.state, '', '#' + tab);
        setRoute((previous) => ({ ...previous, hash: tab }));
    }

    async function openDetails(entity, id) {
        setMessage(null);
        try {
            setDetail({ entity, record: (await api('/admin/' + entity + '/' + id)).data });
        } catch (error) {
            setMessage({ error: true, text: error.message });
        }
    }

    function openQuickJumpRecord(entity, record) {
        if (entity === 'medicines') setDetail({ entity, record });
        else openDetails(entity, record.id);
    }

    function pharmacyApproved(application) {
        setDetail(current => current?.entity === 'pharmacies' && current.record.id === application.id ? null : current);
        setMessage({ text: application.name_en + ' is now active.' });
        setPharmacyRevision(revision => revision + 1);
    }

    async function logout() {
        try {
            await api('/auth/logout', { method: 'POST' });
            setDetail(null);
            setMessage({ text: 'You have been signed out.' });
            setUser(null);
        } catch (error) {
            setMessage({ error: true, text: error.message });
        }
    }

    if (user === undefined) return <main className="loading" role="status">Loading Mawjood…</main>;
    if (!user) return <Login message={message?.text} onLogin={(admin) => { setMessage(null); setUser(admin); }} />;

    let content;
    if (tab === 'overview') {
        content = <Overview onDetails={openDetails} refresh={pharmacyRevision} />;
    } else if (tab === 'medicines') {
        content = <Medicines />;
    } else if (tab === 'reservations') {
        const governorate = params.get('governorate') ?? '';
        const deadline = params.get('deadline') === 'upcoming' ? 'upcoming' : '';
        content = <Reservations onClearRoute={clearRouteFilters} key={route.entry}
            initialStatus={status} initialGovernorate={governorate} initialDeadline={deadline} />;
    } else if (tab === 'profile') {
        content = <ProfileSettings user={user} onUpdated={setUser} />;
    } else {
        const initialStatus = tab === 'pharmacies' ? status : '';
        const initialGovernorate = tab === 'pharmacies' ? params.get('governorate') ?? '' : '';
        content = <Directory onClearRoute={clearRouteFilters} key={route.entry} entity={tab}
            initialStatus={initialStatus} initialGovernorate={initialGovernorate}
            onChanged={() => setMessage(null)} onDetails={openDetails} refresh={tab === 'pharmacies' ? pharmacyRevision : 0} />;
    }

    return <TableFilterAccount.Provider key={user.id} value={user.id}><div className="workspace">
        <Sidebar user={user} tab={tab} onLogout={logout} onOpenRecord={openQuickJumpRecord} />
        <main className="content">
            <Notice error={message?.error}>{message?.text}</Notice>
            {content}
        </main>
        {detail && (detail.entity === 'medicines'
            ? <MedicineDetails summary={detail.record} onClose={() => setDetail(null)} />
            : <RecordDetails {...detail} onClose={() => setDetail(null)} onApproved={pharmacyApproved} />)}
    </div></TableFilterAccount.Provider>;
}

createRoot(document.querySelector('#mawjood-admin')).render(<App />);
