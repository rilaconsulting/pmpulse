import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import {
    ArrowLeftIcon,
    WrenchScrewdriverIcon,
    PhoneIcon,
    EnvelopeIcon,
    MapPinIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    MinusIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    CalendarIcon,
    XMarkIcon,
    CurrencyDollarIcon,
    ClipboardDocumentListIcon,
    ArrowDownTrayIcon,
} from '@heroicons/react/24/outline';
import {
    LineChart,
    Line,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';

const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1'];

export default function VendorShow({
    vendor,
    metrics,
    periodComparison,
    tradeAnalysis,
    responseMetrics,
    responseComparison,
    spendTrend,
    spendByProperty,
    insuranceStatus,
    workOrders,
    workOrderProperties = [],
    workOrderStats = {},
    workOrderFilters = {},
}) {
    const [chartType, setChartType] = useState('line');
    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '-';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const formatDays = (days) => {
        if (days === null || days === undefined) return '-';
        return `${days} days`;
    };

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
            // Sanitize filename by removing special characters
            const safeName = vendor.company_name.replace(/[^a-zA-Z0-9\s-]/g, '').replace(/\s+/g, '-').toLowerCase();
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

    const InsuranceStatusBadge = ({ status, date, label }) => {
        const styles = {
            current: { bg: 'bg-green-100', text: 'text-green-800', icon: CheckCircleIcon },
            expiring_soon: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: ClockIcon },
            expired: { bg: 'bg-red-100', text: 'text-red-800', icon: XCircleIcon },
            missing: { bg: 'bg-gray-100', text: 'text-gray-600', icon: ExclamationTriangleIcon },
        };

        const style = styles[status] || styles.missing;
        const Icon = style.icon;

        return (
            <div className={`flex items-center justify-between p-3 rounded-lg ${style.bg}`}>
                <div className="flex items-center gap-2">
                    <Icon className={`w-5 h-5 ${style.text}`} />
                    <span className={`font-medium ${style.text}`}>{label}</span>
                </div>
                <span className={`text-sm ${style.text}`}>
                    {date ? formatDate(date) : 'Not on file'}
                </span>
            </div>
        );
    };

    const MetricCard = ({ title, value, subtitle, change, invertChange = false }) => {
        const showChange = change !== null && change !== undefined;
        const isPositive = invertChange ? change < 0 : change > 0;
        const isNegative = invertChange ? change > 0 : change < 0;

        return (
            <div className="card">
                <div className="card-body">
                    <p className="text-sm font-medium text-gray-500">{title}</p>
                    <p className="text-2xl font-semibold text-gray-900">{value}</p>
                    {subtitle && <p className="text-xs text-gray-500 mt-1">{subtitle}</p>}
                    {showChange && (
                        <div className={`flex items-center mt-2 text-sm ${
                            isPositive ? 'text-green-600' : isNegative ? 'text-red-600' : 'text-gray-500'
                        }`}>
                            {isPositive ? (
                                <ArrowTrendingUpIcon className="w-4 h-4 mr-1" />
                            ) : isNegative ? (
                                <ArrowTrendingDownIcon className="w-4 h-4 mr-1" />
                            ) : (
                                <MinusIcon className="w-4 h-4 mr-1" />
                            )}
                            {Math.abs(change)}% vs last year
                        </div>
                    )}
                </div>
            </div>
        );
    };

    const WorkOrderStatusBadge = ({ status }) => {
        const styles = {
            completed: 'bg-green-100 text-green-800',
            cancelled: 'bg-gray-100 text-gray-800',
            open: 'bg-blue-100 text-blue-800',
            in_progress: 'bg-yellow-100 text-yellow-800',
        };

        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${styles[status] || 'bg-gray-100 text-gray-800'}`}>
                {status?.replace(/_/g, ' ') || 'Unknown'}
            </span>
        );
    };

    // Prepare chart data
    const chartData = spendTrend?.data?.map(item => ({
        period: item.period,
        spend: item.total_spend || 0,
        workOrders: item.work_order_count || 0,
    })) || [];

    const trades = vendor.vendor_trades?.split(',').map(t => t.trim()) || [];
    const yearlyChange = periodComparison?.last_12_months?.changes?.total_spend;

    return (
        <Layout>
            <Head title={`${vendor.company_name} - Vendor`} />

            <div className="space-y-6">
                {/* Back Button */}
                <Link
                    href="/vendors"
                    className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
                >
                    <ArrowLeftIcon className="w-4 h-4 mr-1" />
                    Back to Vendors
                </Link>

                {/* Header */}
                <div className="card">
                    <div className="card-body">
                        <div className="flex items-start justify-between">
                            <div className="flex items-start gap-4">
                                <div className="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <WrenchScrewdriverIcon className="w-8 h-8 text-blue-600" />
                                </div>
                                <div>
                                    <h1 className="text-2xl font-semibold text-gray-900">
                                        {vendor.company_name}
                                        {vendor.duplicate_vendors?.length > 0 && (
                                            <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded text-sm font-medium bg-gray-100 text-gray-600">
                                                +{vendor.duplicate_vendors.length} linked
                                            </span>
                                        )}
                                    </h1>
                                    {trades.length > 0 && (
                                        <div className="flex flex-wrap gap-2 mt-2">
                                            {trades.map((trade, idx) => (
                                                <span
                                                    key={idx}
                                                    className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                                >
                                                    {trade}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                    <div className="mt-3 flex flex-wrap gap-4 text-sm text-gray-500">
                                        {vendor.contact_name && (
                                            <span>{vendor.contact_name}</span>
                                        )}
                                        {vendor.phone && (
                                            <a href={`tel:${vendor.phone}`} className="flex items-center hover:text-gray-700">
                                                <PhoneIcon className="w-4 h-4 mr-1" />
                                                {vendor.phone}
                                            </a>
                                        )}
                                        {vendor.email && (
                                            <a href={`mailto:${vendor.email}`} className="flex items-center hover:text-gray-700">
                                                <EnvelopeIcon className="w-4 h-4 mr-1" />
                                                {vendor.email}
                                            </a>
                                        )}
                                    </div>
                                    {(vendor.address_street || vendor.address_city) && (
                                        <div className="mt-2 flex items-center text-sm text-gray-500">
                                            <MapPinIcon className="w-4 h-4 mr-1" />
                                            {[vendor.address_street, vendor.address_city, vendor.address_state, vendor.address_zip]
                                                .filter(Boolean)
                                                .join(', ')}
                                        </div>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                                    vendor.is_active
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-red-100 text-red-800'
                                }`}>
                                    {vendor.is_active ? 'Active' : 'Inactive'}
                                </span>
                                {vendor.do_not_use && (
                                    <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                        Do Not Use
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Metrics Summary */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <MetricCard
                        title="Work Orders (12 mo)"
                        value={metrics?.work_order_count || 0}
                        change={yearlyChange}
                    />
                    <MetricCard
                        title="Total Spend (12 mo)"
                        value={formatCurrency(metrics?.total_spend)}
                        change={periodComparison?.last_12_months?.changes?.total_spend}
                    />
                    <MetricCard
                        title="Avg Cost per WO"
                        value={formatCurrency(metrics?.avg_cost_per_wo)}
                        subtitle="Lower is better"
                        change={periodComparison?.last_12_months?.changes?.avg_cost_per_wo}
                        invertChange
                    />
                    <MetricCard
                        title="Avg Completion Time"
                        value={formatDays(metrics?.avg_completion_time)}
                        subtitle={responseComparison?.is_faster_than_average ? 'Faster than avg' : 'Slower than avg'}
                        change={periodComparison?.last_12_months?.changes?.avg_completion_time}
                        invertChange
                    />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Compliance Card */}
                    <div className="card">
                        <div className="card-header">
                            <h2 className="text-lg font-medium text-gray-900">Insurance Compliance</h2>
                        </div>
                        <div className="card-body space-y-3">
                            <InsuranceStatusBadge
                                status={insuranceStatus?.workers_comp}
                                date={vendor.workers_comp_expires}
                                label="Workers Comp"
                            />
                            <InsuranceStatusBadge
                                status={insuranceStatus?.liability}
                                date={vendor.liability_ins_expires}
                                label="Liability Insurance"
                            />
                            <InsuranceStatusBadge
                                status={insuranceStatus?.auto}
                                date={vendor.auto_ins_expires}
                                label="Auto Insurance"
                            />
                            {vendor.state_lic_expires && (
                                <div className="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                                    <span className="font-medium text-gray-700">State License</span>
                                    <span className="text-sm text-gray-600">
                                        {formatDate(vendor.state_lic_expires)}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Trade Comparison */}
                    {tradeAnalysis?.primary_trade && (
                        <div className="card lg:col-span-2">
                            <div className="card-header">
                                <h2 className="text-lg font-medium text-gray-900">
                                    Trade Comparison: {tradeAnalysis.primary_trade}
                                </h2>
                            </div>
                            <div className="card-body">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    {tradeAnalysis.rankings?.work_order_count && (
                                        <div className="text-center p-3 bg-gray-50 rounded-lg">
                                            <p className="text-2xl font-semibold text-gray-900">
                                                #{tradeAnalysis.rankings.work_order_count.rank}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                of {tradeAnalysis.rankings.work_order_count.total} vendors
                                            </p>
                                            <p className="text-sm font-medium text-gray-700 mt-1">Work Orders</p>
                                        </div>
                                    )}
                                    {tradeAnalysis.rankings?.total_spend && (
                                        <div className="text-center p-3 bg-gray-50 rounded-lg">
                                            <p className="text-2xl font-semibold text-gray-900">
                                                #{tradeAnalysis.rankings.total_spend.rank}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                of {tradeAnalysis.rankings.total_spend.total} vendors
                                            </p>
                                            <p className="text-sm font-medium text-gray-700 mt-1">Total Spend</p>
                                        </div>
                                    )}
                                    {tradeAnalysis.rankings?.avg_cost && (
                                        <div className="text-center p-3 bg-gray-50 rounded-lg">
                                            <p className="text-2xl font-semibold text-gray-900">
                                                #{tradeAnalysis.rankings.avg_cost.rank}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                of {tradeAnalysis.rankings.avg_cost.total} vendors
                                            </p>
                                            <p className="text-sm font-medium text-gray-700 mt-1">Avg Cost</p>
                                        </div>
                                    )}
                                    {tradeAnalysis.rankings?.avg_completion_time && (
                                        <div className="text-center p-3 bg-gray-50 rounded-lg">
                                            <p className="text-2xl font-semibold text-gray-900">
                                                #{tradeAnalysis.rankings.avg_completion_time.rank}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                of {tradeAnalysis.rankings.avg_completion_time.total} vendors
                                            </p>
                                            <p className="text-sm font-medium text-gray-700 mt-1">Speed</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Spend Trend Chart */}
                {chartData.length > 0 && (
                    <div className="card">
                        <div className="card-header flex items-center justify-between">
                            <h2 className="text-lg font-medium text-gray-900">Spending Trend (12 Months)</h2>
                            <div className="flex items-center gap-2">
                                <select
                                    value={chartType}
                                    onChange={(e) => setChartType(e.target.value)}
                                    className="input text-sm py-1"
                                >
                                    <option value="line">Line Chart</option>
                                    <option value="bar">Bar Chart</option>
                                </select>
                                <button
                                    onClick={() => exportSpendDataToCSV()}
                                    className="btn-secondary flex items-center text-sm py-1 px-2"
                                >
                                    <ArrowDownTrayIcon className="w-4 h-4 mr-1" />
                                    Export CSV
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
                                                    if (name === 'spend') return [formatCurrency(value), 'Spend'];
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
                )}

                {/* Spend by Property */}
                {spendByProperty && spendByProperty.length > 0 && (
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
                )}

                {/* Work Order History */}
                <div className="card">
                    <div className="card-header">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-medium text-gray-900">Work Order History</h2>
                            {workOrders.total > 0 && (
                                <span className="text-sm text-gray-500">
                                    Showing {workOrders.from}-{workOrders.to} of {workOrders.total}
                                </span>
                            )}
                        </div>

                        {/* Summary Stats */}
                        {workOrderStats && Object.keys(workOrderStats).length > 0 && (
                            <div className="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div className="bg-gray-50 p-3 rounded-lg">
                                    <p className="text-xs text-gray-500 uppercase">Total</p>
                                    <p className="text-lg font-semibold text-gray-900">{workOrderStats.total || 0}</p>
                                </div>
                                <div className="bg-gray-50 p-3 rounded-lg">
                                    <p className="text-xs text-gray-500 uppercase">Total Spend</p>
                                    <p className="text-lg font-semibold text-gray-900">{formatCurrency(workOrderStats.total_spend)}</p>
                                </div>
                                <div className="bg-gray-50 p-3 rounded-lg">
                                    <p className="text-xs text-gray-500 uppercase">Completed</p>
                                    <p className="text-lg font-semibold text-green-600">{workOrderStats.completed || 0}</p>
                                </div>
                                <div className="bg-gray-50 p-3 rounded-lg">
                                    <p className="text-xs text-gray-500 uppercase">Open</p>
                                    <p className="text-lg font-semibold text-blue-600">{workOrderStats.open || 0}</p>
                                </div>
                            </div>
                        )}

                        {/* Filters */}
                        <div className="mt-4 flex flex-wrap gap-3 items-end">
                            <div>
                                <label htmlFor="wo_status" className="block text-xs font-medium text-gray-500 mb-1">
                                    Status
                                </label>
                                <select
                                    id="wo_status"
                                    className="input text-sm py-1.5"
                                    value={workOrderFilters.wo_status || ''}
                                    onChange={(e) => {
                                        router.get(`/vendors/${vendor.id}`, {
                                            ...workOrderFilters,
                                            wo_status: e.target.value || undefined,
                                            wo_page: 1,
                                        }, {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <option value="">All Statuses</option>
                                    <option value="completed">Completed</option>
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            {workOrderProperties.length > 0 && (
                                <div>
                                    <label htmlFor="wo_property" className="block text-xs font-medium text-gray-500 mb-1">
                                        Property
                                    </label>
                                    <select
                                        id="wo_property"
                                        className="input text-sm py-1.5"
                                        value={workOrderFilters.wo_property || ''}
                                        onChange={(e) => {
                                            router.get(`/vendors/${vendor.id}`, {
                                                ...workOrderFilters,
                                                wo_property: e.target.value || undefined,
                                                wo_page: 1,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <option value="">All Properties</option>
                                        {workOrderProperties.map((property) => (
                                            <option key={property.id} value={property.id}>
                                                {property.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            {(workOrderFilters.wo_status || workOrderFilters.wo_property) && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        router.get(`/vendors/${vendor.id}`, {
                                            wo_sort: workOrderFilters.wo_sort,
                                            wo_direction: workOrderFilters.wo_direction,
                                        }, {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                    className="btn-secondary flex items-center text-sm py-1.5"
                                >
                                    <XMarkIcon className="w-4 h-4 mr-1" />
                                    Clear Filters
                                </button>
                            )}
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Work Order
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Property
                                    </th>
                                    <th
                                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        onClick={() => {
                                            const newDirection = workOrderFilters.wo_sort === 'status' && workOrderFilters.wo_direction === 'asc' ? 'desc' : 'asc';
                                            router.get(`/vendors/${vendor.id}`, {
                                                ...workOrderFilters,
                                                wo_sort: 'status',
                                                wo_direction: newDirection,
                                                wo_page: 1,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <div className="flex items-center gap-1">
                                            Status
                                            {workOrderFilters.wo_sort === 'status' ? (
                                                workOrderFilters.wo_direction === 'asc' ?
                                                    <ChevronUpIcon className="w-4 h-4 text-blue-600" /> :
                                                    <ChevronDownIcon className="w-4 h-4 text-blue-600" />
                                            ) : (
                                                <ChevronUpIcon className="w-4 h-4 text-gray-300" />
                                            )}
                                        </div>
                                    </th>
                                    <th
                                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        onClick={() => {
                                            const newDirection = workOrderFilters.wo_sort === 'opened_at' && workOrderFilters.wo_direction === 'desc' ? 'asc' : 'desc';
                                            router.get(`/vendors/${vendor.id}`, {
                                                ...workOrderFilters,
                                                wo_sort: 'opened_at',
                                                wo_direction: newDirection,
                                                wo_page: 1,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <div className="flex items-center gap-1">
                                            Opened
                                            {workOrderFilters.wo_sort === 'opened_at' ? (
                                                workOrderFilters.wo_direction === 'asc' ?
                                                    <ChevronUpIcon className="w-4 h-4 text-blue-600" /> :
                                                    <ChevronDownIcon className="w-4 h-4 text-blue-600" />
                                            ) : (
                                                <ChevronUpIcon className="w-4 h-4 text-gray-300" />
                                            )}
                                        </div>
                                    </th>
                                    <th
                                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        onClick={() => {
                                            const newDirection = workOrderFilters.wo_sort === 'closed_at' && workOrderFilters.wo_direction === 'desc' ? 'asc' : 'desc';
                                            router.get(`/vendors/${vendor.id}`, {
                                                ...workOrderFilters,
                                                wo_sort: 'closed_at',
                                                wo_direction: newDirection,
                                                wo_page: 1,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <div className="flex items-center gap-1">
                                            Closed
                                            {workOrderFilters.wo_sort === 'closed_at' ? (
                                                workOrderFilters.wo_direction === 'asc' ?
                                                    <ChevronUpIcon className="w-4 h-4 text-blue-600" /> :
                                                    <ChevronDownIcon className="w-4 h-4 text-blue-600" />
                                            ) : (
                                                <ChevronUpIcon className="w-4 h-4 text-gray-300" />
                                            )}
                                        </div>
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Days
                                    </th>
                                    <th
                                        className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        onClick={() => {
                                            const newDirection = workOrderFilters.wo_sort === 'amount' && workOrderFilters.wo_direction === 'desc' ? 'asc' : 'desc';
                                            router.get(`/vendors/${vendor.id}`, {
                                                ...workOrderFilters,
                                                wo_sort: 'amount',
                                                wo_direction: newDirection,
                                                wo_page: 1,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <div className="flex items-center gap-1 justify-end">
                                            Amount
                                            {workOrderFilters.wo_sort === 'amount' ? (
                                                workOrderFilters.wo_direction === 'asc' ?
                                                    <ChevronUpIcon className="w-4 h-4 text-blue-600" /> :
                                                    <ChevronDownIcon className="w-4 h-4 text-blue-600" />
                                            ) : (
                                                <ChevronUpIcon className="w-4 h-4 text-gray-300" />
                                            )}
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {workOrders.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="px-6 py-12 text-center">
                                            <ClipboardDocumentListIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                            <p className="text-gray-500">No work orders found</p>
                                            {(workOrderFilters.wo_status || workOrderFilters.wo_property) && (
                                                <button
                                                    onClick={() => {
                                                        router.get(`/vendors/${vendor.id}`, {}, {
                                                            preserveState: true,
                                                            preserveScroll: true,
                                                        });
                                                    }}
                                                    className="mt-2 text-blue-600 hover:text-blue-700 text-sm"
                                                >
                                                    Clear filters
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ) : (
                                    workOrders.data.map((wo) => {
                                        const daysToComplete = wo.opened_at && wo.closed_at
                                            ? Math.ceil((new Date(wo.closed_at) - new Date(wo.opened_at)) / (1000 * 60 * 60 * 24))
                                            : null;

                                        return (
                                            <tr key={wo.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {wo.external_id || wo.id.slice(0, 8)}
                                                    </div>
                                                    {wo.description && (
                                                        <div className="text-sm text-gray-500 truncate max-w-xs">
                                                            {wo.description}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {wo.property ? (
                                                        <Link
                                                            href={`/properties/${wo.property.id}`}
                                                            className="text-sm text-blue-600 hover:text-blue-800"
                                                        >
                                                            {wo.property.name}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-sm text-gray-400">-</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <WorkOrderStatusBadge status={wo.status} />
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDate(wo.opened_at)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatDate(wo.closed_at)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {daysToComplete !== null ? (
                                                        <span className={daysToComplete <= 7 ? 'text-green-600' : daysToComplete <= 14 ? 'text-yellow-600' : 'text-red-600'}>
                                                            {daysToComplete}
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-400">-</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    {formatCurrency(wo.amount)}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {workOrders.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <div className="text-sm text-gray-500">
                                Page {workOrders.current_page} of {workOrders.last_page}
                            </div>
                            <div className="flex gap-2">
                                {workOrders.current_page > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            router.get(`/vendors/${vendor.id}`, {
                                                ...workOrderFilters,
                                                wo_page: workOrders.current_page - 1,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                        className="btn-secondary flex items-center"
                                    >
                                        <ChevronLeftIcon className="w-4 h-4 mr-1" />
                                        Previous
                                    </button>
                                )}
                                {workOrders.current_page < workOrders.last_page && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            router.get(`/vendors/${vendor.id}`, {
                                                ...workOrderFilters,
                                                wo_page: workOrders.current_page + 1,
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                        className="btn-secondary flex items-center"
                                    >
                                        Next
                                        <ChevronRightIcon className="w-4 h-4 ml-1" />
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* Response Time Breakdown */}
                {responseMetrics?.total_completed > 0 && (
                    <div className="card">
                        <div className="card-header">
                            <h2 className="text-lg font-medium text-gray-900">Response Time Breakdown</h2>
                        </div>
                        <div className="card-body">
                            <div className="grid grid-cols-2 md:grid-cols-6 gap-4">
                                {Object.entries(responseMetrics.completion_buckets || {}).map(([key, bucket]) => (
                                    <div key={key} className="text-center p-3 bg-gray-50 rounded-lg">
                                        <p className="text-xl font-semibold text-gray-900">{bucket.count}</p>
                                        <p className="text-xs text-gray-500">{bucket.percentage}%</p>
                                        <p className="text-sm font-medium text-gray-700 mt-1">{bucket.label}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </Layout>
    );
}
