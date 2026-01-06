import { useState } from 'react';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';

const UtilityLineColors = {
    water: '#3B82F6',      // blue
    electric: '#EAB308',   // yellow
    gas: '#F97316',        // orange
    garbage: '#6B7280',    // gray
    sewer: '#22C55E',      // green
    other: '#A855F7',      // purple
};

export default function PropertyUtilityTrend({ data, utilityTypes }) {
    const [selectedType, setSelectedType] = useState(Object.keys(utilityTypes)[0] || 'water');

    // Check if we have any data
    const hasData = data && Object.keys(data).some(type => data[type]?.length > 0);

    if (!hasData) {
        return (
            <div className="card">
                <div className="card-header">
                    <h3 className="text-lg font-medium text-gray-900">Historical Trend</h3>
                </div>
                <div className="card-body">
                    <div className="h-64 flex items-center justify-center text-gray-500">
                        No trend data available
                    </div>
                </div>
            </div>
        );
    }

    const chartData = data[selectedType] || [];

    const formatCurrency = (value) => {
        if (value >= 1000) {
            return `$${(value / 1000).toFixed(0)}k`;
        }
        return `$${value}`;
    };

    return (
        <div className="card">
            <div className="card-header flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900">Historical Trend</h3>
                <div className="flex flex-wrap gap-2">
                    {Object.entries(utilityTypes).map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => setSelectedType(key)}
                            className={`px-3 py-1.5 text-xs font-medium rounded transition-colors ${
                                selectedType === key
                                    ? 'text-white'
                                    : 'text-gray-600 bg-gray-100 hover:bg-gray-200'
                            }`}
                            style={selectedType === key ? { backgroundColor: UtilityLineColors[key] } : {}}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>
            <div className="card-body">
                <div className="h-64">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={chartData}>
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
                                formatter={(value, name) => {
                                    if (name === 'cost') {
                                        return [`$${value.toLocaleString()}`, 'Total Cost'];
                                    }
                                    if (name === 'cost_per_unit') {
                                        return [`$${value.toLocaleString()}`, 'Cost/Unit'];
                                    }
                                    return [value, name];
                                }}
                                contentStyle={{
                                    backgroundColor: 'white',
                                    border: '1px solid #E5E7EB',
                                    borderRadius: '8px',
                                    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                                }}
                            />
                            <Line
                                type="monotone"
                                dataKey="cost"
                                name="cost"
                                stroke={UtilityLineColors[selectedType]}
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 4 }}
                            />
                            <Line
                                type="monotone"
                                dataKey="cost_per_unit"
                                name="cost_per_unit"
                                stroke={UtilityLineColors[selectedType]}
                                strokeWidth={2}
                                strokeDasharray="5 5"
                                dot={false}
                                activeDot={{ r: 4 }}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
                <div className="mt-4 flex items-center justify-center space-x-6 text-xs text-gray-500">
                    <span className="flex items-center">
                        <span
                            className="w-4 h-0.5 mr-2"
                            style={{ backgroundColor: UtilityLineColors[selectedType] }}
                        />
                        Total Cost
                    </span>
                    <span className="flex items-center">
                        <span
                            className="w-4 h-0.5 mr-2"
                            style={{
                                backgroundColor: UtilityLineColors[selectedType],
                                backgroundImage: `repeating-linear-gradient(90deg, ${UtilityLineColors[selectedType]} 0, ${UtilityLineColors[selectedType]} 3px, transparent 3px, transparent 6px)`,
                            }}
                        />
                        Cost per Unit
                    </span>
                </div>
            </div>
        </div>
    );
}
