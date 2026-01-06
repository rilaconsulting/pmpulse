import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import PropertyUtilityTrend from '../../components/Utilities/PropertyUtilityTrend';
import { UtilityIcons, UtilityColors, formatCurrency, formatPercent } from '../../components/Utilities/constants';
import {
    ArrowLeftIcon,
    CubeIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    MinusIcon,
} from '@heroicons/react/24/outline';

export default function UtilitiesShow({
    property,
    period,
    periodLabel,
    costBreakdown,
    comparisons,
    propertyTrend,
    recentExpenses,
    utilityTypes,
}) {
    const [selectedPeriod, setSelectedPeriod] = useState(period);

    const getChangeIcon = (value) => {
        if (value === null || value === undefined) return MinusIcon;
        if (value > 0) return ArrowTrendingUpIcon;
        if (value < 0) return ArrowTrendingDownIcon;
        return MinusIcon;
    };

    const getChangeColor = (value, invertColors = false) => {
        if (value === null || value === undefined) return 'text-gray-500';
        // For costs, increases are bad (red), decreases are good (green)
        if (invertColors) {
            return value > 0 ? 'text-green-600' : value < 0 ? 'text-red-600' : 'text-gray-500';
        }
        return value > 0 ? 'text-red-600' : value < 0 ? 'text-green-600' : 'text-gray-500';
    };

    const handlePeriodChange = (newPeriod) => {
        setSelectedPeriod(newPeriod);
        router.get(`/utilities/property/${property.id}`, { period: newPeriod }, { preserveState: true });
    };

    return (
        <Layout>
            <Head title={`${property.name} - Utilities`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link
                            href="/utilities"
                            className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                        >
                            <ArrowLeftIcon className="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">{property.name}</h1>
                            <p className="mt-1 text-sm text-gray-500">
                                {property.unit_count} units
                                {property.total_sqft && ` • ${property.total_sqft.toLocaleString()} sqft`}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center space-x-2">
                        <span className="text-sm text-gray-500">Period:</span>
                        <select
                            value={selectedPeriod}
                            onChange={(e) => handlePeriodChange(e.target.value)}
                            className="input py-1.5 pr-8"
                        >
                            <option value="month">This Month</option>
                            <option value="quarter">This Quarter</option>
                            <option value="ytd">Year to Date</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>
                </div>

                {/* Period Label */}
                <div className="text-sm text-gray-600">
                    Showing data for: <span className="font-medium">{periodLabel}</span>
                </div>

                {/* Cost Breakdown */}
                <div className="card">
                    <div className="card-header">
                        <h2 className="text-lg font-medium text-gray-900">Utility Cost Breakdown</h2>
                    </div>
                    <div className="card-body">
                        <div className="flex items-center justify-between mb-6">
                            <div>
                                <p className="text-sm text-gray-500">Total Utility Costs</p>
                                <p className="text-3xl font-bold text-gray-900">
                                    {formatCurrency(costBreakdown.total)}
                                </p>
                            </div>
                        </div>

                        {/* Breakdown Bars */}
                        <div className="space-y-3">
                            {costBreakdown.breakdown.map((item) => {
                                const Icon = UtilityIcons[item.type] || CubeIcon;
                                const colors = UtilityColors[item.type] || UtilityColors.other;
                                const widthPercent = costBreakdown.total > 0
                                    ? Math.max((item.cost / costBreakdown.total) * 100, 2)
                                    : 0;

                                return (
                                    <div key={item.type} className="flex items-center space-x-4">
                                        <div className={`p-2 rounded-lg ${colors.bg} ${colors.text}`}>
                                            <Icon className="w-4 h-4" />
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex items-center justify-between mb-1">
                                                <span className="text-sm font-medium text-gray-700">
                                                    {utilityTypes[item.type]}
                                                </span>
                                                <span className="text-sm text-gray-900">
                                                    {formatCurrency(item.cost)}
                                                    <span className="text-gray-500 ml-1">
                                                        ({item.percentage}%)
                                                    </span>
                                                </span>
                                            </div>
                                            <div className="w-full bg-gray-100 rounded-full h-2">
                                                <div
                                                    className={`h-2 rounded-full ${colors.bar}`}
                                                    style={{ width: `${widthPercent}%` }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Period Comparisons */}
                <div className="card">
                    <div className="card-header">
                        <h2 className="text-lg font-medium text-gray-900">Period Comparisons</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Utility Type
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        This Month
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        vs Last Month
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        This Quarter
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        vs Last Quarter
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        $/Unit
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        vs Portfolio
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {comparisons.map((comp) => {
                                    const Icon = UtilityIcons[comp.type] || CubeIcon;
                                    const MonthChangeIcon = getChangeIcon(comp.month_change);
                                    const QuarterChangeIcon = getChangeIcon(comp.quarter_change);
                                    const PortfolioIcon = getChangeIcon(comp.vs_portfolio);

                                    return (
                                        <tr key={comp.type} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center space-x-3">
                                                    <Icon className="w-5 h-5 text-gray-400" />
                                                    <span className="text-sm font-medium text-gray-900">
                                                        {comp.label}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                                {formatCurrency(comp.current_month)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <div className={`flex items-center justify-end space-x-1 ${getChangeColor(comp.month_change)}`}>
                                                    <MonthChangeIcon className="w-4 h-4" />
                                                    <span className="text-sm font-medium">
                                                        {formatPercent(comp.month_change)}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                                {formatCurrency(comp.current_quarter)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <div className={`flex items-center justify-end space-x-1 ${getChangeColor(comp.quarter_change)}`}>
                                                    <QuarterChangeIcon className="w-4 h-4" />
                                                    <span className="text-sm font-medium">
                                                        {formatPercent(comp.quarter_change)}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                                {formatCurrency(comp.cost_per_unit)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <div className={`flex items-center justify-end space-x-1 ${getChangeColor(comp.vs_portfolio)}`}>
                                                    <PortfolioIcon className="w-4 h-4" />
                                                    <span className="text-sm font-medium">
                                                        {formatPercent(comp.vs_portfolio)}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Trend Chart */}
                <PropertyUtilityTrend data={propertyTrend} utilityTypes={utilityTypes} />

                {/* Recent Expenses */}
                <div className="card">
                    <div className="card-header">
                        <h2 className="text-lg font-medium text-gray-900">Recent Expenses</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Vendor
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {recentExpenses.length === 0 ? (
                                    <tr>
                                        <td colSpan="4" className="px-6 py-8 text-center text-gray-500">
                                            No expense records found
                                        </td>
                                    </tr>
                                ) : (
                                    recentExpenses.map((expense) => (
                                        <tr key={expense.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {new Date(expense.expense_date).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {(() => {
                                                    const colors = UtilityColors[expense.utility_type] || UtilityColors.other;
                                                    return (
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors.bg} ${colors.text}`}>
                                                            {expense.utility_label}
                                                        </span>
                                                    );
                                                })()}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {expense.vendor_name || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                                {formatCurrency(expense.amount)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
