import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, lazy, Suspense } from 'react';
import { Disclosure, Transition } from '@headlessui/react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import AdjustedValue from '../../components/AdjustedValue';
import MobileCard from '../../components/MobileCard';
import {
    MagnifyingGlassIcon,
    XMarkIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    BuildingOfficeIcon,
    MapPinIcon,
    ListBulletIcon,
    MapIcon,
    FunnelIcon,
    AdjustmentsHorizontalIcon,
} from '@heroicons/react/24/outline';

// Lazy load the map component to avoid SSR issues
const PropertyMap = lazy(() => import('../../components/PropertyMap'));

const VIEW_MODE_STORAGE_KEY = 'pmpulse-properties-view-mode';
const PAGE_SIZE_STORAGE_KEY = 'pmpulse-properties-page-size';

/**
 * Normalize is_active filter value to a string for the select element.
 * Handles both boolean values from Inertia and string values from URL params.
 */
const normalizeIsActive = (value) => {
    if (value === true || value === '1') return '1';
    if (value === false || value === '0') return '0';
    return '';
};

export default function PropertiesIndex({ properties, portfolios, propertyTypes, filters, perPage, allowedPageSizes, googleMapsApiKey }) {
    const [search, setSearch] = useState(filters.search || '');
    const [viewMode, setViewMode] = useState(() => {
        // Initialize from localStorage, defaulting to 'table'
        if (typeof window !== 'undefined') {
            return localStorage.getItem(VIEW_MODE_STORAGE_KEY) || 'table';
        }
        return 'table';
    });

    // Initialize page size from props (which came from URL) or localStorage
    const [pageSize, setPageSize] = useState(() => {
        // URL parameter takes precedence (perPage from server)
        if (perPage !== undefined && perPage !== null) {
            return perPage;
        }
        // Fall back to localStorage
        if (typeof window !== 'undefined') {
            const stored = localStorage.getItem(PAGE_SIZE_STORAGE_KEY);
            if (stored) {
                return stored === 'all' ? 'all' : parseInt(stored, 10);
            }
        }
        return 15;
    });

    // Persist view mode preference to localStorage
    useEffect(() => {
        localStorage.setItem(VIEW_MODE_STORAGE_KEY, viewMode);
    }, [viewMode]);

    // Persist page size preference to localStorage
    useEffect(() => {
        localStorage.setItem(PAGE_SIZE_STORAGE_KEY, String(pageSize));
    }, [pageSize]);

    const handleFilter = (key, value) => {
        router.get(route('properties.index'), {
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
        router.get(route('properties.index'), {
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
        router.get(route('properties.index'), {
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
        router.get(route('properties.index'), {
            per_page: pageSize,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
        setSearch('');
    };

    const hasActiveFilters = Boolean(filters.search) ||
        Boolean(filters.portfolio) ||
        Boolean(filters.property_type) ||
        (filters.is_active !== '' && filters.is_active !== null && filters.is_active !== undefined);

    const SortIcon = ({ field }) => {
        if (filters.sort !== field) {
            return <ChevronUpIcon className="w-4 h-4 text-gray-300" />;
        }
        return filters.direction === 'asc'
            ? <ChevronUpIcon className="w-4 h-4 text-blue-600" />
            : <ChevronDownIcon className="w-4 h-4 text-blue-600" />;
    };

    const SortableHeader = ({ field, children, className = '' }) => {
        const isSorted = filters.sort === field;
        const sortDirection = isSorted ? (filters.direction === 'asc' ? 'ascending' : 'descending') : 'none';

        return (
            <th
                className={`px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${className}`}
                aria-sort={sortDirection}
            >
                <button
                    type="button"
                    className="flex items-center gap-1 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    onClick={() => handleSort(field)}
                >
                    {children}
                    <SortIcon field={field} />
                </button>
            </th>
        );
    };

    return (
        <Layout>
            <Head title="Properties" />

            <div className="space-y-6">
                {/* Header */}
                <PageHeader
                    title="Properties"
                    subtitle="Manage and view all properties in your portfolio"
                    actions={
                        <div className="flex items-center gap-1 bg-gray-100 p-1 rounded-lg">
                            <button
                                type="button"
                                onClick={() => setViewMode('table')}
                                className={`flex items-center gap-2 px-2 py-1.5 sm:px-3 text-sm font-medium rounded-md transition-colors touch-target ${
                                    viewMode === 'table'
                                        ? 'bg-white text-gray-900 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                }`}
                                title="Table view"
                            >
                                <ListBulletIcon className="w-5 h-5 sm:w-4 sm:h-4" />
                                <span className="hidden sm:inline">Table</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setViewMode('map')}
                                className={`flex items-center gap-2 px-2 py-1.5 sm:px-3 text-sm font-medium rounded-md transition-colors touch-target ${
                                    viewMode === 'map'
                                        ? 'bg-white text-gray-900 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                }`}
                                title="Map view"
                            >
                                <MapIcon className="w-5 h-5 sm:w-4 sm:h-4" />
                                <span className="hidden sm:inline">Map</span>
                            </button>
                        </div>
                    }
                />

                {/* Filters */}
                <div className="card sticky top-16 z-10 shadow-sm">
                    <div className="card-body">
                        {/* Search - Always visible */}
                        <form onSubmit={handleSearch} className="flex gap-2">
                            <div className="relative flex-1">
                                <input
                                    type="text"
                                    id="search"
                                    className="input pl-10 min-h-[44px]"
                                    placeholder="Search by name or address..."
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
                            {/* Portfolio Filter */}
                            {portfolios.length > 0 && (
                                <div>
                                    <label htmlFor="portfolio-desktop" className="label">
                                        Portfolio
                                    </label>
                                    <select
                                        id="portfolio-desktop"
                                        className="input"
                                        value={filters.portfolio}
                                        onChange={(e) => handleFilter('portfolio', e.target.value)}
                                    >
                                        <option value="">All Portfolios</option>
                                        {portfolios.map((portfolio) => (
                                            <option key={portfolio} value={portfolio}>
                                                {portfolio}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* Property Type Filter */}
                            {propertyTypes.length > 0 && (
                                <div>
                                    <label htmlFor="property_type-desktop" className="label">
                                        Property Type
                                    </label>
                                    <select
                                        id="property_type-desktop"
                                        className="input"
                                        value={filters.property_type}
                                        onChange={(e) => handleFilter('property_type', e.target.value)}
                                    >
                                        <option value="">All Types</option>
                                        {propertyTypes.map((type) => (
                                            <option key={type} value={type}>
                                                {type}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* Status Filter */}
                            <div>
                                <label htmlFor="is_active-desktop" className="label">
                                    Status
                                </label>
                                <select
                                    id="is_active-desktop"
                                    className="input"
                                    value={normalizeIsActive(filters.is_active)}
                                    onChange={(e) => handleFilter('is_active', e.target.value)}
                                >
                                    <option value="">All Statuses</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
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
                                                        {[filters.search, filters.portfolio, filters.property_type, filters.is_active !== '' && filters.is_active !== null && filters.is_active !== undefined].filter(Boolean).length}
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
                                                {/* Portfolio Filter */}
                                                {portfolios.length > 0 && (
                                                    <div>
                                                        <label htmlFor="portfolio-mobile" className="label">
                                                            Portfolio
                                                        </label>
                                                        <select
                                                            id="portfolio-mobile"
                                                            className="input w-full min-h-[44px]"
                                                            value={filters.portfolio}
                                                            onChange={(e) => handleFilter('portfolio', e.target.value)}
                                                        >
                                                            <option value="">All Portfolios</option>
                                                            {portfolios.map((portfolio) => (
                                                                <option key={portfolio} value={portfolio}>
                                                                    {portfolio}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                )}

                                                {/* Property Type Filter */}
                                                {propertyTypes.length > 0 && (
                                                    <div>
                                                        <label htmlFor="property_type-mobile" className="label">
                                                            Property Type
                                                        </label>
                                                        <select
                                                            id="property_type-mobile"
                                                            className="input w-full min-h-[44px]"
                                                            value={filters.property_type}
                                                            onChange={(e) => handleFilter('property_type', e.target.value)}
                                                        >
                                                            <option value="">All Types</option>
                                                            {propertyTypes.map((type) => (
                                                                <option key={type} value={type}>
                                                                    {type}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                )}

                                                {/* Status Filter */}
                                                <div>
                                                    <label htmlFor="is_active-mobile" className="label">
                                                        Status
                                                    </label>
                                                    <select
                                                        id="is_active-mobile"
                                                        className="input w-full min-h-[44px]"
                                                        value={normalizeIsActive(filters.is_active)}
                                                        onChange={(e) => handleFilter('is_active', e.target.value)}
                                                    >
                                                        <option value="">All Statuses</option>
                                                        <option value="1">Active</option>
                                                        <option value="0">Inactive</option>
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

                {/* Map View */}
                {viewMode === 'map' && (
                    <Suspense fallback={
                        <div className="card p-8 text-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                            <p className="mt-2 text-gray-500">Loading map...</p>
                        </div>
                    }>
                        <PropertyMap properties={properties.data} apiKey={googleMapsApiKey} />
                    </Suspense>
                )}

                {/* Properties Table */}
                {viewMode === 'table' && (
                <div className="card">
                    {/* Mobile Card View */}
                    <div className="md:hidden">
                        {properties.data.length === 0 ? (
                            <div className="px-4 py-12 text-center">
                                <BuildingOfficeIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                <p className="text-gray-500">No properties found</p>
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
                                {properties.data.map((property) => (
                                    <Link
                                        key={property.id}
                                        href={route('properties.show', property.id)}
                                        className="block"
                                    >
                                        <MobileCard
                                            header={property.name}
                                            subheader={
                                                property.city
                                                    ? `${property.address_line1 || ''}, ${property.city}, ${property.state}`
                                                    : property.address_line1 || 'No address'
                                            }
                                            icon={
                                                <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-blue-100">
                                                    <BuildingOfficeIcon className="w-5 h-5 text-blue-600" />
                                                </div>
                                            }
                                            badges={[
                                                {
                                                    label: property.is_active ? 'Active' : 'Inactive',
                                                    variant: property.is_active ? 'success' : 'danger',
                                                },
                                                ...(property.property_type ? [{
                                                    label: property.property_type,
                                                    variant: 'neutral',
                                                }] : []),
                                            ]}
                                            fields={[
                                                {
                                                    label: 'Units',
                                                    value: property.effective_values?.unit_count?.value ?? property.units_count ?? property.unit_count ?? '-',
                                                },
                                                {
                                                    label: 'Occupancy',
                                                    value: property.occupancy_rate !== null ? (
                                                        <div className="flex items-center">
                                                            <div className="w-12 bg-gray-200 rounded-full h-1.5 mr-2">
                                                                <div
                                                                    className={`h-1.5 rounded-full ${
                                                                        property.occupancy_rate >= 90
                                                                            ? 'bg-green-500'
                                                                            : property.occupancy_rate >= 70
                                                                                ? 'bg-yellow-500'
                                                                                : 'bg-red-500'
                                                                    }`}
                                                                    style={{ width: `${property.occupancy_rate}%` }}
                                                                />
                                                            </div>
                                                            <span>{property.occupancy_rate}%</span>
                                                        </div>
                                                    ) : '-',
                                                },
                                                {
                                                    label: 'Sqft',
                                                    value: property.total_sqft ? property.total_sqft.toLocaleString() : '-',
                                                },
                                                ...(property.portfolio ? [{
                                                    label: 'Portfolio',
                                                    value: property.portfolio,
                                                }] : []),
                                            ]}
                                        />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Desktop Table View */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <SortableHeader field="name">Property</SortableHeader>
                                    <SortableHeader field="city">Location</SortableHeader>
                                    <SortableHeader field="unit_count">Units</SortableHeader>
                                    <SortableHeader field="total_sqft">Sqft</SortableHeader>
                                    <SortableHeader field="property_type">Type</SortableHeader>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Occupancy
                                    </th>
                                    <SortableHeader field="is_active">Status</SortableHeader>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {properties.data.length === 0 ? (
                                    <tr>
                                        <td colSpan="7" className="px-6 py-12 text-center">
                                            <BuildingOfficeIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                            <p className="text-gray-500">No properties found</p>
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
                                    properties.data.map((property) => (
                                        <tr key={property.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={route('properties.show', property.id)}
                                                    className="group"
                                                >
                                                    <div className="flex items-center">
                                                        <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                            <BuildingOfficeIcon className="w-5 h-5 text-blue-600" />
                                                        </div>
                                                        <div className="ml-4">
                                                            <div className="text-sm font-medium text-gray-900 group-hover:text-blue-600">
                                                                {property.name}
                                                            </div>
                                                            {property.portfolio && (
                                                                <div className="text-xs text-gray-500">
                                                                    {property.portfolio}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </Link>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center text-sm text-gray-900">
                                                    <MapPinIcon className="w-4 h-4 text-gray-400 mr-1 flex-shrink-0" />
                                                    <div>
                                                        <div>{property.address_line1 || '-'}</div>
                                                        {property.city && (
                                                            <div className="text-xs text-gray-500">
                                                                {property.city}, {property.state} {property.zip}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm text-gray-900">
                                                    <AdjustedValue
                                                        value={property.effective_values?.unit_count?.value ?? property.units_count ?? property.unit_count ?? '-'}
                                                        isAdjusted={property.effective_values?.unit_count?.is_adjusted}
                                                        original={property.effective_values?.unit_count?.original}
                                                        label="Unit Count"
                                                    />
                                                </div>
                                                {property.vacant_units_count > 0 && (
                                                    <div className="text-xs text-orange-600">
                                                        {property.vacant_units_count} vacant
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <AdjustedValue
                                                    value={property.effective_values?.total_sqft?.value ?? property.total_sqft}
                                                    isAdjusted={property.effective_values?.total_sqft?.is_adjusted}
                                                    original={property.effective_values?.total_sqft?.original}
                                                    label="Square Footage"
                                                    formatter={(v) => v ? v.toLocaleString() : '-'}
                                                />
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {property.property_type ? (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        {property.property_type}
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-gray-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {property.occupancy_rate !== null ? (
                                                    <div className="flex items-center">
                                                        <div className="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                            <div
                                                                className={`h-2 rounded-full ${
                                                                    property.occupancy_rate >= 90
                                                                        ? 'bg-green-500'
                                                                        : property.occupancy_rate >= 70
                                                                            ? 'bg-yellow-500'
                                                                            : 'bg-red-500'
                                                                }`}
                                                                style={{ width: `${property.occupancy_rate}%` }}
                                                            />
                                                        </div>
                                                        <span className="text-sm text-gray-900">
                                                            {property.occupancy_rate}%
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-gray-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    property.is_active
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800'
                                                }`}>
                                                    {property.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination and Page Size */}
                    <div className="px-4 md:px-6 py-4 border-t border-gray-200">
                        {/* Mobile: Stacked layout */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            {/* Results count and page size */}
                            <div className="flex items-center justify-between sm:justify-start gap-4">
                                <div className="text-xs sm:text-sm text-gray-500">
                                    <span className="hidden sm:inline">Showing </span>
                                    {properties.from || 0}-{properties.to || 0} of {properties.total}
                                </div>
                                <div className="flex items-center gap-2">
                                    <label htmlFor="page-size" className="text-xs sm:text-sm text-gray-500">
                                        Show:
                                    </label>
                                    <select
                                        id="page-size"
                                        value={pageSize}
                                        onChange={(e) => handlePageSizeChange(e.target.value === 'all' ? 'all' : parseInt(e.target.value, 10))}
                                        className="text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 min-h-[36px]"
                                    >
                                        {(allowedPageSizes || [15, 50, 100]).map((size) => (
                                            <option key={size} value={size}>{size}</option>
                                        ))}
                                        <option value="all">All</option>
                                    </select>
                                </div>
                            </div>

                            {/* Pagination controls */}
                            {properties.last_page > 1 && (
                                <div className="flex items-center justify-between sm:justify-end gap-2">
                                    {/* Mobile: Page indicator */}
                                    <span className="text-sm text-gray-500 sm:hidden">
                                        {properties.current_page} / {properties.last_page}
                                    </span>

                                    <div className="flex gap-2">
                                        {properties.prev_page_url ? (
                                            <Link
                                                href={properties.prev_page_url}
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
                                        {properties.next_page_url ? (
                                            <Link
                                                href={properties.next_page_url}
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
                            )}
                        </div>
                    </div>
                </div>
                )}
            </div>
        </Layout>
    );
}
