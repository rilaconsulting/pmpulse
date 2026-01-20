import { Link, router } from '@inertiajs/react';
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    XMarkIcon,
    ClipboardDocumentListIcon,
} from '@heroicons/react/24/outline';
import WorkOrderStatusBadge from './WorkOrderStatusBadge';
import MobileCard from '../MobileCard';
import { formatCurrency, formatDate } from './formatters';

/**
 * Work order history table with filters, sorting, and pagination
 */
export default function WorkOrderHistory({
    vendorId,
    workOrders,
    workOrderProperties = [],
    workOrderStats = {},
    workOrderFilters = {},
}) {
    const handleFilterChange = (key, value) => {
        router.get(`/vendors/${vendorId}`, {
            ...workOrderFilters,
            [key]: value || undefined,
            wo_page: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSort = (field, defaultDirection = 'asc') => {
        const isCurrentSort = workOrderFilters.wo_sort === field;
        const currentDirection = workOrderFilters.wo_direction;
        const newDirection = isCurrentSort
            ? (currentDirection === 'asc' ? 'desc' : 'asc')
            : defaultDirection;

        router.get(`/vendors/${vendorId}`, {
            ...workOrderFilters,
            wo_sort: field,
            wo_direction: newDirection,
            wo_page: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        router.get(`/vendors/${vendorId}`, {
            wo_sort: workOrderFilters.wo_sort,
            wo_direction: workOrderFilters.wo_direction,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page) => {
        router.get(`/vendors/${vendorId}`, {
            ...workOrderFilters,
            wo_page: page,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const SortIcon = ({ field }) => {
        if (workOrderFilters.wo_sort !== field) {
            return <ChevronUpIcon className="w-4 h-4 text-gray-300" />;
        }
        return workOrderFilters.wo_direction === 'asc'
            ? <ChevronUpIcon className="w-4 h-4 text-blue-600" />
            : <ChevronDownIcon className="w-4 h-4 text-blue-600" />;
    };

    const SortableHeader = ({ field, children, className = '', defaultDirection = 'asc', label }) => {
        const isSorted = workOrderFilters.wo_sort === field;
        const sortDirection = isSorted ? (workOrderFilters.wo_direction === 'asc' ? 'ascending' : 'descending') : 'none';
        const columnLabel = label || children;

        // Build descriptive aria-label for the button
        let ariaLabel;
        if (isSorted) {
            const nextDirection = workOrderFilters.wo_direction === 'asc' ? 'descending' : 'ascending';
            ariaLabel = `${columnLabel}, sorted ${sortDirection}, activate to sort ${nextDirection}`;
        } else {
            ariaLabel = `${columnLabel}, not sorted, activate to sort ${defaultDirection === 'asc' ? 'ascending' : 'descending'}`;
        }

        return (
            <th
                className={`px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${className}`}
                aria-sort={sortDirection}
            >
                <button
                    type="button"
                    className="flex items-center gap-1 cursor-pointer hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    onClick={() => handleSort(field, defaultDirection)}
                    aria-label={ariaLabel}
                >
                    {children}
                    <SortIcon field={field} />
                </button>
            </th>
        );
    };

    const hasFilters = workOrderFilters.wo_status || workOrderFilters.wo_property;

    return (
        <div className="card">
            <div className="card-header">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                    <h2 className="text-lg font-medium text-gray-900">Work Order History</h2>
                    {workOrders.total > 0 && (
                        <span className="text-sm text-gray-500">
                            Showing {workOrders.from}-{workOrders.to} of {workOrders.total}
                        </span>
                    )}
                </div>

                {/* Summary Stats */}
                {workOrderStats && Object.keys(workOrderStats).length > 0 && (
                    <div className="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="bg-gray-50 p-3 rounded-lg">
                            <p className="text-xs text-gray-500 uppercase">Total</p>
                            <p className="text-lg font-semibold text-gray-900">{workOrderStats.total || 0}</p>
                        </div>
                        <div className="bg-gray-50 p-3 rounded-lg">
                            <p className="text-xs text-gray-500 uppercase">Total Spend</p>
                            <p className="text-lg font-semibold text-gray-900">{formatCurrency(workOrderStats.total_spend)}</p>
                        </div>
                        <div className="bg-gray-50 p-3 rounded-lg">
                            <p className="text-xs text-gray-500 uppercase">Completed</p>
                            <p className="text-lg font-semibold text-green-600">{workOrderStats.completed || 0}</p>
                        </div>
                        <div className="bg-gray-50 p-3 rounded-lg">
                            <p className="text-xs text-gray-500 uppercase">Open</p>
                            <p className="text-lg font-semibold text-blue-600">{workOrderStats.open || 0}</p>
                        </div>
                    </div>
                )}

                {/* Filters */}
                <div className="mt-4 flex flex-wrap gap-3 items-end">
                    <div>
                        <label htmlFor="wo_status" className="block text-xs font-medium text-gray-500 mb-1">
                            Status
                        </label>
                        <select
                            id="wo_status"
                            className="input text-sm py-1.5"
                            value={workOrderFilters.wo_status || ''}
                            onChange={(e) => handleFilterChange('wo_status', e.target.value)}
                        >
                            <option value="">All Statuses</option>
                            <option value="completed">Completed</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    {workOrderProperties.length > 0 && (
                        <div>
                            <label htmlFor="wo_property" className="block text-xs font-medium text-gray-500 mb-1">
                                Property
                            </label>
                            <select
                                id="wo_property"
                                className="input text-sm py-1.5"
                                value={workOrderFilters.wo_property || ''}
                                onChange={(e) => handleFilterChange('wo_property', e.target.value)}
                            >
                                <option value="">All Properties</option>
                                {workOrderProperties.map((property) => (
                                    <option key={property.id} value={property.id}>
                                        {property.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    {hasFilters && (
                        <button
                            type="button"
                            onClick={handleClearFilters}
                            className="btn-secondary flex items-center text-sm py-1.5"
                        >
                            <XMarkIcon className="w-4 h-4 mr-1" />
                            Clear Filters
                        </button>
                    )}
                </div>
            </div>

            {/* Mobile Card View */}
            <div className="md:hidden">
                {workOrders.data.length === 0 ? (
                    <div className="px-4 py-12 text-center">
                        <ClipboardDocumentListIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                        <p className="text-gray-500">No work orders found</p>
                        {hasFilters && (
                            <button
                                onClick={() => {
                                    router.get(`/vendors/${vendorId}`, {}, {
                                        preserveState: true,
                                        preserveScroll: true,
                                    });
                                }}
                                className="mt-2 text-blue-600 hover:text-blue-700 text-sm min-h-[44px]"
                            >
                                Clear filters
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="divide-y divide-gray-200">
                        {workOrders.data.map((wo) => {
                            const daysToComplete = wo.opened_at && wo.closed_at
                                ? Math.ceil((new Date(wo.closed_at) - new Date(wo.opened_at)) / (1000 * 60 * 60 * 24))
                                : null;

                            const statusLabel =
                                wo.status === 'in_progress'
                                    ? 'In Progress'
                                    : wo.status
                                        ? wo.status.charAt(0).toUpperCase() + wo.status.slice(1)
                                        : '-';
                            const statusVariant =
                                wo.status === 'completed' ? 'success'
                                : wo.status === 'open' ? 'warning'
                                : wo.status === 'in_progress' ? 'info'
                                : wo.status === 'cancelled' ? 'neutral'
                                : 'default';
                            const fields = [
                                ...(wo.property ? [{
                                    label: 'Property',
                                    value: (
                                        <Link
                                            href={`/properties/${wo.property.id}`}
                                            className="text-blue-600 hover:text-blue-800"
                                        >
                                            {wo.property.name}
                                        </Link>
                                    ),
                                }] : []),
                                { label: 'Opened', value: formatDate(wo.opened_at) || '-' },
                                { label: 'Closed', value: formatDate(wo.closed_at) || '-' },
                                ...(daysToComplete !== null ? [{
                                    label: 'Days',
                                    value: (
                                        <span className={daysToComplete <= 7 ? 'text-green-600 font-medium' : daysToComplete <= 14 ? 'text-yellow-600 font-medium' : 'text-red-600 font-medium'}>
                                            {daysToComplete}
                                        </span>
                                    ),
                                }] : []),
                                { label: 'Amount', value: <span className="font-medium">{formatCurrency(wo.amount)}</span> },
                            ];

                            return (
                                <MobileCard
                                    key={wo.id}
                                    header={wo.external_id || wo.id.slice(0, 8)}
                                    subheader={wo.description}
                                    badges={[{ label: statusLabel, variant: statusVariant }]}
                                    fields={fields}
                                />
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Desktop Table View */}
            <div className="hidden md:block overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Work Order
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Property
                            </th>
                            <SortableHeader field="status">Status</SortableHeader>
                            <SortableHeader field="opened_at" defaultDirection="desc">Opened</SortableHeader>
                            <SortableHeader field="closed_at" defaultDirection="desc">Closed</SortableHeader>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Days
                            </th>
                            <SortableHeader field="amount" className="text-right" defaultDirection="desc">
                                <span className="flex justify-end w-full">Amount</span>
                            </SortableHeader>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {workOrders.data.length === 0 ? (
                            <tr>
                                <td colSpan="7" className="px-6 py-12 text-center">
                                    <ClipboardDocumentListIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                    <p className="text-gray-500">No work orders found</p>
                                    {hasFilters && (
                                        <button
                                            onClick={() => {
                                                router.get(`/vendors/${vendorId}`, {}, {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                });
                                            }}
                                            className="mt-2 text-blue-600 hover:text-blue-700 text-sm"
                                        >
                                            Clear filters
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ) : (
                            workOrders.data.map((wo) => {
                                const daysToComplete = wo.opened_at && wo.closed_at
                                    ? Math.ceil((new Date(wo.closed_at) - new Date(wo.opened_at)) / (1000 * 60 * 60 * 24))
                                    : null;

                                return (
                                    <tr key={wo.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">
                                                {wo.external_id || wo.id.slice(0, 8)}
                                            </div>
                                            {wo.description && (
                                                <div className="text-sm text-gray-500 truncate max-w-xs">
                                                    {wo.description}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {wo.property ? (
                                                <Link
                                                    href={`/properties/${wo.property.id}`}
                                                    className="text-sm text-blue-600 hover:text-blue-800"
                                                >
                                                    {wo.property.name}
                                                </Link>
                                            ) : (
                                                <span className="text-sm text-gray-400">-</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <WorkOrderStatusBadge status={wo.status} />
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {formatDate(wo.opened_at)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {formatDate(wo.closed_at)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {daysToComplete !== null ? (
                                                <span className={daysToComplete <= 7 ? 'text-green-600' : daysToComplete <= 14 ? 'text-yellow-600' : 'text-red-600'}>
                                                    {daysToComplete}
                                                </span>
                                            ) : (
                                                <span className="text-gray-400">-</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {formatCurrency(wo.amount)}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination - Responsive */}
            {workOrders.last_page > 1 && (
                <div className="px-4 sm:px-6 py-4 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div className="text-sm text-gray-500 order-2 sm:order-1">
                        Page {workOrders.current_page} of {workOrders.last_page}
                    </div>
                    <div className="flex gap-2 order-1 sm:order-2 w-full sm:w-auto justify-between sm:justify-end">
                        {workOrders.current_page > 1 ? (
                            <button
                                type="button"
                                onClick={() => handlePageChange(workOrders.current_page - 1)}
                                className="btn-secondary flex items-center min-h-[44px] sm:min-h-0"
                            >
                                <ChevronLeftIcon className="w-5 h-5 sm:w-4 sm:h-4 sm:mr-1" />
                                <span className="hidden sm:inline">Previous</span>
                            </button>
                        ) : (
                            <div className="w-10 sm:w-auto" />
                        )}
                        <span className="sm:hidden text-sm text-gray-500 flex items-center">
                            {workOrders.current_page} / {workOrders.last_page}
                        </span>
                        {workOrders.current_page < workOrders.last_page ? (
                            <button
                                type="button"
                                onClick={() => handlePageChange(workOrders.current_page + 1)}
                                className="btn-secondary flex items-center min-h-[44px] sm:min-h-0"
                            >
                                <span className="hidden sm:inline">Next</span>
                                <ChevronRightIcon className="w-5 h-5 sm:w-4 sm:h-4 sm:ml-1" />
                            </button>
                        ) : (
                            <div className="w-10 sm:w-auto" />
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
