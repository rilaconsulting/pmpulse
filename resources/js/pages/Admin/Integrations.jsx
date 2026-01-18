import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import {
    CheckCircleIcon,
    XCircleIcon,
    EyeIcon,
    EyeSlashIcon,
    MapPinIcon,
    ExclamationCircleIcon,
} from '@heroicons/react/24/outline';

// AppFolio Logo SVG component
function AppFolioLogo({ className }) {
    return (
        <svg className={className} viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="6" fill="#1E3A5F"/>
            <path d="M8 22L12 10H14.5L18.5 22H16L15.2 19.5H11.3L10.5 22H8ZM11.8 17.5H14.7L13.25 13L11.8 17.5Z" fill="white"/>
            <path d="M19 22V10H24C25.1 10 26 10.4 26.6 11.1C27.2 11.8 27.5 12.7 27.5 13.8C27.5 14.9 27.2 15.8 26.6 16.5C26 17.2 25.1 17.6 24 17.6H21.2V22H19ZM21.2 15.6H23.8C24.3 15.6 24.7 15.4 24.9 15.1C25.2 14.8 25.3 14.4 25.3 13.8C25.3 13.2 25.2 12.8 24.9 12.5C24.7 12.2 24.3 12 23.8 12H21.2V15.6Z" fill="white"/>
        </svg>
    );
}

// Google Logo SVG component
function GoogleLogo({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
    );
}

// Google Maps Logo SVG component
function GoogleMapsLogo({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path fill="#4285F4" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
            <circle fill="#fff" cx="12" cy="9" r="2.5"/>
        </svg>
    );
}

