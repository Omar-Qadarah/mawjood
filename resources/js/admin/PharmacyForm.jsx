import { useState } from 'react';
import { places } from './api';
import { Field, Icon, Modal, Notice } from './ui';
import { LocationPicker } from './JordanMap';

export const weekdays = [
    ['sunday', 'Sunday'], ['monday', 'Monday'], ['tuesday', 'Tuesday'], ['wednesday', 'Wednesday'],
    ['thursday', 'Thursday'], ['friday', 'Friday'], ['saturday', 'Saturday'],
];

function readWeeklyHours(hours) {
    if (Array.isArray(hours)) return hours.filter((slot) => slot?.day);
    return weekdays.flatMap(([day]) => {
        const entry = typeof hours?.[day] === 'string' ? hours[day].match(/^(\d{2}:\d{2})\s*[-–]\s*(\d{2}:\d{2})$/) : null;
        return entry ? [{ day, opens_at: entry[1], closes_at: entry[2] }] : [];
    });
}

function WeeklyHours({ schedule, onChange, hasLegacy }) {
    function toggle(day) {
        const isSelected = schedule.some((slot) => slot.day === day);
        onChange(isSelected ? schedule.filter((slot) => slot.day !== day) : [...schedule, { day, opens_at: '09:00', closes_at: '17:00' }]);
    }

    return <fieldset className="wide fieldset weekly-hours"><legend>Weekly opening hours</legend>
        <p>Select the days the pharmacy opens and set the hours for each day.</p>
        {hasLegacy && <p className="help-note">Previously saved hours are kept unless you change this weekly schedule.</p>}
        <div className="day-buttons" aria-label="Weekdays">
            {weekdays.map(([day, label]) => <button className="day-button" aria-label={label} aria-pressed={schedule.some((slot) => slot.day === day)} type="button" key={day} onClick={() => toggle(day)}>{label.slice(0, 3)}</button>)}
        </div>
        {weekdays.map(([day, label]) => {
            const indices = schedule.flatMap((slot, index) => slot.day === day ? [index] : []);
            if (!indices.length) return null;

            return <div className="weekly-day" key={day}>
                <strong>{label}</strong>
                <div className="time-slots">{indices.map((index, position) => <div className="time-slot" key={index}>
                    {['opens_at', 'closes_at'].map((field) => {
                        const action = field === 'opens_at' ? 'opens' : 'closes';
                        return <Field key={field} label={action === 'opens' ? 'Opens' : 'Closes'}>
                            <input type="time" required name={'slot-' + index + '-' + field} aria-label={label + ' ' + action + (position ? ' slot ' + (position + 1) : '')}
                                value={schedule[index][field]} onInput={(event) => onChange(schedule.map((entry, entryIndex) =>
                                    entryIndex === index ? { ...entry, [field]: event.target.value } : entry))} />
                        </Field>;
                    })}
                    {indices.length > 1 && <button type="button" className="icon-button" aria-label={'Remove ' + label + ' slot ' + (position + 1)}
                        onClick={() => onChange(schedule.filter((_, entryIndex) => entryIndex !== index))}><Icon name="close" size={15} /></button>}
                </div>)}
                <button type="button" className="text-button" onClick={() => onChange([...schedule, { day, opens_at: '', closes_at: '' }])}>Add time slot</button>
                </div>
            </div>;
        })}
    </fieldset>;
}

const fields = [
    ['name_ar', 'Arabic name'], ['name_en', 'English name'], ['license_no', 'License number'],
    ['phone', 'Jordan phone'], ['email', 'Email'], ['address', 'Address'],
];

