import { ResetSortButton } from './TableSort';
import { Field, Icon } from './ui';

export function TableFilters({ label = 'Search', search, onSearch, placeholder, maxLength = 150, onClear, sort, onSortReset, children }) {
    return <div className="table-filters" role="group" aria-label="Search and filters">
        <Field label={label}>
            <span className="table-search">
                <Icon name="search" size={16} />
                <input type="search" value={search} maxLength={maxLength} placeholder={placeholder}
                    onChange={(event) => onSearch(event.target.value)} />
            </span>
        </Field>
        {children && <div className="table-filter-options">{children}</div>}
        <div className="table-controls">
            <button className="button secondary" type="button" onClick={onClear}>Clear filters</button>
            <ResetSortButton sort={sort} onReset={onSortReset} />
        </div>
        <span className="table-swipe-hint">Swipe for more columns</span>
    </div>;
}
