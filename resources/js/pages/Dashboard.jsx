import { Head } from '@inertiajs/react';
import Layout from '../components/Layout';
import KpiCard from '../components/Dashboard/KpiCard';
import SyncStatus from '../components/Dashboard/SyncStatus';
import OccupancyChart from '../components/Dashboard/OccupancyChart';
import DelinquencyChart from '../components/Dashboard/DelinquencyChart';
import {
    BuildingOfficeIcon,
    CurrencyDollarIcon,
    WrenchScrewdriverIcon,
    UserGroupIcon,
} from '@heroicons/react/24/outline';

export default function Dashboard({ syncStatus, kpis, propertyRollups }) {
    const currentKpis = kpis?.current;

    const formatCurrency = (value) => {
        if (!value) return '$0';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    const formatPercent = (value) => {
        if (!value) return '0%';
        return `${parseFloat(value).toFixed(1)}%`;
    };

    const formatDays = (value) => {
        return (value || 0).toFixed(1);
    };

    return (
        <Layout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Property management overview and key metrics
                    </p>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <KpiCard
                        title="Occupancy Rate"
                        value={formatPercent(currentKpis?.occupancy_rate)}
                        subtitle={`${currentKpis?.vacancy_count || 0} vacant units`}
                        icon={BuildingOfficeIcon}
                    />
                    <KpiCard
                        title="Total Delinquency"
                        value={formatCurrency(currentKpis?.delinquency_amount)}
                        icon={CurrencyDollarIcon}
                    />
                    <KpiCard
                        title="Open Work Orders"
                        value={currentKpis?.open_work_orders || 0}
                        subtitle={`Avg ${formatDays(currentKpis?.avg_days_open_work_orders)} days open`}
                        icon={WrenchScrewdriverIcon}
                    />
                    <KpiCard
                        title="Vacancy Count"
                        value={currentKpis?.vacancy_count || 0}
                        icon={UserGroupIcon}
                    />
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <OccupancyChart data={kpis?.trend} />
                    <DelinquencyChart data={kpis?.trend} />
                </div>

                {/* Bottom Row */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Sync Status */}
                    <SyncStatus syncStatus={syncStatus} />

                    {/* Property Rollups */}
                    <div className="lg:col-span-2 card">
                        <div className="card-header">
                            <h3 className="text-lg font-medium text-gray-900">Property Summary</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Property
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Vacancies
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Delinquency
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Work Orders
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {(!propertyRollups || propertyRollups.length === 0) ? (
                                        <tr>
                                            <td colSpan="4" className="px-6 py-8 text-center text-gray-500">
                                                No property data available
                                            </td>
                                        </tr>
                                    ) : (
                                        propertyRollups.map((property) => (
                                            <tr key={property.property_id}>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {property.property_name}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {property.vacancy_count}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatCurrency(property.delinquency_amount)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {property.open_work_orders}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
