import { Brand, Icon } from './ui';

export const sections = { overview: 'Overview', pharmacies: 'Pharmacies', medicines: 'Medicines', inventory: 'Inventory', reservations: 'Reservations', customers: 'Customers' };

export function Sidebar({ user, tab, onLogout }) {
    return <aside className="sidebar"><Brand />
        <nav aria-label="Dashboard">{Object.entries(sections).map(([key, title]) =>
            <a key={key} href={'#' + key} aria-current={tab === key ? 'page' : undefined}><Icon name={key} />{title}</a>)}</nav>
        <div className="sidebar-account"><span className="avatar">{user.name?.slice(0, 1).toUpperCase() ?? 'A'}</span>
            <span>{user.name}<small>Administrator</small></span>
            <button className="icon-button" type="button" onClick={onLogout} aria-label="Log out" title="Log out"><Icon name="logout" /></button></div>
    </aside>;
}
