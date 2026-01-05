import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ConnectionForm({ connection }) {
    const [showSecret, setShowSecret] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        database: connection?.database || '',
        client_id: connection?.client_id || '',
        client_secret: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/integrations/connection');
    };

    // Generate preview URL from database name
    const previewUrl = data.database ? `https://${data.database}.appfolio.com` : '';

    return (
        <div className="card">
            <div className="card-header">
                <h3 className="text-lg font-medium text-gray-900">AppFolio Connection</h3>
                <p className="mt-1 text-sm text-gray-500">
                    Configure your AppFolio API credentials to enable data synchronization.
                </p>
            </div>
            <div className="card-body">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label htmlFor="database" className="label">
                            Database Name
                        </label>
                        <input
                            type="text"
                            id="database"
                            className="input"
                            value={data.database}
                            onChange={(e) => setData('database', e.target.value.toLowerCase())}
                            placeholder="e.g., sutro"
                        />
                        {previewUrl && (
                            <p className="mt-1 text-sm text-gray-500">
                                API URL: <span className="font-mono text-gray-700">{previewUrl}</span>
                            </p>
                        )}
                        {errors.database && (
                            <p className="mt-1 text-sm text-red-600">{errors.database}</p>
                        )}
                        <p className="mt-1 text-xs text-gray-400">
                            The subdomain from your AppFolio URL (e.g., "sutro" from sutro.appfolio.com)
                        </p>
                    </div>

                    <div>
                        <label htmlFor="client_id" className="label">
                            Client ID
                        </label>
                        <input
                            type="text"
                            id="client_id"
                            className="input"
                            value={data.client_id}
                            onChange={(e) => setData('client_id', e.target.value)}
                            placeholder="Your AppFolio Client ID"
                        />
                        {errors.client_id && (
                            <p className="mt-1 text-sm text-red-600">{errors.client_id}</p>
                        )}
                    </div>

                    <div>
                        <label htmlFor="client_secret" className="label">
                            Client Secret
                            {connection?.has_secret && (
                                <span className="ml-2 text-xs text-gray-400">
                                    (leave empty to keep current)
                                </span>
                            )}
                        </label>
                        <div className="relative">
                            <input
                                type={showSecret ? 'text' : 'password'}
                                id="client_secret"
                                className="input pr-20"
                                value={data.client_secret}
                                onChange={(e) => setData('client_secret', e.target.value)}
                                placeholder={connection?.has_secret ? '••••••••' : 'Your AppFolio Client Secret'}
                            />
                            <button
                                type="button"
                                className="absolute inset-y-0 right-0 px-3 text-sm text-gray-500 hover:text-gray-700"
                                onClick={() => setShowSecret(!showSecret)}
                            >
                                {showSecret ? 'Hide' : 'Show'}
                            </button>
                        </div>
                        {errors.client_secret && (
                            <p className="mt-1 text-sm text-red-600">{errors.client_secret}</p>
                        )}
                    </div>

                    <div className="pt-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="btn-primary"
                        >
                            {processing ? 'Saving...' : 'Save Connection'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
