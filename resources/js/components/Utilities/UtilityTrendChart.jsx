import { useState } from 'react';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from 'recharts';

const UtilityLineColors = {
    water: '#3B82F6',      // blue
    electric: '#EAB308',   // yellow
    gas: '#F97316',        // orange
    garbage: '#6B7280',    // gray
    sewer: '#22C55E',      // green
    other: '#A855F7',      // purple
    total: '#1F2937',      // dark gray
};

export default function UtilityTrendChart({ data, utilityTypes }) {
    const [visibleTypes, setVisibleTypes] = useState(() => {
        const initial = { total: true };
        Object.keys(utilityTypes).forEach(type => {
            initial[type] = false;
        });
        return initial;
    });

    if (!data || data.length === 0) {
        return (
            <div className="card">
                <div className="card-header">
                    <h3 className="text-lg font-medium text-gray-900">Utility Cost Trend</h3>
                </div>
                <div className="card-body">
                    <div className="h-80 flex items-center justify-center text-gray-500">
                        No trend data available
                    </div>
                </div>
            </div>
        );
    }

    const formatCurrency = (value) => {
        if (value >= 1000) {
            return `$${(value / 1000).toFixed(0)}k`;
        }
        return `$${value}`;
    };

    const toggleType = (type) => {
        setVisibleTypes(prev => ({
            ...prev,
            [type]: !prev[type],
        }));
    };

    const allTypes = ['total', ...Object.keys(utilityTypes)];

    return (
        <div className="card">
            <div className="card-header flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900">Utility Cost Trend</h3>
                <div className="flex flex-wrap gap-2">
                    {allTypes.map(type => (
                        <button
                            key={type}
                            onClick={() => toggleType(type)}
                            className={`px-2 py-1 text-xs font-medium rounded transition-colors ${
                                visibleTypes[type]
                                    ? 'text-white'
                                    : 'text-gray-600 bg-gray-100 hover:bg-gray-200'
                            }`}
                            style={visibleTypes[type] ? { backgroundColor: UtilityLineColors[type] } : {}}
                        >
                            {type === 'total' ? 'Total' : utilityTypes[type]}
                        </button>
                    ))}
                </div>
            </div>
            <div className="card-body">
                <div className="h-80">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={data}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                            <XAxis
                                dataKey="period"
                                tick={{ fontSize: 12, fill: '#6B7280' }}
                                tickLine={false}
                                axisLine={{ stroke: '#E5E7EB' }}
                            />
                            <YAxis
                                tick={{ fontSize: 12, fill: '#6B7280' }}
                                tickLine={false}
                                axisLine={{ stroke: '#E5E7EB' }}
                                tickFormatter={formatCurrency}
                            />
                            <Tooltip
                                formatter={(value, name) => [
                                    `$${value.toLocaleString()}`,
                                    name === 'total' ? 'Total' : utilityTypes[name] || name,
                                ]}
                                contentStyle={{
                                    backgroundColor: 'white',
                                    border: '1px solid #E5E7EB',
                                    borderRadius: '8px',
                                    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                                }}
                            />
                            {allTypes.map(type => (
                                visibleTypes[type] && (
                                    <Line
                                        key={type}
                                        type="monotone"
                                        dataKey={type}
                                        name={type}
                                        stroke={UtilityLineColors[type]}
                                        strokeWidth={type === 'total' ? 3 : 2}
                                        dot={false}
                                        activeDot={{ r: 4 }}
                                    />
                                )
                            ))}
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            </div>
        </div>
    );
}
