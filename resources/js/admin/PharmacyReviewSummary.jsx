import { useEffect, useState } from 'react';
import { api } from './api';
import { Notice } from './ui';

export function PharmacyReviewSummary({ pharmacyId, onOpenRelated }) {
    const [summary, setSummary] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        const request = new AbortController();
        api('/admin/pharmacies/' + pharmacyId + '/review-summary', { signal: request.signal })
            .then(({ data }) => { if (!request.signal.aborted) setSummary(data); })
            .catch(exception => { if (!request.signal.aborted) setError(exception.message); });
        return () => request.abort();
    }, [pharmacyId]);

    return <section className="catalog-fieldset" aria-label="Application review summary">
        <h3>Directory check</h3>
        <Notice error>{error}</Notice>
        {!summary && !error && <p role="status">Checking the directory…</p>}
        {summary && <>
            <p>{summary.summary}</p>
            {!!summary.matches.length && <ul>{summary.matches.map(match => <li key={match.id}>
                <button className="text-button" type="button" onClick={() => onOpenRelated(match.id)}>{match.name_en}</button>
                {' · ' + match.city + ' · ' + (match.same_name && match.same_address ? 'Matching name and address' :
                    match.same_name ? 'Matching name' : match.same_address ? 'Matching address' : 'Possible match')}
            </li>)}</ul>}
        </>}
    </section>;
}
