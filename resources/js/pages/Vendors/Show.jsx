import { Head, Link } from '@inertiajs/react';
import Layout from '../../components/Layout';
import {
    MetricCard,
    VendorHeader,
    InsuranceComplianceCard,
    TradeComparisonCard,
    SpendTrendChart,
    SpendByPropertyChart,
    WorkOrderHistory,
    ResponseTimeBreakdown,
    formatCurrency,
    formatDays,
} from '../../components/Vendor';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

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
    const yearlyChange = periodComparison?.last_12_months?.changes?.total_spend;

    return (
        <Layout>
            <Head title={`${vendor.company_name} - Vendor`} />

            <div className="space-y-6">
                {/* Back Button */}
                <Link
                    href={route('vendors.index')}
                    className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
                >
                    <ArrowLeftIcon className="w-4 h-4 mr-1" />
                    Back to Vendors
                </Link>

                {/* Header */}
                <VendorHeader vendor={vendor} />

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

                {/* Insurance and Trade Comparison */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <InsuranceComplianceCard
                        vendor={vendor}
                        insuranceStatus={insuranceStatus}
                    />
                    <TradeComparisonCard tradeAnalysis={tradeAnalysis} />
                </div>

                {/* Spend Trend Chart */}
                <SpendTrendChart
                    spendTrend={spendTrend}
                    vendorName={vendor.company_name}
                />

                {/* Spend by Property */}
                <SpendByPropertyChart spendByProperty={spendByProperty} />

                {/* Work Order History */}
                <WorkOrderHistory
                    vendorId={vendor.id}
                    workOrders={workOrders}
                    workOrderProperties={workOrderProperties}
                    workOrderStats={workOrderStats}
                    workOrderFilters={workOrderFilters}
                />

                {/* Response Time Breakdown */}
                <ResponseTimeBreakdown responseMetrics={responseMetrics} />
            </div>
        </Layout>
    );
}
