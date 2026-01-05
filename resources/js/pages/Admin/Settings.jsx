import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import {
    MapIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
} from '@heroicons/react/24/outline';

export default function Settings({ features, googleMaps }) {
    const [showApiKey, setShowApiKey] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        maps_api_key: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/settings/google-maps', {
            onSuccess: () => {
                reset();
                setShowApiKey(false);
            },
        });
    };

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

                {/* Google Maps API */}
                <div className="card max-w-2xl">
                    <div className="card-header flex items-center gap-2">
                        <MapIcon className="w-5 h-5 text-gray-400" />
                        <h3 className="text-lg font-medium text-gray-900">Google Maps</h3>
                    </div>
                    <div className="card-body">
                        <p className="text-sm text-gray-600 mb-4">
                            Configure Google Maps API for property geocoding and map views.
                            Without an API key, geocoding and map features will be disabled.
                        </p>

                        <div className="flex items-center gap-2 mb-4">
                            {googleMaps?.has_api_key ? (
                                <>
                                    <CheckCircleIcon className="w-5 h-5 text-green-500" />
                                    <span className="text-sm text-green-700">API key is configured</span>
                                </>
                            ) : (
                                <>
                                    <ExclamationCircleIcon className="w-5 h-5 text-yellow-500" />
                                    <span className="text-sm text-yellow-700">No API key configured - geocoding disabled</span>
                                </>
                            )}
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label htmlFor="maps_api_key" className="label">
                                    Google Maps API Key
                                    {googleMaps?.has_api_key && (
                                        <span className="ml-2 text-xs text-gray-400">
                                            (leave empty to keep current)
                                        </span>
                                    )}
                                </label>
                                <div className="relative">
                                    <input
                                        type={showApiKey ? 'text' : 'password'}
                                        id="maps_api_key"
                                        className="input pr-16"
                                        value={data.maps_api_key}
                                        onChange={(e) => setData('maps_api_key', e.target.value)}
                                        placeholder={googleMaps?.has_api_key ? '••••••••' : 'Enter API key'}
                                    />
                                    <button
                                        type="button"
                                        className="absolute inset-y-0 right-0 px-3 text-sm text-gray-500 hover:text-gray-700"
                                        onClick={() => setShowApiKey(!showApiKey)}
                                    >
                                        {showApiKey ? 'Hide' : 'Show'}
                                    </button>
                                </div>
                                <p className="mt-1 text-xs text-gray-500">
                                    Get an API key from{' '}
                                    <a
                                        href="https://console.cloud.google.com/apis/credentials"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-blue-600 hover:underline"
                                    >
                                        Google Cloud Console
                                    </a>
                                    . Enable the Geocoding API.
                                </p>
                            </div>

                            <div className="pt-2 flex gap-3">
                                <button
                                    type="submit"
                                    disabled={processing || (!data.maps_api_key && !googleMaps?.has_api_key)}
                                    className="btn-primary"
                                >
                                    {processing ? 'Saving...' : 'Save API Key'}
                                </button>
                                {googleMaps?.has_api_key && (
                                    <button
                                        type="button"
                                        disabled={processing}
                                        onClick={() => {
                                            if (confirm('Are you sure you want to remove the API key? Map features will be disabled.')) {
                                                post('/admin/settings/google-maps', {
                                                    data: { maps_api_key: '' },
                                                    onSuccess: () => reset(),
                                                });
                                            }
                                        }}
                                        className="btn-secondary text-red-600 hover:text-red-700"
                                    >
                                        Remove Key
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
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
