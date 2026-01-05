import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';

const getHeatColor = (vsAvg) => {
    if (vsAvg === null || vsAvg === undefined) {
        return 'bg-gray-50 text-gray-400';
    }
    if (vsAvg <= -20) {
        return 'bg-green-100 text-green-800';
    }
    if (vsAvg <= -10) {
        return 'bg-green-50 text-green-700';
    }
    if (vsAvg <= 10) {
        return 'bg-yellow-50 text-yellow-700';
    }
    if (vsAvg <= 20) {
        return 'bg-orange-50 text-orange-700';
    }
    return 'bg-red-100 text-red-800';
};

export default function UtilityHeatMap({ data, utilityTypes }) {
    const [sortField, setSortField] = useState('property_name');
    const [sortDirection, setSortDirection] = useState('asc');

    if (!data || !data.properties || data.properties.length === 0) {
        return (
            <div className="card">
                <div className="card-header">
                    <h3 className="text-lg font-medium text-gray-900">Property Utility Comparison</h3>
                </div>
                <div className="card-body">
                    <div className="py-12 text-center text-gray-500">
                        No property data available
                    </div>
                </div>
            </div>
        );
    }

    const formatCurrency = (value) => {
        if (value === null || value === undefined) return '-';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    const handleSort = (field) => {
        if (sortField === field) {
            setSortDirection(prev => prev === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const sortedProperties = [...data.properties].sort((a, b) => {
        let aVal, bVal;

        if (sortField === 'property_name') {
            aVal = a.property_name.toLowerCase();
            bVal = b.property_name.toLowerCase();
        } else if (sortField === 'unit_count') {
            aVal = a.unit_count || 0;
            bVal = b.unit_count || 0;
        } else {
            // Sorting by utility type
            aVal = a[sortField]?.value ?? -Infinity;
            bVal = b[sortField]?.value ?? -Infinity;
        }

        if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    const SortIndicator = ({ field }) => (
        <span className="ml-1 text-gray-400">
            {sortField === field && (sortDirection === 'asc' ? '↑' : '↓')}
        </span>
    );

    const exportToCsv = () => {
        const headers = ['Property', 'Units', ...Object.values(utilityTypes).map(t => `${t} ($/unit)`), ...Object.values(utilityTypes).map(t => `${t} vs Avg`)];
        const rows = sortedProperties.map(p => [
            p.property_name,
            p.unit_count || 0,
            ...Object.keys(utilityTypes).map(t => p[t]?.value ?? ''),
            ...Object.keys(utilityTypes).map(t => p[t]?.vs_avg !== null ? `${p[t].vs_avg}%` : ''),
        ]);

        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.map(cell => `"${cell}"`).join(',')),
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'utility-comparison.csv';
        link.click();
    };

    return (
        <div className="card">
            <div className="card-header flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">Property Utility Comparison</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Cost per unit compared to portfolio average
                    </p>
                </div>
                <button
                    onClick={exportToCsv}
                    className="btn-secondary text-sm"
                >
                    <ArrowDownTrayIcon className="w-4 h-4 mr-1" />
                    Export CSV
                </button>
            </div>

            {/* Legend */}
            <div className="px-6 py-3 border-b border-gray-200 bg-gray-50">
                <div className="flex items-center space-x-4 text-xs">
                    <span className="text-gray-500">Legend:</span>
                    <span className="flex items-center">
                        <span className="w-4 h-4 rounded bg-green-100 mr-1"></span>
                        &gt;20% below avg
                    </span>
                    <span className="flex items-center">
                        <span className="w-4 h-4 rounded bg-green-50 mr-1"></span>
                        10-20% below
                    </span>
                    <span className="flex items-center">
                        <span className="w-4 h-4 rounded bg-yellow-50 mr-1"></span>
                        Near average
                    </span>
                    <span className="flex items-center">
                        <span className="w-4 h-4 rounded bg-orange-50 mr-1"></span>
                        10-20% above
                    </span>
                    <span className="flex items-center">
                        <span className="w-4 h-4 rounded bg-red-100 mr-1"></span>
                        &gt;20% above avg
                    </span>
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th
                                onClick={() => handleSort('property_name')}
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                Property<SortIndicator field="property_name" />
                            </th>
                            <th
                                onClick={() => handleSort('unit_count')}
                                className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                Units<SortIndicator field="unit_count" />
                            </th>
                            {Object.entries(utilityTypes).map(([key, label]) => (
                                <th
                                    key={key}
                                    onClick={() => handleSort(key)}
                                    className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                >
                                    {label}<SortIndicator field={key} />
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {sortedProperties.map((property) => (
                            <tr key={property.property_id} className="hover:bg-gray-50">
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <Link
                                        href={`/utilities/property/${property.property_id}`}
                                        className="text-sm font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        {property.property_name}
                                    </Link>
                                </td>
                                <td className="px-4 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                    {property.unit_count || '-'}
                                </td>
                                {Object.keys(utilityTypes).map((type) => {
                                    const cellData = property[type];
                                    const colorClass = getHeatColor(cellData?.vs_avg);
                                    return (
                                        <td key={type} className="px-4 py-4 whitespace-nowrap text-center">
                                            <Link
                                                href={`/utilities/property/${property.property_id}`}
                                                className={`inline-block px-3 py-1 rounded text-sm font-medium ${colorClass}`}
                                            >
                                                {cellData?.value !== null && cellData?.value !== undefined ? (
                                                    <>
                                                        {formatCurrency(cellData.value)}
                                                        {cellData.vs_avg !== null && (
                                                            <span className="ml-1 text-xs opacity-75">
                                                                ({cellData.vs_avg > 0 ? '+' : ''}{cellData.vs_avg}%)
                                                            </span>
                                                        )}
                                                    </>
                                                ) : (
                                                    '-'
                                                )}
                                            </Link>
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Portfolio Averages Footer */}
            <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div className="flex items-center space-x-6 text-sm">
                    <span className="font-medium text-gray-700">Portfolio Averages ($/unit):</span>
                    {Object.entries(utilityTypes).map(([key, label]) => (
                        <span key={key} className="text-gray-600">
                            {label}: <span className="font-medium">{formatCurrency(data.averages[key])}</span>
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
}
