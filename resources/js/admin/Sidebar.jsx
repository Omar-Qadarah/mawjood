import { useEffect, useRef, useState } from 'react';
import { Brand, Icon } from './ui';

export const sections = { overview: 'Overview', pharmacies: 'Pharmacies', medicines: 'Medicines', inventory: 'Inventory', reservations: 'Reservations', customers: 'Customers', profile: 'Profile settings' };
const navigation = Object.entries(sections).filter(([key]) => key !== 'profile');

export function Sidebar({ user, tab, onLogout }) {
    const [open, setOpen] = useState(false);
    const account = useRef(null);
    const trigger = useRef(null);

    useEffect(() => {
        if (!open) return;

        function dismiss(event) {
            if (!account.current?.contains(event.target)) setOpen(false);
        }

        function escape(event) {
            if (event.key === 'Escape') {
                setOpen(false);
                trigger.current?.focus();
            }
        }

        document.addEventListener('pointerdown', dismiss);
        document.addEventListener('focusin', dismiss);
        document.addEventListener('keydown', escape);
        return () => {
            document.removeEventListener('pointerdown', dismiss);
            document.removeEventListener('focusin', dismiss);
            document.removeEventListener('keydown', escape);
        };
    }, [open]);

    function closeMenu() {
        setOpen(false);
        trigger.current?.focus();
    }

    return <aside className="sidebar"><Brand />
        <nav aria-label="Dashboard">{navigation.map(([key, title]) =>
            <a key={key} href={'#' + key} aria-current={tab === key ? 'page' : undefined}><Icon name={key} />{title}</a>)}</nav>
        <div className="sidebar-account" ref={account}>
            <button className="account-trigger" type="button" ref={trigger} aria-label="Account options"
                aria-expanded={open} aria-controls="sidebar-account-actions" onClick={() => setOpen((value) => !value)}>
                <span className="avatar" aria-hidden="true">
                    {user.avatar_url ? <img src={user.avatar_url} alt="" /> : (user.name?.slice(0, 1).toUpperCase() ?? 'A')}
                </span>
                <span className="account-identity">{user.name}<small>Administrator</small></span>
                <span className="account-chevron"><Icon name="chevron" size={15} /></span>
            </button>
            <ul className="account-menu" id="sidebar-account-actions" hidden={!open}>
                <li><a href="#profile" aria-current={tab === 'profile' ? 'page' : undefined} onClick={closeMenu}>
                    <Icon name="profile" size={18} />Profile settings
                </a></li>
                <li><button type="button" className="account-logout" onClick={() => { closeMenu(); onLogout(); }}>
                    <Icon name="logout" size={18} />Log out
                </button></li>
            </ul>
        </div>
    </aside>;
}
