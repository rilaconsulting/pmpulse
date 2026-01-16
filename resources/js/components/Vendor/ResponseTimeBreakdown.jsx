/**
 * Response time breakdown by completion buckets
 */
export default function ResponseTimeBreakdown({ responseMetrics }) {
    if (!responseMetrics?.total_completed || responseMetrics.total_completed === 0) {
        return null;
    }

    return (
        <div className="card">
            <div className="card-header">
                <h2 className="text-lg font-medium text-gray-900">Response Time Breakdown</h2>
            </div>
            <div className="card-body">
                <div className="grid grid-cols-2 md:grid-cols-6 gap-4">
                    {Object.entries(responseMetrics.completion_buckets || {}).map(([key, bucket]) => (
                        <div key={key} className="text-center p-3 bg-gray-50 rounded-lg">
                            <p className="text-xl font-semibold text-gray-900">{bucket.count}</p>
                            <p className="text-xs text-gray-500">{bucket.percentage}%</p>
                            <p className="text-sm font-medium text-gray-700 mt-1">{bucket.label}</p>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
