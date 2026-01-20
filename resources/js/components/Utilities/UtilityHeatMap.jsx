import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import { findUtilityType, getIconComponent, getColorScheme } from './constants';

export default function UtilityHeatMap({ data, utilityTypes, selectedType, period }) {
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

    const formatCurrency = (value, decimals = 0) => {
        if (value === null || value === undefined) return '-';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
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

    const handleUtilityTypeChange = (newType) => {
        router.get(route('utilities.data'), { utility_type: newType }, { preserveState: true });
    };

    const sortedProperties = [...data.properties].sort((a, b) => {
        let aVal, bVal;

        if (sortField === 'property_name') {
            aVal = a.property_name.toLowerCase();
            bVal = b.property_name.toLowerCase();
        } else {
            aVal = a[sortField] ?? -Infinity;
            bVal = b[sortField] ?? -Infinity;
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

    const escapeCsvCell = (value) => {
        if (value === null || value === undefined) return '';
        const str = String(value);
        if (str.includes(',') || str.includes('"') || str.includes('\n')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    };

    const exportToCsv = () => {
        const headers = [
            'Property',
            'Units',
            'Sq Ft',
            'Current Month',
            'Previous Month',
            'Prev 3 Mo Avg',
            'Prev 12 Mo Avg',
            'Avg $/Unit',
            'Avg $/Sq Ft',
        ];
        const rows = sortedProperties.map(p => [
            p.property_name,
            p.unit_count || '',
            p.total_sqft || '',
            p.current_month ?? '',
            p.prev_month ?? '',
            p.prev_3_months ?? '',
            p.prev_12_months ?? '',
            p.avg_per_unit ?? '',
            p.avg_per_sqft ?? '',
        ]);

        const csvContent = [
            headers.map(escapeCsvCell).join(','),
            ...rows.map(row => row.map(escapeCsvCell).join(',')),
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `utility-comparison-${selectedType}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    const selectedUtilityType = findUtilityType(utilityTypes, selectedType);
    const Icon = getIconComponent(selectedUtilityType?.icon);
    const colors = getColorScheme(selectedUtilityType?.color_scheme);

    return (
        <div className="card">
            <div className="card-header flex items-center justify-between">
                <div className="flex items-center space-x-4">
                    <div>
                        <h3 className="text-lg font-medium text-gray-900">Property Utility Comparison</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Monthly costs and averages by property
                        </p>
                    </div>
                </div>
                <div className="flex items-center space-x-3">
                    {/* Utility Type Selector */}
                    <div className="flex items-center space-x-2">
                        <span className="text-sm text-gray-500">Utility:</span>
                        <select
                            value={selectedType}
                            onChange={(e) => handleUtilityTypeChange(e.target.value)}
                            className="input py-1.5 pr-8"
                        >
                            {Array.isArray(utilityTypes) && utilityTypes.map((type) => (
                                <option key={type.key} value={type.key}>{type.label}</option>
                            ))}
                        </select>
                    </div>
                    <button
                        onClick={exportToCsv}
                        className="btn-secondary text-sm"
                    >
                        <ArrowDownTrayIcon className="w-4 h-4 mr-1" />
                        Export CSV
                    </button>
                </div>
            </div>

            {/* Selected Utility Type Badge */}
            <div className="px-6 py-3 border-b border-gray-200 bg-gray-50">
                <div className="flex items-center space-x-3">
                    <div className={`p-2 rounded-lg ${colors.bg} ${colors.text}`}>
                        <Icon className="w-5 h-5" />
                    </div>
                    <div>
                        <span className="font-medium text-gray-900">{selectedUtilityType?.label || selectedType}</span>
                        <span className="ml-2 text-sm text-gray-500">
                            {data.property_count} properties
                        </span>
                    </div>
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
                                className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                Units<SortIndicator field="unit_count" />
                            </th>
                            <th
                                onClick={() => handleSort('current_month')}
                                className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                Current Mo<SortIndicator field="current_month" />
                            </th>
                            <th
                                onClick={() => handleSort('prev_month')}
                                className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                Prev Mo<SortIndicator field="prev_month" />
                            </th>
                            <th
                                onClick={() => handleSort('prev_3_months')}
                                className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                3 Mo Avg<SortIndicator field="prev_3_months" />
                            </th>
                            <th
                                onClick={() => handleSort('prev_12_months')}
                                className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                12 Mo Avg<SortIndicator field="prev_12_months" />
                            </th>
                            <th
                                onClick={() => handleSort('avg_per_unit')}
                                className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                $/Unit<SortIndicator field="avg_per_unit" />
                            </th>
                            <th
                                onClick={() => handleSort('avg_per_sqft')}
                                className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            >
                                $/Sq Ft<SortIndicator field="avg_per_sqft" />
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {sortedProperties.map((property) => (
                            <tr key={property.property_id} className="hover:bg-gray-50">
                                <td className="px-6 py-3 whitespace-nowrap">
                                    <Link
                                        href={`/utilities/property/${property.property_id}`}
                                        className="text-sm font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        {property.property_name}
                                    </Link>
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-right text-sm text-gray-500">
                                    {property.unit_count || '-'}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-right text-sm text-gray-900">
                                    {formatCurrency(property.current_month)}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-right text-sm text-gray-900">
                                    {formatCurrency(property.prev_month)}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-right text-sm text-gray-900">
                                    {formatCurrency(property.prev_3_months)}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-right text-sm text-gray-900">
                                    {formatCurrency(property.prev_12_months)}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-right text-sm text-gray-900 font-medium">
                                    {formatCurrency(property.avg_per_unit, 2)}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap text-right text-sm text-gray-900">
                                    {property.avg_per_sqft !== null ? `$${property.avg_per_sqft.toFixed(4)}` : '-'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Portfolio Totals Footer */}
            <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span className="text-gray-500">Current Month Total:</span>
                        <span className="ml-2 font-medium text-gray-900">{formatCurrency(data.totals?.current_month)}</span>
                    </div>
                    <div>
                        <span className="text-gray-500">Prev Month Total:</span>
                        <span className="ml-2 font-medium text-gray-900">{formatCurrency(data.totals?.prev_month)}</span>
                    </div>
                    <div>
                        <span className="text-gray-500">Avg per Property (3 mo):</span>
                        <span className="ml-2 font-medium text-gray-900">{formatCurrency(data.averages?.prev_3_months)}</span>
                    </div>
                    <div>
                        <span className="text-gray-500">Avg per Property (12 mo):</span>
                        <span className="ml-2 font-medium text-gray-900">{formatCurrency(data.averages?.prev_12_months)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
