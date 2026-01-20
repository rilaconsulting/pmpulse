import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import AdminLayout from './Index';
import SyncControls from '../../components/Admin/SyncControls';
import SyncConfigurationForm from '../../components/Admin/SyncConfigurationForm';
import {
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/solid';

export default function Sync({ syncHistory, hasConnection, stats, syncConfiguration, syncStatus, timezones }) {
    const [expandedRow, setExpandedRow] = useState(null);
    const [isResetting, setIsResetting] = useState(false);

    const handleResetUtilityExpenses = () => {
        if (!confirm('This will delete all utility expenses and rebuild them from bill details. Continue?')) {
            return;
        }

        setIsResetting(true);
        router.post(route('admin.sync.reset-utility'), {}, {
            onFinish: () => setIsResetting(false),
        });
    };

    const getStatusIcon = (status) => {
        switch (status) {
            case 'completed':
                return <CheckCircleIcon className="w-5 h-5 text-green-500" />;
            case 'failed':
                return <XCircleIcon className="w-5 h-5 text-red-500" />;
            case 'running':
            case 'pending':
                return <ClockIcon className="w-5 h-5 text-yellow-500 animate-spin" />;
            default:
                return <ClockIcon className="w-5 h-5 text-gray-400" />;
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    const formatDuration = (startedAt, endedAt) => {
        if (!startedAt || !endedAt) return '-';
        const start = new Date(startedAt);
        const end = new Date(endedAt);
        const diffMs = end - start;
        const diffSecs = Math.floor(diffMs / 1000);
        const diffMins = Math.floor(diffSecs / 60);

        if (diffMins > 0) {
            return `${diffMins}m ${diffSecs % 60}s`;
        }
        return `${diffSecs}s`;
    };

    const formatDateRange = (customDateRange) => {
        if (!customDateRange) return 'Default';
        const { from_date, to_date, preset } = customDateRange;
        if (preset === 'custom') {
            return `${from_date} to ${to_date}`;
        }
        return preset.replace('_', ' ');
    };

    const toggleRow = (id) => {
        setExpandedRow(expandedRow === id ? null : id);
    };

    return (
        <AdminLayout>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-lg font-medium text-gray-900">Data Synchronization</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Run syncs, manage date ranges, and reset data tables
                    </p>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                    <div className="card p-4">
                        <div className="text-sm font-medium text-gray-500">Total Runs</div>
                        <div className="mt-1 text-2xl font-semibold text-gray-900">{stats.total_runs}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-sm font-medium text-gray-500">Successful</div>
                        <div className="mt-1 text-2xl font-semibold text-green-600">{stats.successful_runs}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-sm font-medium text-gray-500">With Errors</div>
                        <div className="mt-1 text-2xl font-semibold text-yellow-600">{stats.runs_with_errors}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-sm font-medium text-gray-500">Failed</div>
                        <div className="mt-1 text-2xl font-semibold text-red-600">{stats.failed_runs}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-sm font-medium text-gray-500">Bill Details</div>
                        <div className="mt-1 text-2xl font-semibold text-gray-900">{stats.bill_details_count.toLocaleString()}</div>
                    </div>
                    <div className="card p-4">
                        <div className="text-sm font-medium text-gray-500">Utility Expenses</div>
                        <div className="mt-1 text-2xl font-semibold text-gray-900">{stats.utility_expenses_count.toLocaleString()}</div>
                    </div>
                </div>

                {/* Sync Controls */}
                <SyncControls hasConnection={hasConnection} />

                {/* Sync Schedule */}
                <SyncConfigurationForm
                    syncConfiguration={syncConfiguration}
                    syncStatus={syncStatus}
                    timezones={timezones}
                />

                {/* Data Management */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="text-lg font-medium text-gray-900">Data Management</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Reset and rebuild data from source tables
                        </p>
                    </div>
                    <div className="p-6 border-t border-gray-200">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h4 className="font-medium text-gray-900">Reset Utility Expenses</h4>
                                <p className="text-sm text-gray-500">
                                    Clears all utility expenses and rebuilds from bill details.
                                    Use this if utility account GL mappings have changed.
                                </p>
                            </div>
                            <button
                                onClick={handleResetUtilityExpenses}
                                disabled={isResetting || stats.bill_details_count === 0}
                                className="btn-secondary whitespace-nowrap w-full sm:w-auto"
                            >
                                {isResetting ? 'Resetting...' : 'Reset & Rebuild'}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Sync History */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="text-lg font-medium text-gray-900">Sync History</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Recent sync runs with detailed metrics. Tap to view details.
                        </p>
                    </div>

                    {/* Mobile Card View */}
                    <div className="md:hidden divide-y divide-gray-200">
                        {syncHistory.length === 0 ? (
                            <div className="px-4 py-8 text-center text-gray-500">
                                No sync runs yet
                            </div>
                        ) : (
                            syncHistory.map((run) => (
                                <div key={run.id}>
                                    <div
                                        className="p-4 cursor-pointer hover:bg-gray-50"
                                        onClick={() => toggleRow(run.id)}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                {getStatusIcon(run.status)}
                                                <span className="text-sm font-medium text-gray-900 capitalize">
                                                    {run.status}
                                                </span>
                                                <span className="text-sm text-gray-500 capitalize">
                                                    ({run.mode})
                                                </span>
                                            </div>
                                            {expandedRow === run.id ? (
                                                <ChevronUpIcon className="w-5 h-5 text-gray-400" />
                                            ) : (
                                                <ChevronDownIcon className="w-5 h-5 text-gray-400" />
                                            )}
                                        </div>
                                        <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
                                            <div>
                                                <span className="text-gray-500">Started:</span>{' '}
                                                <span className="text-gray-900">{formatDate(run.started_at)}</span>
                                            </div>
                                            <div>
                                                <span className="text-gray-500">Duration:</span>{' '}
                                                <span className="text-gray-900">{formatDuration(run.started_at, run.ended_at)}</span>
                                            </div>
                                            <div>
                                                <span className="text-gray-500">Resources:</span>{' '}
                                                <span className="text-gray-900">{run.resources_synced?.toLocaleString() || 0}</span>
                                            </div>
                                            <div>
                                                <span className="text-gray-500">Errors:</span>{' '}
                                                {run.errors_count > 0 ? (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        {run.errors_count}
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-500">-</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    {expandedRow === run.id && (
                                        <div className="px-4 pb-4 bg-gray-50">
                                            <div className="space-y-4">
                                                {/* Resource Metrics */}
                                                {run.resource_metrics && Object.keys(run.resource_metrics).length > 0 && (
                                                    <div>
                                                        <h4 className="text-sm font-medium text-gray-900 mb-2">Resource Metrics</h4>
                                                        <div className="grid grid-cols-2 gap-2">
                                                            {Object.entries(run.resource_metrics).map(([resource, metrics]) => (
                                                                <div key={resource} className="bg-white rounded p-2 border border-gray-200">
                                                                    <div className="text-xs font-medium text-gray-500 uppercase">{resource}</div>
                                                                    <div className="mt-1 text-sm">
                                                                        <span className="text-green-600">+{metrics.created || 0}</span>
                                                                        <span className="mx-1 text-gray-400">|</span>
                                                                        <span className="text-blue-600">~{metrics.updated || 0}</span>
                                                                        {metrics.errors > 0 && (
                                                                            <>
                                                                                <span className="mx-1 text-gray-400">|</span>
                                                                                <span className="text-red-600">!{metrics.errors}</span>
                                                                            </>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Errors */}
                                                {run.resource_errors && Object.keys(run.resource_errors).length > 0 && (
                                                    <div>
                                                        <h4 className="text-sm font-medium text-gray-900 mb-2 flex items-center">
                                                            <ExclamationTriangleIcon className="w-4 h-4 text-red-500 mr-1" />
                                                            Errors
                                                        </h4>
                                                        <div className="space-y-2">
                                                            {Object.entries(run.resource_errors).map(([resource, errors]) => (
                                                                <div key={resource}>
                                                                    <div className="text-xs font-medium text-gray-500 uppercase">{resource}</div>
                                                                    <div className="mt-1 space-y-1">
                                                                        {(errors || []).slice(0, 3).map((error, idx) => (
                                                                            <div key={idx} className="text-sm text-red-600 bg-red-50 rounded px-2 py-1">
                                                                                {error.message}
                                                                            </div>
                                                                        ))}
                                                                        {(errors || []).length > 3 && (
                                                                            <div className="text-xs text-gray-500">
                                                                                ... and {errors.length - 3} more
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Error Summary */}
                                                {run.error_summary && (
                                                    <div>
                                                        <h4 className="text-sm font-medium text-gray-900 mb-2">Error Summary</h4>
                                                        <pre className="text-xs bg-red-50 text-red-700 p-2 rounded overflow-x-auto">
                                                            {run.error_summary}
                                                        </pre>
                                                    </div>
                                                )}

                                                {/* No details */}
                                                {(!run.resource_metrics || Object.keys(run.resource_metrics).length === 0) &&
                                                 (!run.resource_errors || Object.keys(run.resource_errors).length === 0) &&
                                                 !run.error_summary && (
                                                    <div className="text-sm text-gray-500">
                                                        No detailed metrics available.
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))
                        )}
                    </div>

                    {/* Desktop Table View */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Mode
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date Range
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Started
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Duration
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Resources
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Errors
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">

                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {syncHistory.length === 0 ? (
                                    <tr>
                                        <td colSpan="8" className="px-6 py-8 text-center text-gray-500">
                                            No sync runs yet
                                        </td>
                                    </tr>
                                ) : (
                                    syncHistory.map((run) => (
                                        <>
                                            <tr
                                                key={run.id}
                                                className="cursor-pointer hover:bg-gray-50"
                                                onClick={() => toggleRow(run.id)}
                                            >
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        {getStatusIcon(run.status)}
                                                        <span className="ml-2 text-sm text-gray-900 capitalize">
                                                            {run.status}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 capitalize">
                                                    {run.mode}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDateRange(run.custom_date_range)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDate(run.started_at)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDuration(run.started_at, run.ended_at)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {run.resources_synced?.toLocaleString() || 0}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {run.errors_count > 0 ? (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            {run.errors_count}
                                                        </span>
                                                    ) : (
                                                        <span className="text-sm text-gray-500">-</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                                    {expandedRow === run.id ? (
                                                        <ChevronUpIcon className="w-5 h-5" />
                                                    ) : (
                                                        <ChevronDownIcon className="w-5 h-5" />
                                                    )}
                                                </td>
                                            </tr>
                                            {expandedRow === run.id && (
                                                <tr key={`${run.id}-expanded`}>
                                                    <td colSpan="8" className="px-6 py-4 bg-gray-50">
                                                        <div className="space-y-4">
                                                            {/* Resource Metrics */}
                                                            {run.resource_metrics && Object.keys(run.resource_metrics).length > 0 && (
                                                                <div>
                                                                    <h4 className="text-sm font-medium text-gray-900 mb-2">Resource Metrics</h4>
                                                                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                                                        {Object.entries(run.resource_metrics).map(([resource, metrics]) => (
                                                                            <div key={resource} className="bg-white rounded p-3 border border-gray-200">
                                                                                <div className="text-xs font-medium text-gray-500 uppercase">{resource}</div>
                                                                                <div className="mt-1 text-sm">
                                                                                    <span className="text-green-600">+{metrics.created || 0}</span>
                                                                                    <span className="mx-1 text-gray-400">|</span>
                                                                                    <span className="text-blue-600">~{metrics.updated || 0}</span>
                                                                                    {metrics.errors > 0 && (
                                                                                        <>
                                                                                            <span className="mx-1 text-gray-400">|</span>
                                                                                            <span className="text-red-600">!{metrics.errors}</span>
                                                                                        </>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            )}

                                                            {/* Errors */}
                                                            {run.resource_errors && Object.keys(run.resource_errors).length > 0 && (
                                                                <div>
                                                                    <h4 className="text-sm font-medium text-gray-900 mb-2 flex items-center">
                                                                        <ExclamationTriangleIcon className="w-4 h-4 text-red-500 mr-1" />
                                                                        Errors
                                                                    </h4>
                                                                    <div className="space-y-2">
                                                                        {Object.entries(run.resource_errors).map(([resource, errors]) => (
                                                                            <div key={resource}>
                                                                                <div className="text-xs font-medium text-gray-500 uppercase">{resource}</div>
                                                                                <div className="mt-1 space-y-1">
                                                                                    {(errors || []).slice(0, 5).map((error, idx) => (
                                                                                        <div key={idx} className="text-sm text-red-600 bg-red-50 rounded px-2 py-1">
                                                                                            {error.message}
                                                                                            {error.timestamp && (
                                                                                                <span className="text-xs text-gray-400 ml-2">
                                                                                                    {new Date(error.timestamp).toLocaleTimeString()}
                                                                                                </span>
                                                                                            )}
                                                                                        </div>
                                                                                    ))}
                                                                                    {(errors || []).length > 5 && (
                                                                                        <div className="text-xs text-gray-500">
                                                                                            ... and {errors.length - 5} more errors
                                                                                        </div>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                </div>
                                                            )}

                                                            {/* Error Summary */}
                                                            {run.error_summary && (
                                                                <div>
                                                                    <h4 className="text-sm font-medium text-gray-900 mb-2">Error Summary</h4>
                                                                    <pre className="text-xs bg-red-50 text-red-700 p-2 rounded overflow-x-auto">
                                                                        {run.error_summary}
                                                                    </pre>
                                                                </div>
                                                            )}

                                                            {/* No details available */}
                                                            {(!run.resource_metrics || Object.keys(run.resource_metrics).length === 0) &&
                                                             (!run.resource_errors || Object.keys(run.resource_errors).length === 0) &&
                                                             !run.error_summary && (
                                                                <div className="text-sm text-gray-500">
                                                                    No detailed metrics available for this run.
                                                                </div>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
