import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import {
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    NoSymbolIcon,
    PrinterIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    TableCellsIcon,
    ShieldCheckIcon,
    ScaleIcon,
    LinkIcon,
} from '@heroicons/react/24/outline';

export default function VendorsCompliance({
    expired,
    expiringSoon,
    expiringQuarter,
    missingInfo,
    compliant,
    doNotUse,
    workersCompIssues,
    stats,
}) {
    const [activeTab, setActiveTab] = useState('overview');
    const [expandedSections, setExpandedSections] = useState({
        expired: true,
        expiringSoon: true,
        expiringQuarter: false,
        workersComp: true,
    });

    const toggleSection = (section) => {
        setExpandedSections(prev => ({
            ...prev,
            [section]: !prev[section],
        }));
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const { auth } = usePage().props;
    const isAdmin = auth.user?.role?.name === 'admin';

    // Navigation tabs for vendor pages
    const navTabs = [
        { label: 'All Vendors', href: route('vendors.index'), icon: TableCellsIcon },
        { label: 'Compliance', href: route('vendors.compliance'), icon: ShieldCheckIcon },
        { label: 'Compare', href: route('vendors.compare'), icon: ScaleIcon },
        ...(isAdmin ? [{ label: 'Deduplication', href: route('vendors.deduplication'), icon: LinkIcon }] : []),
    ];

    // Internal tabs for this page
    const internalTabs = [
        { id: 'overview', name: 'Overview', count: null },
        { id: 'expired', name: 'Expired', count: stats.expired, color: 'bg-red-100 text-red-800' },
        { id: 'expiring', name: 'Expiring Soon', count: stats.expiring_soon, color: 'bg-yellow-100 text-yellow-800' },
        { id: 'workers-comp', name: 'Workers Comp', count: stats.workers_comp_issues, color: 'bg-orange-100 text-orange-800' },
        { id: 'do-not-use', name: 'Do Not Use', count: stats.do_not_use, color: 'bg-gray-100 text-gray-800' },
    ];

    const VendorIssueRow = ({ vendor, issues, showDaysPast = false }) => (
        <tr className="hover:bg-gray-50">
            <td className="px-6 py-4">
                <div className="text-sm font-medium text-gray-900">{vendor.company_name}</div>
                {vendor.contact_name && (
                    <div className="text-xs text-gray-500">{vendor.contact_name}</div>
                )}
            </td>
            <td className="px-6 py-4">
                <div className="flex flex-wrap gap-1">
                    {issues.map((issue, idx) => (
                        <span
                            key={idx}
                            className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                                showDaysPast
                                    ? 'bg-red-100 text-red-800'
                                    : 'bg-yellow-100 text-yellow-800'
                            }`}
                        >
                            {issue.type}
                        </span>
                    ))}
                </div>
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {issues.map((issue, idx) => (
                    <div key={idx}>{formatDate(issue.date)}</div>
                ))}
            </td>
            <td className="px-6 py-4 whitespace-nowrap">
                {issues.map((issue, idx) => (
                    <div key={idx} className={`text-sm font-medium ${showDaysPast ? 'text-red-600' : 'text-yellow-600'}`}>
                        {showDaysPast
                            ? `${issue.days_past} days overdue`
                            : `${issue.days_until} days left`}
                    </div>
                ))}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {vendor.phone || vendor.email || '-'}
            </td>
        </tr>
    );

    const SectionHeader = ({ title, count, sectionKey, icon: Icon, color }) => (
        <button
            type="button"
            onClick={() => toggleSection(sectionKey)}
            className="w-full flex items-center justify-between px-4 sm:px-6 py-4 bg-gray-50 hover:bg-gray-100 transition-colors min-h-[56px]"
        >
            <div className="flex items-center gap-2 sm:gap-3 flex-wrap">
                <Icon className={`w-5 h-5 ${color}`} />
                <span className="text-sm font-medium text-gray-900">{title}</span>
                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                    color === 'text-red-600' ? 'bg-red-100 text-red-800' :
                    color === 'text-yellow-600' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-gray-100 text-gray-800'
                }`}>
                    {count}
                </span>
            </div>
            {expandedSections[sectionKey] ? (
                <ChevronUpIcon className="w-5 h-5 text-gray-400 flex-shrink-0" />
            ) : (
                <ChevronDownIcon className="w-5 h-5 text-gray-400 flex-shrink-0" />
            )}
        </button>
    );

    return (
        <Layout>
            <Head title="Vendor Compliance" />

            <div className="flex flex-col h-[calc(100vh-64px)] -m-4 md:-m-8">
                {/* Header - doesn't scroll */}
                <div className="flex-shrink-0 px-4 md:px-8 pt-4 md:pt-8">
                    <PageHeader
                        title="Insurance Compliance"
                        subtitle="Track vendor insurance status and compliance issues"
                        tabs={navTabs}
                        activeTab="Compliance"
                        sticky={false}
                        actions={
                            <button
                                type="button"
                                onClick={() => window.print()}
                                className="btn-secondary flex items-center min-h-[44px] sm:min-h-0"
                                title="Print report"
                            >
                                <PrinterIcon className="w-4 h-4 sm:mr-2" />
                                <span className="hidden sm:inline">Print</span>
                            </button>
                        }
                    />
                </div>

                {/* Summary Stats - doesn't scroll */}
                <div className="flex-shrink-0 px-4 md:px-8 pt-6">
                    {/* Summary Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                    <div className="card">
                        <div className="card-body text-center">
                            <p className="text-xs font-medium text-gray-500 uppercase">Total</p>
                            <p className="text-2xl font-semibold text-gray-900">{stats.total_vendors}</p>
                        </div>
                    </div>
                    <div className="card bg-green-50 border-green-200">
                        <div className="card-body text-center">
                            <p className="text-xs font-medium text-green-700 uppercase">Compliant</p>
                            <p className="text-2xl font-semibold text-green-700">{stats.compliant}</p>
                        </div>
                    </div>
                    <div className="card bg-red-50 border-red-200">
                        <div className="card-body text-center">
                            <p className="text-xs font-medium text-red-700 uppercase">Expired</p>
                            <p className="text-2xl font-semibold text-red-700">{stats.expired}</p>
                        </div>
                    </div>
                    <div className="card bg-yellow-50 border-yellow-200">
                        <div className="card-body text-center">
                            <p className="text-xs font-medium text-yellow-700 uppercase">Expiring Soon</p>
                            <p className="text-2xl font-semibold text-yellow-700">{stats.expiring_soon}</p>
                        </div>
                    </div>
                    <div className="card bg-orange-50 border-orange-200">
                        <div className="card-body text-center">
                            <p className="text-xs font-medium text-orange-700 uppercase">This Quarter</p>
                            <p className="text-2xl font-semibold text-orange-700">{stats.expiring_quarter}</p>
                        </div>
                    </div>
                    <div className="card bg-gray-50 border-gray-200">
                        <div className="card-body text-center">
                            <p className="text-xs font-medium text-gray-600 uppercase">Missing Info</p>
                            <p className="text-2xl font-semibold text-gray-700">{stats.missing_info}</p>
                        </div>
                    </div>
                    <div className="card bg-gray-100 border-gray-300">
                        <div className="card-body text-center">
                            <p className="text-xs font-medium text-gray-600 uppercase">Do Not Use</p>
                            <p className="text-2xl font-semibold text-gray-700">{stats.do_not_use}</p>
                        </div>
                    </div>
                    </div>
                </div>

                {/* Internal Tabs - doesn't scroll */}
                <div className="flex-shrink-0 px-4 md:px-8 pt-6">
                    <div className="border-b border-gray-200 overflow-x-auto">
                        <nav className="-mb-px flex space-x-4 sm:space-x-8 min-w-max">
                            {internalTabs.map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 min-h-[44px] ${
                                    activeTab === tab.id
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                {tab.name}
                                {tab.count !== null && tab.count > 0 && (
                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${tab.color}`}>
                                        {tab.count}
                                    </span>
                                )}
                            </button>
                        ))}
                        </nav>
                    </div>
                </div>

                {/* Tab Content - scrollable */}
                <div className="flex-1 min-h-0 px-4 md:px-8 pt-6 pb-4 md:pb-8 overflow-auto">
                    {activeTab === 'overview' && (
                    <div className="space-y-4">
                        {/* Expired Section */}
                        {expired.length > 0 && (
                            <div className="card overflow-hidden">
                                <SectionHeader
                                    title="Expired Insurance"
                                    count={expired.length}
                                    sectionKey="expired"
                                    icon={XCircleIcon}
                                    color="text-red-600"
                                />
                                {expandedSections.expired && (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Insurance Type</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expired Date</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {expired.map((item, idx) => (
                                                    <VendorIssueRow
                                                        key={idx}
                                                        vendor={item.vendor}
                                                        issues={item.issues}
                                                        showDaysPast={true}
                                                    />
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Expiring Soon Section */}
                        {expiringSoon.length > 0 && (
                            <div className="card overflow-hidden">
                                <SectionHeader
                                    title="Expiring Within 30 Days"
                                    count={expiringSoon.length}
                                    sectionKey="expiringSoon"
                                    icon={ClockIcon}
                                    color="text-yellow-600"
                                />
                                {expandedSections.expiringSoon && (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Insurance Type</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiration Date</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Left</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {expiringSoon.map((item, idx) => (
                                                    <VendorIssueRow
                                                        key={idx}
                                                        vendor={item.vendor}
                                                        issues={item.issues}
                                                        showDaysPast={false}
                                                    />
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Expiring This Quarter */}
                        {expiringQuarter.length > 0 && (
                            <div className="card overflow-hidden">
                                <SectionHeader
                                    title="Expiring This Quarter (31-90 Days)"
                                    count={expiringQuarter.length}
                                    sectionKey="expiringQuarter"
                                    icon={ClockIcon}
                                    color="text-gray-500"
                                />
                                {expandedSections.expiringQuarter && (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Insurance Type</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiration Date</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Left</th>
                                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {expiringQuarter.map((item, idx) => (
                                                    <VendorIssueRow
                                                        key={idx}
                                                        vendor={item.vendor}
                                                        issues={item.issues}
                                                        showDaysPast={false}
                                                    />
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* No Issues */}
                        {expired.length === 0 && expiringSoon.length === 0 && expiringQuarter.length === 0 && (
                            <div className="card">
                                <div className="card-body py-12 text-center">
                                    <CheckCircleIcon className="w-12 h-12 text-green-500 mx-auto mb-4" />
                                    <p className="text-gray-900 font-medium">All vendors are compliant!</p>
                                    <p className="text-gray-500 text-sm mt-1">No insurance issues to report.</p>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'expired' && (
                    <div className="card">
                        {expired.length === 0 ? (
                            <div className="card-body py-12 text-center">
                                <CheckCircleIcon className="w-12 h-12 text-green-500 mx-auto mb-4" />
                                <p className="text-gray-500">No expired insurance</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Insurance Type</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expired Date</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {expired.map((item, idx) => (
                                            <VendorIssueRow
                                                key={idx}
                                                vendor={item.vendor}
                                                issues={item.issues}
                                                showDaysPast={true}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'expiring' && (
                    <div className="card">
                        {expiringSoon.length === 0 ? (
                            <div className="card-body py-12 text-center">
                                <CheckCircleIcon className="w-12 h-12 text-green-500 mx-auto mb-4" />
                                <p className="text-gray-500">No insurance expiring in the next 30 days</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Insurance Type</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiration Date</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Left</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {expiringSoon.map((item, idx) => (
                                            <VendorIssueRow
                                                key={idx}
                                                vendor={item.vendor}
                                                issues={item.issues}
                                                showDaysPast={false}
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'workers-comp' && (
                    <div className="space-y-4">
                        {/* Workers Comp Expired */}
                        {workersCompIssues.expired.length > 0 && (
                            <div className="card">
                                <div className="px-6 py-4 bg-red-50 border-b border-red-100">
                                    <h3 className="text-sm font-medium text-red-800 flex items-center gap-2">
                                        <XCircleIcon className="w-5 h-5" />
                                        Expired Workers Comp ({workersCompIssues.expired.length})
                                    </h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expired Date</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Overdue</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {workersCompIssues.expired.map((item, idx) => (
                                                <tr key={idx} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4">
                                                        <div className="text-sm font-medium text-gray-900">{item.vendor.company_name}</div>
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-900">{formatDate(item.date)}</td>
                                                    <td className="px-6 py-4">
                                                        <span className="text-sm font-medium text-red-600">{item.days_past} days</span>
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{item.vendor.phone || item.vendor.email || '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Workers Comp Expiring Soon */}
                        {workersCompIssues.expiring_soon.length > 0 && (
                            <div className="card">
                                <div className="px-6 py-4 bg-yellow-50 border-b border-yellow-100">
                                    <h3 className="text-sm font-medium text-yellow-800 flex items-center gap-2">
                                        <ClockIcon className="w-5 h-5" />
                                        Expiring Soon ({workersCompIssues.expiring_soon.length})
                                    </h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiration Date</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Left</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {workersCompIssues.expiring_soon.map((item, idx) => (
                                                <tr key={idx} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4">
                                                        <div className="text-sm font-medium text-gray-900">{item.vendor.company_name}</div>
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-900">{formatDate(item.date)}</td>
                                                    <td className="px-6 py-4">
                                                        <span className="text-sm font-medium text-yellow-600">{item.days_until} days</span>
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{item.vendor.phone || item.vendor.email || '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Workers Comp Missing */}
                        {workersCompIssues.missing.length > 0 && (
                            <div className="card">
                                <div className="px-6 py-4 bg-gray-50 border-b border-gray-100">
                                    <h3 className="text-sm font-medium text-gray-700 flex items-center gap-2">
                                        <ExclamationTriangleIcon className="w-5 h-5" />
                                        Missing Workers Comp ({workersCompIssues.missing.length})
                                    </h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trade</th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {workersCompIssues.missing.map((vendor, idx) => (
                                                <tr key={idx} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4">
                                                        <div className="text-sm font-medium text-gray-900">{vendor.company_name}</div>
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{vendor.vendor_trades || '-'}</td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{vendor.phone || vendor.email || '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {workersCompIssues.expired.length === 0 &&
                         workersCompIssues.expiring_soon.length === 0 &&
                         workersCompIssues.missing.length === 0 && (
                            <div className="card">
                                <div className="card-body py-12 text-center">
                                    <CheckCircleIcon className="w-12 h-12 text-green-500 mx-auto mb-4" />
                                    <p className="text-gray-900 font-medium">All vendors have current Workers Comp!</p>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'do-not-use' && (
                    <div className="card">
                        {doNotUse.length === 0 ? (
                            <div className="card-body py-12 text-center">
                                <CheckCircleIcon className="w-12 h-12 text-green-500 mx-auto mb-4" />
                                <p className="text-gray-500">No vendors marked as "Do Not Use"</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trade</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {doNotUse.map((vendor, idx) => (
                                            <tr key={idx} className="hover:bg-gray-50">
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-medium text-gray-900">{vendor.company_name}</div>
                                                    {vendor.contact_name && (
                                                        <div className="text-xs text-gray-500">{vendor.contact_name}</div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500">{vendor.vendor_trades || '-'}</td>
                                                <td className="px-6 py-4 text-sm text-gray-500">{vendor.phone || vendor.email || '-'}</td>
                                                <td className="px-6 py-4">
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        <NoSymbolIcon className="w-3.5 h-3.5 mr-1" />
                                                        Do Not Use
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
                </div>
            </div>
        </Layout>
    );
}
