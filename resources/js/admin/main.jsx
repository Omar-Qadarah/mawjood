import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { api, signIn } from './api';
import { Brand, Field, Icon, Notice, Status } from './ui';
import { Directory, RecordDetails } from './Directory';
import { JordanMap } from './JordanMap';
import { Medicines } from './Medicines';
import { Inventory } from './Inventory';
import './admin.css';

const sections = { overview: 'Overview', pharmacies: 'Pharmacies', medicines: 'Medicines', inventory: 'Inventory', customers: 'Customers' };
const currentSection = () => Object.hasOwn(sections, location.hash.slice(1)) ? location.hash.slice(1) : 'overview';

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

    return <main className="login-page"><section className="login-identity"><Brand />
        <div><h1>Manage the pharmacy network.</h1><p>Review listings and coordinate the Mawjood directory.</p></div>
        <div className="login-decoration" aria-hidden="true" />
    </section><section className="login-content"><div className="login-box">
        <h2>Administrator sign in</h2>
        <Notice>{message}</Notice><Notice error>{error}</Notice>
        <form onSubmit={submit}>
            <Field label="Email"><input type="email" name="email" autoComplete="username" required /></Field>
            <Field label="Password"><input type="password" name="password" autoComplete="current-password" required /></Field>
            <label className="checkbox"><input type="checkbox" name="remember" />Remember me</label>
            <button className="button primary" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}<Icon name="arrow" /></button>
        </form>
    </div></section></main>;
}

function Overview({ onDetails }) {
    const [summary, setSummary] = useState(null);
    const [sites, setSites] = useState([]);
    const [error, setError] = useState('');

    useEffect(() => {
        let current = true;
        api('/admin/dashboard/summary').then((next) => { if (current) setSummary(next); })
            .catch((exception) => { if (current) setError(exception.message); });
        api('/admin/pharmacy-sites').then((next) => { if (current) setSites(next.data); })
            .catch((exception) => { if (current) setError(exception.message); });
        return () => { current = false; };
    }, []);

    const stats = [
        ['Pharmacies', summary?.counts.total, 'pharmacies'],
        ['Customers', summary?.customers_count, 'customers'],
        ['Pharmacy owners', summary?.owners_count, 'customers'],
    ];
    const statuses = ['active', 'pending', 'suspended'];
    const total = summary?.counts.total ?? 0;

    return <section>
        <div className="page-heading"><div><h1>Dashboard overview</h1><p>Monitor the pharmacy directory and customer activity.</p></div>
            <a className="button secondary" href="#pharmacies">Manage pharmacies <Icon name="arrow" /></a></div>
        <Notice error>{error}</Notice>
        <div className="overview-grid"><div className="overview-main">
            <div className="stats-grid">{stats.map(([title, value, icon]) => <div className="stat" key={title}>
                <span className="stat-icon"><Icon name={icon} /></span><p>{title}</p><strong>{value ?? '—'}</strong>
            </div>)}</div>
            <section className="panel status-panel"><header className="panel-header"><div><h2>Pharmacy status</h2><p>Listings by review status</p></div></header>
                <div className="status-chart">{statuses.map((status) => {
                    const value = summary?.counts[status] ?? 0;
                    return <div className="chart-row" key={status}><span>{status}</span><div className="bar-track">
                        <div className={'bar bar-' + status} style={{ width: (total ? (value / total) * 100 : 0) + '%' }} />
                    </div><strong>{summary ? value : '—'}</strong></div>;
                })}</div>
            </section>
            <section className="panel recent-panel"><header className="panel-header"><div><h2>Recently added pharmacies</h2><p>Latest records in the directory</p></div>
                <a className="text-button" href="#pharmacies">View all</a></header>
                <div className="table-scroll"><table><thead><tr><th scope="col">Pharmacy</th><th scope="col">Added</th><th scope="col">Status</th></tr></thead>
                    <tbody>{summary?.recent_pharmacies.length ? summary.recent_pharmacies.map((pharmacy) => <tr key={pharmacy.id}>
                        <td><button className="text-button" onClick={() => onDetails('pharmacies', pharmacy.id)}>{pharmacy.name_en}</button></td>
                        <td>{new Date(pharmacy.created_at).toLocaleDateString()}</td><td><Status status={pharmacy.status} /></td>
                    </tr>) : <tr><td className="empty" colSpan="3">{summary ? 'No pharmacies have been added yet.' : 'Loading pharmacies…'}</td></tr>}</tbody>
                </table></div>
            </section>
        </div><aside className="overview-side">
            <JordanMap sites={sites} onSite={(id) => onDetails('pharmacies', id)} />
            <section className="panel activity-panel"><header className="panel-header"><div><h2>Recent updates</h2><p>Latest pharmacy changes</p></div></header>
                {summary?.recent_activity.length ? <ul className="activity-list">{summary.recent_activity.map((pharmacy) => <li key={pharmacy.id}>
                    <div><button className="text-button" onClick={() => onDetails('pharmacies', pharmacy.id)}>{pharmacy.name_en}</button><small>{new Date(pharmacy.updated_at).toLocaleString()}</small></div>
                    <Status status={pharmacy.status} />
                </li>)}</ul> : <p className="empty">{summary ? 'No pharmacy updates yet.' : 'Loading activity…'}</p>}
            </section>
        </aside></div>
    </section>;
}

function App() {
    const [user, setUser] = useState(undefined);
    const [tab, setTab] = useState(currentSection);
    const [message, setMessage] = useState(null);
    const [detail, setDetail] = useState(null);

    useEffect(() => {
        const navigate = () => setTab(currentSection());
        window.addEventListener('hashchange', navigate);
        api('/auth/me').then(({ data }) => setUser(data.platform_role === 'admin' ? data : null)).catch(() => setUser(null));
        return () => window.removeEventListener('hashchange', navigate);
    }, []);

    async function openDetails(entity, id) {
        setMessage(null);
        try {
            setDetail({ entity, record: (await api('/admin/' + entity + '/' + id)).data });
        } catch (error) {
            setMessage({ error: true, text: error.message });
        }
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
        content = <Overview onDetails={openDetails} />;
    } else if (tab === 'medicines') {
        content = <Medicines />;
    } else if (tab === 'inventory') {
        content = <Inventory />;
    } else {
        content = <Directory key={tab} entity={tab} onChanged={() => setMessage(null)} onDetails={openDetails} />;
    }

    return <div className="workspace">
        <aside className="sidebar"><Brand />
            <nav aria-label="Dashboard">{Object.entries(sections).map(([key, title]) =>
                <a key={key} href={'#' + key} aria-current={tab === key ? 'page' : undefined}><Icon name={key} />{title}</a>)}</nav>
            <div className="sidebar-account"><span className="avatar">{user.name?.slice(0, 1).toUpperCase() ?? 'A'}</span>
                <span>{user.name}<small>Administrator</small></span>
                <button className="icon-button" type="button" onClick={logout} aria-label="Log out" title="Log out"><Icon name="logout" /></button></div>
        </aside>
        <main className="content">
            <Notice error={message?.error}>{message?.text}</Notice>
            {content}
        </main>
        {detail && <RecordDetails {...detail} onClose={() => setDetail(null)} />}
    </div>;
}

createRoot(document.querySelector('#mawjood-admin')).render(<App />);
