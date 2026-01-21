import { Head } from '@inertiajs/react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import Badge from '../../components/Badge';
import {
    MetricCard,
    InsuranceComplianceCard,
    TradeComparisonCard,
    SpendTrendChart,
    SpendByPropertyChart,
    WorkOrderHistory,
    ResponseTimeBreakdown,
    formatCurrency,
    formatDays,
} from '../../components/Vendor';
import {
    WrenchScrewdriverIcon,
    PhoneIcon,
    EnvelopeIcon,
    MapPinIcon,
} from '@heroicons/react/24/outline';

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
    const workOrderCountChange = periodComparison?.last_12_months?.changes?.work_order_count;

    return (
        <Layout>
            <Head title={`${vendor.company_name} - Vendor`} />

            <div className="space-y-6">
                {/* Header */}
                <PageHeader
                    title={
                        <>
                            {vendor.company_name}
                            {vendor.duplicate_vendors?.length > 0 && (
                                <Badge
                                    label={`+${vendor.duplicate_vendors.length} linked`}
                                    variant="neutral"
                                    size="sm"
                                    className="ml-2"
                                />
                            )}
                        </>
                    }
                    backHref={route('vendors.index')}
                    icon={WrenchScrewdriverIcon}
                    iconBgColor="bg-blue-100"
                    iconColor="text-blue-600"
                    statusBadge={{
                        label: vendor.is_active ? 'Active' : 'Inactive',
                        variant: vendor.is_active ? 'success' : 'danger',
                    }}
                    badges={vendor.do_not_use ? [{ label: 'Do Not Use', variant: 'danger' }] : []}
                    tags={(vendor.vendor_trades ?? '').split(',').map(t => t.trim()).filter(Boolean)}
                    tagVariant="blue"
                    secondaryInfo={
                        <div className="flex flex-col sm:flex-row sm:flex-wrap gap-2 sm:gap-4">
                            {vendor.contact_name && (
                                <span>{vendor.contact_name}</span>
                            )}
                            {vendor.phone && (
                                <a href={`tel:${vendor.phone}`} className="flex items-center hover:text-gray-700 min-h-[44px] sm:min-h-0">
                                    <PhoneIcon className="w-4 h-4 mr-1" />
                                    {vendor.phone}
                                </a>
                            )}
                            {vendor.email && (
                                <a href={`mailto:${vendor.email}`} className="flex items-center hover:text-gray-700 min-h-[44px] sm:min-h-0 truncate max-w-full">
                                    <EnvelopeIcon className="w-4 h-4 mr-1 flex-shrink-0" />
                                    <span className="truncate">{vendor.email}</span>
                                </a>
                            )}
                            {(vendor.address_street || vendor.address_city) && (
                                <span className="flex items-center">
                                    <MapPinIcon className="w-4 h-4 mr-1 flex-shrink-0" />
                                    <span className="truncate">{[vendor.address_street, vendor.address_city, vendor.address_state, vendor.address_zip]
                                        .filter(Boolean)
                                        .join(', ')}</span>
                                </span>
                            )}
                        </div>
                    }
                    sticky
                />

                {/* Metrics Summary - 1-col on mobile, 3-col on desktop */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4">
                    <MetricCard
                        title="Work Orders (12 mo)"
                        value={metrics?.work_order_count || 0}
                        change={workOrderCountChange}
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
