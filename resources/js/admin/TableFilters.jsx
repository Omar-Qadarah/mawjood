import { Field, Icon } from './ui';

export function TableFilters({ label = 'Search', search, onSearch, placeholder, maxLength = 150, onClear, children }) {
    return <div className="table-filters" role="group" aria-label="Search and filters">
        <Field label={label}>
            <span className="table-search">
                <Icon name="search" size={16} />
                <input type="search" value={search} maxLength={maxLength} placeholder={placeholder}
                    onChange={(event) => onSearch(event.target.value)} />
            </span>
        </Field>
        {children}
        <button className="button secondary" type="button" onClick={onClear}>Clear filters</button>
    </div>;
}
