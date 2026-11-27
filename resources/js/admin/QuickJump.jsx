import { useEffect, useRef, useState } from 'react';
import { Icon, Modal } from './ui';

const shortcuts = [
    { label: 'Pending pharmacy approvals', href: '#pharmacies?status=pending', icon: 'pharmacies' },
    { label: 'Completed reservations', href: '#reservations?status=completed', icon: 'reservations' },
];

export function QuickJump({ sections }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const input = useRef(null);
    const trigger = useRef(null);
    const returnFocus = useRef(null);
    const destinations = [...shortcuts, ...Object.entries(sections).map(([icon, label]) => ({ label, href: '#' + icon, icon }))];
    const matches = destinations.filter(({ label }) => label.toLowerCase().includes(search.trim().toLowerCase()));

    function openMenu() {
        returnFocus.current = document.activeElement === document.body ? trigger.current : document.activeElement;
        setSearch('');
        setOpen(true);
    }

    useEffect(() => {
        const desktop = window.matchMedia('(min-width: 1024.01px)');

        function shortcut(event) {
            if (!desktop.matches || event.key.toLowerCase() !== 'k' || !(event.ctrlKey || event.metaKey)
                || event.altKey || event.shiftKey || document.querySelector('dialog[open]')) return;

            event.preventDefault();
            openMenu();
        }

        function closeOnCompactLayout(event) {
            if (!event.matches) setOpen(false);
        }

        window.addEventListener('keydown', shortcut);
        desktop.addEventListener('change', closeOnCompactLayout);
        return () => {
            window.removeEventListener('keydown', shortcut);
            desktop.removeEventListener('change', closeOnCompactLayout);
        };
    }, []);

    useEffect(() => {
        if (open) {
            input.current?.focus();
        } else if (returnFocus.current) {
            if (returnFocus.current.isConnected) returnFocus.current.focus();
            returnFocus.current = null;
        }
    }, [open]);

    function navigate(event) {
        event.preventDefault();
        if (!matches.length) return;

        window.location.hash = matches[0].href;
        setOpen(false);
    }

    return <div className="quick-jump">
        <button ref={trigger} type="button" className="quick-jump-trigger" aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K" onClick={openMenu}>
            <Icon name="search" size={16} /> Quick jump <kbd aria-hidden="true">Ctrl/⌘ K</kbd>
        </button>
        {open && <Modal title="Quick jump" onClose={() => setOpen(false)}>
            <form className="quick-jump-form" onSubmit={navigate}>
                <label className="field">
                    <span>Find an admin page</span>
                    <input ref={input} type="search" value={search} onChange={event => setSearch(event.target.value)} />
                </label>
                {matches.length ? <ul className="quick-jump-results">{matches.map(({ label, href, icon }) =>
                    <li key={href}><a href={href} onClick={() => setOpen(false)}><Icon name={icon} size={17} />{label}</a></li>)}
                </ul> : <p className="empty" role="status">No matching pages.</p>}
            </form>
        </Modal>}
    </div>;
}
