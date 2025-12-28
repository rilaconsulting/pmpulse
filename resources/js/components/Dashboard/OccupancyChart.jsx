import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';

export default function OccupancyChart({ data }) {
    if (!data || data.length === 0) {
        return (
            <div className="card">
                <div className="card-header">
                    <h3 className="text-lg font-medium text-gray-900">Occupancy Rate Trend</h3>
                </div>
                <div className="card-body">
                    <div className="h-64 flex items-center justify-center text-gray-500">
                        No data available
                    </div>
                </div>
            </div>
        );
    }

    const chartData = data.map((item) => ({
        date: new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
        occupancy: parseFloat(item.occupancy_rate) || 0,
    }));

    return (
        <div className="card">
            <div className="card-header">
                <h3 className="text-lg font-medium text-gray-900">Occupancy Rate Trend</h3>
            </div>
            <div className="card-body">
                <div className="h-64">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={chartData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                            <XAxis
                                dataKey="date"
                                tick={{ fontSize: 12, fill: '#6B7280' }}
                                tickLine={false}
                                axisLine={{ stroke: '#E5E7EB' }}
                            />
                            <YAxis
                                domain={[0, 100]}
                                tick={{ fontSize: 12, fill: '#6B7280' }}
                                tickLine={false}
                                axisLine={{ stroke: '#E5E7EB' }}
                                tickFormatter={(value) => `${value}%`}
                            />
                            <Tooltip
                                formatter={(value) => [`${value.toFixed(1)}%`, 'Occupancy']}
                                contentStyle={{
                                    backgroundColor: 'white',
                                    border: '1px solid #E5E7EB',
                                    borderRadius: '8px',
                                    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                                }}
                            />
                            <Line
                                type="monotone"
                                dataKey="occupancy"
                                stroke="#3B82F6"
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 4, fill: '#3B82F6' }}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            </div>
        </div>
    );
}
