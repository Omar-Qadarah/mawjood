import { TableHead, useTableSort } from './TableSort';
import { useEffect, useState } from 'react';
import { api } from './api';
import { useTableLoader } from './useTableLoader';
import { TableFilters } from './TableFilters';
import { Field, Modal, Notice, TableAction } from './ui';

const endpoint = '/admin/active-ingredients';

export function IngredientManager({ onClose }) {
    const [search, setSearch] = useState('');
    const [usage, setUsage] = useState('');
    const sort = useTableSort('active-ingredients', 'name');
    const [page, setPage] = useState(1);
    const [revision, setRevision] = useState(0);
    const [result, setResult] = useState(null);
    const [editor, setEditor] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [busy, setBusy] = useState(false);

    const loadTable = useTableLoader();
    useEffect(() => {
        let current = true;
        const cancel = loadTable(search, async () => {
            try {
                const response = await api(endpoint + '?' + new URLSearchParams({ search, usage, page, ...sort.params }));
                if (current) {
                    if (page > response.meta.last_page) setPage(response.meta.last_page);
                    else setResult(response);
                }
            } catch (exception) {
                if (current) setError(exception.message);
            }
        });
        return () => { current = false; cancel?.(); };
    }, [loadTable, search, usage, page, revision, sort.column, sort.direction]);

    async function write(method, id, body) {
        setBusy(true);
        setError('');
        setNotice('');
        try {
            await api(endpoint + (id ? '/' + id : ''), { method, body });
            if (method === 'POST') {
                setSearch('');
                setUsage('');
                setPage(1);
            }
            setEditor(null);
            setDeleting(null);
            setRevision((count) => count + 1);
            setNotice(method === 'DELETE' ? 'Ingredient deleted.' : 'Ingredient saved.');
        } catch (exception) {
            setError(exception.message);
        } finally {
            setBusy(false);
        }
    }

    return <Modal title="Manage active ingredients" onClose={() => { if (!busy) onClose(); }} wide>
        <Notice error>{error}</Notice><Notice>{notice}</Notice>
        {editor && <form className="catalog-fieldset" key={editor.id ?? 'new'} onSubmit={(event) => {
            event.preventDefault();
            write(editor.id ? 'PATCH' : 'POST', editor.id, Object.fromEntries(new FormData(event.currentTarget)));
        }}>
            <div className="form-grid">
                <Field label="English ingredient name"><input name="name_en" required maxLength={150} defaultValue={editor.name_en} /></Field>
                <Field label="Arabic ingredient name"><input name="name_ar" dir="rtl" maxLength={150} defaultValue={editor.name_ar ?? ''} /></Field>
                <Field label="ATC code"><input name="atc_code" maxLength={20} defaultValue={editor.atc_code ?? ''} /></Field>
            </div>
            <footer className="form-actions"><button className="button secondary" type="button" onClick={() => { setEditor(null); setError(''); }} disabled={busy}>Cancel</button>
                <button className="button primary" disabled={busy}>Save ingredient</button></footer>
        </form>}
        {deleting && <div className="catalog-fieldset" role="group" aria-label="Confirm ingredient deletion">
            <p>Delete {deleting.name_en}?</p>
            <footer className="form-actions"><button className="button secondary" type="button" onClick={() => { setDeleting(null); setError(''); }} disabled={busy}>Cancel</button>
                <button className="button danger" type="button" disabled={busy} onClick={() => write('DELETE', deleting.id)}>Confirm delete ingredient</button></footer>
        </div>}
        {!editor && !deleting && <TableFilters label="Search active ingredients" search={search} placeholder="Name or ATC code"
            onClear={() => {
                setError('');
                setSearch('');
                setUsage('');
                setPage(1);
            }}
            onSearch={(value) => { setError(''); setSearch(value); setPage(1); }}>
            <Field label="Package usage"><select value={usage} onChange={(event) => {
                setError(''); setUsage(event.target.value); setPage(1);
            }}>
                <option value="">All ingredients</option>
                <option value="used">Used in a package</option>
                <option value="unused">Unused</option>
            </select></Field>
            <button className="button primary" type="button" onClick={() => { setError(''); setNotice(''); setEditor({}); }}>Add active ingredient</button>
        </TableFilters>}
        <div className="table-scroll"><table>
            <TableHead columns={[["Ingredient","name"],["ATC code","atc"],["Packages","packages"],["Actions"]]} sort={sort} onSort={() => setPage(1)} />
            <tbody>{result?.data.length ? result.data.map((ingredient) => <tr key={ingredient.id}>
                <td><strong>{ingredient.name_en}</strong>{ingredient.name_ar && <small className="table-secondary" lang="ar">{ingredient.name_ar}</small>}</td>
                <td>{ingredient.atc_code || '—'}</td><td className="table-cell-centered">{ingredient.medicine_ingredients_count}</td>
                <td><div className="row-actions">
                    <TableAction icon="edit" label={'Edit ' + ingredient.name_en} disabled={busy} onClick={() => { setError(''); setNotice(''); setDeleting(null); setEditor(ingredient); }} />
                    <TableAction icon="delete" label={'Delete ' + ingredient.name_en} disabled={busy || ingredient.medicine_ingredients_count > 0}
                        title={ingredient.medicine_ingredients_count > 0 ? 'This ingredient is used by a medicine package.' : undefined}
                        onClick={() => { setError(''); setNotice(''); setEditor(null); setDeleting(ingredient); }} />
                </div></td>
            </tr>) : <tr><td className="empty" colSpan="4">{result ? 'No ingredients found.' : 'Loading ingredients…'}</td></tr>}</tbody>
        </table></div>
        {result && <footer className="pagination">
            <button className="button secondary small" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</button>
            <span>Page {result.meta.current_page} of {result.meta.last_page}</span>
            <button className="button secondary small" type="button" disabled={page >= result.meta.last_page} onClick={() => setPage(page + 1)}>Next</button>
        </footer>}
    </Modal>;
}
