import { Link } from '@inertiajs/react';
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer } from 'recharts';
import { formatCurrency } from './formatters';

const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1'];

/**
 * Spend by property pie chart and table
 */
export default function SpendByPropertyChart({ spendByProperty }) {
    if (!spendByProperty || spendByProperty.length === 0) {
        return null;
    }

    return (
        <div className="card">
            <div className="card-header">
                <h2 className="text-lg font-medium text-gray-900">Spend by Property (Top 10)</h2>
            </div>
            <div className="card-body">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="h-72">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={spendByProperty}
                                    dataKey="total_spend"
                                    nameKey="property_name"
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={100}
                                    label={({ property_name, percent }) =>
                                        `${property_name.substring(0, 15)}${property_name.length > 15 ? '...' : ''} (${(percent * 100).toFixed(0)}%)`
                                    }
                                    labelLine={false}
                                >
                                    {spendByProperty.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    formatter={(value) => [formatCurrency(value), 'Spend']}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                    <div className="overflow-y-auto max-h-72">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Property</th>
                                    <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Spend</th>
                                    <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">WOs</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {spendByProperty.map((item, index) => (
                                    <tr key={item.property_id} className="hover:bg-gray-50">
                                        <td className="px-4 py-2 whitespace-nowrap">
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className="w-3 h-3 rounded-full"
                                                    style={{ backgroundColor: COLORS[index % COLORS.length] }}
                                                />
                                                <Link
                                                    href={`/properties/${item.property_id}`}
                                                    className="text-sm text-blue-600 hover:text-blue-800"
                                                >
                                                    {item.property_name}
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="px-4 py-2 text-right text-sm text-gray-900">
                                            {formatCurrency(item.total_spend)}
                                        </td>
                                        <td className="px-4 py-2 text-right text-sm text-gray-500">
                                            {item.work_order_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
}
