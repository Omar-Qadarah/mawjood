import { useEffect, useRef, useState } from 'react';
import { api } from './api';
import { PharmacyApplicationComparison } from './PharmacyApplicationComparison';
import { Icon, Notice } from './ui';

export function PharmacyReviewSummary({ application, onApproved, children }) {
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
        requestAnimationFrame(() => compareButton.current?.focus());
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


    return <section className="application-review" aria-label="Application review summary">
        <div className="review-overview">
            <header className="review-overview-header">
                <span className="review-overview-icon"><Icon name="search" size={17} /></span>
                <h3>{summary?.source === 'ai' ? 'AI review summary' : 'Directory check'}</h3>
                {summary && summary.source !== 'ai' && <button className="button secondary" type="button" disabled={generating} onClick={generateSummary}>
                    {generating ? 'Generating…' : 'Generate AI summary'}
                </button>}
            </header>
            <Notice error>{error}</Notice>
            {!summary && !error && <p role="status">Checking the directory…</p>}
            {summary && <p aria-live="polite">{summary.summary}</p>}
            {unavailable && <Notice>AI summary is unavailable. The directory check is shown.</Notice>}
        </div>
        {!!summary?.matches.length && <div className="review-matches" hidden={Boolean(comparison)}>
            <h3>Directory matches</h3>
            <ul>{summary.matches.map(match => <li key={match.id}>
                <button className="review-match" type="button" aria-label={'Compare with ' + match.name_en}
                    onClick={event => {
                        compareButton.current = event.currentTarget;
                        setComparisonId(match.id);
                    }}>
                    <span className="review-match-name">
                        <strong>{match.name_en}</strong>
                        <small>{match.city} · {match.license_no}</small>
                    </span>
                    <span className="review-match-reason">{match.same_name && match.same_address ? 'Matching name and address' :
                        match.same_name ? 'Matching name' : match.same_address ? 'Matching address' : 'Possible match'}</span>
                    <span className="review-match-action" aria-hidden="true">Compare <Icon name="arrow" size={15} /></span>
                </button>
            </li>)}</ul>
        </div>}
        {comparison && <PharmacyApplicationComparison application={application} match={comparison}
            headingRef={comparisonHeading} onClose={closeComparison} onApproved={onApproved} />}
        {!comparison && children}
    </section>;
}
