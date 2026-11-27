import { useCallback, useRef } from 'react';

export function useTableLoader() {
    const previousSearch = useRef('');

    return useCallback((search, load) => {
        const typing = search !== '' && search !== previousSearch.current;
        previousSearch.current = search;
        if (typing) {
            const timer = setTimeout(load, 150);
            return () => clearTimeout(timer);
        }
        load();
    }, []);
}
