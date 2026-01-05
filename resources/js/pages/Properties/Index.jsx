import { Head, Link, router } from '@inertiajs/react';
import { useState, lazy, Suspense } from 'react';
import Layout from '../../components/Layout';
import AdjustedValue from '../../components/AdjustedValue';
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
} from '@heroicons/react/24/outline';

// Lazy load the map component to avoid SSR issues
const PropertyMap = lazy(() => import('../../components/PropertyMap'));

export default function PropertiesIndex({ properties, portfolios, propertyTypes, filters, googleMapsApiKey }) {
    const [search, setSearch] = useState(filters.search || '');
    const [viewMode, setViewMode] = useState('table'); // 'table' or 'map'

    const handleFilter = (key, value) => {
        router.get('/properties', {
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
        router.get('/properties', {
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
        router.get('/properties', {}, {
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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Properties</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage and view all properties in your portfolio
                        </p>
                    </div>
                    <div className="flex items-center gap-1 bg-gray-100 p-1 rounded-lg">
                        <button
                            type="button"
                            onClick={() => setViewMode('table')}
                            className={`flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                                viewMode === 'table'
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            <ListBulletIcon className="w-4 h-4" />
                            Table
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewMode('map')}
                            className={`flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                                viewMode === 'map'
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            <MapIcon className="w-4 h-4" />
                            Map
                        </button>
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
                                            placeholder="Search by name or address..."
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

                            {/* Portfolio Filter */}
                            {portfolios.length > 0 && (
                                <div>
                                    <label htmlFor="portfolio" className="label">
                                        Portfolio
                                    </label>
                                    <select
                                        id="portfolio"
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
                                    <label htmlFor="property_type" className="label">
                                        Property Type
                                    </label>
                                    <select
                                        id="property_type"
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
                                <label htmlFor="is_active" className="label">
                                    Status
                                </label>
                                <select
                                    id="is_active"
                                    className="input"
                                    value={filters.is_active}
                                    onChange={(e) => handleFilter('is_active', e.target.value)}
                                >
                                    <option value="">All Statuses</option>
                                    <option value="true">Active</option>
                                    <option value="false">Inactive</option>
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
                    <div className="overflow-x-auto">
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
                                                    href={`/properties/${property.id}`}
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

                    {/* Pagination */}
                    {properties.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <div className="text-sm text-gray-500">
                                Showing {properties.from} to {properties.to} of {properties.total} properties
                            </div>
                            <div className="flex gap-2">
                                {properties.prev_page_url && (
                                    <Link
                                        href={properties.prev_page_url}
                                        className="btn-secondary flex items-center"
                                        preserveScroll
                                    >
                                        <ChevronLeftIcon className="w-4 h-4 mr-1" />
                                        Previous
                                    </Link>
                                )}
                                {properties.next_page_url && (
                                    <Link
                                        href={properties.next_page_url}
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
                )}
            </div>
        </Layout>
    );
}
