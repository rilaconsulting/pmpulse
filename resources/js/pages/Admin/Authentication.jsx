import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import { EyeIcon, EyeSlashIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/react/24/outline';

export default function Authentication({ googleSso }) {
    const [showClientSecret, setShowClientSecret] = useState(false);

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        google_client_id: googleSso?.client_id || '',
        google_client_secret: '',
        google_enabled: googleSso?.enabled || false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.authentication.update'), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout currentTab="authentication">
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-lg font-medium text-gray-900">Authentication Settings</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Configure authentication providers and SSO settings
                    </p>
                </div>

                {/* Google SSO Configuration */}
                <div className="card max-w-2xl">
                    <div className="card-header flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <svg className="w-6 h-6" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            <h3 className="text-lg font-medium text-gray-900">Google SSO</h3>
                        </div>
                        <div className="flex items-center gap-2">
                            {googleSso?.configured ? (
                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <CheckCircleIcon className="w-4 h-4" />
                                    Configured
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <XCircleIcon className="w-4 h-4" />
                                    Not Configured
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="card-body">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Enable Toggle */}
                            <div className="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <label htmlFor="google_enabled" className="text-sm font-medium text-gray-900">
                                            Enable Google SSO
                                        </label>
                                        <p className="text-sm text-gray-500">
                                            {data.google_enabled
                                                ? 'Users can sign in with their Google account'
                                                : 'Google SSO is disabled'}
                                        </p>
                                    </div>
                                    <label className="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            id="google_enabled"
                                            className="sr-only peer"
                                            checked={data.google_enabled}
                                            onChange={(e) => setData('google_enabled', e.target.checked)}
                                        />
                                        <div className={`w-11 h-6 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all ${
                                            data.google_enabled
                                                ? 'bg-green-600'
                                                : 'bg-gray-300'
                                        }`}></div>
                                    </label>
                                </div>
                            </div>

                            {/* Client ID */}
                            <div>
                                <label htmlFor="google_client_id" className="label">
                                    Client ID
                                </label>
                                <input
                                    type="text"
                                    id="google_client_id"
                                    className="input"
                                    value={data.google_client_id}
                                    onChange={(e) => setData('google_client_id', e.target.value)}
                                    placeholder="your-client-id.apps.googleusercontent.com"
                                />
                                {errors.google_client_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.google_client_id}</p>
                                )}
                            </div>

                            {/* Client Secret */}
                            <div>
                                <label htmlFor="google_client_secret" className="label">
                                    Client Secret
                                    {googleSso?.has_secret && (
                                        <span className="ml-2 text-xs text-gray-400 font-normal">
                                            (leave empty to keep current)
                                        </span>
                                    )}
                                </label>
                                <div className="relative">
                                    <input
                                        type={showClientSecret ? 'text' : 'password'}
                                        id="google_client_secret"
                                        className="input pr-10"
                                        value={data.google_client_secret}
                                        onChange={(e) => setData('google_client_secret', e.target.value)}
                                        placeholder={googleSso?.has_secret ? '••••••••••••••••' : 'Enter client secret'}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowClientSecret(!showClientSecret)}
                                        className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                                        aria-label={showClientSecret ? 'Hide client secret' : 'Show client secret'}
                                    >
                                        {showClientSecret ? (
                                            <EyeSlashIcon className="w-5 h-5" />
                                        ) : (
                                            <EyeIcon className="w-5 h-5" />
                                        )}
                                    </button>
                                </div>
                                {errors.google_client_secret && (
                                    <p className="mt-1 text-sm text-red-600">{errors.google_client_secret}</p>
                                )}
                            </div>

                            {/* Redirect URI */}
                            <div>
                                <label className="label">Redirect URI</label>
                                <div className="mt-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg">
                                    <code className="text-sm text-gray-700 break-all">
                                        {googleSso?.redirect_uri || `${window.location.origin}/auth/google/callback`}
                                    </code>
                                    <p className="mt-2 text-xs text-gray-500">
                                        Add this URI to your Google Cloud Console OAuth 2.0 credentials.
                                    </p>
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                                {recentlySuccessful && (
                                    <span className="text-sm text-green-600">Saved successfully!</span>
                                )}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="btn-primary"
                                >
                                    {processing ? 'Saving...' : 'Save Settings'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Info Card */}
                <div className="card max-w-2xl bg-blue-50 border-blue-200">
                    <div className="card-body">
                        <h4 className="text-sm font-medium text-blue-900">Setting up Google SSO</h4>
                        <ol className="mt-2 text-sm text-blue-800 list-decimal list-inside space-y-1">
                            <li>Go to the Google Cloud Console</li>
                            <li>Create or select a project</li>
                            <li>Navigate to APIs & Services → Credentials</li>
                            <li>Create OAuth 2.0 Client ID credentials</li>
                            <li>Add the redirect URI shown above</li>
                            <li>Copy the Client ID and Client Secret here</li>
                        </ol>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
