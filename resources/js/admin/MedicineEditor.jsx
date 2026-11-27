import { useEffect, useRef, useState } from 'react';
import { api } from './api';
import { Field, Modal, Notice } from './ui';

const sections = [
    ['Package', [
        ['name_en', 'English product name', 200, true], ['name_ar', 'Arabic product name', 200],
        ['generic_name_en', 'English generic name', 200, true], ['generic_name_ar', 'Arabic generic name', 200],
        ['brand_name', 'Brand', 150], ['manufacturer_name', 'Manufacturer', 150, true],
        ['dosage_form', 'Dosage form', 40, true], ['route', 'Route', 40, true],
        ['pack_size', 'Package size', 'number', true], ['pack_unit', 'Package unit', 30, true],
        ['dispensing_class', 'Dispensing class', 30, true], ['catalog_status', 'Catalog status', 20, true],
    ]],
    ['Regulatory and pricing', [
        ['barcode', 'Barcode', 64], ['jfda_registration_no', 'JFDA registration number', 100],
        ['registration_status', 'Registration status', 30], ['registration_expiry', 'Registration expiry', 'date'],
        ['official_price', 'Official price', 'number'], ['currency', 'Currency', 3, true],
        ['official_price_effective_date', 'Price effective date', 'date'],
    ]],
];
const defaults = { currency: 'JOD', catalog_status: 'active' };

function ComponentEditor({ component, index, change, remove, removable }) {
    const [search, setSearch] = useState('');
    const [options, setOptions] = useState([]);
    const [error, setError] = useState('');

    useEffect(() => {
        let current = true;
        const timer = setTimeout(async () => {
            try {
                const { data } = await api('/admin/active-ingredients?' + new URLSearchParams({ search }));
                if (current) { setOptions(data); setError(''); }
            } catch (exception) {
                if (current) setError(exception.message);
            }
        }, 150);
        return () => { current = false; clearTimeout(timer); };
    }, [search]);

    const available = options.some((entry) => entry.id === component.active_ingredient_id)
        ? options : component.active_ingredient_id ? [{ id: component.active_ingredient_id, name_en: component.name_en }, ...options] : options;

    return <fieldset className="catalog-component">
        <legend>Ingredient {index + 1}</legend>
        <Notice error>{error}</Notice>
        <div className="form-grid">
            <Field label={'Find ingredient ' + (index + 1)}>
                <input type="search" value={search} maxLength={150} onChange={(event) => setSearch(event.target.value)} placeholder="Search reusable ingredients" />
            </Field>
            <Field label={'Active ingredient ' + (index + 1)}>
                <select required value={component.active_ingredient_id ?? ''} onChange={(event) => {
                    const chosen = available.find((entry) => entry.id === Number(event.target.value));
                    change({ active_ingredient_id: chosen?.id ?? null, name_en: chosen?.name_en ?? '' });
                }}>
                    <option value="">Choose an ingredient</option>
                    {available.map((entry) => <option value={entry.id} key={entry.id}>{entry.name_en}</option>)}
                </select>
            </Field>
            {[
                ['strength_value', 'Strength value', true], ['strength_unit', 'Strength unit', true],
                ['per_value', 'Per value', false], ['per_unit', 'Per unit', false],
            ].map(([key, label, required]) => <Field key={key} label={label + ' ' + (index + 1)}>
                <input type={key.endsWith('_value') ? 'number' : 'text'}
                    step={key.endsWith('_value') ? '0.0001' : undefined}
                    min={key.endsWith('_value') ? '0.0001' : undefined}
                    maxLength={key.endsWith('_unit') ? 10 : undefined}
                    value={component[key] ?? ''} required={required}
                    onChange={(event) => change({ [key]: event.target.value })} />
            </Field>)}
        </div>
        <button className="button secondary small" type="button" disabled={!removable} onClick={remove}>Remove ingredient</button>
    </fieldset>;
}

function EditorForm({ record, busy, onSave, onClose }) {
    const nextRow = useRef(1);
    const [components, setComponents] = useState(() => record?.ingredients.length
        ? record.ingredients.map((ingredient) => ({ ...ingredient, key: 'saved-' + ingredient.id }))
        : [{ key: 'new-0' }]);

    function submit(event) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget));
        data.currency = data.currency.trim().toUpperCase();
        data.ingredients = components.map(({ active_ingredient_id, strength_value, strength_unit, per_value, per_unit }) => ({
            active_ingredient_id, strength_value, strength_unit, per_value: per_value || null, per_unit: per_unit || null,
        }));
        onSave(data);
    }

    return <form onSubmit={submit}>
        {sections.map(([title, fields]) => <fieldset className="catalog-fieldset" key={title}>
            <legend>{title}</legend>
            <div className="form-grid">{fields.map(([name, label, format, required]) =>
                <Field key={name} label={label}>
                    <input name={name} required={required} defaultValue={record?.[name] ?? defaults[name] ?? ''}
                        type={typeof format === 'number' ? 'text' : format}
                        maxLength={typeof format === 'number' ? format : undefined}
                        step={format === 'number' ? '0.001' : undefined}
                        min={name === 'pack_size' ? '0.001' : format === 'number' ? '0' : undefined}
                        dir={name.endsWith('_ar') ? 'rtl' : undefined} />
                </Field>)}</div>
        </fieldset>)}
        <fieldset className="catalog-fieldset"><legend>Active ingredients and strengths</legend>
            <p>Enter the amount and unit for each ingredient. Use both “Per” fields for strengths expressed per tablet or volume.</p>
            {components.map((component, index) => <ComponentEditor key={component.key} component={component} index={index}
                removable={components.length > 1}
                change={(changes) => setComponents((rows) => rows.map((row) => row.key === component.key ? { ...row, ...changes } : row))}
                remove={() => setComponents((rows) => rows.filter((row) => row.key !== component.key))} />)}
            <button className="button secondary small" type="button" onClick={() => {
                const key = 'new-' + nextRow.current++;
                setComponents((rows) => [...rows, { key }]);
            }}>Add ingredient</button>
        </fieldset>
        <footer className="form-actions"><button className="button secondary" type="button" onClick={onClose} disabled={busy}>Cancel</button>
            <button className="button primary" disabled={busy}>{busy ? 'Saving…' : 'Save medicine'}</button></footer>
    </form>;
}

export function MedicineEditor({ id, onClose, onSaved }) {
    const [record, setRecord] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!id) return undefined;
        let current = true;
        api('/admin/medicines/' + id)
            .then(({ data }) => { if (current) setRecord(data); })
            .catch((exception) => { if (current) setError(exception.message); });
        return () => { current = false; };
    }, [id]);

    async function save(data) {
        setBusy(true);
        setError('');
        try {
            await api('/admin/medicines' + (id ? '/' + id : ''), { method: id ? 'PATCH' : 'POST', body: data });
            onSaved(id ? 'Medicine updated.' : 'Medicine added.');
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <Modal title={id ? 'Edit medicine' : 'Add medicine'} onClose={() => { if (!busy) onClose(); }} wide>
        <Notice error>{error}</Notice>
        {!id || record ? <EditorForm record={record} busy={busy} onSave={save} onClose={onClose} />
            : !error && <p role="status">Loading medicine…</p>}
    </Modal>;
}
