import { Head } from '@inertiajs/react';
import Layout from '../components/Layout';

export default function Changelog({ releases, error }) {
    return (
        <Layout>
            <Head title="What's New" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">What's New</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Recent updates and improvements
                    </p>
                </div>

                {/* Releases */}
                {error ? (
                    <div className="card">
                        <div className="card-body text-red-600">
                            Unable to load changelog.
                        </div>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {releases.map((release, index) => (
                            <div key={release.version} className="card">
                                <div className="card-header">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <div className="flex items-center gap-3">
                                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                                                v{release.version}
                                            </span>
                                            {index === 0 && (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    Latest
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-sm text-gray-500">
                                            {release.date}
                                        </span>
                                    </div>
                                </div>
                                <div className="card-body">
                                    <div
                                        className="prose-changelog"
                                        dangerouslySetInnerHTML={{ __html: release.content }}
                                    />
                                </div>
                            </div>
                        ))}

                        {releases.length === 0 && (
                            <div className="card">
                                <div className="card-body text-gray-500 text-center py-8">
                                    No releases found.
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Layout>
    );
}
