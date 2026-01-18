import { useForm } from '@inertiajs/react';
import { CheckCircleIcon, XCircleIcon, ClockIcon } from '@heroicons/react/24/solid';

export default function SyncHistory({ syncHistory, hasConnection }) {
    const { post, processing } = useForm({
        mode: 'incremental',
    });

    const handleSync = (mode) => {
        post(route('admin.sync.trigger'), {
            data: { mode },
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
                return <ClockIcon className="w-5 h-5 text-yellow-500" />;
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

    return (
        <div className="card">
            <div className="card-header flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">Sync History</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Recent data synchronization runs
                    </p>
                </div>
                <div className="flex gap-2">
                    <button
                        onClick={() => handleSync('incremental')}
                        disabled={processing || !hasConnection}
                        className="btn-secondary"
                    >
                        {processing ? 'Syncing...' : 'Incremental Sync'}
                    </button>
                    <button
                        onClick={() => handleSync('full')}
                        disabled={processing || !hasConnection}
                        className="btn-primary"
                    >
                        {processing ? 'Syncing...' : 'Full Sync'}
                    </button>
                </div>
            </div>
            <div className="overflow-x-auto">
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
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {syncHistory.length === 0 ? (
                            <tr>
                                <td colSpan="6" className="px-6 py-8 text-center text-gray-500">
                                    No sync runs yet
                                </td>
                            </tr>
                        ) : (
                            syncHistory.map((run) => (
                                <tr key={run.id}>
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
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
