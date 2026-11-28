import { createContext, useContext, useEffect, useRef, useState } from 'react';

export const TableFilterAccount = createContext(null);

function readFilters(key, defaults) {
    if (!key) return defaults;

    try {
        const saved = JSON.parse(window.localStorage.getItem(key));
        const filters = Object.entries(defaults).map(([name, fallback]) => [
            name, typeof fallback === 'string' && typeof saved?.[name] === 'string' ? saved[name] : fallback,
        ]);
        return Object.fromEntries(filters);
    } catch {
        return defaults;
    }
}

function saveFilters(key, filters) {
    if (!key) return;

    try {
        const selected = Object.entries(filters).filter(([, value]) => typeof value === 'string' && value !== '');
        if (selected.length) window.localStorage.setItem(key, JSON.stringify(Object.fromEntries(selected)));
        else window.localStorage.removeItem(key);
    } catch {
        // The filters still work when browser storage is unavailable.
    }
}

export function useSavedTableFilters(table, defaults, routeFilters = {}) {
    const account = useContext(TableFilterAccount);
    const key = account == null ? null : `mawjood.admin.tableFilters.${account}.${table}`;
    const [filters, setFilters] = useState(() => ({ ...readFilters(key, defaults), ...routeFilters }));
    const initialFilters = useRef(filters);
    const hasRouteFilters = Object.keys(routeFilters).length > 0;

    useEffect(() => {
        if (hasRouteFilters) saveFilters(key, initialFilters.current);
    }, [key, hasRouteFilters]);

    function updateFilters(changes) {
        const next = { ...filters, ...changes };
        setFilters(next);
        saveFilters(key, next);
    }

    function clearFilters() {
        setFilters(defaults);
        saveFilters(key, defaults);
    }

    return [filters, updateFilters, clearFilters];
}

