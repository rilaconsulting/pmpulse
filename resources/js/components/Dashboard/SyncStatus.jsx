import { CheckCircleIcon, XCircleIcon, ClockIcon } from '@heroicons/react/24/solid';

export default function SyncStatus({ syncStatus }) {
    const { lastRun, connectionStatus, lastSuccessAt } = syncStatus;

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
        switch (status) {
            case 'connected':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Connected
                    </span>
                );
            case 'configured':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        Configured
                    </span>
                );
            case 'error':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        Error
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        Not Configured
                    </span>
                );
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    return (
        <div className="card">
            <div className="card-header">
                <h3 className="text-base md:text-lg font-medium text-gray-900">Sync Status</h3>
            </div>
            <div className="card-body">
                <div className="space-y-3 md:space-y-4">
                    {/* Connection Status */}
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-xs md:text-sm text-gray-500">AppFolio Connection</span>
                        {getConnectionStatusBadge(connectionStatus)}
                    </div>

                    {/* Last Sync */}
                    {lastRun && (
                        <>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs md:text-sm text-gray-500">Last Sync Status</span>
                                <div className="flex items-center">
                                    {getStatusIcon(lastRun.status)}
                                    <span className="ml-2 text-xs md:text-sm font-medium text-gray-900">
                                        {getStatusText(lastRun.status)}
                                    </span>
                                </div>
                            </div>

                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-2">
                                <span className="text-xs md:text-sm text-gray-500">Last Sync Time</span>
                                <span className="text-xs md:text-sm text-gray-900">
                                    {formatDate(lastRun.started_at)}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs md:text-sm text-gray-500">Mode</span>
                                <span className="text-xs md:text-sm text-gray-900 capitalize">
                                    {lastRun.mode}
                                </span>
                            </div>

                            {lastRun.resources_synced > 0 && (
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs md:text-sm text-gray-500">Resources Synced</span>
                                    <span className="text-xs md:text-sm text-gray-900">
                                        {lastRun.resources_synced.toLocaleString()}
                                    </span>
                                </div>
                            )}
                        </>
                    )}

                    {!lastRun && (
                        <p className="text-xs md:text-sm text-gray-500 text-center py-3 md:py-4">
                            No sync runs yet. Configure your AppFolio connection in Admin settings.
                        </p>
                    )}

                    {/* Last Success */}
                    {lastSuccessAt && (
                        <div className="pt-3 md:pt-4 border-t border-gray-100">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-2">
                                <span className="text-xs md:text-sm text-gray-500">Last Successful Sync</span>
                                <span className="text-xs md:text-sm text-gray-900">
                                    {formatDate(lastSuccessAt)}
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
