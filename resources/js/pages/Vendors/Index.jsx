import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { Disclosure, Transition } from '@headlessui/react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import MobileCard from '../../components/MobileCard';
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
    AdjustmentsHorizontalIcon,
    TableCellsIcon,
    ScaleIcon,
    ShieldCheckIcon,
} from '@heroicons/react/24/outline';

const PAGE_SIZE_STORAGE_KEY = 'pmpulse-vendors-page-size';

export default function VendorsIndex({ vendors, trades, vendorTypes, stats, filters, perPage, allowedPageSizes }) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role?.name === 'admin';
    const [search, setSearch] = useState(filters.search || '');
    const [expandedVendors, setExpandedVendors] = useState(new Set());

    // Initialize page size from props (which came from URL) or localStorage
    const [pageSize, setPageSize] = useState(() => {
        if (perPage !== undefined && perPage !== null) {
            return perPage;
        }
        if (typeof window !== 'undefined') {
            const stored = localStorage.getItem(PAGE_SIZE_STORAGE_KEY);
            if (stored) {
                return stored === 'all' ? 'all' : parseInt(stored, 10);
            }
        }
        return 15;
    });

    // Persist page size preference to localStorage
    useEffect(() => {
        localStorage.setItem(PAGE_SIZE_STORAGE_KEY, String(pageSize));
    }, [pageSize]);

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
        router.get(route('vendors.index'), {
            ...filters,
            per_page: pageSize,
            [key]: value,
            page: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageSizeChange = (newSize) => {
        setPageSize(newSize);
        router.get(route('vendors.index'), {
            ...filters,
            per_page: newSize,
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
        router.get(route('vendors.index'), {
            ...filters,
            per_page: pageSize,
            sort: field,
            direction: direction,
            page: 1,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        router.get(route('vendors.index'), {
            per_page: pageSize,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
        setSearch('');
    };

    const hasActiveFilters = Boolean(filters.search) ||
        Boolean(filters.trade) ||
        Boolean(filters.insurance_status) ||
        (filters.is_active !== '' && filters.is_active !== null && filters.is_active !== undefined) ||
        (filters.canonical_filter && filters.canonical_filter !== 'canonical_only') ||
        (filters.has_work_orders === 'false' || filters.has_work_orders === false);

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

    // Build tabs array - conditionally include Deduplication for admins
    const tabs = [
        { label: 'All Vendors', href: route('vendors.index'), icon: TableCellsIcon },
        { label: 'Compliance', href: route('vendors.compliance'), icon: ShieldCheckIcon },
        { label: 'Compare', href: route('vendors.compare'), icon: ScaleIcon },
        ...(isAdmin ? [{ label: 'Deduplication', href: route('vendors.deduplication'), icon: LinkIcon }] : []),
    ];

    return (
        <Layout>
            <Head title="Vendors" />

            <div className="flex flex-col h-[calc(100vh-64px)] -m-4 md:-m-8">
                {/* Header - doesn't scroll */}
                <div className="flex-shrink-0 px-4 md:px-8 pt-4 md:pt-8">
                    <PageHeader
                        title="Vendors"
                        subtitle="Manage vendors and track insurance compliance"
                        tabs={tabs}
                        activeTab="All Vendors"
                        sticky={false}
                    />
                </div>

                {/* Filters - doesn't scroll */}
                <div className="flex-shrink-0 px-4 md:px-8 pt-6">
                    <div className="card shadow-sm">
                        <div className="card-body">
                            {/* Search - Always visible */}
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative flex-1">
                                    <input
                                        type="text"
                                        id="search"
                                        className="input pl-10 min-h-[44px]"
                                        placeholder="Search by name, contact, or email..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                    />
                                    <MagnifyingGlassIcon className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                </div>
                                <button type="submit" className="btn-secondary min-h-[44px]">
                                    Search
                                </button>
                            </form>

                            {/* Desktop Filters - Hidden on mobile */}
                            <div className="hidden md:flex flex-wrap gap-4 items-end mt-4">
                                {/* Trade Filter */}
                                {trades.length > 0 && (
                                    <div>
                                        <label htmlFor="trade-desktop" className="label">
                                            Trade
                                        </label>
                                        <select
                                            id="trade-desktop"
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
                                    <label htmlFor="insurance_status-desktop" className="label">
                                        Insurance Status
                                    </label>
                                    <select
                                        id="insurance_status-desktop"
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
                                    <label htmlFor="is_active-desktop" className="label">
                                        Status
                                    </label>
                                    <select
                                        id="is_active-desktop"
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
                                    <label htmlFor="canonical_filter-desktop" className="label">
                                        Grouping
                                    </label>
                                    <select
                                        id="canonical_filter-desktop"
                                        className="input"
                                        value={filters.canonical_filter || 'canonical_only'}
                                        onChange={(e) => handleFilter('canonical_filter', e.target.value)}
                                    >
                                        <option value="canonical_only">Canonical Only</option>
                                        <option value="all">Show All</option>
                                        <option value="duplicates_only">Duplicates Only</option>
                                    </select>
                                </div>

                                {/* Work Orders Filter */}
                                <div>
                                    <label htmlFor="has_work_orders-desktop" className="label">
                                        Work Orders
                                    </label>
                                    <select
                                        id="has_work_orders-desktop"
                                        className="input"
                                        value={filters.has_work_orders === false || filters.has_work_orders === 'false' ? 'false' : 'true'}
                                        onChange={(e) => handleFilter('has_work_orders', e.target.value)}
                                    >
                                        <option value="true">With Work Orders (12 mo)</option>
                                        <option value="false">All Vendors</option>
                                    </select>
                                </div>

                                {/* Page Size */}
                                <div>
                                    <label htmlFor="page-size-desktop" className="label">
                                        Show
                                    </label>
                                    <select
                                        id="page-size-desktop"
                                        value={pageSize}
                                        onChange={(e) => handlePageSizeChange(e.target.value === 'all' ? 'all' : parseInt(e.target.value, 10))}
                                        className="input"
                                    >
                                        {(allowedPageSizes || [15, 50, 100]).map((size) => (
                                            <option key={size} value={size}>{size}</option>
                                        ))}
                                        <option value="all">All</option>
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

                                {/* Spacer */}
                                <div className="flex-1" />

                                {/* Results count and Pagination */}
                                <div className="flex items-center gap-4">
                                    <span className="text-sm text-gray-500">
                                        {vendors.from || 0}-{vendors.to || 0} of {vendors.total}
                                    </span>
                                    {vendors.last_page > 1 && (
                                        <div className="flex gap-1">
                                            {vendors.prev_page_url ? (
                                                <Link
                                                    href={vendors.prev_page_url}
                                                    className="btn-secondary p-1.5"
                                                    preserveScroll
                                                    title="Previous page"
                                                >
                                                    <ChevronLeftIcon className="w-4 h-4" />
                                                </Link>
                                            ) : (
                                                <span className="btn-secondary p-1.5 opacity-50 cursor-not-allowed">
                                                    <ChevronLeftIcon className="w-4 h-4" />
                                                </span>
                                            )}
                                            {vendors.next_page_url ? (
                                                <Link
                                                    href={vendors.next_page_url}
                                                    className="btn-secondary p-1.5"
                                                    preserveScroll
                                                    title="Next page"
                                                >
                                                    <ChevronRightIcon className="w-4 h-4" />
                                                </Link>
                                            ) : (
                                                <span className="btn-secondary p-1.5 opacity-50 cursor-not-allowed">
                                                    <ChevronRightIcon className="w-4 h-4" />
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Mobile Filters - Collapsible */}
                            <div className="md:hidden mt-3">
                                <Disclosure>
                                    {({ open }) => (
                                        <>
                                            <Disclosure.Button className="flex items-center justify-between w-full py-2 text-sm font-medium text-gray-700 touch-target">
                                                <span className="flex items-center gap-2">
                                                    <AdjustmentsHorizontalIcon className="w-5 h-5" />
                                                    Filters
                                                    {hasActiveFilters && (
                                                        <span className="inline-flex items-center justify-center w-5 h-5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                                            {[filters.trade, filters.insurance_status, filters.is_active, filters.canonical_filter && filters.canonical_filter !== 'canonical_only', filters.has_work_orders === false || filters.has_work_orders === 'false'].filter(Boolean).length}
                                                        </span>
                                                    )}
                                                </span>
                                                <ChevronDownIcon className={`w-5 h-5 transition-transform ${open ? 'rotate-180' : ''}`} />
                                            </Disclosure.Button>
                                            <Transition
                                                enter="transition duration-100 ease-out"
                                                enterFrom="transform opacity-0 -translate-y-2"
                                                enterTo="transform opacity-100 translate-y-0"
                                                leave="transition duration-75 ease-out"
                                                leaveFrom="transform opacity-100 translate-y-0"
                                                leaveTo="transform opacity-0 -translate-y-2"
                                            >
                                                <Disclosure.Panel className="pt-3 pb-1 space-y-3">
                                                    {/* Trade Filter */}
                                                    {trades.length > 0 && (
                                                        <div>
                                                            <label htmlFor="trade-mobile" className="label">
                                                                Trade
                                                            </label>
                                                            <select
                                                                id="trade-mobile"
                                                                className="input w-full min-h-[44px]"
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
                                                        <label htmlFor="insurance_status-mobile" className="label">
                                                            Insurance Status
                                                        </label>
                                                        <select
                                                            id="insurance_status-mobile"
                                                            className="input w-full min-h-[44px]"
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
                                                        <label htmlFor="is_active-mobile" className="label">
                                                            Status
                                                        </label>
                                                        <select
                                                            id="is_active-mobile"
                                                            className="input w-full min-h-[44px]"
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
                                                        <label htmlFor="canonical_filter-mobile" className="label">
                                                            Grouping
                                                        </label>
                                                        <select
                                                            id="canonical_filter-mobile"
                                                            className="input w-full min-h-[44px]"
                                                            value={filters.canonical_filter || 'canonical_only'}
                                                            onChange={(e) => handleFilter('canonical_filter', e.target.value)}
                                                        >
                                                            <option value="canonical_only">Canonical Only</option>
                                                            <option value="all">Show All</option>
                                                            <option value="duplicates_only">Duplicates Only</option>
                                                        </select>
                                                    </div>

                                                    {/* Work Orders Filter */}
                                                    <div>
                                                        <label htmlFor="has_work_orders-mobile" className="label">
                                                            Work Orders
                                                        </label>
                                                        <select
                                                            id="has_work_orders-mobile"
                                                            className="input w-full min-h-[44px]"
                                                            value={filters.has_work_orders === false || filters.has_work_orders === 'false' ? 'false' : 'true'}
                                                            onChange={(e) => handleFilter('has_work_orders', e.target.value)}
                                                        >
                                                            <option value="true">With Work Orders (12 mo)</option>
                                                            <option value="false">All Vendors</option>
                                                        </select>
                                                    </div>

                                                    {/* Page Size */}
                                                    <div>
                                                        <label htmlFor="page-size-mobile" className="label">
                                                            Show
                                                        </label>
                                                        <select
                                                            id="page-size-mobile"
                                                            value={pageSize}
                                                            onChange={(e) => handlePageSizeChange(e.target.value === 'all' ? 'all' : parseInt(e.target.value, 10))}
                                                            className="input w-full min-h-[44px]"
                                                        >
                                                            {(allowedPageSizes || [15, 50, 100]).map((size) => (
                                                                <option key={size} value={size}>{size}</option>
                                                            ))}
                                                            <option value="all">All</option>
                                                        </select>
                                                    </div>

                                                    {/* Clear Filters */}
                                                    {hasActiveFilters && (
                                                        <button
                                                            type="button"
                                                            onClick={clearFilters}
                                                            className="btn-secondary flex items-center w-full justify-center min-h-[44px]"
                                                        >
                                                            <XMarkIcon className="w-4 h-4 mr-1" />
                                                            Clear Filters
                                                        </button>
                                                    )}
                                                </Disclosure.Panel>
                                            </Transition>
                                        </>
                                    )}
                                </Disclosure>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Content area - grows to fill remaining space */}
                <div className="flex-1 min-h-0 px-4 md:px-8 pt-6 pb-4 md:pb-8 flex flex-col">
                    {/* Vendors Table */}
                    <div className="card flex-1 flex flex-col overflow-hidden min-h-0">
                        {/* Mobile Card View */}
                        <div className="md:hidden flex-1 overflow-auto">
                            {vendors.data.length === 0 ? (
                                <div className="px-4 py-12 text-center">
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
                                </div>
                            ) : (
                                <div className="divide-y divide-gray-200">
                                    {vendors.data.map((vendor) => {
                                        const isDuplicate = vendor.canonical_vendor_id !== null;
                                        const insuranceVariant = vendor.insurance_status === 'current' ? 'success' :
                                                                vendor.insurance_status === 'expiring_soon' ? 'warning' :
                                                                vendor.insurance_status === 'expired' ? 'danger' : 'neutral';
                                        return (
                                            <Link key={vendor.id} href={route('vendors.show', vendor.id)} className="block">
                                                <MobileCard
                                                    header={vendor.company_name}
                                                    subheader={vendor.contact_name || null}
                                                    icon={
                                                        <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
                                                            isDuplicate ? 'bg-gray-100' : 'bg-blue-100'
                                                        }`}>
                                                            {isDuplicate ? (
                                                                <LinkIcon className="w-5 h-5 text-gray-500" />
                                                            ) : (
                                                                <WrenchScrewdriverIcon className="w-5 h-5 text-blue-600" />
                                                            )}
                                                        </div>
                                                    }
                                                    badges={[
                                                        {
                                                            label: vendor.is_active ? 'Active' : 'Inactive',
                                                            variant: vendor.is_active ? 'success' : 'danger',
                                                        },
                                                        {
                                                            label: vendor.insurance_status === 'current' ? 'Insured' :
                                                                   vendor.insurance_status === 'expiring_soon' ? 'Expiring' :
                                                                   vendor.insurance_status === 'expired' ? 'Expired' : 'Unknown',
                                                            variant: insuranceVariant,
                                                        },
                                                    ]}
                                                    fields={[
                                                        {
                                                            label: 'Work Orders',
                                                            value: vendor.metrics?.work_order_count ?? 0,
                                                        },
                                                        {
                                                            label: 'Avg Cost',
                                                            value: formatCurrency(vendor.metrics?.avg_cost_per_wo),
                                                        },
                                                        ...(vendor.vendor_trades ? [{
                                                            label: 'Trade',
                                                            value: vendor.vendor_trades.split(',')[0]?.trim() || '-',
                                                        }] : []),
                                                    ]}
                                                />
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Desktop Table View */}
                        <div className="hidden md:block flex-1 overflow-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50 sticky top-0 z-10 shadow-sm">
                                    <tr>
                                        <SortableHeader field="company_name">Vendor</SortableHeader>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Trade(s)
                                        </th>
                                        <SortableHeader field="work_orders_count">Work Orders</SortableHeader>
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
                                            <td colSpan="6" className="px-6 py-12 text-center">
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
                                                                <Link href={route('vendors.show', vendor.id)} className="flex items-center group">
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
                                                                    <Link href={route('vendors.show', duplicate.id)} className="flex items-center group">
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
                                                                <td colSpan="5" className="px-6 py-3 text-xs text-gray-500">
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

                        {/* Bottom Pagination */}
                        {vendors.last_page > 1 && (
                            <div className="flex-shrink-0 px-4 md:px-6 py-4 border-t border-gray-200">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500">
                                        {vendors.from || 0}-{vendors.to || 0} of {vendors.total}
                                    </span>
                                    <div className="flex gap-2">
                                        {vendors.prev_page_url ? (
                                            <Link
                                                href={vendors.prev_page_url}
                                                className="btn-secondary flex items-center min-h-[44px] sm:min-h-0"
                                                preserveScroll
                                            >
                                                <ChevronLeftIcon className="w-5 h-5 sm:w-4 sm:h-4 sm:mr-1" />
                                                <span className="hidden sm:inline">Previous</span>
                                            </Link>
                                        ) : (
                                            <span className="btn-secondary flex items-center min-h-[44px] sm:min-h-0 opacity-50 cursor-not-allowed">
                                                <ChevronLeftIcon className="w-5 h-5 sm:w-4 sm:h-4 sm:mr-1" />
                                                <span className="hidden sm:inline">Previous</span>
                                            </span>
                                        )}
                                        {vendors.next_page_url ? (
                                            <Link
                                                href={vendors.next_page_url}
                                                className="btn-secondary flex items-center min-h-[44px] sm:min-h-0"
                                                preserveScroll
                                            >
                                                <span className="hidden sm:inline">Next</span>
                                                <ChevronRightIcon className="w-5 h-5 sm:w-4 sm:h-4 sm:ml-1" />
                                            </Link>
                                        ) : (
                                            <span className="btn-secondary flex items-center min-h-[44px] sm:min-h-0 opacity-50 cursor-not-allowed">
                                                <span className="hidden sm:inline">Next</span>
                                                <ChevronRightIcon className="w-5 h-5 sm:w-4 sm:h-4 sm:ml-1" />
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </Layout>
    );
}
