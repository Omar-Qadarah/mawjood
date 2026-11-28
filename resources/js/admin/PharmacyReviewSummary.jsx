import { useEffect, useRef, useState } from 'react';
import { api } from './api';
import { PharmacyApplicationComparison } from './PharmacyApplicationComparison';
import { Notice } from './ui';

export function PharmacyReviewSummary({ application, onApproved }) {
    const pharmacyId = application.id;
    const [comparisonId, setComparisonId] = useState(null);
    const comparisonHeading = useRef(null);
    const compareButton = useRef(null);
    const [summary, setSummary] = useState(null);
    const [error, setError] = useState('');
    const [generating, setGenerating] = useState(false);
    const [unavailable, setUnavailable] = useState(false);
    const generation = useRef(null);

    useEffect(() => {
        const request = new AbortController();
        api('/admin/pharmacies/' + pharmacyId + '/review-summary', { signal: request.signal })
            .then(({ data }) => { if (!request.signal.aborted) setSummary(data); })
            .catch(exception => { if (!request.signal.aborted) setError(exception.message); });
        return () => {
            request.abort();
            generation.current?.abort();
        };
    }, [pharmacyId]);

    const comparison = summary?.matches.find(match => match.id === comparisonId);

    useEffect(() => {
        if (comparisonId !== null) comparisonHeading.current?.focus();
    }, [comparisonId]);

    function closeComparison() {
        setComparisonId(null);
        compareButton.current?.focus();
    }

    async function generateSummary() {
        const request = new AbortController();
        generation.current = request;
        setGenerating(true);
        setError('');
        setUnavailable(false);
        try {
            const { data } = await api('/admin/pharmacies/' + pharmacyId + '/review-summary', {
                method: 'POST', signal: request.signal,
            });
            if (request.signal.aborted) return;

            setSummary(data);
            setUnavailable(data.source !== 'ai');
        } catch (exception) {
            if (!request.signal.aborted) setError(exception.message);
        } finally {
            if (!request.signal.aborted) setGenerating(false);
        }
    }

    return <section className="catalog-fieldset" aria-label="Application review summary">
        <h3>{summary?.source === 'ai' ? 'AI review summary' : 'Directory check'}</h3>
        <Notice error>{error}</Notice>
        {!summary && !error && <p role="status">Checking the directory…</p>}
        {summary && <>
            <p aria-live="polite">{summary.summary}</p>
            {unavailable && <Notice>AI summary is unavailable. The directory check is shown.</Notice>}
            {summary.source !== 'ai' && <button className="button secondary" type="button" disabled={generating} onClick={generateSummary}>
                {generating ? 'Generating…' : 'Generate AI summary'}
            </button>}
            {!!summary.matches.length && <ul>{summary.matches.map(match => <li key={match.id}>
                <button className="text-button" type="button" aria-pressed={comparisonId === match.id}
                    onClick={event => {
                        compareButton.current = event.currentTarget;
                        setComparisonId(match.id);
                    }}>Compare with {match.name_en}</button>
                {' · ' + match.city + ' · ' + (match.same_name && match.same_address ? 'Matching name and address' :
                    match.same_name ? 'Matching name' : match.same_address ? 'Matching address' : 'Possible match')}
            </li>)}</ul>}
            {comparison && <PharmacyApplicationComparison application={application} match={comparison}
                headingRef={comparisonHeading} onClose={closeComparison} onApproved={onApproved} />}
        </>}
    </section>;
}
