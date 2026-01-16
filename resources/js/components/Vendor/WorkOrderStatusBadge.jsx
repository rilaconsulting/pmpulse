/**
 * Work order status badge
 * @param {{ status: string }} props
 */
export default function WorkOrderStatusBadge({ status }) {
    const styles = {
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-gray-100 text-gray-800',
        open: 'bg-blue-100 text-blue-800',
        in_progress: 'bg-yellow-100 text-yellow-800',
    };

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${styles[status] || 'bg-gray-100 text-gray-800'}`}>
            {status?.replace(/_/g, ' ') || 'Unknown'}
        </span>
    );
}
