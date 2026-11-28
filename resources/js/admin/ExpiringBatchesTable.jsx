import { TableHead } from './TableSort';
import { Status, TableAction } from './ui';

const columns = [["Batch","batch"],["Expiry date","expiry"],["Pharmacy","pharmacy"],["Medicine","medicine"],["On hand","on_hand"],["Status","status"],["Details"]];

export function ExpiringBatchesTable({ batches, emptyText, onDetails, sort, onSort }) {
    return <div className="table-scroll"><table>
        <caption className="sr-only">Batches expiring within 30 days</caption>
        <TableHead columns={columns} sort={sort} onSort={onSort} />
        <tbody>{batches.length ? batches.map(batch => <tr key={batch.id}>
            <td><strong>{batch.batch_no}</strong></td>
            <td>{batch.expiry_date}</td>
            <td><strong>{batch.item.pharmacy.name_en}</strong><small className="table-secondary">{batch.item.pharmacy.city}</small></td>
            <td><strong>{batch.item.medicine.name_en}</strong><small className="table-secondary">{batch.item.medicine.strength_text}</small></td>
            <td>{batch.quantity_on_hand}</td>
            <td><Status status={batch.status} /></td>
            <td><TableAction icon="view" onClick={() => onDetails(batch.item)}
                label={'View stock for ' + batch.batch_no} /></td>
        </tr>) : <tr><td className="empty" colSpan={columns.length}>{emptyText}</td></tr>}</tbody>
    </table></div>;
}
