import { useState } from 'react';

const dayLabel = new Intl.DateTimeFormat('en', { weekday: 'short', timeZone: 'UTC' });
const dateLabel = new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', timeZone: 'UTC' });

export function RegistrationTrend({ registrations }) {
    const [days, setDays] = useState(7);
    const data = registrations?.slice(-days) ?? [];
    const total = data.reduce((sum, day) => sum + day.total, 0);
    const peak = Math.max(1, ...data.map(day => day.total));

    return <section className="panel trend-panel" aria-labelledby="registrations-heading">
        <header className="panel-header">
            <div><h2 id="registrations-heading">Pharmacy registrations</h2><p>New listings · last {days} days</p></div>
            <div className="trend-controls" aria-label="Registration period">
                {[7, 30].map(value => <button key={value} type="button" aria-pressed={days === value}
                    onClick={() => setDays(value)}>{value} days</button>)}
            </div>
        </header>
        <div className="trend-body">
            <strong className="trend-total">{registrations ? total.toLocaleString() : '—'}</strong>
            <div className="trend-chart" role="group" aria-label="Daily registration counts"
                style={{ '--chart-days': days }}>
                {data.map(({ date, total: count }, index) => {
                    const day = new Date(date + 'T00:00:00Z');
                    return <div className="trend-column" key={date} title={dateLabel.format(day) + ' · ' + count + ' registrations'}>
                        <div className="trend-track"><span style={{ height: (count / peak * 100) + '%' }} /></div>
                        <span className="trend-label" aria-hidden="true">{days === 7 ? dayLabel.format(day) :
                            index % 7 === 0 ? dateLabel.format(day) : ''}</span>
                        <span className="sr-only">{dateLabel.format(day)}: {count}</span>
                    </div>;
                })}
            </div>
        </div>
    </section>;
}