export default function Integrations({ appfolio, googleMaps, googleSso }) {
    // AppFolio form state
    const [showAppfolioSecret, setShowAppfolioSecret] = useState(false);
    const appfolioForm = useForm({
        database: appfolio?.database || '',
        client_id: appfolio?.client_id || '',
        client_secret: '',
    });

    // Google Maps form state
    const [showMapsKey, setShowMapsKey] = useState(false);
    const mapsForm = useForm({
        maps_api_key: '',
    });

    // Google SSO form state
    const [showSsoSecret, setShowSsoSecret] = useState(false);
    const ssoForm = useForm({
        google_client_id: googleSso?.client_id || '',
        google_client_secret: '',
        google_enabled: googleSso?.enabled || false,
    });

    const handleAppfolioSubmit = (e) => {
        e.preventDefault();
        appfolioForm.post(route('admin.integrations.connection'));
    };

    const handleMapsSubmit = (e) => {
        e.preventDefault();
        mapsForm.post(route('admin.integrations.google-maps'), {
            onSuccess: () => {
                mapsForm.reset();
                setShowMapsKey(false);
            },
        });
    };

    const handleSsoSubmit = (e) => {
        e.preventDefault();
        ssoForm.post(route('admin.integrations.google-sso'), {
            preserveScroll: true,
        });
    };

    // Generate preview URL from database name
    const previewUrl = appfolioForm.data.database ? `https://${appfolioForm.data.database}.appfolio.com` : '';

    return (
        <AdminLayout currentTab="integrations">
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-lg font-medium text-gray-900">Integrations</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Connect external services and configure API credentials
                    </p>
                </div>

                {/* AppFolio Integration */}
                <div className="card">
                    <div className="card-header flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <AppFolioLogo className="w-10 h-10" />
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">AppFolio</h3>
                                <p className="text-sm text-gray-500">Property management data sync</p>
                            </div>
                        </div>
                        {appfolio?.client_id ? (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                <CheckCircleIcon className="w-4 h-4" />
                                Connected
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                                <XCircleIcon className="w-4 h-4" />
                                Not Connected
                            </span>
                        )}
                    </div>
                    <div className="card-body">
                        <form onSubmit={handleAppfolioSubmit} className="space-y-4">
                            <div>
                                <label htmlFor="database" className="label">Database Name</label>
                                <input
                                    type="text"
                                    id="database"
                                    className="input"
                                    value={appfolioForm.data.database}
                                    onChange={(e) => appfolioForm.setData('database', e.target.value.toLowerCase())}
                                    placeholder="e.g., sutro"
                                />
                                {previewUrl && (
                                    <p className="mt-1 text-sm text-gray-500">
                                        API URL: <span className="font-mono text-gray-700">{previewUrl}</span>
                                    </p>
                                )}
                                {appfolioForm.errors.database && (
                                    <p className="mt-1 text-sm text-red-600">{appfolioForm.errors.database}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="client_id" className="label">Client ID</label>
                                    <input
                                        type="text"
                                        id="client_id"
                                        className="input"
                                        value={appfolioForm.data.client_id}
                                        onChange={(e) => appfolioForm.setData('client_id', e.target.value)}
                                        placeholder="Your AppFolio Client ID"
                                    />
                                    {appfolioForm.errors.client_id && (
                                        <p className="mt-1 text-sm text-red-600">{appfolioForm.errors.client_id}</p>
                                    )}
                                </div>
                                <div>
                                    <label htmlFor="client_secret" className="label">
                                        Client Secret
                                        {appfolio?.has_secret && (
                                            <span className="ml-2 text-xs text-gray-400">(leave empty to keep current)</span>
                                        )}
                                    </label>
                                    <div className="relative">
                                        <input
                                            type={showAppfolioSecret ? 'text' : 'password'}
                                            id="client_secret"
                                            className="input pr-10"
                                            value={appfolioForm.data.client_secret}
                                            onChange={(e) => appfolioForm.setData('client_secret', e.target.value)}
                                            placeholder={appfolio?.has_secret ? '••••••••' : 'Your Client Secret'}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowAppfolioSecret(!showAppfolioSecret)}
                                            className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                        >
                                            {showAppfolioSecret ? <EyeSlashIcon className="w-5 h-5" /> : <EyeIcon className="w-5 h-5" />}
                                        </button>
                                    </div>
                                    {appfolioForm.errors.client_secret && (
                                        <p className="mt-1 text-sm text-red-600">{appfolioForm.errors.client_secret}</p>
                                    )}
                                </div>
                            </div>

                            <div className="pt-2">
                                <button type="submit" disabled={appfolioForm.processing} className="btn-primary">
                                    {appfolioForm.processing ? 'Saving...' : 'Save Connection'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Google Maps Integration */}
                <div className="card">
                    <div className="card-header flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                                <GoogleMapsLogo className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">Google Maps</h3>
                                <p className="text-sm text-gray-500">Property geocoding and map views</p>
                            </div>
                        </div>
                        {googleMaps?.has_api_key ? (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                <CheckCircleIcon className="w-4 h-4" />
                                Configured
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                <ExclamationCircleIcon className="w-4 h-4" />
                                Not Configured
                            </span>
                        )}
                    </div>
                    <div className="card-body">
                        <p className="text-sm text-gray-600 mb-4">
                            Enable property geocoding and interactive map views. Without an API key, these features will be disabled.
                        </p>
                        <form onSubmit={handleMapsSubmit} className="space-y-4">
                            <div>
                                <label htmlFor="maps_api_key" className="label">
                                    API Key
                                    {googleMaps?.has_api_key && (
                                        <span className="ml-2 text-xs text-gray-400">(leave empty to keep current)</span>
                                    )}
                                </label>
                                <div className="relative">
                                    <input
                                        type={showMapsKey ? 'text' : 'password'}
                                        id="maps_api_key"
                                        className="input pr-10"
                                        value={mapsForm.data.maps_api_key}
                                        onChange={(e) => mapsForm.setData('maps_api_key', e.target.value)}
                                        placeholder={googleMaps?.has_api_key ? '••••••••' : 'Enter API key'}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowMapsKey(!showMapsKey)}
                                        className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                    >
                                        {showMapsKey ? <EyeSlashIcon className="w-5 h-5" /> : <EyeIcon className="w-5 h-5" />}
                                    </button>
                                </div>
                                <p className="mt-1 text-xs text-gray-500">
                                    Get an API key from{' '}
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">
                                        Google Cloud Console
                                    </a>
                                    . Enable the Geocoding API.
                                </p>
                            </div>
                            <div className="pt-2 flex gap-3">
                                <button
                                    type="submit"
                                    disabled={mapsForm.processing || (!mapsForm.data.maps_api_key && !googleMaps?.has_api_key)}
                                    className="btn-primary"
                                >
                                    {mapsForm.processing ? 'Saving...' : 'Save API Key'}
                                </button>
                                {googleMaps?.has_api_key && (
                                    <button
                                        type="button"
                                        disabled={mapsForm.processing}
                                        onClick={() => {
                                            if (confirm('Remove the API key? Map features will be disabled.')) {
                                                mapsForm.transform(() => ({ maps_api_key: '' })).post(route('admin.integrations.google-maps'), {
                                                    onSuccess: () => mapsForm.reset(),
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

                {/* Google SSO Integration */}
                <div className="card">
                    <div className="card-header flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center">
                                <GoogleLogo className="w-6 h-6" />
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">Google SSO</h3>
                                <p className="text-sm text-gray-500">Single sign-on authentication</p>
                            </div>
                        </div>
                        {googleSso?.configured ? (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                <CheckCircleIcon className="w-4 h-4" />
                                {googleSso?.enabled ? 'Enabled' : 'Configured'}
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                                <XCircleIcon className="w-4 h-4" />
                                Not Configured
                            </span>
                        )}
                    </div>
                    <div className="card-body">
                        <form onSubmit={handleSsoSubmit} className="space-y-4">
                            {/* Enable Toggle */}
                            <div className="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <label htmlFor="google_enabled" className="text-sm font-medium text-gray-900">
                                            Enable Google SSO
                                        </label>
                                        <p className="text-sm text-gray-500">
                                            {ssoForm.data.google_enabled
                                                ? 'Users can sign in with their Google account'
                                                : 'Google SSO is disabled'}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => ssoForm.setData('google_enabled', !ssoForm.data.google_enabled)}
                                        className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                                            ssoForm.data.google_enabled ? 'bg-green-600' : 'bg-gray-200'
                                        }`}
                                    >
                                        <span
                                            className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                                ssoForm.data.google_enabled ? 'translate-x-5' : 'translate-x-0'
                                            }`}
                                        />
                                    </button>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="google_client_id" className="label">Client ID</label>
                                    <input
                                        type="text"
                                        id="google_client_id"
                                        className="input"
                                        value={ssoForm.data.google_client_id}
                                        onChange={(e) => ssoForm.setData('google_client_id', e.target.value)}
                                        placeholder="your-client-id.apps.googleusercontent.com"
                                    />
                                    {ssoForm.errors.google_client_id && (
                                        <p className="mt-1 text-sm text-red-600">{ssoForm.errors.google_client_id}</p>
                                    )}
                                </div>
                                <div>
                                    <label htmlFor="google_client_secret" className="label">
                                        Client Secret
                                        {googleSso?.has_secret && (
                                            <span className="ml-2 text-xs text-gray-400">(leave empty to keep current)</span>
                                        )}
                                    </label>
                                    <div className="relative">
                                        <input
                                            type={showSsoSecret ? 'text' : 'password'}
                                            id="google_client_secret"
                                            className="input pr-10"
                                            value={ssoForm.data.google_client_secret}
                                            onChange={(e) => ssoForm.setData('google_client_secret', e.target.value)}
                                            placeholder={googleSso?.has_secret ? '••••••••' : 'Enter client secret'}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowSsoSecret(!showSsoSecret)}
                                            className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                        >
                                            {showSsoSecret ? <EyeSlashIcon className="w-5 h-5" /> : <EyeIcon className="w-5 h-5" />}
                                        </button>
                                    </div>
                                    {ssoForm.errors.google_client_secret && (
                                        <p className="mt-1 text-sm text-red-600">{ssoForm.errors.google_client_secret}</p>
                                    )}
                                </div>
                            </div>

                            {/* Redirect URI */}
                            <div>
                                <label className="label">Redirect URI</label>
                                <div className="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg">
                                    <code className="text-sm text-gray-700 break-all">
                                        {googleSso?.redirect_uri || `${window.location.origin}/auth/google/callback`}
                                    </code>
                                    <p className="mt-2 text-xs text-gray-500">
                                        Add this URI to your Google Cloud Console OAuth 2.0 credentials.
                                    </p>
                                </div>
                            </div>

                            <div className="pt-2 flex items-center gap-3">
                                <button type="submit" disabled={ssoForm.processing} className="btn-primary">
                                    {ssoForm.processing ? 'Saving...' : 'Save Settings'}
                                </button>
                                {ssoForm.recentlySuccessful && (
                                    <span className="text-sm text-green-600">Saved!</span>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
