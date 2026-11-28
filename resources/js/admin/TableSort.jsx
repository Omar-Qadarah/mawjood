import { useState } from 'react';

const textOrder = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

export function useTableSort(defaultColumn, defaultDirection = 'asc') {
    const [selection, setSelection] = useState(null);
    const { column, direction } = selection ?? { column: defaultColumn, direction: defaultDirection };

    function toggle(key) {
        setSelection({ column: key, direction: key === column && direction === 'asc' ? 'desc' : 'asc' });
    }

    return { column, direction, toggle, params: selection ? { sort: column, direction } : {} };
}

export function TableHead({ columns, sort, onSort }) {
    return <thead><tr>{columns.map(([label, key]) => {
        const active = Boolean(key && sort.column === key);
        return <th key={label} scope="col" aria-sort={active ? (sort.direction === 'asc' ? 'ascending' : 'descending') : undefined}>
            {key ? <button type="button" className="table-sort" onClick={() => {
                sort.toggle(key);
                onSort?.();
            }}>{label}{active && <span aria-hidden="true">{sort.direction === 'asc' ? '↑' : '↓'}</span>}</button> : label}
        </th>;
    })}</tr></thead>;
}

export function sortRows(rows, sort, readValue) {
    if (!sort.column) return rows;

    return [...rows].sort((left, right) => {
        const a = readValue(left, sort.column);
        const b = readValue(right, sort.column);
        if (a == null || b == null) return Number(a == null) - Number(b == null);

        const order = typeof a === 'number' && typeof b === 'number' ? a - b : textOrder.compare(a, b);
        return sort.direction === 'asc' ? order : -order;
    });
}
