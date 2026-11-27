import { useState } from 'react';

const dayLabel = new Intl.DateTimeFormat('en', { weekday: 'short', timeZone: 'UTC' });
const dateLabel = new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', timeZone: 'UTC' });

const rangeLabel = (start, end) => start.getTime() === end.getTime() ? dateLabel.format(start) : dateLabel.formatRange(start, end);

export function RegistrationTrend({ registrations }) {
    const [days, setDays] = useState(7);
    const data = registrations?.slice(-days) ?? [];
    const total = data.reduce((sum, day) => sum + day.total, 0);
    const groupSize = days === 30 ? 5 : 1;
    const columns = [];
    for (let index = 0; index < data.length; index += groupSize) {
        const group = data.slice(index, index + groupSize);
        const start = new Date(group[0].date + 'T00:00:00Z');
        const end = new Date(group.at(-1).date + 'T00:00:00Z');
        columns.push({
            key: group[0].date,
            label: days === 7 ? dayLabel.format(start) : rangeLabel(start, end),
            dates: rangeLabel(start, end),
            count: group.reduce((sum, day) => sum + day.total, 0),
        });
    }
    const peak = Math.max(1, ...columns.map(column => column.count));
    const interval = Math.ceil(peak / 4);
    const scale = interval * 4;
    const ticks = [scale, interval * 3, interval * 2, interval, 0];

    return <section className="panel trend-panel" aria-labelledby="registrations-heading">
        <header className="panel-header">
            <div><h2 id="registrations-heading">Pharmacy registrations</h2><p>New listings · last {days} days</p></div>
            <div className="trend-controls" aria-label="Registration period">
                {[7, 30].map(value => <button key={value} type="button" aria-pressed={days === value}
                    onClick={() => setDays(value)}>{value} days</button>)}
            </div>
        </header>
        <div className="trend-body">
            <div className="trend-summary">
                <strong className="trend-total">{registrations ? total.toLocaleString() : '—'}</strong>
                <span>{total === 1 ? 'new pharmacy' : 'new pharmacies'}</span>
            </div>
            <div className={days === 30 ? "trend-plot trend-plot-grouped" : "trend-plot"}>
                <div className="trend-axis" aria-hidden="true">{ticks.map(tick => <span key={tick}>{tick}</span>)}</div>
                <div className="trend-gridlines" aria-hidden="true">{ticks.map(tick => <span key={tick} />)}</div>
                <div className="trend-chart" role="group" aria-label={days === 30 ? 'Registrations per five-day period' : 'Daily registration counts'}
                    style={{ '--chart-columns': Math.ceil(days / groupSize) }}>
                    {columns.map(({ key, label, dates, count }) => <div className="trend-column" key={key} title={dates + ' · ' + count + ' registrations'}>
                        <div className="trend-track"><span style={{ height: (count / scale * 100) + '%' }} /></div>
                        <span className="trend-label" aria-hidden="true">{label}</span>
                        <span className="sr-only">{dates}: {count}</span>
                    </div>)}
                </div>
            </div>
        </div>
    </section>;
}