export function PharmacyForm({ pharmacy, onClose, onSave }) {
    const [governorate, setGovernorate] = useState(pharmacy?.governorate || '');
    const [city, setCity] = useState(pharmacy?.city || '');
    const [coordinates, setCoordinates] = useState(pharmacy ? { latitude: Number(pharmacy.latitude), longitude: Number(pharmacy.longitude) } : null);
    const [weekly, setWeekly] = useState(() => readWeeklyHours(pharmacy?.opening_hours));
    const [hoursChanged, setHoursChanged] = useState(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const cities = places[governorate]?.cities ?? [];
    const savedHours = pharmacy?.opening_hours;
    const hasLegacy = savedHours != null && (!Array.isArray(savedHours) || savedHours.some((slot) => !slot?.day));

    async function submit(event) {
        event.preventDefault();
        setError('');
        const form = new FormData(event.currentTarget);
        const body = Object.fromEntries([...form].filter(([key]) => !key.startsWith('slot-')));
        const schedule = weekly.map(({ day }, index) => ({
            day,
            opens_at: form.get('slot-' + index + '-opens_at'),
            closes_at: form.get('slot-' + index + '-closes_at'),
        }));
        if (!coordinates) {
            setError('Place the pharmacy pin on the map before saving.');
            return;
        }
        if (!governorate) delete body.governorate;

        if (pharmacy) {
            for (const [key] of fields) {
                if (body[key] === (pharmacy[key] ?? '')) delete body[key];
            }
        }

        if (body.name_ar !== undefined && !/^(?=.*(?=\p{Script=Arabic})\p{L})(?:(?=\p{Script=Arabic})\p{L}|\p{M}|[ .،&’'–-])+$/u.test(body.name_ar)) {
            setError('Enter the Arabic name using Arabic letters.');
            return;
        }
        if (body.name_en !== undefined && !/^(?=.*[A-Za-z])[A-Za-z .&’'–-]+$/.test(body.name_en)) {
            setError('Enter the English name using English letters.');
            return;
        }

        if (hoursChanged || !pharmacy || JSON.stringify(schedule) !== JSON.stringify(weekly)) {
            if (schedule.some((slot) => slot.closes_at <= slot.opens_at)) {
                setError('Each closing time must be later than its opening time.');
                return;
            }
            body.opening_hours = schedule;
        }

        setBusy(true);
        try {
            await onSave({ ...body, ...coordinates });
            onClose();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <Modal title={pharmacy ? 'Edit pharmacy' : 'Add pharmacy'} onClose={() => { if (!busy) onClose(); }} wide>
        <form className="form-grid" onSubmit={submit}>
            <div className="wide"><Notice error>{error}</Notice></div>
            {fields.map(([key, label]) => <Field label={label} key={key} wide={key === 'address'}>
                <input name={key} dir={key === 'name_ar' ? 'rtl' : undefined} lang={key === 'name_ar' ? 'ar' : undefined}
                    type={key === 'phone' ? 'tel' : key === 'email' ? 'email' : 'text'}
                    defaultValue={pharmacy?.[key] ?? ''} maxLength={key === 'address' ? 255 : key === 'license_no' ? 100 : key === 'phone' ? 30 : key === 'email' ? 190 : 150}
                    required={!['phone', 'email'].includes(key)} placeholder={key === 'phone' ? '079 123 4567' : undefined} />
            </Field>)}
            <Field label="Governorate"><select name="governorate" value={governorate} required={!pharmacy || Boolean(pharmacy.governorate)}
                onChange={(event) => { setGovernorate(event.target.value); setCity(''); }}>
                <option value="">Choose a governorate</option>{Object.keys(places).map((name) => <option key={name}>{name}</option>)}
            </select></Field>
            <Field label="City"><select name="city" value={city} onChange={(event) => setCity(event.target.value)} required>
                <option value="">Choose a city</option>{cities.map((name) => <option key={name}>{name}</option>)}
                {city && !cities.includes(city) && <option value={city}>{city} (existing)</option>}
            </select></Field>
            <LocationPicker coordinates={coordinates} governorate={governorate} center={places[governorate]?.center} onChange={setCoordinates} />
            <Field label="Status"><select name="status" defaultValue={pharmacy?.status ?? 'pending'}>
                {['pending', 'active', 'suspended'].map((status) => <option key={status} value={status}>{status[0].toUpperCase() + status.slice(1)}</option>)}
            </select></Field>
            <WeeklyHours schedule={weekly} hasLegacy={hasLegacy && !hoursChanged} onChange={(next) => { setWeekly(next); setHoursChanged(true); }} />
            <footer className="form-actions wide"><button type="button" className="button secondary" onClick={onClose} disabled={busy}>Cancel</button><button className="button primary" disabled={busy}>{busy ? 'Saving…' : pharmacy ? 'Save changes' : 'Create pharmacy'}</button></footer>
        </form>
    </Modal>;
}
