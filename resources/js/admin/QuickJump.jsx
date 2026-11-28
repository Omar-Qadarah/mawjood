import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { api } from './api';
import { Icon, Modal } from './ui';

const shortcuts = [
    { label: 'Pending pharmacy approvals', href: '#pharmacies?status=pending', icon: 'pharmacies' },
    { label: 'Completed reservations', href: '#reservations?status=completed', icon: 'reservations' },
];

const recordTypes = {
    pharmacies: { name: 'name_en', label: 'Pharmacy' },
    medicines: { name: 'name_en', label: 'Medicine' },
    customers: { name: 'name', label: 'Customer' },
};

export function QuickJump({ sections, onOpenRecord, onOpenFromTrigger }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [recordSearch, setRecordSearch] = useState(null);
    const [selectedIndex, setSelectedIndex] = useState(0);
    const listId = useId();
    const input = useRef(null);
    const trigger = useRef(null);
    const returnFocus = useRef(null);
    const destinations = [...shortcuts, ...Object.entries(sections).map(([icon, label]) => ({ label, href: '#' + icon, icon }))];
    const term = search.trim();
    const currentSearch = term === recordSearch?.term ? recordSearch : null;
    const loadingRecords = term.length >= 2 && !currentSearch;
    const pages = destinations.filter(({ label }) => label.toLowerCase().includes(term.toLowerCase()));
    const matches = [...pages, ...(currentSearch?.results ?? [])];

    const selected = matches[selectedIndex];
    const selectedId = selected ? listId + '-' + selectedIndex : undefined;

    function openMenu(focusTarget) {
        returnFocus.current = focusTarget ?? (document.activeElement === document.body ? trigger.current : document.activeElement);
        setSearch('');
        setRecordSearch(null);
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

    useEffect(() => {
        if (!open || term.length < 2) return;

        const request = new AbortController();
        const timer = setTimeout(async () => {
            const query = new URLSearchParams({ name: term });
            const results = await Promise.allSettled(Object.entries(recordTypes).map(async ([entity, type]) => {
                const { data } = await api('/admin/' + entity + '?' + query, { signal: request.signal });
                return data.map(record => ({
                    label: record[type.name],
                    context: entity === 'customers' ? record.email : record.name_ar,
                    category: type.label,
                    icon: entity,
                    record,
                }));
            }));
            if (request.signal.aborted) return;

            setRecordSearch({
                term,
                results: results.flatMap(result => result.status === 'fulfilled' ? result.value : []),
                incomplete: results.some(result => result.status === 'rejected'),
            });
        }, 180);

        return () => {
            clearTimeout(timer);
            request.abort();
        };
    }, [open, term]);

    function moveSelection(event) {
        if (event.nativeEvent.isComposing || !matches.length || (event.key !== 'ArrowDown' && event.key !== 'ArrowUp')) return;

        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        setSelectedIndex(index => (index + step + matches.length) % matches.length);
    }

    function choose(match) {
        setOpen(false);
        if (match.record) onOpenRecord(match.icon, match.record);
        else window.location.hash = match.href;
    }

    function navigate(event) {
        event.preventDefault();
        if (selected) choose(selected);
    }

    return <div className="quick-jump">
        <button ref={trigger} type="button" className="quick-jump-trigger" aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K"
            onClick={() => openMenu(onOpenFromTrigger?.() ?? trigger.current)}>
            <Icon name="search" size={16} /> Quick jump <kbd aria-hidden="true">Ctrl/⌘ K</kbd>
        </button>
        {open && createPortal(<Modal title="Quick jump" onClose={() => setOpen(false)}>
            <form className="quick-jump-form" onSubmit={navigate}>
                <label className="field">
                    <span>Find a page, pharmacy, medicine, or customer</span>
                    <input ref={input} type="search" role="combobox" aria-autocomplete="list" aria-expanded={matches.length > 0}
                        aria-controls={listId} aria-activedescendant={selectedId} maxLength={100} value={search}
                        onKeyDown={moveSelection} onChange={event => {
                            setSearch(event.target.value);
                            setSelectedIndex(0);
                        }} />
                </label>
                <ul id={listId} className="quick-jump-results" role="listbox" aria-label="Quick jump results" hidden={!matches.length}>
                    {matches.map((match, index) => {
                        const { label, href, icon, record, category, context } = match;
                        const props = { id: listId + '-' + index, role: 'option', tabIndex: -1,
                            'aria-selected': index === selectedIndex };
                        return <li key={href ?? icon + '-' + record.id} role="presentation">
                            {record ? <button {...props} type="button" onClick={() => choose(match)}>
                                <Icon name={icon} size={17} />
                                <span>{label}{context && <small>{context}</small>}</span>
                                <small className="quick-jump-category">{category}</small>
                            </button> : <a {...props} href={href} onClick={(event) => {
                                event.preventDefault();
                                choose(match);
                            }}>
                                <Icon name={icon} size={17} />{label}
                            </a>}
                        </li>;
                    })}
                </ul>
                {loadingRecords && <p className="empty" role="status">Searching records…</p>}
                {currentSearch?.incomplete && <p className="empty" role="status">Some records could not be searched.</p>}
                {!matches.length && !loadingRecords && !currentSearch?.incomplete && <p className="empty" role="status">
                    {term.length < 2 ? 'Type at least two characters to search records.' : 'No matching pages or records.'}
                </p>}
            </form>
        </Modal>, document.body)}
    </div>;
}
