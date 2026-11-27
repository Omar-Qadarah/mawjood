import { useEffect, useId, useRef, useState } from 'react';
import { Icon, Modal } from './ui';

const shortcuts = [
    { label: 'Pending pharmacy approvals', href: '#pharmacies?status=pending', icon: 'pharmacies' },
    { label: 'Completed reservations', href: '#reservations?status=completed', icon: 'reservations' },
];

export function QuickJump({ sections }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const listId = useId();
    const input = useRef(null);
    const trigger = useRef(null);
    const returnFocus = useRef(null);
    const destinations = [...shortcuts, ...Object.entries(sections).map(([icon, label]) => ({ label, href: '#' + icon, icon }))];
    const matches = destinations.filter(({ label }) => label.toLowerCase().includes(search.trim().toLowerCase()));

    const selected = matches[selectedIndex];
    const selectedId = selected ? listId + '-' + selectedIndex : undefined;

    function openMenu() {
        returnFocus.current = document.activeElement === document.body ? trigger.current : document.activeElement;
        setSearch('');
        setSelectedIndex(0);
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

    useEffect(() => {
        if (open && selectedId) document.getElementById(selectedId)?.scrollIntoView({ block: 'nearest' });
    }, [open, selectedId]);

    function moveSelection(event) {
        if (event.nativeEvent.isComposing || !matches.length || (event.key !== 'ArrowDown' && event.key !== 'ArrowUp')) return;

        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        setSelectedIndex(index => (index + step + matches.length) % matches.length);
    }

    function navigate(event) {
        event.preventDefault();
        if (!selected) return;

        window.location.hash = selected.href;
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
                    <input ref={input} type="search" role="combobox" aria-autocomplete="list" aria-expanded={matches.length > 0}
                        aria-controls={listId} aria-activedescendant={selectedId} value={search}
                        onKeyDown={moveSelection} onChange={event => {
                            setSearch(event.target.value);
                            setSelectedIndex(0);
                        }} />
                </label>
                <ul id={listId} className="quick-jump-results" role="listbox" aria-label="Matching admin pages" hidden={!matches.length}>
                    {matches.map(({ label, href, icon }, index) =>
                        <li key={href} role="presentation"><a id={listId + '-' + index} role="option" tabIndex={-1}
                            aria-selected={index === selectedIndex} href={href} onClick={() => setOpen(false)}>
                            <Icon name={icon} size={17} />{label}
                        </a></li>)}
                </ul>
                {!matches.length && <p className="empty" role="status">No matching pages.</p>}
            </form>
        </Modal>}
    </div>;
}
