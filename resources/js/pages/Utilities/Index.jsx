import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import KpiCard from '../../components/Dashboard/KpiCard';
import UtilityTrendChart from '../../components/Utilities/UtilityTrendChart';
import UtilityHeatMap from '../../components/Utilities/UtilityHeatMap';
import AnomalyAlerts from '../../components/Utilities/AnomalyAlerts';
import {
    BoltIcon,
    FireIcon,
    BeakerIcon,
    TrashIcon,
    SparklesIcon,
    CubeIcon,
} from '@heroicons/react/24/outline';

const UtilityIcons = {
    water: BeakerIcon,
    electric: BoltIcon,
    gas: FireIcon,
    garbage: TrashIcon,
    sewer: SparklesIcon,
    other: CubeIcon,
};

const UtilityColors = {
    water: 'bg-blue-50 text-blue-600',
    electric: 'bg-yellow-50 text-yellow-600',
    gas: 'bg-orange-50 text-orange-600',
    garbage: 'bg-gray-50 text-gray-600',
    sewer: 'bg-green-50 text-green-600',
    other: 'bg-purple-50 text-purple-600',
};

export default function UtilitiesIndex({
    period,
    periodLabel,
    utilitySummary,
    portfolioTotal,
    anomalies,
    trendData,
    heatMapData,
    utilityTypes,
}) {
    const [selectedPeriod, setSelectedPeriod] = useState(period);

    const formatCurrency = (value) => {
        if (!value) return '$0';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    const handlePeriodChange = (newPeriod) => {
        setSelectedPeriod(newPeriod);
        router.get('/utilities', { period: newPeriod }, { preserveState: true });
    };

    return (
        <Layout>
            <Head title="Utility Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Utility Dashboard</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Portfolio utility expense tracking and analysis
                        </p>
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
                        const Icon = UtilityIcons[utility.type] || CubeIcon;
                        const colorClass = UtilityColors[utility.type] || UtilityColors.other;
                        return (
                            <div key={utility.type} className="card">
                                <div className="card-body">
                                    <div className="flex items-center space-x-3">
                                        <div className={`p-2 rounded-lg ${colorClass}`}>
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

                {/* Trend Chart */}
                <UtilityTrendChart data={trendData} utilityTypes={utilityTypes} />

                {/* Heat Map Table */}
                <UtilityHeatMap data={heatMapData} utilityTypes={utilityTypes} />
            </div>
        </Layout>
    );
}
