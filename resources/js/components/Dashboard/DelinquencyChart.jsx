import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';

export default function DelinquencyChart({ data }) {
    if (!data || data.length === 0) {
        return (
            <div className="card">
                <div className="card-header">
                    <h3 className="text-base md:text-lg font-medium text-gray-900">Delinquency Trend</h3>
                </div>
                <div className="card-body">
                    <div className="h-48 md:h-64 flex items-center justify-center text-gray-500">
                        No data available
                    </div>
                </div>
            </div>
        );
    }

    const chartData = data.map((item) => ({
        date: new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
        amount: parseFloat(item.delinquency_amount) || 0,
    }));

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    // Shorter format for mobile Y-axis
    const formatCurrencyShort = (value) => {
        if (value >= 1000) {
            return `$${(value / 1000).toFixed(0)}k`;
        }
        return `$${value}`;
    };

    return (
        <div className="card">
            <div className="card-header">
                <h3 className="text-base md:text-lg font-medium text-gray-900">Delinquency Trend</h3>
            </div>
            <div className="card-body">
                <div className="h-48 md:h-64">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={chartData} margin={{ top: 5, right: 5, left: -10, bottom: 5 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                            <XAxis
                                dataKey="date"
                                tick={{ fontSize: 10, fill: '#6B7280' }}
                                tickLine={false}
                                axisLine={{ stroke: '#E5E7EB' }}
                                interval="preserveStartEnd"
                            />
                            <YAxis
                                tick={{ fontSize: 10, fill: '#6B7280' }}
                                tickLine={false}
                                axisLine={{ stroke: '#E5E7EB' }}
                                tickFormatter={formatCurrencyShort}
                                width={40}
                            />
                            <Tooltip
                                formatter={(value) => [formatCurrency(value), 'Delinquency']}
                                contentStyle={{
                                    backgroundColor: 'white',
                                    border: '1px solid #E5E7EB',
                                    borderRadius: '8px',
                                    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                                    fontSize: '12px',
                                }}
                            />
                            <Bar
                                dataKey="amount"
                                fill="#EF4444"
                                radius={[4, 4, 0, 0]}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>
        </div>
    );
}
