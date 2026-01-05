import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AdminLayout from './Index';
import {
    ArrowDownTrayIcon,
    FunnelIcon,
    XMarkIcon,
    ArrowTopRightOnSquareIcon,
    AdjustmentsHorizontalIcon,
} from '@heroicons/react/24/outline';

export default function AdjustmentsReport({
    adjustments,
    adjustableFields,
    creators,
    summary,
    filters,
}) {
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState(filters);

    const handleFilterChange = (key, value) => {
        setLocalFilters((prev) => ({ ...prev, [key]: value }));
    };

    const applyFilters = () => {
        router.get('/admin/adjustments', localFilters, { preserveState: true });
    };

    const clearFilters = () => {
        const cleared = { status: 'active', field: '', creator: '', from: '', to: '' };
        setLocalFilters(cleared);
        router.get('/admin/adjustments', cleared, { preserveState: true });
    };

    const handleExport = () => {
        const params = new URLSearchParams(filters);
        window.location.href = `/admin/adjustments/export?${params.toString()}`;
    };

    const formatValue = (value, fieldName) => {
        if (value === null || value === undefined) return '-';
        const fieldType = adjustableFields[fieldName]?.type;
        if (fieldType === 'decimal') {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
            }).format(value);
        }
        if (fieldType === 'integer') {
            return new Intl.NumberFormat('en-US').format(value);
        }
        return value;
    };

    const formatDate = (date) => {
        if (!date) return 'Permanent';
        return new Date(date).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const hasActiveFilters = filters.field || filters.creator || filters.from || filters.to;

    return (
        <AdminLayout currentTab="adjustments">
            <div className="space-y-6">
                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <AdjustmentsHorizontalIcon className="w-5 h-5 text-blue-600" />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Total Adjustments</p>
                                    <p className="text-2xl font-semibold text-gray-900">{summary.total}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                    <svg className="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Properties Affected</p>
                                    <p className="text-2xl font-semibold text-gray-900">{summary.properties_affected}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm text-gray-500 mb-2">By Field Type</p>
                            <div className="flex flex-wrap gap-2">
                                {Object.entries(summary.by_field || {}).map(([field, count]) => (
                                    <span
                                        key={field}
                                        className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                                    >
                                        {adjustableFields[field]?.label || field}: {count}
                                    </span>
                                ))}
                                {Object.keys(summary.by_field || {}).length === 0 && (
                                    <span className="text-sm text-gray-400">No adjustments</span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Toolbar */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        {/* Status Tabs */}
                        <div className="flex rounded-lg border border-gray-200 bg-white p-1">
                            {['active', 'historical', 'all'].map((status) => (
                                <button
                                    key={status}
                                    type="button"
                                    onClick={() => {
                                        handleFilterChange('status', status);
                                        router.get('/admin/adjustments', { ...localFilters, status }, { preserveState: true });
                                    }}
                                    className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                                        filters.status === status
                                            ? 'bg-blue-100 text-blue-700'
                                            : 'text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    {status.charAt(0).toUpperCase() + status.slice(1)}
                                </button>
                            ))}
                        </div>

                        {/* Filters Toggle */}
                        <button
                            type="button"
                            onClick={() => setShowFilters(!showFilters)}
                            className={`inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border transition-colors ${
                                hasActiveFilters
                                    ? 'border-blue-300 bg-blue-50 text-blue-700'
                                    : 'border-gray-200 text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            <FunnelIcon className="w-4 h-4" />
                            Filters
                            {hasActiveFilters && (
                                <span className="w-2 h-2 bg-blue-500 rounded-full" />
                            )}
                        </button>
                    </div>

                    {/* Export Button */}
                    <button
                        type="button"
                        onClick={handleExport}
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"
                    >
                        <ArrowDownTrayIcon className="w-4 h-4" />
                        Export CSV
                    </button>
                </div>

                {/* Filters Panel */}
                {showFilters && (
                    <div className="card">
                        <div className="card-body">
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Field</label>
                                    <select
                                        className="input"
                                        value={localFilters.field}
                                        onChange={(e) => handleFilterChange('field', e.target.value)}
                                    >
                                        <option value="">All Fields</option>
                                        {Object.entries(adjustableFields).map(([key, field]) => (
                                            <option key={key} value={key}>{field.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Created By</label>
                                    <select
                                        className="input"
                                        value={localFilters.creator}
                                        onChange={(e) => handleFilterChange('creator', e.target.value)}
                                    >
                                        <option value="">All Users</option>
                                        {creators.map((user) => (
                                            <option key={user.id} value={user.id}>{user.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                                    <input
                                        type="date"
                                        className="input"
                                        value={localFilters.from}
                                        onChange={(e) => handleFilterChange('from', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                                    <input
                                        type="date"
                                        className="input"
                                        value={localFilters.to}
                                        onChange={(e) => handleFilterChange('to', e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end gap-3 mt-4">
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                                >
                                    Clear Filters
                                </button>
                                <button
                                    type="button"
                                    onClick={applyFilters}
                                    className="btn btn-primary"
                                >
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Adjustments Table */}
                <div className="card overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Property
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Field
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Original
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Adjusted
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Effective Period
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Created By
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {adjustments.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center">
                                            <AdjustmentsHorizontalIcon className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                                            <p className="text-gray-500">No adjustments found</p>
                                            <p className="text-sm text-gray-400 mt-1">
                                                {hasActiveFilters ? 'Try changing your filters' : 'Adjustments will appear here when created'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    adjustments.data.map((adjustment) => (
                                        <tr key={adjustment.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <Link
                                                    href={`/properties/${adjustment.property?.id}`}
                                                    className="text-sm font-medium text-blue-600 hover:text-blue-800 inline-flex items-center gap-1"
                                                >
                                                    {adjustment.property?.name || 'Unknown Property'}
                                                    <ArrowTopRightOnSquareIcon className="w-3 h-3" />
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    {adjustableFields[adjustment.field_name]?.label || adjustment.field_name}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatValue(adjustment.original_value, adjustment.field_name)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                                {formatValue(adjustment.adjusted_value, adjustment.field_name)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div>
                                                    {formatDate(adjustment.effective_from)} - {formatDate(adjustment.effective_to)}
                                                </div>
                                                {!adjustment.effective_to && (
                                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                        Permanent
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-gray-900">{adjustment.creator?.name || 'Unknown'}</div>
                                                <div className="text-xs text-gray-500">
                                                    {new Date(adjustment.created_at).toLocaleDateString()}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {adjustments.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <div className="text-sm text-gray-500">
                                Showing {adjustments.from} to {adjustments.to} of {adjustments.total} adjustments
                            </div>
                            <div className="flex gap-2">
                                {adjustments.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`px-3 py-1 text-sm rounded ${
                                            link.active
                                                ? 'bg-blue-600 text-white'
                                                : link.url
                                                    ? 'text-gray-700 hover:bg-gray-100'
                                                    : 'text-gray-300 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
