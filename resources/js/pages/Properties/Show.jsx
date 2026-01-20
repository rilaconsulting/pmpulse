import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import { GoogleMap, LoadScript, Marker } from '@react-google-maps/api';
import Layout from '../../components/Layout';
import AdjustmentList from '../../components/Property/AdjustmentList';
import AdjustedValue from '../../components/AdjustedValue';
import {
    BuildingOfficeIcon,
    MapPinIcon,
    ArrowLeftIcon,
    HomeModernIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CalendarIcon,
    Square3Stack3DIcon,
    CurrencyDollarIcon,
    FlagIcon,
    PlusIcon,
    XMarkIcon,
    ArrowTopRightOnSquareIcon,
} from '@heroicons/react/24/outline';

export default function PropertyShow({
    property,
    units,
    unitFilters,
    stats,
    flagTypes,
    appfolioUrl,
    googleMapsApiKey,
    adjustableFields,
    activeAdjustments,
    historicalAdjustments,
    effectiveValues,
}) {
    const { auth } = usePage().props;
    const isAdmin = auth?.user?.role?.name === 'admin';

    const [showAddFlagModal, setShowAddFlagModal] = useState(false);
    const [deletingFlagId, setDeletingFlagId] = useState(null);

    // Helper to update unit filters via Inertia
    const updateUnitFilters = useCallback((newFilters) => {
        const merged = { ...unitFilters, ...newFilters };
        const data = {};

        if (merged.status) data.unit_status = merged.status;
        if (merged.sort && merged.sort !== 'unit_number') data.unit_sort = merged.sort;
        if (merged.direction && merged.direction !== 'asc') data.unit_direction = merged.direction;

        router.get(
            route('properties.show', property.id),
            data,
            { preserveScroll: true, preserveState: true }
        );
    }, [property.id, unitFilters]);

    // Form for adding flags
    const { data, setData, post, processing, errors, reset } = useForm({
        flag_type: '',
        reason: '',
    });

    const handleAddFlag = (e) => {
        e.preventDefault();
        post(route('properties.flags.store', property.id), {
            onSuccess: () => {
                reset();
                setShowAddFlagModal(false);
            },
        });
    };

    const handleDeleteFlag = (flagId) => {
        if (!confirm('Are you sure you want to remove this flag?')) return;
        setDeletingFlagId(flagId);
        router.delete(route('properties.flags.destroy', [property.id, flagId]), {
            onFinish: () => setDeletingFlagId(null),
        });
    };

    // Get available flag types (not already on property)
    const existingFlagTypes = (property.flags || []).map(f => f.flag_type);
    const availableFlagTypes = Object.entries(flagTypes || {}).filter(
        ([key]) => !existingFlagTypes.includes(key)
    );

    const getFlagBadgeClass = (flagType) => {
        switch (flagType) {
            case 'hoa':
                return 'bg-blue-100 text-blue-800';
            case 'tenant_pays_utilities':
                return 'bg-purple-100 text-purple-800';
            case 'exclude_from_reports':
                return 'bg-red-100 text-red-800';
            case 'under_renovation':
                return 'bg-orange-100 text-orange-800';
            case 'sold':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-yellow-100 text-yellow-800';
        }
    };

    const handleUnitSort = (field) => {
        const newDirection = unitFilters.sort === field && unitFilters.direction === 'asc' ? 'desc' : 'asc';
        updateUnitFilters({ sort: field, direction: newDirection });
    };

    const handleUnitStatusFilter = (status) => {
        updateUnitFilters({ status: status === 'all' ? '' : status });
    };

    const SortIcon = ({ field }) => {
        if (unitFilters.sort !== field) {
            return <ChevronUpIcon className="w-4 h-4 text-gray-300" />;
        }
        return unitFilters.direction === 'asc'
            ? <ChevronUpIcon className="w-4 h-4 text-blue-600" />
            : <ChevronDownIcon className="w-4 h-4 text-blue-600" />;
    };

    const SortableHeader = ({ field, children }) => {
        const isSorted = unitFilters.sort === field;
        const sortDirection = isSorted ? (unitFilters.direction === 'asc' ? 'ascending' : 'descending') : 'none';

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

            {/* Sticky Property Header */}
            <div className="sticky top-16 z-20 -mx-8 px-8 py-4 bg-white border-b border-gray-200 shadow-sm">
                <div className="flex items-center justify-between gap-4">
                    {/* Left: Back button + Property name */}
                    <div className="flex items-center gap-4 min-w-0">
                        <Link
                            href={route('properties.index')}
                            className="flex-shrink-0 p-2 -m-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
                            title="Back to Properties"
                        >
                            <ArrowLeftIcon className="w-5 h-5" />
                        </Link>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-xl font-semibold text-gray-900 truncate">
                                    {property.name}
                                </h1>
                                <span className={`flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                    property.is_active
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-red-100 text-red-800'
                                }`}>
                                    {property.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-gray-500 mt-0.5">
                                {property.address_line1 && (
                                    <span className="truncate">
                                        {property.address_line1}
                                        {property.city && `, ${property.city}`}
                                        {property.state && `, ${property.state}`}
                                    </span>
                                )}
                                {property.address_line1 && property.portfolio && (
                                    <span className="text-gray-300">•</span>
                                )}
                                {property.portfolio && (
                                    <span className="flex-shrink-0 text-gray-500">{property.portfolio}</span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Right: AppFolio link + Flags */}
                    <div className="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
                        {appfolioUrl && (
                            <a
                                href={appfolioUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors"
                            >
                                <ArrowTopRightOnSquareIcon className="w-3.5 h-3.5" />
                                AppFolio
                            </a>
                        )}
                        {/* Property Flags */}
                        {(property.flags || []).map((flag) => (
                            <span
                                key={flag.id}
                                className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium ${getFlagBadgeClass(flag.flag_type)}`}
                                title={flag.reason || undefined}
                            >
                                <FlagIcon className="w-3 h-3" />
                                {flagTypes?.[flag.flag_type] || flag.flag_type}
                                {isAdmin && (
                                    <button
                                        type="button"
                                        onClick={() => handleDeleteFlag(flag.id)}
                                        disabled={deletingFlagId === flag.id}
                                        className="ml-0.5 hover:opacity-75 focus:outline-none"
                                        title="Remove flag"
                                    >
                                        <XMarkIcon className="w-3 h-3" />
                                    </button>
                                )}
                            </span>
                        ))}
                        {/* Add Flag Button (Admin Only) */}
                        {isAdmin && availableFlagTypes.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setShowAddFlagModal(true)}
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
                            >
                                <PlusIcon className="w-3 h-3" />
                                Flag
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div className="space-y-6 pt-4">

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
                                    <p className="text-2xl font-semibold text-gray-900">
                                        <AdjustedValue
                                            value={effectiveValues?.unit_count?.value ?? stats.total_units}
                                            isAdjusted={effectiveValues?.unit_count?.is_adjusted}
                                            original={effectiveValues?.unit_count?.original}
                                            label="Unit Count"
                                            adjustment={effectiveValues?.unit_count?.adjustment}
                                            size="md"
                                        />
                                    </p>
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
                                {(property.total_sqft || effectiveValues?.total_sqft?.value) && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Total Sqft</dt>
                                        <dd className="text-sm font-medium text-gray-900">
                                            <AdjustedValue
                                                value={effectiveValues?.total_sqft?.value ?? property.total_sqft}
                                                isAdjusted={effectiveValues?.total_sqft?.is_adjusted}
                                                original={effectiveValues?.total_sqft?.original}
                                                label="Total Sqft"
                                                adjustment={effectiveValues?.total_sqft?.adjustment}
                                                formatter={(val) => val?.toLocaleString()}
                                            />
                                        </dd>
                                    </div>
                                )}
                                {(property.unit_count || effectiveValues?.unit_count?.value) && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Unit Count</dt>
                                        <dd className="text-sm font-medium text-gray-900">
                                            <AdjustedValue
                                                value={effectiveValues?.unit_count?.value ?? property.unit_count}
                                                isAdjusted={effectiveValues?.unit_count?.is_adjusted}
                                                original={effectiveValues?.unit_count?.original}
                                                label="Unit Count"
                                                adjustment={effectiveValues?.unit_count?.adjustment}
                                            />
                                        </dd>
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

                    {/* Map */}
                    <div className="lg:col-span-2 card">
                        <div className="card-header">
                            <h2 className="text-lg font-medium text-gray-900">Location</h2>
                        </div>
                        <div className="card-body">
                            {property.latitude && property.longitude && googleMapsApiKey ? (
                                <div className="aspect-video rounded-lg overflow-hidden">
                                    <LoadScript googleMapsApiKey={googleMapsApiKey}>
                                        <GoogleMap
                                            mapContainerStyle={{ width: '100%', height: '100%' }}
                                            center={{
                                                lat: parseFloat(property.latitude),
                                                lng: parseFloat(property.longitude),
                                            }}
                                            zoom={16}
                                            options={{
                                                streetViewControl: true,
                                                mapTypeControl: true,
                                                fullscreenControl: true,
                                            }}
                                        >
                                            <Marker
                                                position={{
                                                    lat: parseFloat(property.latitude),
                                                    lng: parseFloat(property.longitude),
                                                }}
                                                title={property.name}
                                            />
                                        </GoogleMap>
                                    </LoadScript>
                                </div>
                            ) : property.latitude && property.longitude ? (
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

                {/* Data Adjustments */}
                {isAdmin && (
                    <AdjustmentList
                        property={property}
                        activeAdjustments={activeAdjustments || []}
                        historicalAdjustments={historicalAdjustments || []}
                        adjustableFields={adjustableFields || {}}
                        effectiveValues={effectiveValues || {}}
                    />
                )}

                {/* Units List */}
                <div className="card">
                    <div className="card-header flex items-center justify-between">
                        <h2 className="text-lg font-medium text-gray-900">
                            Units ({units?.total || 0})
                        </h2>
                        <div className="flex items-center gap-2">
                            <select
                                className="input text-sm"
                                value={unitFilters.status || 'all'}
                                onChange={(e) => handleUnitStatusFilter(e.target.value)}
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
                                {units?.data?.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center">
                                            <HomeModernIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                            <p className="text-gray-500">
                                                {stats.total_units === 0
                                                    ? 'No units found for this property'
                                                    : 'No units match the selected filter'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    units?.data?.map((unit) => (
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

                    {/* Pagination */}
                    {units?.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <div className="text-sm text-gray-500">
                                Showing {units.from} to {units.to} of {units.total} units
                            </div>
                            <div className="flex gap-2">
                                {units.prev_page_url && (
                                    <Link
                                        href={units.prev_page_url}
                                        className="btn-secondary flex items-center text-sm"
                                        preserveScroll
                                    >
                                        <ChevronLeftIcon className="w-4 h-4 mr-1" />
                                        Previous
                                    </Link>
                                )}
                                {units.next_page_url && (
                                    <Link
                                        href={units.next_page_url}
                                        className="btn-secondary flex items-center text-sm"
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

            {/* Add Flag Modal */}
            {showAddFlagModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        {/* Background overlay */}
                        <div
                            className="fixed inset-0 bg-gray-500/75 transition-opacity"
                            aria-hidden="true"
                            onClick={() => {
                                reset();
                                setShowAddFlagModal(false);
                            }}
                        />

                        {/* Center modal */}
                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div className="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-medium text-gray-900" id="modal-title">
                                    Add Property Flag
                                </h3>
                                <button
                                    type="button"
                                    onClick={() => {
                                        reset();
                                        setShowAddFlagModal(false);
                                    }}
                                    className="text-gray-400 hover:text-gray-500"
                                >
                                    <XMarkIcon className="w-5 h-5" />
                                </button>
                            </div>

                            <form onSubmit={handleAddFlag}>
                                <div className="space-y-4">
                                    {/* Flag Type Select */}
                                    <div>
                                        <label htmlFor="flag_type" className="block text-sm font-medium text-gray-700">
                                            Flag Type
                                        </label>
                                        <select
                                            id="flag_type"
                                            className={`mt-1 input ${errors.flag_type ? 'border-red-300' : ''}`}
                                            value={data.flag_type}
                                            onChange={(e) => setData('flag_type', e.target.value)}
                                            required
                                        >
                                            <option value="">Select a flag type...</option>
                                            {availableFlagTypes.map(([key, label]) => (
                                                <option key={key} value={key}>
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.flag_type && (
                                            <p className="mt-1 text-sm text-red-600">{errors.flag_type}</p>
                                        )}
                                    </div>

                                    {/* Reason (Optional) */}
                                    <div>
                                        <label htmlFor="reason" className="block text-sm font-medium text-gray-700">
                                            Reason (Optional)
                                        </label>
                                        <textarea
                                            id="reason"
                                            className={`mt-1 input ${errors.reason ? 'border-red-300' : ''}`}
                                            value={data.reason}
                                            onChange={(e) => setData('reason', e.target.value)}
                                            rows={3}
                                            maxLength={500}
                                            placeholder="Add a reason for this flag..."
                                        />
                                        {errors.reason && (
                                            <p className="mt-1 text-sm text-red-600">{errors.reason}</p>
                                        )}
                                        <p className="mt-1 text-xs text-gray-500">
                                            {data.reason.length}/500 characters
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-5 sm:mt-6 flex gap-3">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            reset();
                                            setShowAddFlagModal(false);
                                        }}
                                        className="flex-1 btn btn-secondary"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing || !data.flag_type}
                                        className="flex-1 btn btn-primary"
                                    >
                                        {processing ? 'Adding...' : 'Add Flag'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </Layout>
    );
}
