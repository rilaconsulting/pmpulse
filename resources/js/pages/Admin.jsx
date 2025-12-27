import { Head } from '@inertiajs/react';
import Layout from '../components/Layout';
import ConnectionForm from '../components/Admin/ConnectionForm';
import SyncHistory from '../components/Admin/SyncHistory';

export default function Admin({ connection, syncHistory, features }) {
    return (
        <Layout>
            <Head title="Admin" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Admin Settings</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Configure AppFolio connection and manage data synchronization
                    </p>
                </div>

                {/* Feature Flags Status */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="text-lg font-medium text-gray-900">Feature Flags</h3>
                    </div>
                    <div className="card-body">
                        <div className="flex flex-wrap gap-4">
                            <div className="flex items-center">
                                <span
                                    className={`w-2 h-2 rounded-full mr-2 ${
                                        features?.incremental_sync ? 'bg-green-500' : 'bg-gray-300'
                                    }`}
                                />
                                <span className="text-sm text-gray-700">Incremental Sync</span>
                            </div>
                            <div className="flex items-center">
                                <span
                                    className={`w-2 h-2 rounded-full mr-2 ${
                                        features?.notifications ? 'bg-green-500' : 'bg-gray-300'
                                    }`}
                                />
                                <span className="text-sm text-gray-700">Notifications</span>
                            </div>
                        </div>
                        <p className="mt-2 text-xs text-gray-500">
                            Feature flags can be configured via environment variables
                        </p>
                    </div>
                </div>

                {/* Connection Form */}
                <ConnectionForm connection={connection} />

                {/* Sync History */}
                <SyncHistory syncHistory={syncHistory} hasConnection={!!connection} />
            </div>
        </Layout>
    );
}
