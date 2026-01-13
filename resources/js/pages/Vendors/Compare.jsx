import { Head, Link, router } from '@inertiajs/react';
import Layout from '../../components/Layout';
import {
    ArrowLeftIcon,
    WrenchScrewdriverIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ScaleIcon,
} from '@heroicons/react/24/outline';

export default function VendorCompare({ vendors, comparison, trades, selectedTrade }) {
    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '-';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    const formatDays = (days) => {
        if (days === null || days === undefined) return '-';
        return `${Math.round(days)} days`;
    };

    const handleTradeChange = (trade) => {
        router.get('/vendors/compare', { trade }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const getValueClass = (metric, value) => {
        if (value === null || value === undefined || !comparison[metric]) {
            return '';
        }

        if (value === comparison[metric].best) {
            return 'bg-green-100 text-green-800 font-semibold';
        }
        if (value === comparison[metric].worst) {
            return 'bg-red-100 text-red-800';
        }
        return '';
    };

    const getBestLabel = (metric) => {
        // For these metrics, higher is better
        if (metric === 'work_order_count' || metric === 'total_spend') {
            return 'Most';
        }
        // For these metrics, lower is better
        return 'Fastest';
    };

    const getWorstLabel = (metric) => {
        if (metric === 'work_order_count' || metric === 'total_spend') {
            return 'Least';
        }
        return 'Slowest';
    };

    const InsuranceStatusBadge = ({ status }) => {
        const overall = status?.overall || 'missing';

        const styles = {
            current: { bg: 'bg-green-100', text: 'text-green-800', icon: CheckCircleIcon, label: 'Current' },
            expiring_soon: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: ClockIcon, label: 'Expiring' },
            expired: { bg: 'bg-red-100', text: 'text-red-800', icon: XCircleIcon, label: 'Expired' },
            missing: { bg: 'bg-gray-100', text: 'text-gray-600', icon: ExclamationTriangleIcon, label: 'Missing' },
        };

        const style = styles[overall] || styles.missing;
        const Icon = style.icon;

        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${style.bg} ${style.text}`}>
                <Icon className="w-3.5 h-3.5 mr-1" />
                {style.label}
            </span>
        );
    };

    return (
        <Layout>
            <Head title="Compare Vendors" />

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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900 flex items-center gap-2">
                            <ScaleIcon className="w-7 h-7 text-blue-600" />
                            Compare Vendors
                        </h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Compare vendors side-by-side within a trade
                        </p>
                    </div>
                </div>

                {/* Trade Selector */}
                <div className="card">
                    <div className="card-body">
                        <div className="flex flex-wrap gap-4 items-end">
                            <div>
                                <label htmlFor="trade" className="label">
                                    Select Trade to Compare
                                </label>
                                <select
                                    id="trade"
                                    className="input"
                                    value={selectedTrade || ''}
                                    onChange={(e) => handleTradeChange(e.target.value)}
                                >
                                    {trades.length === 0 ? (
                                        <option value="">No trades available</option>
                                    ) : (
                                        trades.map((trade) => (
                                            <option key={trade} value={trade}>
                                                {trade}
                                            </option>
                                        ))
                                    )}
                                </select>
                            </div>
                            {selectedTrade && (
                                <div className="text-sm text-gray-500">
                                    Comparing {vendors.length} vendor{vendors.length !== 1 ? 's' : ''} in {selectedTrade}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Legend */}
                {vendors.length > 1 && (
                    <div className="flex items-center gap-4 text-sm">
                        <span className="inline-flex items-center gap-1">
                            <span className="w-4 h-4 rounded bg-green-100 border border-green-300" />
                            <span className="text-gray-600">Best value</span>
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <span className="w-4 h-4 rounded bg-red-100 border border-red-300" />
                            <span className="text-gray-600">Worst value</span>
                        </span>
                    </div>
                )}

                {/* Comparison Table */}
                {vendors.length === 0 ? (
                    <div className="card">
                        <div className="card-body py-12 text-center">
                            <WrenchScrewdriverIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                            <p className="text-gray-500">
                                {selectedTrade
                                    ? `No active vendors found in ${selectedTrade}`
                                    : 'Select a trade to compare vendors'}
                            </p>
                        </div>
                    </div>
                ) : vendors.length === 1 ? (
                    <div className="card">
                        <div className="card-body py-12 text-center">
                            <ExclamationTriangleIcon className="w-12 h-12 text-yellow-400 mx-auto mb-4" />
                            <p className="text-gray-600 font-medium">Only one vendor in this trade</p>
                            <p className="text-gray-500 text-sm mt-2">
                                Select a different trade to compare multiple vendors
                            </p>
                            <Link
                                href={`/vendors/${vendors[0].id}`}
                                className="inline-flex items-center mt-4 text-blue-600 hover:text-blue-800"
                            >
                                View {vendors[0].company_name} details
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="card">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">
                                            Vendor
                                        </th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Work Orders
                                            <div className="text-[10px] normal-case text-gray-400 font-normal">Last 12 mo</div>
                                        </th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total Spend
                                            <div className="text-[10px] normal-case text-gray-400 font-normal">Last 12 mo</div>
                                        </th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Avg Cost/WO
                                            <div className="text-[10px] normal-case text-gray-400 font-normal">Lower is better</div>
                                        </th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Avg Completion
                                            <div className="text-[10px] normal-case text-gray-400 font-normal">Lower is better</div>
                                        </th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Insurance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {vendors.map((vendor) => (
                                        <tr key={vendor.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap sticky left-0 bg-white z-10">
                                                <Link href={`/vendors/${vendor.id}`} className="flex items-center group">
                                                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                        <WrenchScrewdriverIcon className="w-5 h-5 text-blue-600" />
                                                    </div>
                                                    <div className="ml-3">
                                                        <div className="text-sm font-medium text-gray-900 group-hover:text-blue-600">
                                                            {vendor.company_name}
                                                        </div>
                                                        {vendor.contact_name && (
                                                            <div className="text-xs text-gray-500">
                                                                {vendor.contact_name}
                                                            </div>
                                                        )}
                                                    </div>
                                                </Link>
                                            </td>
                                            <td className={`px-6 py-4 whitespace-nowrap text-center ${getValueClass('work_order_count', vendor.work_order_count)}`}>
                                                <div className="text-sm font-medium">
                                                    {vendor.work_order_count}
                                                </div>
                                            </td>
                                            <td className={`px-6 py-4 whitespace-nowrap text-center ${getValueClass('total_spend', vendor.total_spend)}`}>
                                                <div className="text-sm font-medium">
                                                    {formatCurrency(vendor.total_spend)}
                                                </div>
                                            </td>
                                            <td className={`px-6 py-4 whitespace-nowrap text-center ${getValueClass('avg_cost_per_wo', vendor.avg_cost_per_wo)}`}>
                                                <div className="text-sm font-medium">
                                                    {vendor.avg_cost_per_wo ? formatCurrency(vendor.avg_cost_per_wo) : '-'}
                                                </div>
                                            </td>
                                            <td className={`px-6 py-4 whitespace-nowrap text-center ${getValueClass('avg_completion_time', vendor.avg_completion_time)}`}>
                                                <div className="text-sm font-medium">
                                                    {formatDays(vendor.avg_completion_time)}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-center">
                                                <InsuranceStatusBadge status={vendor.insurance_status} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Summary Row */}
                        {Object.keys(comparison).length > 0 && (
                            <div className="border-t border-gray-200 bg-gray-50 px-6 py-4">
                                <h3 className="text-sm font-medium text-gray-700 mb-3">Trade Averages</h3>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    {comparison.work_order_count && (
                                        <div className="text-center">
                                            <p className="text-xs text-gray-500 uppercase">Avg Work Orders</p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {Math.round(comparison.work_order_count.avg)}
                                            </p>
                                        </div>
                                    )}
                                    {comparison.total_spend && (
                                        <div className="text-center">
                                            <p className="text-xs text-gray-500 uppercase">Avg Spend</p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatCurrency(comparison.total_spend.avg)}
                                            </p>
                                        </div>
                                    )}
                                    {comparison.avg_cost_per_wo && (
                                        <div className="text-center">
                                            <p className="text-xs text-gray-500 uppercase">Avg Cost/WO</p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatCurrency(comparison.avg_cost_per_wo.avg)}
                                            </p>
                                        </div>
                                    )}
                                    {comparison.avg_completion_time && (
                                        <div className="text-center">
                                            <p className="text-xs text-gray-500 uppercase">Avg Completion</p>
                                            <p className="text-lg font-semibold text-gray-900">
                                                {formatDays(comparison.avg_completion_time.avg)}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Vendor Cards for Mobile */}
                {vendors.length > 1 && (
                    <div className="md:hidden space-y-4">
                        {vendors.map((vendor) => (
                            <div key={vendor.id} className="card">
                                <div className="card-body">
                                    <Link href={`/vendors/${vendor.id}`} className="flex items-center gap-3 mb-4">
                                        <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <WrenchScrewdriverIcon className="w-6 h-6 text-blue-600" />
                                        </div>
                                        <div>
                                            <h3 className="font-medium text-gray-900">{vendor.company_name}</h3>
                                            {vendor.contact_name && (
                                                <p className="text-sm text-gray-500">{vendor.contact_name}</p>
                                            )}
                                        </div>
                                    </Link>

                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                        <div className={`p-2 rounded ${getValueClass('work_order_count', vendor.work_order_count)}`}>
                                            <p className="text-xs text-gray-500">Work Orders</p>
                                            <p className="font-medium">{vendor.work_order_count}</p>
                                        </div>
                                        <div className={`p-2 rounded ${getValueClass('total_spend', vendor.total_spend)}`}>
                                            <p className="text-xs text-gray-500">Total Spend</p>
                                            <p className="font-medium">{formatCurrency(vendor.total_spend)}</p>
                                        </div>
                                        <div className={`p-2 rounded ${getValueClass('avg_cost_per_wo', vendor.avg_cost_per_wo)}`}>
                                            <p className="text-xs text-gray-500">Avg Cost/WO</p>
                                            <p className="font-medium">{vendor.avg_cost_per_wo ? formatCurrency(vendor.avg_cost_per_wo) : '-'}</p>
                                        </div>
                                        <div className={`p-2 rounded ${getValueClass('avg_completion_time', vendor.avg_completion_time)}`}>
                                            <p className="text-xs text-gray-500">Completion Time</p>
                                            <p className="font-medium">{formatDays(vendor.avg_completion_time)}</p>
                                        </div>
                                    </div>

                                    <div className="mt-3 flex justify-between items-center">
                                        <span className="text-xs text-gray-500">Insurance</span>
                                        <InsuranceStatusBadge status={vendor.insurance_status} />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </Layout>
    );
}
