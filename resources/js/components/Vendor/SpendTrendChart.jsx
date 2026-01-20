import { useState } from 'react';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import {
    LineChart,
    Line,
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import { formatCurrency } from './formatters';

/**
 * Spending trend chart with line/bar toggle and CSV export
 */
export default function SpendTrendChart({ spendTrend, vendorName }) {
    const [chartType, setChartType] = useState('line');

    const chartData = spendTrend?.data?.map(item => ({
        period: item.period,
        spend: item.total_spend || 0,
        workOrders: item.work_order_count || 0,
    })) || [];

    if (chartData.length === 0) {
        return null;
    }

    const exportSpendDataToCSV = () => {
        try {
            const headers = ['Period', 'Spend', 'Work Orders', 'Avg Cost'];
            const rows = (spendTrend?.data || []).map(item => [
                item.period,
                item.total_spend || 0,
                item.work_order_count || 0,
                item.avg_cost_per_wo || 0,
            ]);

            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.join(','))
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            const safeName = (vendorName || 'vendor').replace(/[^a-zA-Z0-9\s-]/g, '').replace(/\s+/g, '-').toLowerCase();
            link.setAttribute('href', url);
            link.setAttribute('download', `vendor-spend-${safeName}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Failed to export CSV:', error);
        }
    };

    return (
        <div className="card">
            <div className="card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 className="text-lg font-medium text-gray-900">Spending Trend (12 Months)</h2>
                <div className="flex items-center gap-2">
                    <select
                        value={chartType}
                        onChange={(e) => setChartType(e.target.value)}
                        className="input text-sm py-1 min-h-[44px] sm:min-h-0"
                    >
                        <option value="line">Line Chart</option>
                        <option value="bar">Bar Chart</option>
                    </select>
                    <button
                        onClick={exportSpendDataToCSV}
                        className="btn-secondary flex items-center text-sm py-1 px-2 min-h-[44px] sm:min-h-0"
                        title="Export CSV"
                    >
                        <ArrowDownTrayIcon className="w-4 h-4 sm:mr-1" />
                        <span className="hidden sm:inline">Export CSV</span>
                    </button>
                </div>
            </div>
            <div className="card-body">
                <div className="h-72">
                    <ResponsiveContainer width="100%" height="100%">
                        {chartType === 'line' ? (
                            <LineChart data={chartData}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="period" tick={{ fontSize: 12 }} />
                                <YAxis
                                    yAxisId="left"
                                    tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`}
                                    tick={{ fontSize: 12 }}
                                />
                                <YAxis
                                    yAxisId="right"
                                    orientation="right"
                                    tick={{ fontSize: 12 }}
                                />
                                <Tooltip
                                    formatter={(value, name) => {
                                        if (name === 'Spend') return [formatCurrency(value), 'Spend'];
                                        return [value, 'Work Orders'];
                                    }}
                                />
                                <Legend />
                                <Line
                                    yAxisId="left"
                                    type="monotone"
                                    dataKey="spend"
                                    stroke="#3B82F6"
                                    strokeWidth={2}
                                    name="Spend"
                                    dot={{ r: 3 }}
                                />
                                <Line
                                    yAxisId="right"
                                    type="monotone"
                                    dataKey="workOrders"
                                    stroke="#10B981"
                                    strokeWidth={2}
                                    name="Work Orders"
                                    dot={{ r: 3 }}
                                />
                            </LineChart>
                        ) : (
                            <BarChart data={chartData}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="period" tick={{ fontSize: 12 }} />
                                <YAxis
                                    tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`}
                                    tick={{ fontSize: 12 }}
                                />
                                <Tooltip
                                    formatter={(value, name) => {
                                        if (name === 'Spend') return [formatCurrency(value), 'Spend'];
                                        return [value, 'Work Orders'];
                                    }}
                                />
                                <Legend />
                                <Bar dataKey="spend" fill="#3B82F6" name="Spend" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        )}
                    </ResponsiveContainer>
                </div>
            </div>
        </div>
    );
}
