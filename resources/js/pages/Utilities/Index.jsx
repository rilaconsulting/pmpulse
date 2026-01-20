import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import UtilityTrendChart from '../../components/Utilities/UtilityTrendChart';
import UtilityHeatMap from '../../components/Utilities/UtilityHeatMap';
import AnomalyAlerts from '../../components/Utilities/AnomalyAlerts';
import ExcludedPropertiesList from '../../components/Utilities/ExcludedPropertiesList';
import { formatCurrency, findUtilityType, getIconComponent, getColorScheme } from '../../components/Utilities/constants';
import { BoltIcon, ChartBarIcon, TableCellsIcon } from '@heroicons/react/24/outline';

export default function UtilitiesIndex({
    period,
    periodLabel,
    utilitySummary,
    portfolioTotal,
    anomalies,
    trendData,
    propertyComparison,
    selectedUtilityType,
    utilityTypes,
    excludedProperties,
}) {
    const [selectedPeriod, setSelectedPeriod] = useState(period);

    const handlePeriodChange = (newPeriod) => {
        setSelectedPeriod(newPeriod);
        router.get(route('utilities.index'), { period: newPeriod }, { preserveState: true });
    };

    return (
        <Layout>
            <Head title="Utility Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <PageHeader
                    title="Utility Dashboard"
                    subtitle="Portfolio utility expense tracking and analysis"
                    actions={
                        <div className="flex items-center space-x-2">
                            <span className="text-sm text-gray-500">Period:</span>
                            <select
                                value={selectedPeriod}
                                onChange={(e) => handlePeriodChange(e.target.value)}
                                className="input py-1.5 pr-8"
                            >
                                <option value="month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="last_3_months">Last 3 Months</option>
                                <option value="last_6_months">Last 6 Months</option>
                                <option value="last_12_months">Last 12 Months</option>
                                <option value="quarter">This Quarter</option>
                                <option value="ytd">Year to Date</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                    }
                    tabs={[
                        { label: 'Dashboard', href: route('utilities.dashboard'), icon: ChartBarIcon },
                        { label: 'Data Table', href: route('utilities.data'), icon: TableCellsIcon },
                    ]}
                    activeTab="Dashboard"
                />

                {/* Period Label */}
                <div className="text-sm text-gray-600">
                    Showing data for: <span className="font-medium">{periodLabel}</span>
                </div>

                {/* Portfolio Total */}
                <div className="card">
                    <div className="card-body">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-500">Total Portfolio Utilities</p>
                                <p className="mt-1 text-3xl font-bold text-gray-900">
                                    {formatCurrency(portfolioTotal)}
                                </p>
                            </div>
                            <div className="p-4 bg-blue-50 rounded-xl">
                                <BoltIcon className="w-8 h-8 text-blue-600" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Utility Type Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    {utilitySummary.map((utility) => {
                        const utilityType = findUtilityType(utilityTypes, utility.type);
                        const Icon = getIconComponent(utilityType?.icon);
                        const colors = getColorScheme(utilityType?.color_scheme);
                        return (
                            <div key={utility.type} className="card">
                                <div className="card-body">
                                    <div className="flex items-center space-x-3">
                                        <div className={`p-2 rounded-lg ${colors.bg} ${colors.text}`}>
                                            <Icon className="w-5 h-5" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-xs font-medium text-gray-500 truncate">
                                                {utility.label}
                                            </p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatCurrency(utility.total_cost)}
                                            </p>
                                            {utility.average_per_unit > 0 && (
                                                <p className="text-xs text-gray-500">
                                                    {formatCurrency(utility.average_per_unit)}/unit avg
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Anomaly Alerts */}
                {anomalies && anomalies.length > 0 && (
                    <AnomalyAlerts anomalies={anomalies} />
                )}

                {/* Excluded Properties */}
                <ExcludedPropertiesList excludedProperties={excludedProperties} />

                {/* Trend Chart */}
                <UtilityTrendChart data={trendData} utilityTypes={utilityTypes} />

                {/* Property Comparison Table */}
                <UtilityHeatMap
                    data={propertyComparison}
                    utilityTypes={utilityTypes}
                    selectedType={selectedUtilityType}
                    period={period}
                />
            </div>
        </Layout>
    );
}
