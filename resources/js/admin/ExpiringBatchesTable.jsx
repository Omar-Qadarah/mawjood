import { Status } from './ui';

const headings = ['Batch', 'Expiry date', 'Pharmacy', 'Medicine', 'On hand', 'Status', 'Details'];

export function ExpiringBatchesTable({ batches, emptyText, onDetails }) {
    return <div className="table-scroll"><table>
        <caption className="sr-only">Batches expiring within 30 days</caption>
        <thead><tr>{headings.map(heading => <th key={heading} scope="col">{heading}</th>)}</tr></thead>
        <tbody>{batches.length ? batches.map(batch => <tr key={batch.id}>
            <td><strong>{batch.batch_no}</strong></td>
            <td>{batch.expiry_date}</td>
            <td><strong>{batch.item.pharmacy.name_en}</strong><small className="table-secondary">{batch.item.pharmacy.city}</small></td>
            <td><strong>{batch.item.medicine.name_en}</strong><small className="table-secondary">{batch.item.medicine.strength_text}</small></td>
            <td>{batch.quantity_on_hand}</td>
            <td><Status status={batch.status} /></td>
            <td><button className="button small secondary" type="button" onClick={() => onDetails(batch.item)}
                aria-label={'View stock for ' + batch.batch_no}>View stock</button></td>
        </tr>) : <tr><td className="empty" colSpan={headings.length}>{emptyText}</td></tr>}</tbody>
    </table></div>;
}
