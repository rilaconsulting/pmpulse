import AdminLayout from './Index';

export default function Settings({ features }) {
    return (
        <AdminLayout currentTab="settings">
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-lg font-medium text-gray-900">General Settings</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Configure application-wide settings and feature flags
                    </p>
                </div>

                {/* Feature Flags */}
                <div className="card max-w-2xl">
                    <div className="card-header">
                        <h3 className="text-lg font-medium text-gray-900">Feature Flags</h3>
                    </div>
                    <div className="card-body">
                        <div className="space-y-4">
                            <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <span
                                        className={`w-3 h-3 rounded-full ${
                                            features?.incremental_sync ? 'bg-green-500' : 'bg-gray-300'
                                        }`}
                                    />
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">Incremental Sync</p>
                                        <p className="text-xs text-gray-500">
                                            Only sync data that has changed since the last sync
                                        </p>
                                    </div>
                                </div>
                                <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                    features?.incremental_sync
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-800'
                                }`}>
                                    {features?.incremental_sync ? 'Enabled' : 'Disabled'}
                                </span>
                            </div>

                            <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <span
                                        className={`w-3 h-3 rounded-full ${
                                            features?.notifications ? 'bg-green-500' : 'bg-gray-300'
                                        }`}
                                    />
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">Notifications</p>
                                        <p className="text-xs text-gray-500">
                                            Send email notifications for alerts and threshold breaches
                                        </p>
                                    </div>
                                </div>
                                <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                    features?.notifications
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-800'
                                }`}>
                                    {features?.notifications ? 'Enabled' : 'Disabled'}
                                </span>
                            </div>
                        </div>

                        <p className="mt-4 text-xs text-gray-500">
                            Feature flags are currently configured via environment variables.
                            Database-managed feature flags coming in a future update.
                        </p>
                    </div>
                </div>

                {/* Application Info */}
                <div className="card max-w-2xl">
                    <div className="card-header">
                        <h3 className="text-lg font-medium text-gray-900">Application Information</h3>
                    </div>
                    <div className="card-body">
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-gray-500">Application</dt>
                                <dd className="text-gray-900 font-medium">PMPulse</dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Environment</dt>
                                <dd className="text-gray-900 font-medium capitalize">
                                    {import.meta.env.MODE || 'production'}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
