import AdminLayout from './Index';
import ConnectionForm from '../../components/Admin/ConnectionForm';
import SyncConfigurationForm from '../../components/Admin/SyncConfigurationForm';
import SyncHistory from '../../components/Admin/SyncHistory';

export default function Integrations({ connection, syncHistory, syncConfiguration, syncStatus, timezones }) {
    return (
        <AdminLayout currentTab="integrations">
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-lg font-medium text-gray-900">AppFolio Integration</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Configure AppFolio connection and manage data synchronization
                    </p>
                </div>

                {/* Connection Form */}
                <ConnectionForm connection={connection} />

                {/* Sync Configuration */}
                <SyncConfigurationForm
                    syncConfiguration={syncConfiguration}
                    syncStatus={syncStatus}
                    timezones={timezones}
                />

                {/* Sync History */}
                <SyncHistory syncHistory={syncHistory} hasConnection={!!connection} />
            </div>
        </AdminLayout>
    );
}
