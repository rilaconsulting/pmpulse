import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import Layout from '../../components/Layout';
import { InsuranceStatusBadge, formatCurrency } from '../../components/Vendor';
import {
    MagnifyingGlassIcon,
    XMarkIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    WrenchScrewdriverIcon,
    ExclamationTriangleIcon,
    LinkIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';

export default function VendorsIndex({ vendors, trades, vendorTypes, stats, filters }) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role?.name === 'admin';
    const [search, setSearch] = useState(filters.search || '');
    const [expandedVendors, setExpandedVendors] = useState(new Set());

    const toggleExpanded = (vendorId) => {
        setExpandedVendors((prev) => {
            const next = new Set(prev);
            if (next.has(vendorId)) {
                next.delete(vendorId);
            } else {
                next.add(vendorId);
            }
            return next;
        });
    };

    const handleFilter = (key, value) => {
        router.get('/vendors', {
            ...filters,
            [key]: value,
            page: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSearch = (e) => {
        e.preventDefault();
        handleFilter('search', search);
    };

    const handleSort = (field) => {
        const direction = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/vendors', {
            ...filters,
            sort: field,
            direction: direction,
            page: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        router.get('/vendors', {}, {
            preserveState: true,
            preserveScroll: true,
        });
        setSearch('');
    };

    const hasActiveFilters = Boolean(filters.search) ||
        Boolean(filters.trade) ||
        Boolean(filters.insurance_status) ||
        (filters.is_active !== '' && filters.is_active !== null && filters.is_active !== undefined) ||
        (filters.canonical_filter && filters.canonical_filter !== 'canonical_only');

    const SortIcon = ({ field }) => {
        if (filters.sort !== field) {
            return <ChevronUpIcon className="w-4 h-4 text-gray-300" />;
        }
        return filters.direction === 'asc'
            ? <ChevronUpIcon className="w-4 h-4 text-blue-600" />
            : <ChevronDownIcon className="w-4 h-4 text-blue-600" />;
    };

    const SortableHeader = ({ field, children, className = '', label }) => {
        const isSorted = filters.sort === field;
        const sortDirection = isSorted ? (filters.direction === 'asc' ? 'ascending' : 'descending') : 'none';
        const columnLabel = label || children;

        // Build descriptive aria-label for the button
        let ariaLabel;
        if (isSorted) {
            const nextDirection = filters.direction === 'asc' ? 'descending' : 'ascending';
            ariaLabel = `${columnLabel}, sorted ${sortDirection}, activate to sort ${nextDirection}`;
        } else {
            ariaLabel = `${columnLabel}, not sorted, activate to sort ascending`;
        }

        return (
            <th
                className={`px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${className}`}
                aria-sort={sortDirection}
            >
                <button
                    type="button"
                    className="flex items-center gap-1 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    onClick={() => handleSort(field)}
                    aria-label={ariaLabel}
                >
                    {children}
                    <SortIcon field={field} />
                </button>
            </th>
        );
    };

    return (
        <Layout>
            <Head title="Vendors" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Vendors</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage vendors and track insurance compliance
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {isAdmin && (
                            <Link
                                href="/vendors/deduplication"
                                className="btn-secondary flex items-center"
                            >
                                <LinkIcon className="w-4 h-4 mr-2" />
                                Deduplication
                            </Link>
                        )}
                        <Link
                            href="/vendors/compare"
                            className="btn-secondary flex items-center"
                        >
                            Compare Vendors
                        </Link>
                        <Link
                            href="/vendors/compliance"
                            className="btn-primary flex items-center"
                        >
                            <ExclamationTriangleIcon className="w-4 h-4 mr-2" />
                            Compliance Report
                        </Link>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm font-medium text-gray-500">Total Vendors</p>
                            <p className="text-2xl font-semibold text-gray-900">{stats.total_vendors}</p>
                        </div>
                    </div>
                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm font-medium text-gray-500">Active Vendors</p>
                            <p className="text-2xl font-semibold text-gray-900">{stats.active_vendors}</p>
                        </div>
                    </div>
                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm font-medium text-gray-500">Expired Insurance</p>
                            <p className={`text-2xl font-semibold ${stats.expired_insurance > 0 ? 'text-red-600' : 'text-gray-900'}`}>
                                {stats.expired_insurance}
                            </p>
                        </div>
                    </div>
                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm font-medium text-gray-500">Total Spend (12 mo)</p>
                            <p className="text-2xl font-semibold text-gray-900">
                                {formatCurrency(stats.portfolio_stats?.total_spend)}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Filters */}
                <div className="card">
                    <div className="card-body">
                        <div className="flex flex-wrap gap-4 items-end">
                            {/* Search */}
                            <div className="flex-1 min-w-64">
                                <label htmlFor="search" className="label">
                                    Search
                                </label>
                                <form onSubmit={handleSearch} className="flex gap-2">
                                    <div className="relative flex-1">
                                        <input
                                            type="text"
                                            id="search"
                                            className="input pl-10"
                                            placeholder="Search by name, contact, or email..."
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                        />
                                        <MagnifyingGlassIcon className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                    </div>
                                    <button type="submit" className="btn-secondary">
                                        Search
                                    </button>
                                </form>
                            </div>

                            {/* Trade Filter */}
                            {trades.length > 0 && (
                                <div>
                                    <label htmlFor="trade" className="label">
                                        Trade
                                    </label>
                                    <select
                                        id="trade"
                                        className="input"
                                        value={filters.trade}
                                        onChange={(e) => handleFilter('trade', e.target.value)}
                                    >
                                        <option value="">All Trades</option>
                                        {trades.map((trade) => (
                                            <option key={trade} value={trade}>
                                                {trade}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* Insurance Status Filter */}
                            <div>
                                <label htmlFor="insurance_status" className="label">
                                    Insurance Status
                                </label>
                                <select
                                    id="insurance_status"
                                    className="input"
                                    value={filters.insurance_status}
                                    onChange={(e) => handleFilter('insurance_status', e.target.value)}
                                >
                                    <option value="">All Statuses</option>
                                    <option value="current">Current</option>
                                    <option value="expiring_soon">Expiring Soon</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>

                            {/* Active Status Filter */}
                            <div>
                                <label htmlFor="is_active" className="label">
                                    Status
                                </label>
                                <select
                                    id="is_active"
                                    className="input"
                                    value={filters.is_active}
                                    onChange={(e) => handleFilter('is_active', e.target.value)}
                                >
                                    <option value="">All</option>
                                    <option value="true">Active</option>
                                    <option value="false">Inactive</option>
                                </select>
                            </div>

                            {/* Canonical Filter */}
                            <div>
                                <label htmlFor="canonical_filter" className="label">
                                    Grouping
                                </label>
                                <select
                                    id="canonical_filter"
                                    className="input"
                                    value={filters.canonical_filter || 'canonical_only'}
                                    onChange={(e) => handleFilter('canonical_filter', e.target.value)}
                                >
                                    <option value="canonical_only">Canonical Only</option>
                                    <option value="all">Show All</option>
                                    <option value="duplicates_only">Duplicates Only</option>
                                </select>
                            </div>

                            {/* Clear Filters */}
                            {hasActiveFilters && (
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="btn-secondary flex items-center"
                                >
                                    <XMarkIcon className="w-4 h-4 mr-1" />
                                    Clear
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Vendors Table */}
                <div className="card">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <SortableHeader field="company_name">Vendor</SortableHeader>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Trade(s)
                                    </th>
                                    <SortableHeader field="work_orders_count">Work Orders</SortableHeader>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Spend
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Avg Cost
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Insurance
                                    </th>
                                    <SortableHeader field="is_active">Status</SortableHeader>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {vendors.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="px-6 py-12 text-center">
                                            <WrenchScrewdriverIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                            <p className="text-gray-500">No vendors found</p>
                                            {hasActiveFilters && (
                                                <button
                                                    onClick={clearFilters}
                                                    className="mt-2 text-blue-600 hover:text-blue-700 text-sm"
                                                >
                                                    Clear filters
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ) : (
                                    vendors.data.map((vendor) => {
                                        const hasDuplicates = vendor.duplicate_vendors_count > 0;
                                        const isExpanded = expandedVendors.has(vendor.id);
                                        const isDuplicate = vendor.canonical_vendor_id !== null;

                                        return (
                                            <React.Fragment key={vendor.id}>
                                                <tr className={`hover:bg-gray-50 ${isDuplicate ? 'bg-gray-50/50' : ''}`}>
                                                    <td className="px-6 py-4">
                                                        <div className="flex items-center">
                                                            {/* Expand button for canonical vendors with duplicates */}
                                                            {hasDuplicates ? (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => toggleExpanded(vendor.id)}
                                                                    className="mr-2 p-1 rounded hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                                    aria-expanded={isExpanded}
                                                                    aria-label={isExpanded ? 'Collapse duplicates' : 'Expand duplicates'}
                                                                >
                                                                    {isExpanded ? (
                                                                        <ChevronDownIcon className="w-4 h-4 text-gray-500" />
                                                                    ) : (
                                                                        <ChevronRightIcon className="w-4 h-4 text-gray-500" />
                                                                    )}
                                                                </button>
                                                            ) : (
                                                                <div className="w-7" /> // Spacer for alignment
                                                            )}
                                                            <Link href={`/vendors/${vendor.id}`} className="flex items-center group">
                                                                <div className={`w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ${
                                                                    isDuplicate ? 'bg-gray-100' : 'bg-blue-100'
                                                                }`}>
                                                                    {isDuplicate ? (
                                                                        <LinkIcon className="w-5 h-5 text-gray-500" />
                                                                    ) : (
                                                                        <WrenchScrewdriverIcon className="w-5 h-5 text-blue-600" />
                                                                    )}
                                                                </div>
                                                                <div className="ml-4">
                                                                    <div className={`text-sm font-medium group-hover:text-blue-600 ${
                                                                        isDuplicate ? 'text-gray-600' : 'text-gray-900'
                                                                    }`}>
                                                                        {vendor.company_name}
                                                                        {hasDuplicates && (
                                                                            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
                                                                                <UsersIcon className="w-3 h-3 mr-0.5" />
                                                                                +{vendor.duplicate_vendors_count}
                                                                            </span>
                                                                        )}
                                                                        {isDuplicate && (
                                                                            <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">
                                                                                <LinkIcon className="w-3 h-3 mr-0.5" />
                                                                                Linked
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                    {vendor.contact_name && (
                                                                        <div className="text-xs text-gray-500">
                                                                            {vendor.contact_name}
                                                                            {vendor.phone && ` | ${vendor.phone}`}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </Link>
                                                        </div>
                                                    </td>
                                            <td className="px-6 py-4">
                                                {vendor.vendor_trades ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {vendor.vendor_trades.split(',').slice(0, 2).map((trade, idx) => (
                                                            <span
                                                                key={idx}
                                                                className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700"
                                                            >
                                                                {trade.trim()}
                                                            </span>
                                                        ))}
                                                        {vendor.vendor_trades.split(',').length > 2 && (
                                                            <span className="text-xs text-gray-500">
                                                                +{vendor.vendor_trades.split(',').length - 2}
                                                            </span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-gray-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-gray-900">
                                                    {vendor.metrics?.work_order_count ?? 0}
                                                </div>
                                                <div className="text-xs text-gray-500">Last 12 mo</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-gray-900">
                                                    {formatCurrency(vendor.metrics?.total_spend)}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-gray-900">
                                                    {formatCurrency(vendor.metrics?.avg_cost_per_wo)}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <InsuranceStatusBadge status={vendor.insurance_status} />
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    vendor.is_active
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800'
                                                }`}>
                                                    {vendor.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                                </tr>
                                                {/* Expanded duplicates section */}
                                                {isExpanded && hasDuplicates && vendor.duplicate_vendors && (
                                                    vendor.duplicate_vendors.map((duplicate) => (
                                                        <tr key={duplicate.id} className="bg-purple-50/30">
                                                            <td className="px-6 py-3 pl-20">
                                                                <Link href={`/vendors/${duplicate.id}`} className="flex items-center group">
                                                                    <div className="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                                        <LinkIcon className="w-4 h-4 text-purple-600" />
                                                                    </div>
                                                                    <div className="ml-3">
                                                                        <div className="text-sm text-gray-700 group-hover:text-blue-600">
                                                                            {duplicate.company_name}
                                                                            <span className="ml-2 text-xs text-purple-600">
                                                                                (linked)
                                                                            </span>
                                                                        </div>
                                                                        {duplicate.contact_name && (
                                                                            <div className="text-xs text-gray-500">
                                                                                {duplicate.contact_name}
                                                                                {duplicate.phone && ` | ${duplicate.phone}`}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </Link>
                                                            </td>
                                                            <td colSpan="6" className="px-6 py-3 text-xs text-gray-500">
                                                                Metrics combined with canonical vendor
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </React.Fragment>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {vendors.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <div className="text-sm text-gray-500">
                                Showing {vendors.from} to {vendors.to} of {vendors.total} vendors
                            </div>
                            <div className="flex gap-2">
                                {vendors.prev_page_url && (
                                    <Link
                                        href={vendors.prev_page_url}
                                        className="btn-secondary flex items-center"
                                        preserveScroll
                                    >
                                        <ChevronLeftIcon className="w-4 h-4 mr-1" />
                                        Previous
                                    </Link>
                                )}
                                {vendors.next_page_url && (
                                    <Link
                                        href={vendors.next_page_url}
                                        className="btn-secondary flex items-center"
                                        preserveScroll
                                    >
                                        Next
                                        <ChevronRightIcon className="w-4 h-4 ml-1" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </Layout>
    );
}
