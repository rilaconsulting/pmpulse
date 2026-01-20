import { useState, useMemo } from 'react';
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
import { getLineColor } from './constants';

// Total line uses dark gray
const TOTAL_LINE_COLOR = '#1F2937';

export default function UtilityTrendChart({ data, utilityTypes }) {
    // Create a map of type key to utility type object
    const typeMap = useMemo(() => {
        if (!Array.isArray(utilityTypes)) return {};
        return utilityTypes.reduce((acc, type) => {
            acc[type.key] = type;
            return acc;
        }, {});
    }, [utilityTypes]);

    const typeKeys = useMemo(() => {
        return Array.isArray(utilityTypes) ? utilityTypes.map(t => t.key) : [];
    }, [utilityTypes]);

    const [visibleTypes, setVisibleTypes] = useState(() => {
        const initial = { total: true };
        if (Array.isArray(utilityTypes)) {
            utilityTypes.forEach(type => {
                initial[type.key] = false;
            });
        }
        return initial;
    });

    if (!data || data.length === 0) {
        return (
            <div className="card">
                <div className="card-header">
                    <h3 className="text-lg font-medium text-gray-900">Utility Cost Trend</h3>
                </div>
                <div className="card-body">
                    <div className="h-56 sm:h-80 flex items-center justify-center text-gray-500">
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

    // Get the line color for a type
    const getTypeLineColor = (type) => {
        if (type === 'total') return TOTAL_LINE_COLOR;
        const utilityType = typeMap[type];
        return getLineColor(utilityType?.color_scheme);
    };

    // Get the label for a type
    const getTypeLabel = (type) => {
        if (type === 'total') return 'Total';
        return typeMap[type]?.label || type;
    };

    const allTypes = ['total', ...typeKeys];

    return (
        <div className="card">
            <div className="card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900">Utility Cost Trend</h3>
                <div className="flex flex-wrap gap-1.5 sm:gap-2">
                    {allTypes.map(type => (
                        <button
                            key={type}
                            onClick={() => toggleType(type)}
                            className={`px-2 py-1 text-xs font-medium rounded transition-colors min-h-[32px] sm:min-h-0 ${
                                visibleTypes[type]
                                    ? 'text-white'
                                    : 'text-gray-600 bg-gray-100 hover:bg-gray-200'
                            }`}
                            style={visibleTypes[type] ? { backgroundColor: getTypeLineColor(type) } : {}}
                        >
                            {getTypeLabel(type)}
                        </button>
                    ))}
                </div>
            </div>
            <div className="card-body">
                <div className="h-56 sm:h-80">
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
                                    getTypeLabel(name),
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
                                        stroke={getTypeLineColor(type)}
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
