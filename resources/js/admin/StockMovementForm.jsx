import { useRef, useState } from 'react';
import { api } from './api';
import { Field, Notice } from './ui';

export function StockMovementForm({ item, batch, onCancel, onSaved, onBusyChange }) {
    const requestKey = useRef(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const adjustment = Boolean(batch);

    async function save(event) {
        event.preventDefault();
        setBusy(true);
        onBusyChange(true);
        setError('');
        const body = Object.fromEntries(new FormData(event.currentTarget));
        const base = '/admin/inventory-items/' + item.id;
        try {
            requestKey.current ??= crypto.randomUUID();
            body.request_key = requestKey.current;
            const path = adjustment ? '/batches/' + batch.id + '/adjustments' : '/receipts';
            await api(base + path, { method: 'POST', body });
            onSaved(adjustment ? 'Stock adjustment recorded.' : 'Stock receipt recorded.');
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
            onBusyChange(false);
        }
    }

    return <form className="catalog-fieldset" aria-label={adjustment ? 'Adjust stock' : 'Receive stock'} onSubmit={save}>
        <h3>{adjustment ? 'Adjust ' + batch.batch_no : 'Receive stock'}</h3>
        <Notice error>{error}</Notice>
        <div className="form-grid">
            {!adjustment && <>
                <Field label="Batch number"><input name="batch_no" maxLength={60} required /></Field>
                <Field label="Expiry date"><input name="expiry_date" type="date" required /></Field>
            </>}
            <Field label={adjustment ? 'Quantity change' : 'Packages received'}>
                <input name="quantity" type="number" step="1" min={adjustment ? '-2147483648' : '1'} max="2147483647" required
                    placeholder={adjustment ? 'Use a negative number to reduce stock' : undefined} />
            </Field>
            <Field label={adjustment ? 'Reason' : 'Reason (optional)'}><input name="reason" maxLength={255} required={adjustment} /></Field>
        </div>
        <footer className="form-actions">
            <button className="button secondary" type="button" disabled={busy} onClick={onCancel}>Cancel</button>
            <button className="button primary" disabled={busy}>{busy ? 'Saving…' : adjustment ? 'Save adjustment' : 'Record receipt'}</button>
        </footer>
    </form>;
}
