import { useState, useEffect } from 'react';
import {
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon,
} from '@heroicons/react/24/solid';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';

export default function SyncHealthWidget({ initialData }) {
    const [data, setData] = useState(initialData);
    const [loading, setLoading] = useState(!initialData);
    const [showErrors, setShowErrors] = useState(false);
    const [showResources, setShowResources] = useState(false);

    useEffect(() => {
        if (!initialData) {
            fetchHealthData();
        }
    }, [initialData]);

    const fetchHealthData = async () => {
        try {
            setLoading(true);
            const response = await fetch('/api/sync/health?days=7', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (response.ok) {
                const result = await response.json();
                setData(result);
            }
        } catch (error) {
            console.error('Failed to fetch sync health data:', error);
        } finally {
            setLoading(false);
        }
    };

    const getStatusIcon = (status) => {
        switch (status) {
            case 'completed':
                return <CheckCircleIcon className="w-5 h-5 text-green-500" />;
            case 'failed':
                return <XCircleIcon className="w-5 h-5 text-red-500" />;
            case 'running':
            case 'pending':
                return <ClockIcon className="w-5 h-5 text-yellow-500 animate-pulse" />;
            default:
                return <ClockIcon className="w-5 h-5 text-gray-400" />;
        }
    };

    const getStatusText = (status) => {
        switch (status) {
            case 'completed':
                return 'Completed';
            case 'failed':
                return 'Failed';
            case 'running':
                return 'Running';
            case 'pending':
                return 'Pending';
            default:
                return 'Unknown';
        }
    };

    const getConnectionStatusBadge = (status) => {
        const styles = {
            connected: 'bg-green-100 text-green-800',
            configured: 'bg-blue-100 text-blue-800',
            error: 'bg-red-100 text-red-800',
            not_configured: 'bg-gray-100 text-gray-800',
        };

        const labels = {
            connected: 'Connected',
            configured: 'Configured',
            error: 'Error',
            not_configured: 'Not Configured',
        };

        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${styles[status] || styles.not_configured}`}>
                {labels[status] || 'Unknown'}
            </span>
        );
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    const formatRelativeTime = (dateString) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        return `${diffDays}d ago`;
    };

    const formatDuration = (seconds) => {
        if (!seconds) return '-';
        if (seconds < 60) return `${seconds}s`;
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}m ${secs}s`;
    };

    if (loading) {
        return (
            <div className="card">
                <div className="card-header">
                    <h3 className="text-lg font-medium text-gray-900">Sync Health</h3>
                </div>
                <div className="card-body flex items-center justify-center py-12">
                    <ArrowPathIcon className="w-8 h-8 text-gray-400 animate-spin" />
                </div>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="card">
                <div className="card-header">
                    <h3 className="text-lg font-medium text-gray-900">Sync Health</h3>
                </div>
                <div className="card-body">
                    <p className="text-sm text-gray-500 text-center py-8">
                        Unable to load sync health data
                    </p>
                </div>
            </div>
        );
    }

    const { connection, lastRun, lastSuccessAt, period, chartData, resourceTotals, recentErrors } = data;

    return (
        <div className="card">
            <div className="card-header flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900">Sync Health</h3>
                <button
                    onClick={fetchHealthData}
                    className="p-1 text-gray-400 hover:text-gray-600 transition-colors"
                    title="Refresh"
                >
                    <ArrowPathIcon className="w-5 h-5" />
                </button>
            </div>
            <div className="card-body">
                <div className="space-y-6">
                    {/* Connection & Last Sync Status */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <span className="text-xs text-gray-500 uppercase tracking-wide">Connection</span>
                            <div className="mt-1">
                                {getConnectionStatusBadge(connection?.status)}
                            </div>
                        </div>
                        <div>
                            <span className="text-xs text-gray-500 uppercase tracking-wide">Last Sync</span>
                            <div className="mt-1 flex items-center space-x-2">
                                {lastRun && getStatusIcon(lastRun.status)}
                                <span className="text-sm font-medium text-gray-900">
                                    {lastRun ? formatRelativeTime(lastRun.started_at) : 'Never'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Success Rate */}
                    {period?.success_rate !== null && (
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs text-gray-500 uppercase tracking-wide">
                                    Success Rate ({period.days}d)
                                </span>
                                <span className="text-sm font-semibold text-gray-900">
                                    {period.success_rate}%
                                </span>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    className={`h-2 rounded-full transition-all duration-500 ${
                                        period.success_rate >= 90 ? 'bg-green-500' :
                                        period.success_rate >= 70 ? 'bg-yellow-500' : 'bg-red-500'
                                    }`}
                                    style={{ width: `${period.success_rate}%` }}
                                />
                            </div>
                            <div className="mt-1 flex justify-between text-xs text-gray-500">
                                <span>{period.success_count} successful</span>
                                <span>{period.failure_count} failed</span>
                            </div>
                        </div>
                    )}

                    {/* Last Sync Details */}
                    {lastRun && (
                        <div className="border-t border-gray-100 pt-4">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs text-gray-500 uppercase tracking-wide">Last Sync Details</span>
                                <span className={`text-xs px-2 py-0.5 rounded ${
                                    lastRun.mode === 'full' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'
                                }`}>
                                    {lastRun.mode}
                                </span>
                            </div>
                            <div className="grid grid-cols-3 gap-2 text-center">
                                <div className="bg-gray-50 rounded p-2">
                                    <div className="text-lg font-semibold text-gray-900">
                                        {lastRun.resources_synced?.toLocaleString() || 0}
                                    </div>
                                    <div className="text-xs text-gray-500">Synced</div>
                                </div>
                                <div className="bg-gray-50 rounded p-2">
                                    <div className="text-lg font-semibold text-gray-900">
                                        {formatDuration(lastRun.duration)}
                                    </div>
                                    <div className="text-xs text-gray-500">Duration</div>
                                </div>
                                <div className="bg-gray-50 rounded p-2">
                                    <div className={`text-lg font-semibold ${
                                        lastRun.errors_count > 0 ? 'text-red-600' : 'text-gray-900'
                                    }`}>
                                        {lastRun.errors_count || 0}
                                    </div>
                                    <div className="text-xs text-gray-500">Errors</div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Daily Sync Chart */}
                    {chartData && chartData.length > 0 && (
                        <div className="border-t border-gray-100 pt-4">
                            <span className="text-xs text-gray-500 uppercase tracking-wide">
                                Syncs per Day
                            </span>
                            <div className="h-32 mt-2">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={chartData}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                        <XAxis
                                            dataKey="date"
                                            tick={{ fontSize: 10 }}
                                            tickFormatter={(val) => new Date(val).toLocaleDateString('en-US', { weekday: 'short' })}
                                        />
                                        <YAxis tick={{ fontSize: 10 }} />
                                        <Tooltip
                                            contentStyle={{ fontSize: 12 }}
                                            labelFormatter={(val) => new Date(val).toLocaleDateString()}
                                        />
                                        <Bar dataKey="completed" stackId="a" fill="#10b981" name="Success" />
                                        <Bar dataKey="failed" stackId="a" fill="#ef4444" name="Failed" />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </div>
                    )}

                    {/* Resource Breakdown Toggle */}
                    {Object.keys(resourceTotals || {}).length > 0 && (
                        <div className="border-t border-gray-100 pt-4">
                            <button
                                onClick={() => setShowResources(!showResources)}
                                className="flex items-center justify-between w-full text-left"
                            >
                                <span className="text-xs text-gray-500 uppercase tracking-wide">
                                    Records by Resource
                                </span>
                                <span className="text-xs text-blue-600">
                                    {showResources ? 'Hide' : 'Show'}
                                </span>
                            </button>
                            {showResources && (
                                <div className="mt-3 space-y-2">
                                    {Object.entries(resourceTotals).map(([resource, metrics]) => (
                                        <div key={resource} className="flex items-center justify-between text-sm">
                                            <span className="text-gray-600 capitalize">
                                                {resource.replace('_', ' ')}
                                            </span>
                                            <div className="flex space-x-3 text-xs">
                                                <span className="text-green-600">+{metrics.created}</span>
                                                <span className="text-blue-600">~{metrics.updated}</span>
                                                {metrics.errors > 0 && (
                                                    <span className="text-red-600">!{metrics.errors}</span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Recent Errors Toggle */}
                    {recentErrors && recentErrors.length > 0 && (
                        <div className="border-t border-gray-100 pt-4">
                            <button
                                onClick={() => setShowErrors(!showErrors)}
                                className="flex items-center justify-between w-full text-left"
                            >
                                <span className="text-xs text-gray-500 uppercase tracking-wide flex items-center">
                                    <ExclamationTriangleIcon className="w-4 h-4 text-amber-500 mr-1" />
                                    Recent Errors ({recentErrors.length})
                                </span>
                                <span className="text-xs text-blue-600">
                                    {showErrors ? 'Hide' : 'Show'}
                                </span>
                            </button>
                            {showErrors && (
                                <div className="mt-3 space-y-2 max-h-40 overflow-y-auto">
                                    {recentErrors.map((error, idx) => (
                                        <div key={idx} className="text-xs bg-red-50 border border-red-100 rounded p-2">
                                            <div className="flex items-center justify-between mb-1">
                                                <span className="font-medium text-red-800 capitalize">
                                                    {error.resource}
                                                </span>
                                                <span className="text-red-600">
                                                    {formatRelativeTime(error.timestamp)}
                                                </span>
                                            </div>
                                            <p className="text-red-700 truncate" title={error.message}>
                                                {error.message}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* No Syncs Message */}
                    {!lastRun && (
                        <p className="text-sm text-gray-500 text-center py-4">
                            No sync runs yet. Configure your AppFolio connection in Admin settings.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
