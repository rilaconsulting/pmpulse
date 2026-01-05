import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import {
    BuildingOfficeIcon,
    MapPinIcon,
    ArrowLeftIcon,
    HomeModernIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    CalendarIcon,
    Square3Stack3DIcon,
    CurrencyDollarIcon,
} from '@heroicons/react/24/outline';

export default function PropertyShow({ property, stats }) {
    const [unitFilter, setUnitFilter] = useState('all');
    const [unitSort, setUnitSort] = useState({ field: 'unit_number', direction: 'asc' });

    // Filter units
    const filteredUnits = property.units?.filter(unit => {
        if (unitFilter === 'all') return true;
        return unit.status === unitFilter;
    }) || [];

    // Sort units
    const sortedUnits = [...filteredUnits].sort((a, b) => {
        let aVal = a[unitSort.field];
        let bVal = b[unitSort.field];

        // Handle null values
        if (aVal === null) aVal = '';
        if (bVal === null) bVal = '';

        // Handle numeric sorting
        if (typeof aVal === 'number' && typeof bVal === 'number') {
            return unitSort.direction === 'asc' ? aVal - bVal : bVal - aVal;
        }

        // String comparison
        const comparison = String(aVal).localeCompare(String(bVal), undefined, { numeric: true });
        return unitSort.direction === 'asc' ? comparison : -comparison;
    });

    const handleUnitSort = (field) => {
        setUnitSort(prev => ({
            field,
            direction: prev.field === field && prev.direction === 'asc' ? 'desc' : 'asc',
        }));
    };

    const SortIcon = ({ field }) => {
        if (unitSort.field !== field) {
            return <ChevronUpIcon className="w-4 h-4 text-gray-300" />;
        }
        return unitSort.direction === 'asc'
            ? <ChevronUpIcon className="w-4 h-4 text-blue-600" />
            : <ChevronDownIcon className="w-4 h-4 text-blue-600" />;
    };

    const SortableHeader = ({ field, children }) => {
        const isSorted = unitSort.field === field;
        const sortDirection = isSorted ? (unitSort.direction === 'asc' ? 'ascending' : 'descending') : 'none';

        return (
            <th
                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                aria-sort={sortDirection}
            >
                <button
                    type="button"
                    className="flex items-center gap-1 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    onClick={() => handleUnitSort(field)}
                >
                    {children}
                    <SortIcon field={field} />
                </button>
            </th>
        );
    };

    const getStatusBadgeClass = (status) => {
        switch (status) {
            case 'occupied':
                return 'bg-green-100 text-green-800';
            case 'vacant':
                return 'bg-yellow-100 text-yellow-800';
            case 'not_ready':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusLabel = (status) => {
        switch (status) {
            case 'occupied':
                return 'Occupied';
            case 'vacant':
                return 'Vacant';
            case 'not_ready':
                return 'Not Ready';
            default:
                return status || 'Unknown';
        }
    };

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '-';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    return (
        <Layout>
            <Head title={property.name} />

            <div className="space-y-6">
                {/* Back link */}
                <Link
                    href="/properties"
                    className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
                >
                    <ArrowLeftIcon className="w-4 h-4 mr-1" />
                    Back to Properties
                </Link>

                {/* Property Header */}
                <div className="card">
                    <div className="card-body">
                        <div className="flex items-start justify-between">
                            <div className="flex items-start">
                                <div className="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <BuildingOfficeIcon className="w-8 h-8 text-blue-600" />
                                </div>
                                <div className="ml-5">
                                    <div className="flex items-center gap-3">
                                        <h1 className="text-2xl font-semibold text-gray-900">
                                            {property.name}
                                        </h1>
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            property.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        }`}>
                                            {property.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                    {property.address_line1 && (
                                        <div className="flex items-center mt-1 text-gray-500">
                                            <MapPinIcon className="w-4 h-4 mr-1" />
                                            <span>
                                                {property.address_line1}
                                                {property.address_line2 && `, ${property.address_line2}`}
                                                {property.city && `, ${property.city}`}
                                                {property.state && `, ${property.state}`}
                                                {property.zip && ` ${property.zip}`}
                                            </span>
                                        </div>
                                    )}
                                    {property.portfolio && (
                                        <div className="mt-1 text-sm text-gray-500">
                                            Portfolio: {property.portfolio}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Total Units */}
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <HomeModernIcon className="w-5 h-5 text-blue-600" />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Total Units</p>
                                    <p className="text-2xl font-semibold text-gray-900">{stats.total_units}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Occupancy Rate */}
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
                                    stats.occupancy_rate >= 90
                                        ? 'bg-green-100'
                                        : stats.occupancy_rate >= 70
                                            ? 'bg-yellow-100'
                                            : 'bg-red-100'
                                }`}>
                                    <Square3Stack3DIcon className={`w-5 h-5 ${
                                        stats.occupancy_rate >= 90
                                            ? 'text-green-600'
                                            : stats.occupancy_rate >= 70
                                                ? 'text-yellow-600'
                                                : 'text-red-600'
                                    }`} />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Occupancy Rate</p>
                                    <p className="text-2xl font-semibold text-gray-900">{stats.occupancy_rate}%</p>
                                </div>
                            </div>
                            <div className="mt-3">
                                <div className="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        className={`h-2 rounded-full ${
                                            stats.occupancy_rate >= 90
                                                ? 'bg-green-500'
                                                : stats.occupancy_rate >= 70
                                                    ? 'bg-yellow-500'
                                                    : 'bg-red-500'
                                        }`}
                                        style={{ width: `${stats.occupancy_rate}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Vacant Units */}
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <HomeModernIcon className="w-5 h-5 text-yellow-600" />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Vacant Units</p>
                                    <p className="text-2xl font-semibold text-gray-900">{stats.vacant_units}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Total Market Rent */}
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                    <CurrencyDollarIcon className="w-5 h-5 text-green-600" />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Total Market Rent</p>
                                    <p className="text-2xl font-semibold text-gray-900">
                                        {formatCurrency(stats.total_market_rent)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Property Details */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Details Card */}
                    <div className="card">
                        <div className="card-header">
                            <h2 className="text-lg font-medium text-gray-900">Property Details</h2>
                        </div>
                        <div className="card-body">
                            <dl className="space-y-4">
                                {property.property_type && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Property Type</dt>
                                        <dd className="text-sm font-medium text-gray-900">{property.property_type}</dd>
                                    </div>
                                )}
                                {property.year_built && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Year Built</dt>
                                        <dd className="text-sm font-medium text-gray-900">{property.year_built}</dd>
                                    </div>
                                )}
                                {property.total_sqft && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Total Sqft</dt>
                                        <dd className="text-sm font-medium text-gray-900">
                                            {property.total_sqft.toLocaleString()}
                                        </dd>
                                    </div>
                                )}
                                {property.unit_count && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Unit Count</dt>
                                        <dd className="text-sm font-medium text-gray-900">{property.unit_count}</dd>
                                    </div>
                                )}
                                {property.county && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">County</dt>
                                        <dd className="text-sm font-medium text-gray-900">{property.county}</dd>
                                    </div>
                                )}
                                {stats.avg_market_rent > 0 && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Avg Market Rent</dt>
                                        <dd className="text-sm font-medium text-gray-900">
                                            {formatCurrency(stats.avg_market_rent)}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    </div>

                    {/* Map Placeholder */}
                    <div className="lg:col-span-2 card">
                        <div className="card-header">
                            <h2 className="text-lg font-medium text-gray-900">Location</h2>
                        </div>
                        <div className="card-body">
                            {property.latitude && property.longitude ? (
                                <div className="aspect-video bg-gray-100 rounded-lg flex items-center justify-center">
                                    <div className="text-center">
                                        <MapPinIcon className="w-12 h-12 text-gray-400 mx-auto mb-2" />
                                        <p className="text-sm text-gray-500">
                                            Coordinates: {property.latitude}, {property.longitude}
                                        </p>
                                        <a
                                            href={`https://www.google.com/maps/search/?api=1&query=${property.latitude},${property.longitude}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="mt-2 inline-flex items-center text-sm text-blue-600 hover:text-blue-700"
                                        >
                                            View on Google Maps
                                        </a>
                                    </div>
                                </div>
                            ) : (
                                <div className="aspect-video bg-gray-100 rounded-lg flex items-center justify-center">
                                    <div className="text-center">
                                        <MapPinIcon className="w-12 h-12 text-gray-300 mx-auto mb-2" />
                                        <p className="text-sm text-gray-500">No coordinates available</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Units List */}
                <div className="card">
                    <div className="card-header flex items-center justify-between">
                        <h2 className="text-lg font-medium text-gray-900">
                            Units ({property.units?.length || 0})
                        </h2>
                        <div className="flex items-center gap-2">
                            <select
                                className="input text-sm"
                                value={unitFilter}
                                onChange={(e) => setUnitFilter(e.target.value)}
                            >
                                <option value="all">All Units</option>
                                <option value="occupied">Occupied ({stats.occupied_units})</option>
                                <option value="vacant">Vacant ({stats.vacant_units})</option>
                                <option value="not_ready">Not Ready ({stats.not_ready_units})</option>
                            </select>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <SortableHeader field="unit_number">Unit</SortableHeader>
                                    <SortableHeader field="status">Status</SortableHeader>
                                    <SortableHeader field="bedrooms">Bed/Bath</SortableHeader>
                                    <SortableHeader field="sqft">Sqft</SortableHeader>
                                    <SortableHeader field="market_rent">Market Rent</SortableHeader>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Type
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {sortedUnits.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center">
                                            <HomeModernIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                            <p className="text-gray-500">
                                                {property.units?.length === 0
                                                    ? 'No units found for this property'
                                                    : 'No units match the selected filter'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    sortedUnits.map((unit) => (
                                        <tr key={unit.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm font-medium text-gray-900">
                                                    {unit.unit_number}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(unit.status)}`}>
                                                    {getStatusLabel(unit.status)}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {unit.bedrooms !== null || unit.bathrooms !== null
                                                    ? `${unit.bedrooms ?? '-'} / ${unit.bathrooms ?? '-'}`
                                                    : '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {unit.sqft ? unit.sqft.toLocaleString() : '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {formatCurrency(unit.market_rent)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {unit.unit_type || '-'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
