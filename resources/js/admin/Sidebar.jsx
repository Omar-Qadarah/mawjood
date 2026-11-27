import { useEffect, useRef, useState } from 'react';
import { Brand, Icon } from './ui';

export const sections = { overview: 'Dashboard', pharmacies: 'Pharmacies', medicines: 'Medicines', inventory: 'Inventory', reservations: 'Reservations', customers: 'Customers', profile: 'Profile settings' };
const navigation = Object.entries(sections).filter(([key]) => key !== 'profile');

export function Sidebar({ user, tab, onLogout }) {
    const [menu, setMenu] = useState(null);
    const account = useRef(null);
    const accountTrigger = useRef(null);
    const nav = useRef(null);
    const navTrigger = useRef(null);
    const swipe = useRef(null);

    useEffect(() => {
        if (!menu) return;
        const panel = menu === 'account' ? account : nav;
        const trigger = menu === 'account' ? accountTrigger : navTrigger;

        function dismiss(event) {
            if (!panel.current?.contains(event.target) && !trigger.current?.contains(event.target)) setMenu(null);
        }

        function escape(event) {
            if (event.key === 'Escape') {
                setMenu(null);
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
    }, [menu]);

    function closeMenu() {
        setMenu(null);
        accountTrigger.current?.focus();
    }

    function closeNavigation() {
        setMenu(null);
        navTrigger.current?.focus();
    }

    function startSwipe(event) {
        swipe.current = null;
        if (menu === 'navigation' && event.pointerType === 'touch' && event.isPrimary) {
            swipe.current = { pointerId: event.pointerId, x: event.clientX, y: event.clientY };
        }
    }

    function finishSwipe(event) {
        const start = swipe.current;
        swipe.current = null;
        if (menu !== 'navigation' || start?.pointerId !== event.pointerId) return;

        const left = start.x - event.clientX;
        const vertical = Math.abs(start.y - event.clientY);
        if (left >= 60 && left > vertical * 1.5) {
            swipe.current = { closedDrawer: true };
            closeNavigation();
        }
    }

    function preventSwipeClick(event) {
        if (!swipe.current?.closedDrawer || event.detail === 0) return;

        swipe.current = null;
        event.preventDefault();
        event.stopPropagation();
    }

    return <aside className="sidebar">
        <button className="icon-button navigation-trigger" type="button" ref={navTrigger} aria-label="Navigation menu"
            aria-expanded={menu === 'navigation'} aria-controls="sidebar-navigation"
            onClick={() => setMenu(value => value === 'navigation' ? null : 'navigation')}>
            <Icon name="menu" size={22} />
        </button>
        <Brand />
        <div className="navigation-backdrop" aria-hidden="true" hidden={menu !== 'navigation'} />
        <nav id="sidebar-navigation" ref={nav} aria-label="Dashboard" data-open={menu === 'navigation'}
            onPointerDown={startSwipe} onPointerUp={finishSwipe} onPointerCancel={() => { swipe.current = null; }}
            onClickCapture={preventSwipeClick}>
            <button className="icon-button navigation-close" type="button" aria-label="Close navigation menu" onClick={closeNavigation}>
                <Icon name="close" size={22} />
            </button>
            {navigation.map(([key, title]) =>
                <a key={key} href={'#' + key} aria-current={tab === key ? 'page' : undefined}
                    onClick={menu === 'navigation' ? closeNavigation : undefined}><Icon name={key} />{title}</a>)}
        </nav>
        <div className="sidebar-account" ref={account}>
            <button className="account-trigger" type="button" ref={accountTrigger} aria-label="Account options"
                aria-expanded={menu === 'account'} aria-controls="sidebar-account-actions" onClick={() => setMenu(value => value === 'account' ? null : 'account')}>
                <span className="avatar" aria-hidden="true">
                    {user.avatar_url ? <img src={user.avatar_url} alt="" /> : (user.name?.slice(0, 1).toUpperCase() ?? 'A')}
                </span>
                <span className="account-identity">{user.name}<small>Administrator</small></span>
                <span className="account-chevron"><Icon name="chevron" size={15} /></span>
            </button>
            <ul className="account-menu" id="sidebar-account-actions" hidden={menu !== 'account'}>
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
